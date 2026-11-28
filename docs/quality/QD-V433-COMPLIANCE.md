# QD-V433-COMPLIANCE: kariricode/dotenv 4.5.0

- **Status: PENDING** (automated gates)
- **Date:** 2026-11-28
- **Component:** kariricode/dotenv 4.5.0; kariricode/contract ^2.11 (2.12.1 locked); kariricode/exception ^2.0.2 (2.0.2 locked)
- **Standards:** KaririCode Spec V4.3.3 §29 (gates) and §32 (supply chain); ARFA 1.3 level 1 (Structural); OWASP ASVS 5.0 level 2
- **Authors:** Walmir Silva <walmir.silva@kariricode.org>

Every automated gate of §29.1 passes locally (§1). The status turns PASS
when CI reports the same at the commit this record describes, and FAIL if
a gate fails. PASS covers the automated gates (G1, G4, G5, G6); G3 needs a
second reviewer (§8). No score is assigned (E-006). This is the first
quality record of the package; the review of 2026-09-29 measured PHPUnit,
PHPStan level 9 and PHP-CS-Fixer only (`docs/CODE-REVIEW.md`).

## 1. Gates (Spec V4.3.3 §29.1)

Measured locally on 2026-11-28 (§2).

| Gate | Command | Budget | Measured | Result |
|---|---|---|---|---|
| Tests | `composer test` | all pass, no warning, notice, deprecation or risky test | 412 tests: unit 293, integration 91 (real files in a temporary directory, the CLI as a process), conformance 28 | pass |
| Conformance | `composer test-conformance` | all pass | 28 tests: `ArfaConformanceTest` 25 (23 rules and 2 documentation checks, §3), `CodeBlockRegistryTest` 3 | pass |
| PHPStan | `composer phpstan` | level max on `src`, the CLI and `tests`, strict and PHPUnit rules, cognitive complexity ≤ 15 | 0 errors | pass |
| Psalm | `composer psalm` | level 1 on `src`, no baseline | 0 errors; no suppressions; `#[\Override]` required | pass |
| Coding standard | `composer cs-check` | PER-CS (E-045, E-046) | 0 of 95 files to fix | pass |
| Complexity | `composer complexity`, `composer phpstan` | cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4 per method of `src`; cognitive ≤ 15 (§13) | 202 methods; no accepted breach (empty baseline) | pass |
| Mutation | `composer mutation` | MSI ≥ 80 and covered-code MSI ≥ 80 (§19.4) | 1,049 mutants: 983 killed, 12 timed out, 3 syntax errors, 51 escaped; MSI 95.14 %, covered-code MSI 95.14 %; mutation code coverage 100 % (§7) | pass |
| Security | `composer security` | valid manifest and lock, no advisory, no secret | manifest and lock valid; no advisories in the runtime lock or in `.kcode/composer.lock`; secret scan 0 findings (self-test ok) | pass |

Coverage with PCOV, suites `unit` and `integration`: lines 99.34 %
(898/904). The six lines left need what a test process cannot cause
without root-level tricks: `rename()` failing after the temporary cache
file was written (3 lines of `PhpFileCache::dump()`), `file_get_contents()`
failing on a schema file `is_readable()` accepted (`Dotenv`), OpenSSL
refusing to encrypt (`Encryptor`), and the end of the closing-quote scan
of `EnvFileCipher`, which the parser makes unreachable by rejecting an
unterminated quote first.

`composer sbom` wrote a CycloneDX 1.5 document with 2 runtime components
(kariricode/contract 2.12.1, kariricode/exception 2.0.2) and 115
development components marked `excluded`.

## 2. Environment and Metadata

| Item | Value |
|---|---|
| Runtime | PHP 8.4.28 CLI, PCOV, ext-openssl |
| Toolchain | KaririCode Devkit 1.2.2 with the root configs (ADR-009): PHPUnit 11.5.56, PHPStan 2.3.7 with phpstan-strict-rules 2.1.2 and phpstan-phpunit 2.1.4, Psalm 6.19.1, PHP-CS-Fixer 3.95.27, Infection 0.35.5, tomasvotruba/cognitive-complexity 1.2.0 |
| Lock | `composer.lock` resolved from Packagist with a clean Composer home; no path repository |
| `extra.kariricode` (gate G4, errata E-044) | `arfa` 1.3 level 1, `asvs` 5.0 level 2, `spec` 4.3.3, `error-code-range` ENV 2700–2799 |
| Error codes | `EnvCode`, 20 codes in 2700–2761, prefix ENV; CODE-BLOCKS.md on kariricode/exception's main records the claim (9a40e19); the installed 2.0.2 predates it (§8) |

