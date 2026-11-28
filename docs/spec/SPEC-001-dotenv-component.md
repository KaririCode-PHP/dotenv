# SPEC-001: KaririCode\Dotenv Component

| Field | Value |
|---|---|
| **Version** | 4.5.0 |
| **ARFA** | 1.3 |
| **Conformance Level** | Level 1 (Structural) — P1, P4, P5 |
| **Quality Spec** | KaririCode Specification V4.3.3 |
| **PHP** | ≥ 8.4 |
| **Runtime dependencies** | kariricode/contract ^2.11, kariricode/exception ^2.0.2 (ext-openssl optional, for encryption) |
| **Date** | 2026-09-29 · revised 2026-11-28 (Spec V4.3.3 conformance) |

---

## 1. Overview

KaririCode\Dotenv loads `.env` files into typed, validated, optionally encrypted configuration. It is a Layer 1 foundation component: it runs before the application container exists, so it depends on nothing but PHP and the two packages every KaririCode component may use, kariricode/contract and kariricode/exception (ADR-001, ADR-010).

Feature specifications:

| Spec | Scope |
|---|---|
| [SPEC-002](SPEC-002-env-syntax.md) | `.env` syntax, quoting, escapes, interpolation |
| [SPEC-003](SPEC-003-type-system.md) | Type detection and casting |
| [SPEC-004](SPEC-004-encryption.md) | AES-256-GCM encryption and file encryption |
| [SPEC-005](SPEC-005-validation.md) | Fluent validation DSL |
| [SPEC-006](SPEC-006-schema-format.md) | `.env.schema` declarative validation |
| [SPEC-007](SPEC-007-processors.md) | Variable processors |
| [SPEC-008](SPEC-008-cli.md) | Command-line tool |

---

## 2. Architecture

```
Dotenv (façade, Entity)
├── DotenvParser            .env text → name ⇒ string (quotes, escapes, interpolation)
│   └── InterpolationContext  inherited / locked values, decryption filter
├── Encryptor               AES-256-GCM per value
├── TypeSystem              detectors (priority) → casters
├── VariableProcessor[]     glob-matched post-processing
├── EnvironmentValidator    fluent rules, batch errors
│   └── SchemaParser        .env.schema → validator rules
└── PhpFileCache            OPcache-friendly snapshot (CacheSnapshot)

File tooling (used by the CLI)
├── EnvFileScanner          layout-preserving entries (EnvEntry), multiline-aware
└── EnvFileCipher           encrypt/decrypt whole files (CipherResult)
```

---

## 3. Load Pipeline

```
load() / safeLoad()                 bootEnv(env)
        │                                 │
        ├── fresh cache for same ─────────┤  strategy, files and requested
        │   strategy + files? → use it    │  environment must match
        ▼                                 ▼
for each file: DotenvParser::parse(content, context)
        │   context.inherited = variables loaded so far (decrypted)
        │   context.locked    = pre-existing process values (SkipExisting only)
        │   context.filter    = decrypt same-file references
        ▼
for each (name, value): setVariable()
        1. allow/deny list
        2. load mode: pre-existing = in process AND not loaded by this instance
           Immutable → ImmutableException · SkipExisting → skip · Overwrite → continue
        3. decrypt `encrypted:` values
        4. detect + cast type (unless typeCasting = false)
        5. processors (registration order)
        6. store EnvironmentVariable; populate $_ENV / $_SERVER / putenv
```

The same pipeline runs for values read from the cache, so processors, custom types and decryption always apply.

---

## 4. Type Taxonomy Compliance (Spec V4.3.3)

| Category | PHP declaration | Classes |
|---|---|---|
| Value Object | `final readonly class` | `DotenvConfiguration`, `EnvironmentVariable`, `KeyPair`, `CacheSnapshot`, `EnvEntry`, `CipherResult`, `InterpolationContext`, `ValueContext` |
| Stateless service | `final readonly class` | `DotenvParser`, `SchemaParser`, `Encryptor`, `EnvFileScanner`, `EnvFileCipher`, `PhpFileCache`, all detectors, casters, processors and rules |
| Entity | `final class` | `Dotenv` (load state), `TypeSystem` (detector registry), `EnvironmentValidator` (rule builder) |
| Exception | marker interface + `final` leaves on the kariricode/exception family bases | see §5 |
| Bridge | `final readonly class` | `Integration\DotenvEnvironmentResolver` (ADR-011) |
| Code table | backed `enum` (E-003: `getCode()` reads the integer) | `Exception\EnvCode` |
| Enum | pure `enum` | `LoadMode`, `ValueType` |

