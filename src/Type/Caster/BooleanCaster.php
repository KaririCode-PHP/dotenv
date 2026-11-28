<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;

/**
 * Reads the boolean literals BooleanDetector accepts: true, yes, on and 1 as
 * true, anything else as false, ignoring case.
 *
 * Preconditions: the value passed the detector of the same type.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new BooleanCaster()->cast('Yes'); // true
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure conversion.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class BooleanCaster implements TypeCaster
{
    private const array TRUE_VALUES = ['true', 'yes', 'on', '(true)'];

    #[\Override]
    public function cast(string $value): bool
    {
        return in_array(needle: strtolower($value), haystack: self::TRUE_VALUES, strict: true);
    }
}
