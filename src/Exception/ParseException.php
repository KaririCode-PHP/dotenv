<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\ValidationException as ValidationBase;

// ── Exception: ParseException ────────────────────────────────────────
// .env content that breaks the syntax (2700–2709).
// ─────────────────────────────────────────────────────────────────────

/**
 * Rejects .env content the parser cannot read: a variable name outside
 * the allowed pattern, or a quoted value that never closes. The message
 * names the file and the 1-based line; the offending value is never part
 * of it, so a secret on a broken line does not reach a log.
 *
 * Preconditions: built only through its named constructors; the file path
 * is the one the caller passed to the parser.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $variables = new DotenvParser()->parse($content, '.env');
 *     } catch (ParseException $e) {
 *         $line = $e->getMessage(); // [ENV_2701] Unterminated quoted value at line 3 in ".env"
 *     }
 *
 * References: SPEC-002, ADR-010; Spec V4.3.3 §8.4 (Validation family).
 * ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class ParseException extends ValidationBase implements DotenvException
{
    public static function invalidVariableName(string $name, int $lineNumber, string $filePath, bool $strict = false): self
    {
        $pattern = $strict
            ? '[A-Z][A-Z0-9_]* (uppercase letters, digits, underscores)'
            : '[A-Za-z_][A-Za-z0-9_.]* (letters, digits, underscores, dots)';

        return new self(
            EnvCode::InvalidVariableName,
            'Invalid variable name ' . self::quote($name) . " at line {$lineNumber} in " . self::quote($filePath) . ". Names must match {$pattern}.",
        );
    }

    public static function unterminatedQuote(int $lineNumber, string $filePath): self
    {
        return new self(EnvCode::UnterminatedQuote, "Unterminated quoted value at line {$lineNumber} in " . self::quote($filePath));
    }
}
