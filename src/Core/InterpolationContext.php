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
 * Preconditions: `$valueFilter` turns a stored value into its plaintext and
 * is called only for same-file references.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $parsed = new DotenvParser()->parse(content: $content, filePath: '.env.local', context: new InterpolationContext(inherited: $earlier));
 *
 * References: SPEC-002 §6, ADR-006. ARFA 1.3 P1: an immutable value.
 *
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
    ) {}
}
