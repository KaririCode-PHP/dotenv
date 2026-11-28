<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * One logical entry of a .env file as written on disk: an assignment
 * (possibly spanning several lines) or a verbatim block (blank line,
 * comment, or text that is not a `NAME=value` assignment).
 *
 * Preconditions: `$name` is null for a verbatim block; `$rawValue` is the
 * value as written, quotes and comment included.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     foreach (new EnvFileScanner()->scan($content) as $entry) {
 *         $names[] = $entry->isAssignment() ? $entry->name : null;
 *     }
 *
 * References: SPEC-008. ARFA 1.3 P1: an immutable value.
 *
 * @since   4.5.0
 */
final readonly class EnvEntry
{
    /**
     * @param string $text Exact source text, lines joined with "\n".
     * @param string|null $name Variable name, or null for verbatim entries.
     * @param bool $exported True when written with the `export` prefix.
     * @param string $rawValue Source text after `=` (left-trimmed), before parsing.
     */
    public function __construct(
        public string $text,
        public ?string $name = null,
        public bool $exported = false,
        public string $rawValue = '',
    ) {}

    public function isAssignment(): bool
    {
        return $this->name !== null;
    }
}