## 3. Conformance Rules (§29.2)

`tests/Conformance/ArfaConformanceTest.php`; all 25 pass.

| Rule | Reference |
|---|---|
| Every source file is strict and declares exactly one named type | §25, PSR-4 |
| The functions file declares only the `env()` helper, with no static state | §25, §5 |
| Docblocks meet the five comment criteria (C1 summary; Preconditions, Thread-safety, Side effects, Example, References) | §17 |
| Section markers are 72 columns wide (sources, tests and the CLI) | §25 |
| Concrete classes are final | §5 |
| Values, snapshots and the configuration are final readonly | §5.2 |
| Readonly classes declare no property hooks, nor `readonly` again inside | E-040 |
| Class constants are typed | §25 |
| Inheritance inside the package is at most two levels deep | §18 |
| No type keeps mutable static state | §5 |
| Names and methods avoid the forbidden patterns (Interface suffix, Manager/Helper/Util, toArray/fromArray/jsonSerialize, magic accessors) | §18, E-001 |
| Public methods are not get/set accessors | §18 |
| Regular expressions anchor the end with `\z`, never `$` | §24 |
| Call sites with three or more arguments name them (printf family exempt), in `src`, `env()` and the CLI | §30 |
| Contracts are interfaces | §5, §31 |
| Enums are pure unless an external contract reads the value (`EnvCode`), with the justification written down | E-003 |
| Every directory is a namespace segment | PSR-4 |
| Error codes are unique and inside the reserved block | §8 |
| Exceptions are built only by their named constructors; nothing throws an uncoded exception | §8 |
| Every concrete exception belongs to exactly one contract family, implements the package marker and extends a kariricode/exception base | §8.4 |
| `composer.json` declares the ARFA, ASVS and Spec versions and the code range; runtime dependencies are PHP, kariricode/contract and kariricode/exception | §29.3 G4, E-044, §31 |
| `composer.json` exposes the eight quality gates | §29.1 |
| Supply chain is pinned: lock committed, CI actions by commit SHA | §32 |

Two further tests check the documentation: 11 ADRs with the seven
canonical sections, this record's status line, the SECURITY.md sections
and the CHANGELOG headings; and that the README, SECURITY.md,
CONTRIBUTING.md, the ADRs, the SPECs, this record and the sources cite
Spec V4.3.3. CHANGELOG.md and `docs/CODE-REVIEW.md` keep their record of
the 2026-09-29 review, which cited the Spec of its time.

`CodeBlockRegistryTest` checks that `EnvCode` is a valid code table, that
the installed CODE-BLOCKS.md leaves 2700–2799 and the prefix ENV to this
package (and, once it lists the block, that the row matches), and the
documented sub-ranges.

## 4. Error Model (§8)

Every exception implements the marker `DotenvException`, which declares
`$errorCode`, and extends the kariricode/exception base of exactly one
kariricode/contract family (ADR-010): Validation (`ParseException`
2700–2701, `ValidationException` 2730–2732, `ConversionException`
2740–2741), NotFound (`FileNotFoundException` 2710), Configuration
(`ConfigurationException` 2720–2725) and Runtime (`ImmutableException`
2711, `EncryptionException` 2750–2752, `CacheException` 2760–2761).
`getCode()` is the `EnvCode` number; messages start with `[ENV_<code>]`,
quote paths and names, and carry no value, key or payload. Releases up to
4.5.0 threw uncoded exceptions, so no code moved.

## 5. Security Controls (ASVS 5.0, level 2 declared)

| Control | Test |
|---------|------|
| AES-256-GCM round-trips with a fresh nonce; wrong keys and tampered payloads fail | `EncryptorTest`, `DocumentedValuesTest::aPayloadThatIsNotBase64OrTooShortIsMalformed` |
| `encrypt` never changes a loaded value and leaves references alone | `EnvFileCipherTest`, `EnvFileCipherLayoutTest` |
| The cache never stores plaintext with a key, and is never served stale or malformed | `CacheIntegrationTest`, `PhpFileCacheMetadataTest` |
| Messages carry no values and cannot forge log lines | `ExceptionCatalogTest` |
| Environment names cannot traverse paths | `ProcessEnvironmentTest::anInvalidNameFromAFileIsRejected`, `anInvalidProcessNameIsRejectedEvenWhenAFileNamesAnother` |
| The CLI masks secrets and reports failures without traces | `CliTest` |
| No secrets committed | `tools/secret-scan.php` and its self-test |

SECURITY.md keeps the threat model, the residual risks and the supply-chain
controls.

