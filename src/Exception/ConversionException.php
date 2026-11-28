<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * A raw value could not be converted by a caster or processor.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final class ConversionException extends DotenvException
{
    public static function invalidBase64(): self
    {
        return new self('Invalid base64 value: cannot decode.');
    }

    public static function jsonStructureExpected(string $expected): self
    {
        return new self("Value is valid JSON but not a {$expected}.");
    }
}
