<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * One logical entry of a .env file as written on disk: an assignment
 * (possibly spanning several lines) or a verbatim block (blank line,
 * comment, or text that is not a `NAME=value` assignment).
 *
 * Spec V4.0 Value Object: final readonly.
 *
 * @package KaririCode\Dotenv
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
