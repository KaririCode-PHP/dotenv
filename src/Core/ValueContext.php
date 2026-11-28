<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * What parsing one value needs besides the value itself: the lines a
 * double-quoted value may continue onto, the file to name in errors, the
 * token that stands for an escaped dollar until expansion, and the
 * expansion of references.
 *
 * @internal Built by DotenvParser for one parse() call.
 *
 * @package KaririCode\Dotenv
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
