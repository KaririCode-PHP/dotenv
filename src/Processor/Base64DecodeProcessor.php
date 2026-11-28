<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;
use KaririCode\Dotenv\Exception\ConversionException;

/**
 * Decodes a base64 value into its bytes, so binary secrets such as
 * certificates can live on one line of a .env file; invalid base64 is a
 * ConversionException, never a silent empty string.
 *
 * Preconditions: the raw value is standard base64 (strict alphabet).
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv->addProcessor('*_B64', new Base64DecodeProcessor());
 *
 * References: SPEC-007. ARFA 1.3 P1: values transform, the source stays.
 */
final readonly class Base64DecodeProcessor implements VariableProcessor
{
    #[\Override]
    public function process(string $rawValue, mixed $typedValue): string
    {
        $decoded = base64_decode($rawValue, true);

        if ($decoded === false) {
            throw ConversionException::invalidBase64();
        }

        return $decoded;
    }
}
