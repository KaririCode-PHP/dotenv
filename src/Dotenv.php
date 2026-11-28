<?php

declare(strict_types=1);

namespace KaririCode\Dotenv;

use KaririCode\Dotenv\Cache\CacheSnapshot;
use KaririCode\Dotenv\Cache\PhpFileCache;
use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Contract\VariableProcessor;
use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Core\InterpolationContext;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Enum\ValueType;
use KaririCode\Dotenv\Exception\CacheException;
use KaririCode\Dotenv\Exception\ConfigurationException;
use KaririCode\Dotenv\Exception\FileNotFoundException;
use KaririCode\Dotenv\Exception\ImmutableException;
use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Schema\SchemaParser;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Type\TypeSystem;
use KaririCode\Dotenv\Validation\EnvironmentValidator;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use KaririCode\Dotenv\ValueObject\EnvironmentVariable;

/**
 * Production-grade .env file loader for the KaririCode Framework.
 *
 * Combines auto type casting, native AES-256-GCM encryption,
 * OPcache-friendly caching, fluent validation DSL, environment-aware
 * cascade loading, and variable processors, with no dependency beyond
 * kariricode/contract and kariricode/exception (ADR-001, ADR-010).
 *
 * ## Quick Start
 *
 * ```php
 * $dotenv = new Dotenv('/path/to/project');
 * $dotenv->load();
 *
 * $debug = env('APP_DEBUG'); // bool: true
 * $port  = env('DB_PORT');   // int: 5432
 * ```
 *
 * ## Encrypted .env
 *
 * ```php
 * $config = new DotenvConfiguration(
 *     encryptionKey: $_SERVER['DOTENV_PRIVATE_KEY'] ?? null,
 * );
 * $dotenv = new Dotenv('/path/to/project', $config);
 * $dotenv->load(); // Transparently decrypts "encrypted:..." values
 * ```
 *
 * ## Environment-Aware Loading
 *
 * ```php
 * $dotenv = new Dotenv('/path/to/project');
 * $dotenv->bootEnv(); // .env → .env.local → .env.{APP_ENV} → .env.{APP_ENV}.local
 * ```
 *
 * ARFA 1.3 Level 1 (Structural):
 * - P1 Immutable State: DotenvConfiguration and EnvironmentVariable are readonly.
 * - P4 Protocol Agnostic: the parser accepts any string content.
 * - P5 Continuous Observability: debug() reports source, type, override and encryption.
 *
 * Preconditions: the directory exists; load(), safeLoad() and bootEnv() run
 * once per instance, before the application reads its configuration.
 *
 * Thread-safety: one instance per process, used from one fiber; loading
 * writes the superglobals and the process environment, which every fiber
 * shares.
 *
 * Side effects: reads the .env files and the cache; writes $_ENV, $_SERVER
 * and putenv() as configured; dumpCache() and clearCache() write and delete
 * the cache file.
 *
 * Example:
 *
 *     $dotenv = new Dotenv(__DIR__);
 *     $dotenv->bootEnv(); // .env, .env.local, .env.{APP_ENV}, .env.{APP_ENV}.local
 *     $port = $dotenv->get('DB_PORT', 5432);
 *
 * References: ADR-002, ADR-005 to ADR-008, SPEC-001. ARFA 1.3 P1, P4, P5.
 *
 * @since   4.0.0 ARFA 1.3
 */
final class Dotenv
{
    private const string ENVIRONMENT_NAME_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/';

    private readonly DotenvParser $parser;
    private readonly TypeSystem $typeSystem;
    private readonly DotenvConfiguration $configuration;
    private readonly string $directory;
    private readonly ?Encryptor $encryptor;

    /** @var list<string> Files given to the constructor; read by load() and safeLoad(). */
    private readonly array $filePaths;

    /** @var array<string, EnvironmentVariable> Loaded variables keyed by name. */
    private array $variables = [];

    /** @var list<array{0: string, 1: VariableProcessor}> [pattern, processor] in registration order. */
    private array $processors = [];

    /** @var list<string> Every file the last load considered, present or absent. */
    private array $sourceFiles = [];

