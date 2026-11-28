# Changelog

All notable changes to `kariricode/dotenv`. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [4.5.0] — 2026-11-28

The first 4.x release on Packagist, which until now carried 1.x and 2.0.0:
the 4.x line was developed outside this repository and replaces 2.0.0 here.
It was prepared in two passes — the review of 2026-09-29, whose lists start
at "Security" below, and the conformance pass of 2026-11-28 to KaririCode
Specification V4.3.3, summarised first. Coming from 2.0.0, read both.

### Spec V4.3.3 conformance (2026-11-28)

**Breaking**

- Every exception carries an `EnvCode` from block 2700–2799, prefix `ENV`;
  messages start with `[ENV_<code>]`, quote paths and names, and never
  contain values, keys or payloads (ADR-010).
- `DotenvException` is the marker interface every exception implements,
  with `$errorCode`; `catch (DotenvException $e)` works as before, but code
  that extended the abstract class must implement the interface. Each
  concrete exception extends one kariricode/exception family base:
  Validation (`ParseException`, `ConversionException`,
  `ValidationException`), NotFound (`FileNotFoundException`),
  Configuration (`ConfigurationException`) and Runtime
  (`ImmutableException`, `EncryptionException`, `CacheException`).
- A key of the wrong size is `ConfigurationException::invalidEncryptionKey()`
  or `invalidPrivateKey()` (ENV_2724, ENV_2725), no longer an
  `EncryptionException`.
- `ValidationException::batchErrors()` joins its messages on one line;
  `errors()` still lists them one by one.
- The CLI prints exceptions with their code: `✗ [ENV_2752] Decryption
  failed — wrong key or corrupted payload.`
- Requires `kariricode/contract` ^2.11 and `kariricode/exception` ^2.0.2
  (ADR-001 amended); `composer.lock` is committed; the tools left
  `require-dev`; `extra.kariricode` declares ARFA 1.3, ASVS 5.0 level 2,
  Spec 4.3.3 and the error-code range.

**Added**

- `Integration\DotenvEnvironmentResolver` implements kariricode/contract's
  `Environment\EnvironmentResolver` over a loaded instance (ADR-011).
- `Exception\EnvCode`; `tests/Conformance` with the ARFA 1.3 and Spec
  checks and the registry check; `SECURITY.md`; `CONTRIBUTING.md`;
  `docs/quality/QD-V433-COMPLIANCE.md`; ADR-009 to ADR-011.

**Changed**

- The quality gates run through KaririCode Devkit with the root configs:
  `composer check` (PER-CS 3.0, PHPStan max with the strict, PHPUnit and
  cognitive rules on `src`, the CLI and `tests`, Psalm level 1, the
  complexity budgets of §13, the three suites, mutation testing with
  MSI ≥ 80), `composer security` and `composer sbom`; CI runs them on
  PHP 8.4 and the suites on PHP 8.5 with the lowest dependencies, with
  actions pinned by commit SHA (ADR-009). The release workflow is gone.
- Every class has a docblock with the comment criteria of §17, every call
  with three or more arguments names them (§30), and ADR-001 to ADR-008
  follow the canonical layout.
- `env()` builds a `TypeSystem` per call instead of keeping one in a
  function-level static.
- `DotenvParser`, `EnvFileScanner`, `SchemaParser`, `PhpFileCache` and
  `Dotenv` split their longest methods to meet §13, without changing
  behaviour.

**Fixed**

- Patterns anchor their end with `\z` instead of `$`.
- `MatchesRegexRule` rejects an empty pattern before compiling it.
- A cache that cannot be written raises `CacheException` without a PHP
  warning first.

### Review of 2026-09-29

First release verified by executing the toolchain (PHPUnit, PHPStan level 9, PHP-CS-Fixer) on PHP 8.4. Earlier 4.x notes relied on reading the code only; see `docs/CODE-REVIEW.md`.

### Security

- **Cache no longer stores decrypted secrets.** With a key configured, `dumpCache()` stores every value encrypted — including plaintext derived from secrets such as `DATABASE_URL="…${DB_PASSWORD}…"`.
- **`example:generate` no longer leaks multiline values.** The body of a PEM key was copied into `.env.example`; multiline values are now dropped entirely.
- **`debug` masks encrypted values** as `[encrypted]` unless `--reveal` is given.

### Fixed

