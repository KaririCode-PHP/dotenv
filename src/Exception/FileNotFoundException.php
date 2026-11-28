<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\NotFoundException as NotFoundBase;

// ── Exception: FileNotFoundException ─────────────────────────────────
// A required .env or schema file that is not there (2710).
// ─────────────────────────────────────────────────────────────────────

/**
 * Reports a file the caller required that does not exist or cannot be
 * read: a .env file under `load()`, a schema under `loadWithSchema()`, or
 * a file the CLI was pointed at. `safeLoad()` and the optional files of
 * the environment cascade never raise it.
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
 *     } catch (FileNotFoundException $e) {
 *         $dotenv->safeLoad(); // or fail the boot
 *     }
 *
 * References: ADR-007, ADR-010; Spec V4.3.3 §8.4 (NotFound family).
 * ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class FileNotFoundException extends NotFoundBase implements DotenvException
{
    public static function forPath(string $path): self
    {
        return new self(EnvCode::FileNotFound, 'Environment file not found or not readable: ' . self::quote($path));
    }
}