    private string $strategy = CacheSnapshot::STRATEGY_LOAD;
    private ?string $environment = null;
    private ?string $requestedEnvironment = null;
    private bool $loaded = false;

    public function __construct(
        string $directory,
        ?DotenvConfiguration $configuration = null,
        string ...$fileNames,
    ) {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->parser = new DotenvParser();
        $this->typeSystem = new TypeSystem();
        $this->configuration = $configuration ?? new DotenvConfiguration();
        $this->encryptor = $this->createEncryptor();

        $paths = [];

        foreach ($fileNames === [] ? ['.env'] : $fileNames as $fileName) {
            $paths[] = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        }

        $this->filePaths = $paths;
    }

    // ── Loading ──────────────────────────────────────────────────────────

    /**
     * Loads all configured .env files. When a fresh cache exists for the
     * same files, loads from it instead (no parsing).
     *
     * @throws FileNotFoundException When a configured file does not exist.
     * @throws ImmutableException When a variable conflicts in Immutable mode.
     */
    public function load(): void
    {
        $this->loadConfiguredFiles(required: true);
    }

    /**
     * Same as load(), but silently skips missing files.
     */
    public function safeLoad(): void
    {
        $this->loadConfiguredFiles(required: false);
    }

    /**
     * Environment-aware cascade loading.
     *
     * Loads files in order (each one optional):
     * 1. `.env` — base defaults (committed)
     * 2. `.env.local` — local overrides (gitignored)
     * 3. `.env.{env}` — environment-specific defaults (committed)
     * 4. `.env.{env}.local` — environment-specific local overrides (gitignored, skipped for "test")
     *
     * The environment is the argument, else the configured environmentName,
     * else APP_ENV (from the files loaded so far or the process), else "dev".
     *
     * @throws ConfigurationException When the environment name is not a safe file-name segment.
     */
    public function bootEnv(?string $environmentName = null): void
    {
        $basePath = $this->directory . DIRECTORY_SEPARATOR . '.env';
        $requested = $environmentName ?? $this->configuration->environmentName ?? $this->processEnvironmentName();

        if ($requested !== null) {
            $this->assertValidEnvironmentName($requested);
        }

        if ($this->loadFromCache(strategy: CacheSnapshot::STRATEGY_BOOT_ENV, requested: $requested, basePath: $basePath)) {
            return;
        }

        $this->loadFile($basePath, required: false);
        $this->loadFile($basePath . '.local', required: false);

        $fromFiles = $this->resolveRawValue('APP_ENV');
        $envName = $environmentName
            ?? $this->configuration->environmentName
            ?? ($fromFiles !== null && $fromFiles !== '' ? $fromFiles : 'dev');
        $this->assertValidEnvironmentName($envName);

        $sources = [$basePath, $basePath . '.local', "{$basePath}.{$envName}"];
        $this->loadFile("{$basePath}.{$envName}", required: false);

        // .env.test.local is skipped so test runs are reproducible
        if ($envName !== 'test') {
            $sources[] = "{$basePath}.{$envName}.local";
            $this->loadFile("{$basePath}.{$envName}.local", required: false);
        }

        $this->markLoaded(strategy: CacheSnapshot::STRATEGY_BOOT_ENV, environment: $envName, requested: $requested, sources: $sources);
    }

    // ── Validation ───────────────────────────────────────────────────────

    /**
     * Asserts that all named variables are defined (loaded or in the process environment).
     *
     * @throws ValidationException Listing every missing variable.
     */
    public function required(string ...$names): void
    {
        $missing = [];

        foreach ($names as $name) {
            if ($this->resolveRawValue($name) === null) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw ValidationException::missingRequired($missing);
        }
    }

    /**
     * Returns a fluent validation builder. Call ->assert() to execute.
     *
     * ```php
     * $dotenv->validate()
     *     ->required('DB_HOST', 'DB_PORT')
     *     ->isInteger('DB_PORT')->between(1, 65535)
     *     ->assert();
     * ```
     */
    public function validate(): EnvironmentValidator
    {
        return new EnvironmentValidator(
            fn(string $name): ?string => $this->resolveRawValue($name),
        );
    }