- **Parser:** `\$` inside double quotes is now a literal dollar sign; it was expanded anyway.
- **Parser:** expansion is single-pass. A value containing `$` referenced via `${NAME}` was expanded a second time (`pa$word` became `pa`); operands of `${A:-…}` are now literal as documented.
- **SkipExisting:** later files of the cascade override earlier ones again (`.env.local` could not override `.env`), and `${NAME}` references use the effective process value instead of the skipped file value (ADR-006).
- **Cross-file references** work with `populateEnv` and `populateServer` disabled.
- **References to encrypted values** in the same file receive the plaintext.
- **Cache staleness:** the cache now tracks every file of the `bootEnv()` cascade (present or absent); editing `.env.production` or creating `.env.local` invalidates it. A cache built by `load()` is never used by `bootEnv()`, and one built for another requested environment is ignored.
- **CLI `encrypt`:** values are taken exactly as the parser reads them, so the application loads identical values before and after (escapes and inline comments used to be encrypted literally); multiline values are handled; values with references are skipped and reported; `--output` is implemented.
- **CLI `decrypt`:** values are re-quoted so they read back byte-for-byte (a `$` was re-interpolated); a wrong key reports an error and exits 1 instead of a fatal error; `--output` is implemented.
- **CLI `cache:dump`** loads with `bootEnv()` (it used `safeLoad()`, which ignored the cascade) and reports the environment it actually resolved.
- **CLI `validate`** counts variables present only in the process environment, including with `variables_order = "GPCS"`.
- **CLI `diff`** compares parsed values (`A="x"` equals `A=x`).
- **`ValidationException::errors()`** is populated by every factory.
- An invalid regular expression raises `ConfigurationException` instead of emitting a PHP warning and failing every value.

### Added

- `Dotenv::environment()` — environment resolved by `bootEnv()`.
- `EnvironmentVariable::$encrypted` and the `encrypted` key in `debug()`.
- `DOTENV_PRIVATE_KEY` is also read through `getenv()` (e.g. PHP-FPM with `clear_env = no`).
- Exceptions `EncryptionException`, `CacheException`, `ConversionException`, `ConfigurationException`.
- `Core\InterpolationContext`, `Core\EnvFileScanner`, `Core\EnvEntry`, `Security\EnvFileCipher`, `Security\CipherResult`, `Cache\CacheSnapshot`.
- CLI options `--env`, `--mode`, `--reveal`.
- `composer.json` `extra.kariricode` block: ARFA 1.3 Level 1 (P1, P4, P5), Spec V4.0.
- `phpstan.neon` (level 9, `src` + `bin`), `.php-cs-fixer.dist.php`, `.gitignore`, `LICENSE`.

### Changed — may require code changes

- **Exception types:** `Encryptor` and `KeyPair` throw `EncryptionException` (was `\InvalidArgumentException` / `\RuntimeException`); cache writes throw `CacheException`; `Base64DecodeProcessor` throws `ConversionException`. All extend `DotenvException`, which is now `abstract`.
- **Cache file format 2.** Caches written by 4.4 are ignored (files are parsed) until dumped again.
- `PhpFileCache::dump()` / `load()` take and return a `CacheSnapshot`.
- `dumpCache()` throws `CacheException` when called before any load.
- `CsvToArrayProcessor` rejects an empty separator (`ConfigurationException`).
- Environment names used by `bootEnv()` must match `[A-Za-z0-9][A-Za-z0-9_-]*`.
- `KeyPair`, `Encryptor` and `SchemaParser` are `final readonly`.

### Removed

- `ParseException::invalidLine()` and `ParseException::circularReference()` (never thrown).
- The `ifPresent()` tracking map in `EnvironmentValidator` (it never changed an outcome; behavior unchanged).

## [4.4.0] — 2026-02-28

CLI (`kariricode-dotenv`), `.env.schema` declarative validation, variable processors.

## [4.3.0] — 2026-02-28

Per-value AES-256-GCM encryption, `KeyPair`.

## [4.2.0] — 2026-02-28

OPcache-friendly cache, `bootEnv()` cascade loading.

## [4.1.0] — 2026-02-28

Fluent validation DSL with batch error reporting.

## [4.0.0] — 2026-02-28

Rewrite for PHP 8.4 and ARFA 1.3: zero dependencies, automatic type casting, immutable configuration.
