<p align="center">
  <a href="https://kariricode.org" target="_blank">
    <img src="https://kariricode.org/assets/images/kariricode-logo.svg" alt="KaririCode Logo" width="200">
  </a>
</p>

<h1 align="center">KaririCode\Dotenv</h1>

<p align="center">
  <strong>Production-grade environment variable management for PHP 8.4+</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/kariricode/dotenv"><img src="https://img.shields.io/packagist/v/kariricode/dotenv.svg" alt="Latest Version"></a>
  <a href="https://github.com/KaririCode-Framework/kariricode-dotenv/actions"><img src="https://img.shields.io/github/actions/workflow/status/KaririCode-Framework/kariricode-dotenv/ci.yml" alt="CI Status"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="MIT License"></a>
</p>

Zero-dependency `.env` loader with automatic type casting, per-value AES-256-GCM encryption, a validated OPcache-friendly cache, batch validation (fluent DSL and schema files), environment-aware cascade loading, variable processors, allow/deny filtering, introspection and a CLI.

**Version:** 4.5.0 · **PHP:** ≥ 8.4 · **Dependencies:** 0 · **ARFA 1.3:** Level 1 (P1, P4, P5) · **Spec:** V4.0 · **License:** MIT

## 1. Features

Automatic casting of `true`/`42`/`3.14`/`null`/JSON values; three load modes for pre-existing variables; cascade loading (`.env` → `.env.local` → `.env.{env}` → `.env.{env}.local`); `${VAR}`, `${VAR:-default}`, `${VAR:+alternate}` interpolation, also across files; validation that reports every failure at once; encrypted values decrypted transparently at load; a cache that never serves stale values and never stores plaintext secrets; a CLI whose `encrypt`/`decrypt` never change what the application loads.

## 2. Installation

```bash
composer require kariricode/dotenv
```

`ext-openssl` is needed only for encrypted values.

## 3. Quick Start

```php
use KaririCode\Dotenv\Dotenv;
use function KaririCode\Dotenv\env;

$dotenv = new Dotenv(__DIR__);
$dotenv->load();

env('APP_DEBUG');   // bool(true)   from APP_DEBUG=true
env('DB_PORT');     // int(5432)    from DB_PORT=5432
env('CACHE_TTL');   // null         from CACHE_TTL=null
```

Every library error extends `KaririCode\Dotenv\Exception\DotenvException`, so one `catch` covers them all.

## 4. Type Casting

Detection order: Null (200) → Boolean (190) → Integer (180) → Float (170) → JSON object (160) → JSON array (150) → String. `yes`/`no`/`on`/`off` are booleans; the empty string stays a string. Custom detectors and casters plug in with `addTypeDetector()` / `addTypeCaster()`; `typeCasting: false` keeps everything as strings. See [SPEC-003](docs/spec/SPEC-003-type-system.md).

## 5. Load Modes and Cascade

```php
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;

$dotenv = new Dotenv(__DIR__, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
$dotenv->bootEnv();            // environment: argument, config, APP_ENV, else "dev"
$dotenv->environment();        // e.g. "production"
```

| Mode | Variable already in the process | Later file of the cascade |
|---|---|---|
| `Immutable` (default) | `ImmutableException` | overrides |
| `Overwrite` | file wins | overrides |
| `SkipExisting` | process wins (references see the process value too) | overrides |

`.env.test.local` is never loaded, so test runs are reproducible. See [ADR-006](docs/adr/ADR-006-load-modes.md) and [ADR-007](docs/adr/ADR-007-environment-cascade.md).

## 6. Variable Interpolation

```ini
APP_NAME=KaririCode
GREETING="Welcome to ${APP_NAME}"
CACHE_HOST=${REDIS_HOST:-localhost}
HAS_CACHE=${REDIS_HOST:+yes}
PRICE="costs \$5"          # \$ is a literal dollar sign
PASSWORD='pa$word'         # single quotes are literal
DSN="pgsql:pass=${PASSWORD}"
```

Expansion is a single pass: a substituted value is never expanded again, so `DSN` receives `pa$word` intact. References resolve against earlier lines, earlier files of the cascade, then the process environment. See [SPEC-002](docs/spec/SPEC-002-env-syntax.md).