    /**
     * Loads (if needed) and applies a .env.schema file for declarative validation.
     *
     * @throws ValidationException On any schema violation.
     * @throws FileNotFoundException When the schema file is missing.
     */
    public function loadWithSchema(string $schemaPath): void
    {
        if (!$this->loaded) {
            $this->load();
        }

        if (!is_file($schemaPath) || !is_readable($schemaPath)) {
            throw FileNotFoundException::forPath($schemaPath);
        }

        $content = file_get_contents($schemaPath);

        if ($content === false) {
            throw FileNotFoundException::forPath($schemaPath);
        }

        $schemaParser = new SchemaParser();
        $validator = $this->validate();
        $schemaParser->applyToValidator($schemaParser->parse($content), $validator);
        $validator->assert();
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function get(string $name, mixed $default = null): mixed
    {
        return isset($this->variables[$name])
            ? $this->variables[$name]->value
            : $default;
    }

    /** @return array<string, EnvironmentVariable> */
    public function variables(): array
    {
        return $this->variables;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Environment resolved by the last bootEnv() call (from files, cache or
     * process); null after load()/safeLoad() or before loading.
     */
    public function environment(): ?string
    {
        return $this->environment;
    }

    // ── Debug & Introspection ────────────────────────────────────────────

    /**
     * Returns a report with source tracking, types, override and encryption info.
     * Values are reported decrypted; callers that print it must mask secrets.
     *
     * @return array<string, array{source: string, rawValue: string, type: string, value: mixed, overridden: bool, encrypted: bool}>
     */
    public function debug(): array
    {
        $report = [];

        foreach ($this->variables as $name => $variable) {
            $report[$name] = [
                'source' => $variable->source,
                'rawValue' => $variable->rawValue,
                'type' => $variable->type->name,
                'value' => $variable->value,
                'overridden' => $variable->overridden,
                'encrypted' => $variable->encrypted,
            ];
        }

        return $report;
    }

    // ── Cache ────────────────────────────────────────────────────────────

    /**
     * Writes the loaded variables to an OPcache-friendly PHP file.
     *
     * With an encryption key configured, every value is stored encrypted,
     * so the cache never holds plaintext secrets — including values that
     * were interpolated from encrypted ones.
     *
     * @throws CacheException When nothing was loaded yet or the file cannot be written.
     */
    public function dumpCache(string $path): void
    {
        if (!$this->loaded) {
            throw CacheException::notLoaded();
        }

        $values = [];

        foreach ($this->variables as $name => $variable) {
            $values[$name] = $this->encryptor !== null
                ? $this->encryptor->encrypt($variable->rawValue)
                : $variable->rawValue;
        }

        new PhpFileCache()->dump($path, new CacheSnapshot(
            strategy: $this->strategy,
            environment: $this->environment,
            requestedEnvironment: $this->requestedEnvironment,
            sources: $this->sourceFiles,
            variables: $values,
            encrypted: $this->encryptor !== null,
        ));
    }

    public function clearCache(string $path): void
    {
        new PhpFileCache()->clear($path);
    }

    // ── Extension Points ─────────────────────────────────────────────────

    public function addTypeDetector(TypeDetector $detector): void
    {
        $this->typeSystem->addDetector($detector);
    }

    public function addTypeCaster(ValueType $type, TypeCaster $caster): void
    {
        $this->typeSystem->addCaster($type, $caster);
    }

    /**
     * Registers a post-load processor for variables matching a name or glob pattern.
     *
     * @param string $pattern Exact name or glob (e.g., "*_URL", "ALLOWED_IPS").
     * @param VariableProcessor $processor Transformer applied after type casting.
     */
    public function addProcessor(string $pattern, VariableProcessor $processor): void
    {
        $this->processors[] = [$pattern, $processor];
    }

    // ── Internal: Loading ────────────────────────────────────────────────

    private function loadConfiguredFiles(bool $required): void
    {
        if ($this->loadFromCache(strategy: CacheSnapshot::STRATEGY_LOAD, requested: null, basePath: null)) {
            return;
        }

        foreach ($this->filePaths as $filePath) {
            $this->loadFile($filePath, $required);
        }

        $this->markLoaded(strategy: CacheSnapshot::STRATEGY_LOAD, environment: null, requested: null, sources: $this->filePaths);
    }

    /**
     * @param list<string> $sources
     */
    private function markLoaded(string $strategy, ?string $environment, ?string $requested, array $sources): void
    {
        $this->strategy = $strategy;
        $this->environment = $environment;
        $this->requestedEnvironment = $requested;
        $this->sourceFiles = $sources;
        $this->loaded = true;
    }

    /**
     * Uses the cache only when it was produced by the same loader, for the
     * same files and the same requested environment, and every recorded
     * source file is unchanged (checked by PhpFileCache).
     */
    private function loadFromCache(string $strategy, ?string $requested, ?string $basePath): bool
    {
        $snapshot = $this->matchingSnapshot(strategy: $strategy, requested: $requested, basePath: $basePath);

        if ($snapshot === null) {
            return false;
        }

        foreach ($snapshot->variables as $name => $value) {
            $this->setVariable(name: $name, sourceValue: $value, source: 'cache');
        }

        $this->markLoaded(strategy: $strategy, environment: $snapshot->environment, requested: $requested, sources: $snapshot->sources);

        return true;
    }

    /**
     * The configured cache's snapshot, when the same strategy, environment
     * request and files wrote it and its values can be decrypted.
     */
    private function matchingSnapshot(string $strategy, ?string $requested, ?string $basePath): ?CacheSnapshot
    {
        $cachePath = $this->configuration->cachePath;
        $snapshot = $cachePath === null ? null : new PhpFileCache()->load($cachePath);

        if ($snapshot === null
            || $snapshot->strategy !== $strategy
            || $snapshot->requestedEnvironment !== $requested
            || ($snapshot->encrypted && $this->encryptor === null)
        ) {
            return null;
        }

        $sameFiles = $strategy === CacheSnapshot::STRATEGY_LOAD
            ? $snapshot->sources === $this->filePaths
            : ($snapshot->sources[0] ?? null) === $basePath;

        return $sameFiles ? $snapshot : null;
    }

    private function loadFile(string $filePath, bool $required): void
    {
        $content = is_file($filePath) && is_readable($filePath) ? file_get_contents($filePath) : false;

        if ($content === false) {
            if ($required) {
                throw FileNotFoundException::forPath($filePath);
            }

            return;
        }

        $parsed = $this->parser->parse(
            content: $content,
            filePath: $filePath,
            strictNames: $this->configuration->strictNames,
            context: $this->interpolationContext(),
        );

        $source = basename($filePath);

        foreach ($parsed as $name => $value) {
            $this->setVariable(name: $name, sourceValue: $value, source: $source);
        }
    }

    /**
     * References in the next file see: variables already loaded (decrypted),
     * and — under SkipExisting — the pre-existing environment values that
     * win over the file's own definitions.
     */
    private function interpolationContext(): InterpolationContext
    {
        $inherited = [];

        foreach ($this->variables as $name => $variable) {
            $inherited[$name] = $variable->rawValue;
        }

        $encryptor = $this->encryptor;

        return new InterpolationContext(
            inherited: $inherited,
            locked: $this->configuration->loadMode === LoadMode::SkipExisting ? $this->preExistingEnvironment() : [],
            valueFilter: $encryptor === null ? null : static fn(string $value): string => $encryptor->decrypt($value),
        );
    }

    private function setVariable(string $name, string $sourceValue, string $source): void
    {
        if (!$this->accepts($name)) {
            return;
        }

        $encrypted = Encryptor::isEncrypted($sourceValue);
        $value = $encrypted && $this->encryptor !== null
            ? $this->encryptor->decrypt($sourceValue)
            : $sourceValue;

        $type = $this->configuration->typeCasting ? $this->typeSystem->detect($value) : ValueType::String;
        /** @var mixed $typedValue casters and processors may return any value */
        $typedValue = $this->applyProcessors(name: $name, value: $value, typedValue: $this->typeSystem->cast($value, $type));

        $this->variables[$name] = new EnvironmentVariable(
            name: $name,
            rawValue: $value,
            type: $type,
            value: $typedValue,
            source: $source,
            overridden: isset($this->variables[$name]),
            encrypted: $encrypted,
        );

        $this->publish($name, $value);
    }

    /**
     * Whether a loaded value may set the variable: the allow-list admits it,
     * and the load mode lets it replace a value the process held before this
     * instance loaded it. A variable an earlier file of this instance loaded
     * is a cascade override, not a pre-existing value.
     *
     * @throws ImmutableException When the mode is immutable and the process already defines it.
     */
    private function accepts(string $name): bool
    {
        if (!$this->isAllowed($name)) {
            return false;
        }

        if (isset($this->variables[$name]) || !$this->existsInProcess($name)) {
            return true;
        }

        if ($this->configuration->loadMode === LoadMode::Immutable) {
            throw ImmutableException::alreadyDefined($name);
        }

        return $this->configuration->loadMode !== LoadMode::SkipExisting;
    }

    /** Copies a loaded value into the superglobals and process environment the configuration names. */
    private function publish(string $name, string $value): void
    {
        if ($this->configuration->populateEnv) {
            $_ENV[$name] = $value;
        }

        if ($this->configuration->populateServer) {
            $_SERVER[$name] = $value;
        }

        if ($this->configuration->usePutenv) {
            putenv("{$name}={$value}");
        }
    }

    // ── Internal: Environment ────────────────────────────────────────────

    private function createEncryptor(): ?Encryptor
    {
        $key = $this->configuration->encryptionKey;

        if ($key === null) {
            // getenv() covers runtimes that expose the key only to the process
            // environment (e.g. PHP-FPM with clear_env = no).
            $fromProcess = $_SERVER['DOTENV_PRIVATE_KEY'] ?? $_ENV['DOTENV_PRIVATE_KEY'] ?? getenv('DOTENV_PRIVATE_KEY');
            $key = is_string($fromProcess) ? $fromProcess : null;
        }

        return $key !== null && $key !== '' ? new Encryptor($key) : null;
    }

    private function existsInProcess(string $name): bool
    {
        return isset($_ENV[$name]) || isset($_SERVER[$name]) || getenv($name) !== false;
    }

    /**
     * String values present in the process but not loaded by this instance.
     * Precedence mirrors resolveRawValue(): $_ENV, then $_SERVER, then getenv().
     *
     * @return array<string, string>
     */
    private function preExistingEnvironment(): array
    {
        $environment = [];

        foreach ([getenv(), $_SERVER, $_ENV] as $source) {
            /**
             * @var array-key $name a numeric name becomes an integer key
             * @var mixed $value
             */
            foreach ($source as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $environment[$name] = $value;
                }
            }
        }

        return array_diff_key($environment, $this->variables);
    }

    private function processEnvironmentName(): ?string
    {
        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function assertValidEnvironmentName(string $name): void
    {
        if (preg_match(self::ENVIRONMENT_NAME_PATTERN, $name) !== 1) {
            throw ConfigurationException::invalidEnvironmentName($name);
        }
    }

    private function resolveRawValue(string $name): ?string
    {
        if (isset($this->variables[$name])) {
            return $this->variables[$name]->rawValue;
        }

        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }

    // ── Internal: Filtering & Processing ─────────────────────────────────

    private function isAllowed(string $name): bool
    {
        foreach ($this->configuration->denyList as $pattern) {
            if ($this->matchGlob($pattern, $name)) {
                return false;
            }
        }

        if ($this->configuration->allowList === []) {
            return true;
        }

        foreach ($this->configuration->allowList as $pattern) {
            if ($this->matchGlob($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    private function matchGlob(string $pattern, string $name): bool
    {
        $regex = '/\A' . str_replace(search: ['\*', '\?'], replace: ['.*', '.'], subject: preg_quote($pattern, '/')) . '\z/';

        return preg_match($regex, $name) === 1;
    }

    private function applyProcessors(string $name, string $value, mixed $typedValue): mixed
    {
        foreach ($this->processors as [$pattern, $processor]) {
            if ($this->matchGlob($pattern, $name)) {
                /** @var mixed $typedValue a processor may return any value */
                $typedValue = $processor->process($value, $typedValue);
            }
        }

        return $typedValue;
    }
}
