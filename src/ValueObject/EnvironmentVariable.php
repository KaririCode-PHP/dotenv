<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\ValueObject;

use KaririCode\Dotenv\Enum\ValueType;

/**
 * Immutable representation of a single parsed environment variable.
 *
 * Holds the raw string, detected type, cast PHP value, and source metadata.
 * Once constructed, no mutation is possible — ARFA 1.3 P1 compliant.
 *
 * Preconditions: `$rawValue` is the decrypted string; `$value` is what
 * casting and processors made of it.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $variable = $dotenv->variables()['APP_DEBUG'];
 *     $fromFile = $variable->source; // the path of the file that set it
 *
 * References: ADR-002, SPEC-001. ARFA 1.3 P1: an immutable value.
 *
 * @since   4.0.0 ARFA 1.3
 */
final readonly class EnvironmentVariable
{
    /**
     * @param string $name Variable name.
     * @param string $rawValue Value after decryption, before type casting.
     * @param ValueType $type Detected type.
     * @param mixed $value Cast PHP value.
     * @param string $source File path or label (e.g., ".env.local", "cache", "environment").
     * @param bool $overridden True if this value replaced a previous source.
     * @param bool $encrypted True if the source stored this value as `encrypted:...`.
     */
    public function __construct(
        public string $name,
        public string $rawValue,
        public ValueType $type,
        public mixed $value,
        public string $source = '',
        public bool $overridden = false,
        public bool $encrypted = false,
    ) {}
}
