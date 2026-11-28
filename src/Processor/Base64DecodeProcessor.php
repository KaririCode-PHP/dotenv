<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;
use KaririCode\Dotenv\Exception\ConversionException;

final readonly class Base64DecodeProcessor implements VariableProcessor
{
    public function process(string $rawValue, mixed $typedValue): string
    {
        $decoded = base64_decode($rawValue, true);

        if ($decoded === false) {
            throw ConversionException::invalidBase64();
        }

        return $decoded;
    }
}