## 7. Validation

```php
$dotenv->validate()
    ->required('DB_HOST', 'DB_PORT', 'APP_ENV')
    ->isInteger('DB_PORT')->between(1, 65535)
    ->allowedValues('APP_ENV', ['local', 'staging', 'production'])
    ->ifPresent('REDIS_HOST')->notEmpty()
    ->assert();                                  // one exception listing every failure

$dotenv->loadWithSchema(__DIR__ . '/.env.schema'); // same rules, declared in a file
```

Rules only run against variables that are present; `required()` adds the presence check. See [SPEC-005](docs/spec/SPEC-005-validation.md) and [SPEC-006](docs/spec/SPEC-006-schema-format.md).

## 8. Encryption

```bash
vendor/bin/kariricode-dotenv keygen
vendor/bin/kariricode-dotenv encrypt .env --key=<64-hex-key>
```

```php
$dotenv = new Dotenv(__DIR__, new DotenvConfiguration(encryptionKey: $key));
$dotenv->load();   // or set DOTENV_PRIVATE_KEY in the process environment
```

Values become `encrypted:<base64(nonce ‖ ciphertext ‖ tag)>`. Other values can reference encrypted ones and receive the plaintext. See [SPEC-004](docs/spec/SPEC-004-encryption.md) and [ADR-004](docs/adr/ADR-004-encryption-format.md).

## 9. Production Cache

```php
// Deploy step, same environment and boot method as the application:
$dotenv = new Dotenv(__DIR__);
$dotenv->bootEnv();
$dotenv->dumpCache(__DIR__ . '/.env.cache.php');   // or: vendor/bin/kariricode-dotenv cache:dump

// Application:
$dotenv = new Dotenv(__DIR__, new DotenvConfiguration(cachePath: __DIR__ . '/.env.cache.php'));
$dotenv->bootEnv();   // no parsing when the cache matches
```

The cache is used only when it was produced by the same loader for the same requested environment and every file of the cascade is unchanged; otherwise the files are parsed. With a key configured, every cached value is stored encrypted. See [ADR-005](docs/adr/ADR-005-opcache-cache.md).

## 10. Processors and Filtering

```php
use KaririCode\Dotenv\Processor\{CsvToArrayProcessor, UrlNormalizerProcessor};

$dotenv->addProcessor('ALLOWED_IPS', new CsvToArrayProcessor());   // "a, b" → ["a", "b"]
$dotenv->addProcessor('*_URL', new UrlNormalizerProcessor());      // glob patterns

new DotenvConfiguration(allowList: ['DB_*', 'APP_*'], denyList: ['*_SECRET']);
```

Built-in processors: `CsvToArrayProcessor`, `Base64DecodeProcessor`, `TrimProcessor`, `UrlNormalizerProcessor`. See [SPEC-007](docs/spec/SPEC-007-processors.md).

## 11. CLI

```
vendor/bin/kariricode-dotenv debug | validate | encrypt | decrypt | cache:dump | cache:clear | diff | example:generate | keygen
```

`debug` masks encrypted values unless `--reveal`; `example:generate` never copies secret material; `encrypt` skips values with references. See [SPEC-008](docs/spec/SPEC-008-cli.md).

## 12. Architecture and Quality

Component specification (conformance, type taxonomy, exception model, file inventory, measured quality gates): [SPEC-001](docs/spec/SPEC-001-dotenv-component.md). Decisions: [docs/adr/](docs/adr/). Changes: [CHANGELOG.md](CHANGELOG.md). Review record: [docs/CODE-REVIEW.md](docs/CODE-REVIEW.md).

```bash
composer test       # PHPUnit
composer analyse    # PHPStan level 9 (src + bin)
composer cs-fix     # PHP-CS-Fixer (PSR-12 + PHP 8.4 migration)
```

## 13. License

MIT — see [LICENSE](LICENSE).

**Author:** Walmir Silva — [community.woop@kariricode.org](mailto:community.woop@kariricode.org) · [KaririCode Framework](https://kariricode.org)
