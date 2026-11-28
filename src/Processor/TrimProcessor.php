<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;

/**
 * Trims the raw value with the configured characters and returns the
 * string, replacing whatever type casting produced.
 *
 * Preconditions: none; the characters default to trim()'s whitespace set.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv->addProcessor('*_NAME', new TrimProcessor());
 *
 * References: SPEC-007. ARFA 1.3 P1: values transform, the source stays.
 */
final readonly class TrimProcessor implements VariableProcessor
{
    public function __construct(
        private string $characters = " \t\n\r\0\x0B",
    ) {}

    #[\Override]
    public function process(string $rawValue, mixed $typedValue): string
    {
        return trim($rawValue, $this->characters);
    }
}
