<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\RuntimeException as RuntimeBase;

// ── Exception: CacheException ────────────────────────────────────────
// A cache that cannot be written or has nothing to hold (2760–2769).
// ─────────────────────────────────────────────────────────────────────

/**
 * Reports a cache dump that cannot happen: the temporary file cannot be
 * written or renamed into place, or `dumpCache()` ran before anything was
 * loaded. A cache that cannot be read is not an error: `load()` parses the
 * files instead (ADR-005).
 *
 * Preconditions: built only through its named constructors.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->load();
 *         $dotenv->dumpCache(__DIR__ . '/.env.cache.php');
 *     } catch (CacheException $e) {
 *         $unwritable = $e->errorCode === EnvCode::CacheWriteFailed;
 *     }
 *
 * References: ADR-005, ADR-010; Spec V4.3.3 §8.4 (Runtime family).
 * ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class CacheException extends RuntimeBase implements DotenvException
{
    public static function writeFailed(string $path): self
    {
        return new self(EnvCode::CacheWriteFailed, 'Failed to write cache file: ' . self::quote($path));
    }

    public static function notLoaded(): self
    {
        return new self(EnvCode::CacheNotLoaded, 'Cannot dump the cache before load(), safeLoad() or bootEnv() has run.');
    }
}
