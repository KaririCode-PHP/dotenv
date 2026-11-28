<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;

/**
 * Reads a signed integer literal as a PHP int.
 *
 * Preconditions: the value passed the detector of the same type.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new IntegerCaster()->cast('-42'); // -42
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure conversion.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class IntegerCaster implements TypeCaster
{
    #[\Override]
    public function cast(string $value): int
    {
        return (int) $value;
    }
}