### Serialization Policy (E-001, E-004)

No `jsonSerialize()`, `toArray()` or `fromArray()`. Value objects expose `public readonly` properties. `Dotenv::debug()` is an introspection report, not a serialization contract.

### Enum Policy (E-003)

`LoadMode` and `ValueType` are pure enums: neither crosses a process boundary. The cache stores strategy names as plain strings, not enum values.

### Property Hooks (E-002, E-007)

None. Derived values are methods (`EnvEntry::isAssignment()`).

---

## 5. Exception Model (E-006, E-011, ADR-010)

Every exception implements the marker interface `DotenvException`, so `catch (DotenvException $e)` covers the library, and extends the kariricode/exception base of exactly one kariricode/contract family. All are created through named factories, and each carries an `EnvCode` case in `$errorCode` (block 2700–2799, prefix `ENV`); `getCode()` returns its integer and the message starts with `[ENV_<code>]`.

| Class (family) | Factories | Codes | Raised by |
|---|---|---|---|
| `ParseException` (Validation) | `invalidVariableName()`, `unterminatedQuote()` | 2700–2701 | parser |
| `FileNotFoundException` (NotFound) | `forPath()` | 2710 | missing `.env` / schema / CLI input |
| `ImmutableException` (Runtime) | `alreadyDefined()` | 2711 | Immutable load mode |
| `ConfigurationException` (Configuration) | `invalidEnvironmentName()`, `emptySeparator()`, `invalidOption()`, `invalidRegexPattern()`, `invalidEncryptionKey()`, `invalidPrivateKey()` | 2720–2725 | invalid arguments and keys |
| `ValidationException` (Validation) | `missingRequired()`, `batchErrors()`, `schemaViolation()`; `errors()` always lists every message | 2730–2732 | validation, schema |
| `ConversionException` (Validation) | `invalidBase64()`, `jsonStructureExpected()` | 2740–2741 | casters, processors |
| `EncryptionException` (Runtime) | `encryptionFailed()`, `malformedPayload()`, `decryptionFailed()` | 2750–2752 | Encryptor, EnvFileCipher |
| `CacheException` (Runtime) | `writeFailed()`, `notLoaded()` | 2760–2761 | PhpFileCache, `dumpCache()` |

Messages never contain key material, payloads or decrypted values; paths and names are quoted, and control characters are escaped, so a crafted name cannot forge a log line. `\JsonException` from malformed JSON is left unwrapped (fail-fast, native type).

---

## 6. ARFA 1.3 Compliance

| Principle | Level | Implementation |
|---|---|---|
| P1 — Immutable State Transformation | Required | Configuration and loaded variables are readonly value objects; `with*()` returns new configurations. |
| P2 — Reactive Flow Composition | Level 2 only | Not applicable: loading is a synchronous bootstrap step. |
| P3 — Adaptive Context Awareness | Level 2 only | Not applicable. Environment-aware file selection (`bootEnv()`) is static configuration, not runtime adaptation. |
| P4 — Protocol Agnosticism | Required | The parser consumes any string; the loader works identically under FPM, CLI and long-running servers. |
| P5 — Continuous Observability | Required | `debug()` reports source file, raw value, detected type, override and encryption for every variable; `environment()` reports the environment `bootEnv()` resolved. |

### Conformance Declaration (E-009)

```json
{
    "extra": {
        "kariricode": {
            "arfa": { "version": "1.3", "level": 1 },
            "asvs": { "version": "5.0", "level": 2 },
            "spec": "4.3.3",
            "error-code-range": "ENV 2700–2799"
        }
    }
}
```

The conformance suite (`tests/Conformance`) checks this block, and every
rule of this section a tool can check.

---

## 7. Security Model

