<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a value that is one of the allowed strings, compared exactly.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new AllowedValuesRule(['local', 'production'])->passes('staging'); // false
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class AllowedValuesRule implements ValidationRule
{
    /** @param list<string> $allowed */
    public function __construct(
        private array $allowed,
    ) {}

    #[\Override]
    public function passes(string $value): bool
    {
        return in_array(needle: $value, haystack: $this->allowed, strict: true);
    }

    #[\Override]
    public function message(): string
    {
        $list = implode(', ', $this->allowed);

        return "{name} must be one of: {$list}.";
    }
}
