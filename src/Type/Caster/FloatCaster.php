<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;

/**
 * Reads a float literal, exponent included, as a PHP float.
 *
 * Preconditions: the value passed the detector of the same type.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new FloatCaster()->cast('1.5e3'); // 1500.0
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure conversion.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class FloatCaster implements TypeCaster
{
    #[\Override]
    public function cast(string $value): float
    {
        return (float) $value;
    }
}
