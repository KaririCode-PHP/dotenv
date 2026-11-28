<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Exception\ConversionException;

/**
 * Decodes a JSON object string into a PHP associative array.
 *
 * Throws JsonException on malformed input rather than returning false/null,
 * ensuring fail-fast behavior in production.
 *
 * @package KaririCode\Dotenv
 * @since   4.0.0 ARFA 1.3
 */
final readonly class JsonCaster implements TypeCaster
{
    /**
     * @return array<mixed>
     *
     * @throws \JsonException On malformed JSON.
     * @throws ConversionException When the JSON is valid but not an object or array.
     */
    #[\Override]
    public function cast(string $value): array
    {
        $decoded = json_decode(trim($value), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw ConversionException::jsonStructureExpected('JSON object');
        }

        return $decoded;
    }
}
