# Code Review Record — KaririCode\Dotenv 4.5.0

| Field | Value |
|---|---|
| **Version reviewed** | 4.5.0 (from 4.4.0) |
| **Date** | 2026-09-29 |
| **Standards** | ARFA 1.3 · KaririCode Code Quality Spec V4.0 (errata E-001–E-012) |
| **Environment** | PHP 8.4.23 CLI (Linux) · PHPUnit 11.5.56 · PHPStan 2.2.16 · PHP-CS-Fixer 3.95.27 |
| **Supersedes** | Review of 2026-03-02 and the static review of 4.4.0 |

## 1. Result

| Gate | 4.4.0 (first real run) | 4.5.0 |
|---|---|---|
| PHPUnit | 161 tests, 1 failure, 7 warnings (suite red: `failOnWarning`) | **236 tests, 432 assertions, 0 failures, 0 warnings** |
| PHPStan level 9 (`src` + `bin`) | 47 errors | **0 errors**, no ignores or baseline |
| PHP-CS-Fixer (PSR-12 + PHP 8.4 migration) | 15 of 57 files | **0 of 74 files** |
| Errata E-001–E-012 | E-006, E-008, E-009, E-011 failing | **12/12** |
| Mutation check (defects reintroduced one at a time) | — | **11/11 caught** |

## 2. Method

1. Executed the toolchain; earlier reviews had only read the code.
2. Reproduced every suspected defect with a runtime probe before changing code.
3. Fixed it and added a regression test.
4. Reintroduced each fixed defect into the code and confirmed the suite fails (mutation check), then restored it.
5. Rewrote every documentation claim that could be executed (CLI output, cache file sample, interpolation examples) from real runs.

## 3. Correction of Earlier Reviews

The 4.4.0 documentation and the 2026-03-02 review stated "161 tests" and "PHPStan level 9" without running either. The first execution showed a failing test, 7 warnings and 47 PHPStan errors. The `phpstan.neon` added in the static review used two parameters removed in PHPStan 2 and did not load. Several CLI "Output" blocks in the specs were never produced by the CLI. The 4.4.0 tarball also contained a stray directory literally named `tests/Unit/{Validation,Security,Cache,Schema}`.

## 4. Defects Fixed

**Security**

| # | Defect | Fix |
|---|---|---|
| S1 | `dumpCache()` wrote decrypted secrets to disk in plaintext | With a key, every cached value is stored encrypted (ADR-005) |
| S2 | `example:generate` copied the body of multiline values (PEM keys) into `.env.example` | Scanner-based rewrite; multiline bodies dropped |
| S3 | CLI `debug` printed decrypted secrets | Masked as `[encrypted]` unless `--reveal` |

**Correctness**

| # | Defect | Fix |
|---|---|---|
| C1 | `\$` in double quotes was still expanded | Escaped dollars protected through expansion |
| C2 | Two-pass expansion: `${PASSWORD}` holding `pa$word` became `pa` | Single-pass expansion (SPEC-002 §6.6) |
| C3 | Operand `$NAME` in `${A:-$NAME}` expanded despite the spec | Operands literal |
| C4 | SkipExisting: `.env.local` could not override `.env` | One pre-existing rule for all modes (ADR-006) |
| C5 | SkipExisting: references used the skipped file value | Pre-existing values locked for interpolation |
| C6 | Cross-file references broke with `populateEnv`/`populateServer` off | Earlier files passed as interpolation context |
| C7 | Same-file references to encrypted values got the ciphertext | References receive the plaintext |
| C8 | `bootEnv()` cache ignored edits to cascade files | Cache records every candidate file (format 2) |
| C9 | CLI `cache:dump` used `safeLoad()`, so an application calling `bootEnv()` got `.env` values only | Uses `bootEnv()`; strategy and requested environment recorded |
| C10 | CLI `encrypt` changed loaded values (escapes, inline comments) and broke multiline values | `EnvFileCipher`: values taken exactly as parsed; invariant tested |
| C11 | CLI `decrypt` re-quoted values so `$` was re-interpolated; wrong key ended in a fatal error | Safe quoting; `✗` message, exit 1, file untouched |
| C12 | CLI `--output` documented but not implemented | Implemented for encrypt/decrypt |
| C13 | CLI `validate` reported process-injected variables as missing under `variables_order = "GPCS"` | Same presence rule as `required()` |
| C14 | CLI `cache:dump` reported `environment: dev` for a production cache | `Dotenv::environment()` |
| C15 | `ValidationException::errors()` empty for two of three factories | Populated by every factory |
| C16 | Invalid regex emitted PHP warnings and failed every value | `ConfigurationException` at rule creation |

**Conformance and maintenance**

| # | Item | Fix |
|---|---|---|
| Q1 | 47 PHPStan level-9 errors (mixed superglobals, array shapes, variadics) | Typed guards; 0 errors |
| Q2 | Test expected `'yes'` but the type system returns `true`; cache tests left dotfiles behind (7 warnings) | Expectation fixed; cleanup includes dotfiles |
| Q3 | E-006: concrete non-final `DotenvException` | `abstract` |
| Q4 | E-011: 7 bare `throw new` | 4 semantic exception classes |
| Q5 | E-008: 68 section markers, none 72 characters | All 78 markers at 72 |
| Q6 | E-009: no conformance declaration | `extra.kariricode`: ARFA 1.3 Level 1 (P1, P4, P5) |
| Q7 | Dead code: validator `ifPresent()` map, two unused `ParseException` factories | Removed; behavior unchanged |
| Q8 | Docs describing 4.4 behavior; README linked a missing `LICENSE` | Docs rewritten; `LICENSE` added |

## 5. Known Limitations

- **Deny list and references.** A variable blocked by `denyList` is not loaded, but a reference to it in an allowed variable still receives the file value (`DSN="x:${API_SECRET}"` → `x:s3cret`). Proposed fix: hide denied names from same-file resolution through `InterpolationContext`, so references fall back to the process environment.
- **Cache freshness uses metadata.** An in-place edit keeping size, second and inode is not detected (ADR-005).
- **OPcache with `validate_timestamps=0`.** Reload PHP-FPM after `cache:dump`.
- **Numeric error codes.** Not assigned: Dotenv has no dependency on `kariricode/contract`, where ranges are allocated.
- **Coverage of platforms.** Executed on Linux with the PHP CLI only; not run on Windows, macOS or under PHP-FPM.
- **Reference document.** The canonical Spec V4.0 text was not available in this review; conformance was checked against errata E-001–E-012 as applied in the DependencyInjection and Database components.
