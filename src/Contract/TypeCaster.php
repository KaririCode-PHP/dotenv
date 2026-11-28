<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Contract;

/**
 * Converts a raw environment variable string into a typed PHP value.
 *
 * Each caster is responsible for exactly one ValueType.
 * Casters must be deterministic: same input → same output.
 *
 * Preconditions: called only with values its detector accepted; an
 * implementation is deterministic and keeps no state.
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none in the built-in casters; an implementation must add
 * none.
 *
 * Example:
 *
 *     $dotenv->addTypeCaster(ValueType::Integer, new IntegerCaster());
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure conversion.
 *
 * @since   4.0.0 ARFA 1.3
 */
interface TypeCaster
{
    /** Casts the raw string value into the appropriate PHP type. */
    public function cast(string $value): mixed;
}
