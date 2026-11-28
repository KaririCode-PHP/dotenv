# ADR-009: Quality Gates through KaririCode Devkit, with the Root Configs

## Status

Accepted — V4.5.0

## Date

2026-11-28

## Context

The 4.5.0 tarball ran PHPUnit 11, PHPStan 2 at level 9 on `src` and the
CLI, and PHP-CS-Fixer with a house ruleset over PSR-12, all from
`require-dev`, and ignored `composer.lock`. Its workflows called
`kcode quality` with actions pinned by tag, and a release workflow
published a GitHub release from any `v*` tag. It had no mutation testing,
no complexity gate and no secret scan, and the tests ran outside PHPStan.

KaririCode Specification V4.3.3 §29.1 names eight gates: tests, the
conformance suite, PHPStan at level max, Psalm at error level 1, PER Coding
Style, the §13 complexity budgets (cyclomatic ≤ 10, cognitive ≤ 15,
nesting ≤ 3, parameters ≤ 4), mutation testing with MSI ≥ 80, and
security. The ecosystem runs them through KaririCode Devkit: kcode 1.2.x
installs the versions pinned in `devkit.php` into `.kcode/`, with a lock of
its own, and runs the configs a component commits when `devkit.php` sets
`'root_configs' => true`.

### Prior Art

- KaririCode Devkit 1.2.2 (`kcode init`, `kcode test`, `kcode run`).
- kariricode/event-dispatcher ADR-009, kariricode/i18n ADR-I18N-007 and
  kariricode/runtime ADR-007: the same move, with the shared
  `tools/complexity.php`, `tools/sbom.php` and secret scan.

## Decision

| Item | Before | Now |
|---|---|---|
| Tools | `require-dev`: PHPUnit, PHPStan, PHP-CS-Fixer | `devkit.php`: PHPUnit 11.5.56, PHPStan 2.3.7 with phpstan-strict-rules 2.1.2, phpstan-phpunit 2.1.4 and tomasvotruba/cognitive-complexity 1.2.0, Psalm 6.19.1, PHP-CS-Fixer 3.95.27, Infection 0.35.5 |
| `require-dev` | the three tools | empty: the suites need nothing beyond the runtime |
| `composer.lock` | ignored | committed, resolved from Packagist with a clean Composer home; `export-ignore` |
| Style | PSR-12 with a house ruleset | PER Coding Style 3.0 (`@PER-CS3.0`), the version php-cs-fixer implements of the 3.1 the Spec pins (E-046), on `src`, `tests`, `tools` and the CLI |
| PHPStan | level 9 on `src` and the CLI | level max on `src`, the CLI and `tests`, with the strict, PHPUnit and cognitive rules; tests call PHPUnit's assertions statically |
| Psalm | run by `kcode quality`, with no committed config | error level 1 on `src`, no baseline, no suppressions, `#[\Override]` required |
| Complexity | none | `tools/complexity.php` over `src`, with an empty baseline |
| Mutation testing | none | `composer mutation` through kcode: MSI ≥ 80 and covered MSI ≥ 80 (the Spec's floor) |
| `composer security` | `composer audit` in a workflow | `composer validate --strict`, the audit of the runtime lock and of `.kcode/composer.lock`, the secret scan and its self-test |
| CI | three workflows, actions pinned by tag | one: the gates on PHP 8.4 with pcov, kcode pinned by version and SHA-256, actions by commit SHA; the suites on PHP 8.5 with the lowest versions the constraints allow; dependency review on pull requests |
| Release workflow | a GitHub release from any `v*` tag | removed: releases are cut by hand from a green `main` (§32) |
| `extra` | `arfa` 1.3 | `kariricode`: `arfa` 1.3 level 1, `asvs` 5.0 level 2, `spec` 4.3.3; `branch-alias` `4.x-dev` |

The first measurement found PHPStan reports in the CLI and the tests, 84
Psalm errors (half of them a missing `#[\Override]`), 25 files off
PER-CS 3.0 and eight breaches of §13. The commit that records this
decision fixes them without changing behaviour:

- The 42 methods that implement a contract carry `#[\Override]`.
- `PhpFileCache::load()` validates the file's metadata in typed helpers
  instead of reading offsets of a mixed array, and fingerprints a source
  with one `stat()` instead of three calls that may each return `false`.
- `DotenvParser` passes what parsing a value needs in an internal
  `Core\ValueContext` instead of six parameters, and decodes a line of a
  double-quoted value in a helper of its own; `EnvFileScanner`,
  `SchemaParser::applyToValidator()` and `Dotenv`'s cache and assignment
  paths split the same way.
- `MatchesRegexRule` rejects an empty pattern before compiling it,
  `BetweenRule` compares a float, and `ArrayDetector` reads the type of
  `json_decode()` instead of `json_last_error()`.
- The global `env()` helper builds a `TypeSystem` per call instead of
  keeping one in a function-level static.
- The CLI's `diff` command prints its two report sections in helpers.

## Consequences

### Positive

- The same gates, commands and versions as the other components; both
  locks are audited, and Psalm runs at level 1 with nothing suppressed.
- Mutation testing measures the suites for the first time.

### Negative

- `composer install` alone does not bring the tools; contributors install
  kcode once (CONTRIBUTING.md).
- `env()` allocates a `TypeSystem` and its default detectors on every call;
  code that resolves many values should call `Dotenv::get()` or keep a
  `TypeSystem` of its own.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Keep the tools in `require-dev` | their dependencies enter the component's lock and audit surface, and versions drift between components |
| Keep `kcode quality` | it runs the toolchain's own configs, which differ from the ones the component commits and the Spec names |
| Keep the release workflow | a pushed tag would publish without the review of §32; tags are cut from a commit whose gates passed |
| Baseline the eight §13 breaches | each split above is mechanical and covered by the existing suites; a baseline entry needs a reason none of them has |
| Keep the static `TypeSystem` in `env()` | function-level static state survives requests in a long-running worker, which §5 forbids |

## References

- KaririCode Specification V4.3.3 §5, §13, §19, §24, §25, §29, §31, §32; errata E-045, E-046.
- KaririCode Devkit 1.2.2: https://github.com/KaririCode-PHP/devkit/releases/tag/v1.2.2
- kariricode/event-dispatcher ADR-009, kariricode/i18n ADR-I18N-007, kariricode/runtime ADR-007.
