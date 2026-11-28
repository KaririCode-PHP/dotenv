<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Detector;

use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;

/**
 * Detects integer values: optional sign followed by digits only.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new IntegerDetector()->detect('8080'); // ValueType::Integer
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class IntegerDetector implements TypeDetector
{
    #[\Override]
    public function priority(): int
    {
        return 180;
    }

    #[\Override]
    public function detect(string $value): ?ValueType
    {
        if ($value === '' || $value === '-' || $value === '+') {
            return null;
        }

        return preg_match('/\A[+-]?\d+\z/', $value) === 1 ? ValueType::Integer : null;
    }
}
