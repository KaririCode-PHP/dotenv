<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Contract;

use KaririCode\Dotenv\Enum\ValueType;

/**
 * Determines the semantic type of a raw environment variable string.
 *
 * Implementations are ordered by priority (higher = checked first).
 * The first detector returning a non-null ValueType wins.
 *
 * Preconditions: detect() looks at the raw string only and returns null
 * when the value is not its type.
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none in the built-in detectors; an implementation must add
 * none.
 *
 * Example:
 *
 *     $dotenv->addTypeDetector(new BooleanDetector());
 *
 * References: ADR-003, SPEC-003. ARFA 1.3 P1: pure detection.
 *
 * @since   4.0.0 ARFA 1.3
 */
interface TypeDetector
{
    /** Detection priority — higher values are checked first. */
    public function priority(): int;

    /** Returns the detected type, or null if this detector does not match. */
    public function detect(string $value): ?ValueType;
}
