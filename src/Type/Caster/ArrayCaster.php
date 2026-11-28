<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Caster;

use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Exception\ConversionException;

/**
 * Decodes a JSON array string into a PHP list array.
 *
 * Preconditions: the value passed the detector of the same type.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new ArrayCaster()->cast('[1, 2]'); // [1, 2]
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure conversion.
 *
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
        // JSON allows whitespace around a value, so the raw string is decoded as it is.
        $decoded = json_decode(json: $value, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw ConversionException::jsonStructureExpected('JSON array');
        }

        return $decoded;
    }
}
