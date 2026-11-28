<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a signed integer literal.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new IsIntegerRule()->passes('-10'); // true
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class IsIntegerRule implements ValidationRule
{
    #[\Override]
    public function passes(string $value): bool
    {
        return preg_match('/\A[+-]?\d+\z/', $value) === 1;
    }

    #[\Override]
    public function message(): string
    {
        return '{name} must be an integer.';
    }
}
