<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Exception\ConversionException;

/**
 * Decodes a JSON array string into a PHP list array.
 *
 * @package KaririCode\Dotenv
 * @since   4.0.0 ARFA 1.3
 */
final readonly class ArrayCaster implements TypeCaster
{
    /**
     * @return list<mixed>
     *
     * @throws \JsonException On malformed JSON.
     * @throws ConversionException When the JSON is valid but not a list.
     */
    #[\Override]
    public function cast(string $value): array
    {
        $decoded = json_decode(trim($value), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw ConversionException::jsonStructureExpected('JSON array');
        }

        return $decoded;
    }
}
