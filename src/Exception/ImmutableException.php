<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\RuntimeException as RuntimeBase;

// ── Exception: ImmutableException ────────────────────────────────────
// A file that redefines a variable the process already has (2711).
// ─────────────────────────────────────────────────────────────────────

/**
 * Stops a load in `LoadMode::Immutable` when a file defines a variable the
 * process held before this instance loaded anything; a later file of the
 * same instance overriding an earlier one is a cascade, not a conflict.
 * The message names the variable, never its value.
 *
 * Preconditions: built only through its named constructor.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->load();
 *     } catch (ImmutableException $e) {
 *         // the deployment sets the variable already; drop it from .env or use LoadMode::SkipExisting
 *     }
 *
 * References: ADR-006, ADR-010; Spec V4.3.3 §8.4 (Runtime family).
 * ARFA 1.3 P1: the process environment is not overwritten silently.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class ImmutableException extends RuntimeBase implements DotenvException
{
    public static function alreadyDefined(string $name): self
    {
        return new self(
            EnvCode::AlreadyDefined,
            'Environment variable ' . self::quote($name) . ' is already defined and cannot be overwritten in Immutable mode.',
        );
    }
}
