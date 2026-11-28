<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Contract;

/**
 * Post-load transformer applied to environment variable values.
 * Processors run after type casting and can further transform the typed value.
 *
 * Preconditions: process() receives the decrypted raw string and the value
 * after type casting; it returns the value the application reads.
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none in the built-in processors; an implementation must add
 * none.
 *
 * Example:
 *
 *     $dotenv->addProcessor('*_HOSTS', new CsvToArrayProcessor());
 *
 * References: SPEC-007. ARFA 1.3 P1: values transform, the source stays.
 *
 * @since   4.4.0
 */
interface VariableProcessor
{
    public function process(string $rawValue, mixed $typedValue): mixed;
}
