<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * Values visible to `${VAR}` / `$VAR` references that are not defined
 * in the content being parsed.
 *
 * Resolution order for a reference NAME:
 *   1. $locked[NAME]            — wins even over a same-file definition
 *   2. same-file definition     — earlier line of the content being parsed
 *   3. $inherited[NAME]         — defined by a previously loaded file
 *   4. $_ENV, then $_SERVER     — process environment
 *   5. unset
 *
 * `$locked` exists for LoadMode::SkipExisting, where a pre-existing
 * environment value is the effective one even if the file redefines it.
 *
 * Spec V4.0 Value Object: final readonly, no behaviour beyond construction.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final readonly class InterpolationContext
{
    /**
     * @param array<string, string> $inherited Values from earlier files.
     * @param array<string, string> $locked Values that override same-file definitions.
     * @param (\Closure(string): string)|null $valueFilter Maps a same-file raw value to what references see
     *                                                     (Dotenv uses it to decrypt `encrypted:` values).
     */
    public function __construct(
        public array $inherited = [],
        public array $locked = [],
        public ?\Closure $valueFilter = null,
    ) {
    }
}
