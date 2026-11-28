<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Type\Detector;

use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;

/**
 * Detects null literals: "null", "NULL", "(null)".
 *
 * Empty string ('') is intentionally excluded — `FOO=` must yield an
 * empty string, not null. This matches POSIX behavior and prevents
 * data loss when distinguishing between `FOO=` and `FOO=null`.
 *
 * Preconditions: none; any string may be passed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new NullDetector()->detect('(null)'); // ValueType::Null
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class NullDetector implements TypeDetector
{
    private const array NULL_LITERALS = ['null', 'NULL', '(null)'];

    #[\Override]
    public function priority(): int
    {
        return 200;
    }

    #[\Override]
    public function detect(string $value): ?ValueType
    {
        return in_array(needle: $value, haystack: self::NULL_LITERALS, strict: true) ? ValueType::Null : null;
    }
}
