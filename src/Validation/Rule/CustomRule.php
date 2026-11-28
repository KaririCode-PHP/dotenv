<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a value the given callback accepts, with the message the caller wrote.
 *
 * Preconditions: the callback is pure and returns true for a valid value.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new CustomRule(static fn(string $v): bool => str_starts_with($v, 'pgsql:'), '{name} must use pgsql.');
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class CustomRule implements ValidationRule
{
    /** @param \Closure(string): bool $callback */
    public function __construct(
        private \Closure $callback,
        private string $failureMessage = '{name} failed custom validation.',
    ) {}

    #[\Override]
    public function passes(string $value): bool
    {
        return ($this->callback)($value);
    }

    #[\Override]
    public function message(): string
    {
        return $this->failureMessage;
    }
}