| Concern | Guarantee |
|---|---|
| Secrets in VCS | Values can be committed as `encrypted:` (AES-256-GCM, SPEC-004). |
| Secrets at rest in the cache | With a key configured, **every** cached value is stored encrypted, including values interpolated from secrets. |
| File encryption fidelity | `EnvFileCipher` guarantees the application loads identical values before and after `encrypt`/`decrypt`. |
| `.env.example` generation | Names only; multiline values are dropped entirely. |
| Terminal output | `debug` masks encrypted values unless `--reveal`. |
| Path safety | Environment names must match `[A-Za-z0-9][A-Za-z0-9_-]*` before they become file names. |

---

## 8. PHP Language Features

PHP ≥ 8.4 is the ecosystem baseline. The code relies on `readonly` classes (8.2), typed class constants (8.3), `new` in initializers, first-class callable syntax, named arguments and `array_is_list()`.

---

## 9. Ecosystem Integration

The package requires kariricode/contract and kariricode/exception only. Consumers read values through `env()`, `Dotenv::get()`, `$_ENV` or `$_SERVER`, or through kariricode/contract's `Environment\EnvironmentResolver`, which `Integration\DotenvEnvironmentResolver` implements over a loaded instance (ADR-011). Exceptions belong to the contract families and carry `EnvCode` codes (§5). The CLI ships in the same package (`vendor/bin/kariricode-dotenv`).

---

## 10. Accepted Trade-offs

**Cache freezes the process environment.** Interpolated references to process variables and SkipExisting decisions are resolved when the cache is written. Generate the cache on the target host, in the deploy step.

**Staleness uses file metadata.** Path, mtime, size and inode — no content reads. An edit within the same second that keeps size and inode is not detected.

**Encrypted cache costs one decryption per value per load.** No parsing, but not zero work.

**Encrypted values cannot contain references.** `encrypt` leaves such values as plaintext and reports them, instead of freezing the reference.

**SkipExisting locks every pre-existing name.** References to a pre-existing variable resolve to its process value, even when the file redefines it.

**Deny list does not hide values from references (known limitation):** a denied variable is not loaded, but `${NAME}` in an allowed variable of the same file still receives its value. Planned fix: hide denied names from same-file resolution so references fall back to the process environment. Until then, do not reference denied variables.

---

## 11. File Inventory

| Directory | Files | Purpose |
|---|---:|---|
| (root) | 2 | `Dotenv` façade, `env()` helper |
| Cache/ | 2 | `PhpFileCache`, `CacheSnapshot` |
| Contract/ | 4 | Extension interfaces |
| Core/ | 5 | Parser, value context, interpolation context, scanner, entry |
| Enum/ | 2 | `LoadMode`, `ValueType` |
| Exception/ | 10 | Marker interface, `EnvCode`, 8 exceptions |
| Integration/ | 1 | `DotenvEnvironmentResolver` |
| Processor/ | 4 | Built-in processors |
| Schema/ | 1 | `.env.schema` parser |
| Security/ | 4 | Encryptor, key pair, file cipher, result |
| Type/ | 13 | Type system, 6 detectors, 6 casters |
| Validation/ | 11 | Validator + 10 rules |
| ValueObject/ | 2 | Configuration, variable |
| **Total src** | **61** | |

Tests: 28 files in three suites — unit, integration (real files in a
temporary directory, the CLI as a process) and conformance.

---

## 12. Quality Gates

The gates of Spec V4.3.3 §29.1 run through KaririCode Devkit (ADR-009);
`docs/quality/QD-V433-COMPLIANCE.md` records the latest measurement: 412
tests, PHPStan max and Psalm level 1 with no errors, PER-CS 3.0, the §13
budgets with an empty baseline, line coverage of 99.34 % and a
covered-code MSI of 95.14 % (gate: 80 %). The review of 2026-09-29, before these gates, is in
`docs/CODE-REVIEW.md`.

---

## 13. References

- [ARFA-1.3] Silva, W. "ARFA Technical Specification v1.3." 2026.
- [SPEC-V4.3.3] Silva, W. "KaririCode Specification V4.3.3." 2026.
- [POSIX] IEEE Std 1003.1 — Shell Command Language, variable assignment.
- [NIST-GCM] NIST SP 800-38D — Galois/Counter Mode (GCM).
- [RFC-4648] The Base16, Base32, and Base64 Data Encodings.
- [PHP-OPCACHE] PHP Manual — OPcache.
