# SPEC-001: KaririCode\Dotenv Component

| Field | Value |
|---|---|
| **Version** | 4.5.0 |
| **ARFA** | 1.3 |
| **Conformance Level** | Level 1 (Structural) — P1, P4, P5 |
| **Quality Spec** | KaririCode Code Quality Specification V4.0 |
| **PHP** | ≥ 8.4 |
| **Runtime dependencies** | none (ext-openssl optional, for encryption) |
| **Date** | 2026-09-29 |

---

## 1. Overview

KaririCode\Dotenv loads `.env` files into typed, validated, optionally encrypted configuration. It is a Layer 1 foundation component: it runs before the application container exists, so it depends on nothing but PHP.

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

## 4. Type Taxonomy Compliance (Spec V4.0 §3)

| Category | PHP declaration | Classes |
|---|---|---|
| Value Object | `final readonly class` | `DotenvConfiguration`, `EnvironmentVariable`, `KeyPair`, `CacheSnapshot`, `EnvEntry`, `CipherResult`, `InterpolationContext` |
| Stateless service | `final readonly class` | `DotenvParser`, `SchemaParser`, `Encryptor`, `EnvFileScanner`, `EnvFileCipher`, `PhpFileCache`, all detectors, casters, processors and rules |
| Entity | `final class` | `Dotenv` (load state), `TypeSystem` (detector registry), `EnvironmentValidator` (rule builder) |
| Exception | `abstract` base + `final` leaves | see §5 |
| Enum | pure `enum` | `LoadMode`, `ValueType` |

### Serialization Policy (E-001, E-004)

No `jsonSerialize()`, `toArray()` or `fromArray()`. Value objects expose `public readonly` properties. `Dotenv::debug()` is an introspection report, not a serialization contract.

### Enum Policy (E-003)

`LoadMode` and `ValueType` are pure enums: neither crosses a process boundary. The cache stores strategy names as plain strings, not enum values.

### Property Hooks (E-002, E-007)

None. Derived values are methods (`EnvEntry::isAssignment()`).

---

## 5. Exception Model (E-006, E-011)

Every exception extends the abstract `DotenvException` (itself a `RuntimeException`), so `catch (DotenvException $e)` covers the library. All are created through named factories.

| Class | Factories | Raised by |
|---|---|---|
| `FileNotFoundException` | `forPath()` | missing `.env` / schema / CLI input |
| `ParseException` | `invalidVariableName()`, `unterminatedQuote()` | parser |
| `ImmutableException` | `alreadyDefined()` | Immutable load mode |
| `ValidationException` | `missingRequired()`, `batchErrors()`, `schemaViolation()`; `errors()` always lists every message | validation, schema |
| `EncryptionException` | `invalidKey()`, `invalidPrivateKey()`, `encryptionFailed()`, `malformedPayload()`, `decryptionFailed()` | Encryptor, KeyPair |
| `CacheException` | `writeFailed()`, `notLoaded()` | PhpFileCache, `dumpCache()` |
| `ConversionException` | `invalidBase64()`, `jsonStructureExpected()` | casters, processors |
| `ConfigurationException` | `invalidEnvironmentName()`, `emptySeparator()`, `invalidRegexPattern()`, `invalidOption()` | invalid arguments |

Messages never contain key material or decrypted values. `\JsonException` from malformed JSON is left unwrapped (fail-fast, native type).

**Numeric error codes:** not assigned. Dotenv does not depend on `kariricode/contract`, so exceptions are identified by class.

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
            "component": "dotenv",
            "version": "4.5.0",
            "arfa": { "version": "1.3", "level": 1, "principles": ["P1", "P4", "P5"] },
            "spec": "V4.0"
        }
    }
}
```

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

Zero runtime dependencies. Consumers read values through `env()`, `Dotenv::get()`, `$_ENV` or `$_SERVER`. The CLI ships in the same package (`vendor/bin/kariricode-dotenv`).

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
| Core/ | 4 | Parser, interpolation context, scanner, entry |
| Enum/ | 2 | `LoadMode`, `ValueType` |
| Exception/ | 9 | Abstract base + 8 semantic exceptions |
| Processor/ | 4 | Built-in processors |
| Schema/ | 1 | `.env.schema` parser |
| Security/ | 4 | Encryptor, key pair, file cipher, result |
| Type/ | 13 | Type system, 6 detectors, 6 casters |
| Validation/ | 11 | Validator + 10 rules |
| ValueObject/ | 2 | Configuration, variable |
| **Total src** | **58** | |

Tests: 15 files (unit + integration, including CLI process tests).

---

## 12. Quality Gates

Measured on 2026-09-29, PHP 8.4.23 (details in `docs/CODE-REVIEW.md`):

| Gate | Result |
|---|---|
| PHPUnit | 236 tests, 432 assertions, 0 failures, 0 warnings |
| PHPStan level 9 (`src`, `bin`) | 0 errors, no ignores, no baseline |
| PHP-CS-Fixer (PSR-12 + PHP 8.4 migration) | 0 of 74 files to fix |
| Errata E-001–E-012 | 12/12 |
| Section markers (E-008) | 78/78 at 72 characters |
| Mutation check | 11/11 reintroduced defects caught |

---

## 13. References

- [ARFA-1.3] Silva, W. "ARFA Technical Specification v1.3." 2026.
- [SPEC-V4.0] Silva, W. "KaririCode Code Quality Specification V4.0." 2026.
- [POSIX] IEEE Std 1003.1 — Shell Command Language, variable assignment.
- [NIST-GCM] NIST SP 800-38D — Galois/Counter Mode (GCM).
- [RFC-4648] The Base16, Base32, and Base64 Data Encodings.
- [PHP-OPCACHE] PHP Manual — OPcache.
