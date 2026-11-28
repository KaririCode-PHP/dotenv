<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes the boolean literals the type system reads: true, false, 1, 0, yes, no, on and off, in any case.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new IsBooleanRule()->passes('Off'); // true
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class IsBooleanRule implements ValidationRule
{
    private const array ACCEPTED = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];

    #[\Override]
    public function passes(string $value): bool
    {
        return in_array(needle: strtolower($value), haystack: self::ACCEPTED, strict: true);
    }

    #[\Override]
    public function message(): string
    {
        return '{name} must be a boolean (true/false, yes/no, on/off, 1/0).';
    }
}
