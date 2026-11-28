<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;

/**
 * Passes a numeric value inside the closed range [min, max].
 *
 * Preconditions: min is not greater than max.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new BetweenRule(1, 65535)->passes('8080'); // true
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class BetweenRule implements ValidationRule
{
    public function __construct(
        private int|float $min,
        private int|float $max,
    ) {}

    #[\Override]
    public function passes(string $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $numeric = (float) $value;

        return $numeric >= $this->min && $numeric <= $this->max;
    }

    #[\Override]
    public function message(): string
    {
        return "{name} must be between {$this->min} and {$this->max}.";
    }
}
