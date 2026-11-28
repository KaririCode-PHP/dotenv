<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Detector;

use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;

/**
 * Detects JSON array values: strings wrapped in square brackets that decode successfully.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new ArrayDetector()->detect('["a"]'); // ValueType::Array
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class ArrayDetector implements TypeDetector
{
    #[\Override]
    public function priority(): int
    {
        return 150;
    }

    #[\Override]
    public function detect(string $value): ?ValueType
    {
        $trimmed = trim($value);

        if (!str_starts_with($trimmed, '[') || !str_ends_with($trimmed, ']')) {
            return null;
        }

        return is_array(json_decode($trimmed, true)) ? ValueType::Array : null;
    }
}
