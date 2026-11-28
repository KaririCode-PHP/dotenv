<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Cache;

/**
 * The contents of a validated cache file: the strategy and environment
 * request that wrote it, the source files it fingerprints, and the raw
 * values in load order.
 *
 * Preconditions: `$sources` lists the files in load order; `$variables`
 * holds the raw strings as loaded, encrypted when `$encrypted` is true.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $snapshot = new PhpFileCache()->load(__DIR__ . '/.env.cache.php');
 *     $fresh = $snapshot !== null && $snapshot->strategy === CacheSnapshot::STRATEGY_LOAD;
 *
 * References: ADR-005. ARFA 1.3 P1: an immutable value.
 *
 * @since   4.5.0
 */
final readonly class CacheSnapshot
{
    public const string STRATEGY_LOAD = 'load';
    public const string STRATEGY_BOOT_ENV = 'bootEnv';

    /**
     * @param string $strategy Loader that produced the values: `load` or `bootEnv`.
     * @param string|null $environment Environment resolved by bootEnv(); null for `load`.
     * @param string|null $requestedEnvironment Environment known before reading files (argument,
     *                                          configuration or process APP_ENV). The cache is reused
     *                                          only when the caller requests the same value again.
     * @param list<string> $sources Every file the loader considered, present or absent.
     * @param array<string, string> $variables Name → raw value (`encrypted:` form when $encrypted).
     * @param bool $encrypted True when every value was stored encrypted.
     */
    public function __construct(
        public string $strategy,
        public ?string $environment,
        public ?string $requestedEnvironment,
        public array $sources,
        public array $variables,
        public bool $encrypted = false,
    ) {}
}
