<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * What parsing one value needs besides the value itself: the lines a
 * double-quoted value may continue onto, the file to name in errors, the
 * token that stands for an escaped dollar until expansion, and the
 * expansion of references.
 *
 * Preconditions: built by DotenvParser for one parse() call; `$lines` are
 * the normalized lines of that content.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: expand() runs the expander, which reads the variables
 * parsed so far.
 *
 * Example:
 *
 *     $value = $values->expand('${HOST}:8080');
 *
 * References: SPEC-002 §5–6, ADR-009.
 *
 * @internal Built by DotenvParser for one parse() call.
 * @since   4.5.0
 */
final readonly class ValueContext
{
    /**
     * @param list<string> $lines All normalized lines of the content.
     * @param \Closure(string): string $expander Interpolates the references of a value.
     */
    public function __construct(
        public array $lines,
        public string $filePath,
        public string $escapedDollar,
        private \Closure $expander,
    ) {}

    /** The value with its references interpolated and escaped dollars restored. */
    public function expand(string $value): string
    {
        return ($this->expander)($value);
    }
}
