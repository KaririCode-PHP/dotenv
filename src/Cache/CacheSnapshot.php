<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Cache;

/**
 * Contents of a validated cache file.
 *
 * Spec V4.0 Value Object: final readonly.
 *
 * @package KaririCode\Dotenv
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
