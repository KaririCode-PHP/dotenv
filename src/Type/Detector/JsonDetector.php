<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Detector;

use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;

/**
 * Detects JSON object values: strings wrapped in curly braces that decode successfully.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new JsonDetector()->detect('{"a": 1}'); // ValueType::Json
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class JsonDetector implements TypeDetector
{
    #[\Override]
    public function priority(): int
    {
        return 160;
    }

    #[\Override]
    public function detect(string $value): ?ValueType
    {
        $trimmed = trim($value);

        if (!str_starts_with($trimmed, '{') || !str_ends_with($trimmed, '}')) {
            return null;
        }

        json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE ? ValueType::Json : null;
    }
}
