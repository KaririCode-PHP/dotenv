<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\ValidationException as ValidationBase;

// ── Exception: ConversionException ───────────────────────────────────
// A value a caster or processor cannot convert (2740–2749).
// ─────────────────────────────────────────────────────────────────────

/**
 * Rejects a value that a processor or caster was asked to convert and
 * cannot: base64 that does not decode, or JSON of the wrong shape for the
 * caster. The value itself stays out of the message.
 *
 * Preconditions: built only through its named constructors.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->addProcessor('*_B64', new Base64DecodeProcessor())->load();
 *     } catch (ConversionException $e) {
 *         $notBase64 = $e->errorCode === EnvCode::InvalidBase64;
 *     }
 *
 * References: SPEC-003, SPEC-007, ADR-010; Spec V4.3.3 §8.4 (Validation
 * family). ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class ConversionException extends ValidationBase implements DotenvException
{
    public static function invalidBase64(): self
    {
        return new self(EnvCode::InvalidBase64, 'Invalid base64 value: cannot decode.');
    }

    public static function jsonStructureExpected(string $expected): self
    {
        return new self(EnvCode::JsonStructureExpected, "Value is valid JSON but not a {$expected}.");
    }
}
