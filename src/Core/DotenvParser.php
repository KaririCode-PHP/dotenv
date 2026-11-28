<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

use KaririCode\Dotenv\Exception\ParseException;

/**
 * Parses .env file content into key-value string pairs.
 *
 * Supported syntax (superset of POSIX shell variable assignment):
 *
 *   - Empty lines and lines starting with # are ignored.
 *   - `export` prefix is silently stripped.
 *   - Unquoted, single-quoted, and double-quoted values.
 *   - Double-quoted values support escape sequences: \n, \r, \t, \\, \", \$.
 *     `\$` yields a literal dollar sign that is never interpolated.
 *   - Single-quoted values are literal (no escapes, no interpolation).
 *   - Variable interpolation in unquoted and double-quoted values:
 *     ${VAR}, ${VAR:-default}, ${VAR:+alternate} and $VAR.
 *   - Inline comments: `VALUE # comment` (only outside quotes).
 *   - Multiline double-quoted values (embedded newlines).
 *
 * Interpolation is a single pass: text produced by a substitution is never
 * scanned again, so a referenced value containing `$` is inserted verbatim,
 * and `default`/`alternate` operands are literal text.
 *
 * ARFA 1.3 P1: The parser produces immutable string pairs — no mutation after parse.
 * ARFA 1.3 P4: Protocol-agnostic — works with any input stream providing raw .env content.
 *
 * @package KaririCode\Dotenv
 * @since   4.0.0 ARFA 1.3
 */
