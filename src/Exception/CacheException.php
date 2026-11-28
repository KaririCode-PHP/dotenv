<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * Failures while writing the OPcache-friendly PHP cache.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final class CacheException extends DotenvException
{
    public static function writeFailed(string $path): self
    {
        return new self("Failed to write cache file: {$path}");
    }

    public static function notLoaded(): self
    {
        return new self('Cannot dump the cache before load(), safeLoad() or bootEnv() has run.');
    }
}
