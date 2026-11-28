<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a value FILTER_VALIDATE_URL accepts.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new UrlRule()->passes('https://example.com'); // true
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class UrlRule implements ValidationRule
{
    #[\Override]
    public function passes(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    #[\Override]
    public function message(): string
    {
        return '{name} must be a valid URL.';
    }
}
