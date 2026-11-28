<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes any value is_numeric() accepts, floats and exponents included.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new IsNumericRule()->passes('1e10'); // true
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class IsNumericRule implements ValidationRule
{
    #[\Override]
    public function passes(string $value): bool
    {
        return is_numeric($value);
    }

    #[\Override]
    public function message(): string
    {
        return '{name} must be numeric.';
    }
}
