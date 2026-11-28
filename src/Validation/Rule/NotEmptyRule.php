<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a value with at least one character that is not whitespace.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new NotEmptyRule()->passes('   '); // false
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class NotEmptyRule implements ValidationRule
{
    #[\Override]
    public function passes(string $value): bool
    {
        return trim($value) !== '';
    }

    #[\Override]
    public function message(): string
    {
        return '{name} must not be empty.';
    }
}
