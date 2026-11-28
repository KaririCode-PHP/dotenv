<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Integration;

use KaririCode\Contract\Environment\EnvironmentResolver;
use KaririCode\Dotenv\Dotenv;

/**
 * Exposes what a Dotenv instance loaded through the EnvironmentResolver
 * contract of kariricode/contract, so the configurator, the container or a
 * CLI override reads the .env store without touching $_ENV, $_SERVER or
 * getenv(). get() returns the string the file held, decrypted, before type
 * casting and processors, and only for variables this instance loaded and
 * its allow and deny lists admit: a variable that only the process defines
 * is not this resolver's to answer, so compose it with a resolver of the
 * system environment for that.
 *
 * Preconditions: the Dotenv instance is loaded before the first read; until
 * then the resolver defines nothing.
 *
 * Thread-safety: holds only the Dotenv instance and answers from its loaded
 * store, which does not change once loading is done.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv = new Dotenv(__DIR__);
 *     $dotenv->bootEnv();
 *     $environment = new DotenvEnvironmentResolver($dotenv);
 *     $dsn = $environment->get('DATABASE_URL') ?? 'sqlite::memory:';
 *
 * References: ADR-011; kariricode/contract 2.9 and later,
 * `Environment\EnvironmentResolver`. ARFA 1.3 P4: components read the
 * environment through a contract, not the process globals.
 *
 * @since 4.5.0
 */
final readonly class DotenvEnvironmentResolver implements EnvironmentResolver
{
    public function __construct(
        private Dotenv $dotenv,
    ) {}

    #[\Override]
    public function get(string $name): ?string
    {
        return ($this->dotenv->variables()[$name] ?? null)?->rawValue;
    }

    #[\Override]
    public function has(string $name): bool
    {
        return isset($this->dotenv->variables()[$name]);
    }
}
