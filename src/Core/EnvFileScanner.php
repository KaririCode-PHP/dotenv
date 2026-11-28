<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * Splits .env content into entries while preserving the original text.
 *
 * Tools that rewrite files (encrypt, decrypt, example:generate) need the
 * layout the parser discards: comments, blank lines and the exact span of
 * each assignment. Multiline double-quoted values are kept in one entry,
 * using the same escape rule as DotenvParser, so a PEM body is never
 * mistaken for separate lines.
 *
 * Preconditions: the content is UTF-8 text; scan() does not validate names
 * or values.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $entries = new EnvFileScanner()->scan((string) file_get_contents('.env'));
 *
 * References: SPEC-008, ADR-004. ARFA 1.3 P4: any string content, no I/O.
 *
 * @since   4.5.0
 */
final readonly class EnvFileScanner
{
    private const string ASSIGNMENT_PATTERN = '/\A\s*(export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=(.*)\z/';

    /**
     * @return list<EnvEntry>
     */
    public function scan(string $content): array
    {
        $lines = explode("\n", str_replace(search: ["\r\n", "\r"], replace: "\n", subject: $content));
        $lineCount = count($lines);
        $entries = [];

        for ($index = 0; $index < $lineCount; ++$index) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')
                || preg_match(pattern: self::ASSIGNMENT_PATTERN, subject: $line, matches: $matches) !== 1
            ) {
                $entries[] = new EnvEntry($line);

                continue;
            }

            $rawValue = ltrim($matches[3]);
            $continuation = str_starts_with($rawValue, '"') && !$this->closesDoubleQuote(substr($rawValue, 1))
                ? $this->continuation($lines, $index + 1)
                : [];
            $index += count($continuation);

            $entries[] = new EnvEntry(
                text: implode("\n", [$line, ...$continuation]),
                name: $matches[2],
                exported: $matches[1] !== '',
                rawValue: implode("\n", [$rawValue, ...$continuation]),
            );
        }

        return $entries;
    }

    /**
     * The lines after an unterminated double-quoted value, up to and
     * including the one that closes it, or to the end of the content.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function continuation(array $lines, int $from): array
    {
        $block = [];

        foreach (array_slice($lines, $from) as $line) {
            $block[] = $line;

            if ($this->closesDoubleQuote($line)) {
                break;
            }
        }

        return $block;
    }

    /**
     * True when the fragment contains an unescaped `"` — the parser's rule:
     * a backslash escapes the next character on the same line.
     */
    private function closesDoubleQuote(string $fragment): bool
    {
        $length = strlen($fragment);

        for ($i = 0; $i < $length; ++$i) {
            if ($fragment[$i] === '\\' && $i + 1 < $length) {
                ++$i;

                continue;
            }

            if ($fragment[$i] === '"') {
                return true;
            }
        }

        return false;
    }
}
