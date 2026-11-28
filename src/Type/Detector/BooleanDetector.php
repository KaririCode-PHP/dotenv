<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Detector;

use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;

/**
 * Detects boolean literals: true/false, yes/no, on/off (case-insensitive).
 *
 * Uses normalized lowercase comparison to match the same values the
 * BooleanCaster handles, avoiding asymmetry between detection and casting.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new BooleanDetector()->detect('off'); // ValueType::Boolean
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class BooleanDetector implements TypeDetector
{
    private const array BOOLEAN_LITERALS = [
        'true', 'false', 'yes', 'no', 'on', 'off',
    ];

    private const array BOOLEAN_PARENTHESIZED = ['(true)', '(false)'];

    #[\Override]
    public function priority(): int
    {
        return 190;
    }

    #[\Override]
    public function detect(string $value): ?ValueType
    {
        if (in_array(needle: strtolower($value), haystack: self::BOOLEAN_LITERALS, strict: true)) {
            return ValueType::Boolean;
        }

        return in_array(needle: $value, haystack: self::BOOLEAN_PARENTHESIZED, strict: true) ? ValueType::Boolean : null;
    }
}
