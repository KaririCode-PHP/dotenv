# Contributing to KaririCode Dotenv

Part of the KaririCode Framework. Contributions follow **ARFA 1.3 (Level 1)**
and the **KaririCode Specification V4.3.3**. A pull request is mergeable only
when `composer check` passes.

## Requirements

- PHP 8.4+ with ext-openssl (the encryption tests need it) and Composer 2
- [KaririCode Devkit](https://github.com/KaririCode-PHP/devkit) (`kcode`)
  1.2.2, checked against its published SHA-256; pcov or Xdebug for mutation
  testing

```bash
git clone https://github.com/KaririCode-PHP/dotenv.git
cd dotenv
composer install     # the runtime dependencies, from composer.lock
kcode init           # the toolchain pinned in devkit.php, into .kcode/ (ADR-009)
composer check       # cs-check, phpstan, psalm, complexity, the three suites, mutation
composer security    # validate, audit of both locks, secret scan
```

## Quality Gates

| Gate | Command | Must report |
|---|---|---|
| Tests | `composer test` (`kcode test --testsuite=unit` for one suite) | OK, no warnings, notices, risky tests or deprecations |
| Conformance | `composer test-conformance` | OK |
| PHPStan, level max, strict and PHPUnit rules, cognitive complexity ≤ 15, `src`, the CLI and `tests` | `composer phpstan` | `[OK] No errors` |
| Psalm, error level 1 | `composer psalm` | `No errors found!` |
| PER Coding Style | `composer cs-check` (`composer cs-fix` to apply) | 0 files to fix |
| Complexity (§13) | `composer complexity` | cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4; `tools/complexity-baseline.json` may only shrink |
| Mutation testing | `composer mutation` | MSI ≥ 80 and covered MSI ≥ 80 |
| Security | `composer security` | valid manifest and lock, no advisories in either lock, no secrets |
| SBOM | `composer sbom` | CycloneDX 1.5 written to `sbom.cdx.json` |

A surviving mutant is either killed with a test or documented as equivalent
in the QD-V433 record.

## Code Standards

1. `declare(strict_types=1)` in every file; one class, interface or enum per
   file. `src/env.php`, the global `env()` helper, is the one file of
   functions.
2. Concrete classes are `final`; value objects and configuration are
   `final readonly`.
3. No `toArray()`/`fromArray()`/`jsonSerialize()`, no getters without logic
   (public readonly properties instead), no Manager/Helper/Util names.
4. No global or static mutable state: a long-running worker loads its
   environment once and serves many requests with it.
5. Every exception extends a family base of `kariricode/exception` and
   carries a code from the component's block (ADR-010).
6. No secret leaves the process: an exception message, a log line or a cache
   file never contains a decrypted value or a key (ADR-004, ADR-005).

### Documentation

- Every class and public method has a docblock that says what it does and
  why, not what its name already says.
- No tautological docs ("Gets the…", "Executes the…", "Represents a…") and
  no `{@inheritDoc}`.

### Testing

- Every behaviour change comes with a test; every bug fix with a regression
  test that fails on the previous code.
- Unit tests isolate one class; integration tests load real files from a
  temporary directory and run the CLI as a process; the conformance suite
  checks the ARFA 1.3 and Spec rules a tool can check.

## Architecture Decisions

Read `docs/adr/` before structural changes. New decisions get an ADR named
`ADR-NNN-title.md` with the sections Status, Date, Context (with Prior
Art), Decision, Consequences (Positive, Negative), Alternatives Considered
and References.

## Pull Request Process

1. Branch from `main`.
2. Write the failing test first.
3. Implement, then run `composer check`.
4. Add an entry under `[Unreleased]` in `CHANGELOG.md`.
5. Reference the ADRs and SPEC sections your change touches.