## 6. Judgment Calls

| Topic | Decision | Reason |
|-------|----------|--------|
| Runtime dependencies | kariricode/contract and kariricode/exception | the family bases and the code-table contract §8.4 requires; neither has dependencies of its own (ADR-001 amended, ADR-010) |
| Key errors | `ConfigurationException`, not `EncryptionException` | one class may not span two families, and a bad deployment must be told apart from a tampered value (ADR-010) |
| `ValidationException::errors()` | stays a method | released API since 2.0.0; a property would break callers for no gain (ADR-010) |
| The EnvironmentResolver bridge | in this package (decision D14) | it needs nothing the package lacks; consumers depend on the contract only (ADR-011) |
| `env()` | a `TypeSystem` per call | function-level static state survives requests in a worker (§5, ADR-009) |
| Secret scan | a `secret-scan:allow` marker for reviewed samples | four test fixtures and one SPEC line interpolate a password reference into a DSN, the case the package exists to support |
| Mutation target | MSI ≥ 80 and covered MSI ≥ 80 | the Spec's floor; first measurement of the package (§7) |
| PER-CS 3.1 | checked with `@PER-CS3.0` | PHP-CS-Fixer 3.95 implements 3.0 (E-046) |

## 7. Mutation Testing

| Measure | 2026-11-28, first run | 2026-11-28 |
|---------|-------|-------|
| Mutants | 1,050 | 1,049 |
| Detected (killed, timed out, syntax errors) | 801 | 998 |
| Escaped | 249 | 51 |
| MSI | 76.29 % | **95.14 %** (gate: 80 %) |

The first run failed the gate. The tests added since pin what the mutants
showed untested (the parser's whitespace and escapes, the cipher's quoting
and comments, every field of a cache file, the process reads of the
loader), and code that no test could tell apart from its removal went
(ADR-009, CHANGELOG). The 12 time-outs are mutants that make a scanning
loop endless; Infection counts them as detected. The 51 escaped mutants
are equivalent or depend on what a test process cannot observe:

| Mutants | Where | Why they survive |
|---|---|---|
| 10 | `PhpFileCache::dump()`: the comment at the top of the file (4) and the name of the temporary file (6) | neither changes what `load()` reads |
| 4 | `opcache_invalidate()` in `dump()` and `clear()` | OPcache does not cache files in the CLI test process |
| 2 | `dump()`: the throw after a failed write, and the warning handler's return value | the rename that follows fails and throws the same exception; the handler's result is not observable |
| 1 | `clearstatcache()` in the source fingerprint | PHP clears its own stat cache when PHP writes a file; the call is for edits by other processes |
| 1 | `load()`: `||` of two checks on `$data` and `$meta` | `$meta` is null whenever `$data` is not an array |
| 7 | `DotenvParser::escapedDollarToken()` | any name absent from the content works; the mutants only change the name |
| 3 | `DotenvParser`: `substr(…, 6)` before `ltrim()`, the fast path for values without `$`, a cast of a group that always matched | the results are identical |
| 9 | `array_values()` on the variadic names of `EnvironmentValidator` | variadic arguments are already lists |
| 6 | early returns of `ArrayDetector`, `JsonDetector`, `IntegerDetector` and `FloatDetector` before a pattern that rejects the same input | the pattern decides alike |
| 2 | `TypeSystem::ensureSorted()`: the guard and the flag | sorting an already sorted list again changes nothing |
| 2 | `EnvFileCipher`: the length of a two-character look-ahead, and the bound of a scan the parser makes unreachable | the results are identical |
| 1 | `BetweenRule`: the cast to float | PHP 8 compares numeric strings numerically |
| 1 | `MatchesRegexRule`: the warning handler's return value | not observable from a test |
| 1 | `Encryptor`: the cast of `hex2bin()` on validated hex | `hex2bin()` cannot fail there |
| 1 | `Dotenv::loadWithSchema()`: `is_readable()` beside `is_file()` | the suites run as root in CI, where every file is readable |

## 8. Open Items

| # | Item | Closes when |
|---|---|---|
| 1 | G3: independent review for P0 findings | a second reviewer signs off |
| 2 | CODE-BLOCKS.md on kariricode/exception's main records ENV 2700–2799 as claimed (9a40e19); the installed 2.0.2 predates the claim | a kariricode/exception release ships it |
| 3 | A denied variable is still visible to `${NAME}` references in the same file (SPEC-001 §10) | same-file resolution hides denied names |
| 4 | 51 escaped mutants (§7) | each is killed by a test or stays documented as equivalent |