final readonly class DotenvParser
{
    private const string VARIABLE_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.]*\z/';
    private const string STRICT_NAME_PATTERN = '/\A[A-Z][A-Z0-9_]*\z/';

    /** `${NAME}`, `${NAME:-default}`, `${NAME:+alternate}` or bare `$NAME` — matched in one pass. */
    private const string REFERENCE_PATTERN
        = '/\$\{(?<name>[A-Za-z_][A-Za-z0-9_]*)(?:(?<op>:[-+])(?<operand>[^}]*))?\}|\$(?<bare>[A-Za-z_][A-Za-z0-9_]*)/';

    /**
     * Parses raw .env content into an ordered map of name → raw value.
     *
     * @return array<string, string> Variable name → value after quote handling and interpolation.
     *
     * @throws ParseException On syntax errors (invalid name, unterminated quote).
     */
    public function parse(
        string $content,
        string $filePath = '.env',
        bool $strictNames = false,
        InterpolationContext $context = new InterpolationContext(),
    ): array {
        /** @var array<string, string> $variables */
        $variables = [];
        $lines = $this->normalizeLines($content);
        $escapedDollar = $this->escapedDollarToken($content);

        $expand = function (string $value) use (&$variables, $context, $escapedDollar): string {
            /** @var array<string, string> $variables */
            $expanded = $this->expandReferences($value, $variables, $context);

            return str_replace($escapedDollar, '$', $expanded);
        };

        $lineCount = count($lines);
        $lineNumber = 0;

        while ($lineNumber < $lineCount) {
            $line = $lines[$lineNumber];
            // Increment first: $lineNumber becomes 1-indexed (for error messages) and
            // simultaneously equals the 0-indexed position of the next line (for multiline
            // continuation in parseDoubleQuoted). This dual purpose is intentional.
            ++$lineNumber;

            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = ltrim(substr($trimmed, 7));
            }

            $equalsPos = strpos($trimmed, '=');

            if ($equalsPos === false) {
                // Bare variable name without value — treat as empty string
                $name = $trimmed;
                $rawValue = '';
            } else {
                $name = rtrim(substr($trimmed, 0, $equalsPos));
                $rawValue = ltrim(substr($trimmed, $equalsPos + 1));
            }

            $namePattern = $strictNames ? self::STRICT_NAME_PATTERN : self::VARIABLE_NAME_PATTERN;

            if (preg_match($namePattern, $name) !== 1) {
                throw ParseException::invalidVariableName($name, $lineNumber, $filePath, $strictNames);
            }

            [$parsedValue, $consumed] = $this->parseValue(
                $rawValue,
                $lines,
                $lineNumber,
                $filePath,
                $expand,
                $escapedDollar,
            );
            $lineNumber += $consumed;

            $variables[$name] = $parsedValue;
        }

        return $variables;
    }

    // ── Value Parsing ────────────────────────────────────────────────────

    /**
     * Parses the value portion of a KEY=VALUE line.
     *
     * @param list<string> $lines All normalized lines of the content.
     * @param \Closure(string): string $expand Interpolates references in a value.
     *
     * @return array{0: string, 1: int} Tuple of [parsed value, additional lines consumed].
     */
    private function parseValue(
        string $rawValue,
        array $lines,
        int $currentLine,
        string $filePath,
        \Closure $expand,
        string $escapedDollar,
    ): array {
        if ($rawValue === '') {
            return ['', 0];
        }

        if ($rawValue[0] === '"') {
            return $this->parseDoubleQuoted($rawValue, $lines, $currentLine, $filePath, $expand, $escapedDollar);
        }

        if ($rawValue[0] === "'") {
            return $this->parseSingleQuoted($rawValue, $currentLine, $filePath);
        }

        return [$this->parseUnquoted($rawValue, $expand), 0];
    }

    /**
     * @param list<string> $lines
     * @param \Closure(string): string $expand
     *
     * @return array{0: string, 1: int}
     */
    private function parseDoubleQuoted(
        string $rawValue,
        array $lines,
        int $currentLine,
        string $filePath,
        \Closure $expand,
        string $escapedDollar,
    ): array {
        $value = substr($rawValue, 1);
        $result = '';
        $extraLines = 0;

        while (true) {
            $length = strlen($value);

            for ($i = 0; $i < $length; ++$i) {
                $char = $value[$i];

                if ($char === '\\' && $i + 1 < $length) {
                    $next = $value[$i + 1];
                    $result .= match ($next) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '"' => '"',
                        '\\' => '\\',
                        '$' => $escapedDollar,
                        default => '\\' . $next,
                    };
                    ++$i;

                    continue;
                }

                if ($char === '"') {
                    return [$expand($result), $extraLines];
                }

                $result .= $char;
            }

            $nextLineIndex = $currentLine + $extraLines;

            if ($nextLineIndex >= count($lines)) {
                throw ParseException::unterminatedQuote($currentLine, $filePath);
            }

            $result .= "\n";
            $value = $lines[$nextLineIndex];
            ++$extraLines;
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseSingleQuoted(string $rawValue, int $currentLine, string $filePath): array
    {
        $closingPos = strpos($rawValue, "'", 1);

        if ($closingPos === false) {
            throw ParseException::unterminatedQuote($currentLine, $filePath);
        }

        return [substr($rawValue, 1, $closingPos - 1), 0];
    }

    /**
     * @param \Closure(string): string $expand
     */
    private function parseUnquoted(string $rawValue, \Closure $expand): string
    {
        // Strip inline comment: `#` preceded by whitespace
        $value = preg_replace('/\s+#.*$/', '', $rawValue) ?? $rawValue;

        return $expand(trim($value));
    }

    // ── Variable Expansion ───────────────────────────────────────────────

    /**
     * Expands every reference in a single pass (see InterpolationContext
     * for the resolution order). Substituted text is not rescanned.
     *
     * @param array<string, string> $variables Same-file variables parsed so far.
     */
    private function expandReferences(string $value, array $variables, InterpolationContext $context): string
    {
        if (!str_contains($value, '$')) {
            return $value;
        }

        return preg_replace_callback(
            self::REFERENCE_PATTERN,
            function (array $matches) use ($variables, $context): string {
                if ($matches['bare'] !== null) {
                    return $this->resolveReference($matches['bare'], $variables, $context) ?? '';
                }

                $resolved = $this->resolveReference((string) $matches['name'], $variables, $context);
                $isSet = $resolved !== null && $resolved !== '';
                $operand = (string) $matches['operand'];

                return match ($matches['op']) {
                    ':+' => $isSet ? $operand : '',
                    ':-' => $isSet ? $resolved : $operand,
                    default => $resolved ?? '',
                };
            },
            $value,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL,
        ) ?? $value;
    }

    /**
     * @param array<string, string> $variables
     */
    private function resolveReference(string $name, array $variables, InterpolationContext $context): ?string
    {
        if (isset($context->locked[$name])) {
            return $context->locked[$name];
        }

        if (isset($variables[$name])) {
            return $context->valueFilter !== null
                ? ($context->valueFilter)($variables[$name])
                : $variables[$name];
        }

        if (isset($context->inherited[$name])) {
            return $context->inherited[$name];
        }

        $env = $_ENV[$name] ?? $_SERVER[$name] ?? null;

        return is_string($env) ? $env : null;
    }

    /**
     * Placeholder for `\$` inside double quotes. It must not occur in the
     * content, otherwise literal text could be turned into a dollar sign.
     */
    private function escapedDollarToken(string $content): string
    {
        $token = "\0kc-dollar\0";
        $suffix = 0;

        while (str_contains($content, $token)) {
            $token = "\0kc-dollar-" . ++$suffix . "\0";
        }

        return $token;
    }

    // ── Line Normalization ───────────────────────────────────────────────

    /**
     * Splits content into lines, normalizing line endings (CRLF / CR → LF).
     *
     * @return list<string>
     */
    private function normalizeLines(string $content): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    }
}
