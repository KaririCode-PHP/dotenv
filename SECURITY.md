# Security Policy — kariricode/dotenv

Part of the KaririCode Framework. Security baseline: OWASP ASVS 5.0, target
level 2 (`extra.kariricode.asvs` in `composer.json`), KaririCode
Specification V4.3.3 §20–§24 and §32.

## Reporting a vulnerability

E-mail **walmir.silva@kariricode.org** with a description, the affected
version and a minimal reproduction. Do not open a public issue for
vulnerabilities. The fix timeline is agreed per report.

## Supported Versions

| Version | Status |
|---|---|
| 4.5.0 | Release pending (see `docs/quality/QD-V433-COMPLIANCE.md`) |
| 2.0.x | The published line; 4.5.0 replaces it |
| < 2.0 | Not supported |

## Threat Model

The loader runs at boot, before the application container, and turns
files on disk into configuration: secrets included. The deployment and
the files it ships are trusted to be what the operator wrote; the threats
are secrets leaking out of the process (into the repository, a cache file,
a log, a terminal), a tampered encrypted value, a file the loader did not
mean to read, and the process environment being overwritten silently.

```
.env, .env.local, .env.{env}, .env.{env}.local ──► DotenvParser ──► decrypt (AES-256-GCM)
          ▲                                                              │
          └── environment name (argument, configuration, APP_ENV)        ▼
cache file (PHP array, values encrypted when a key is set) ◄── EnvironmentVariable ──► $_ENV, $_SERVER, putenv()
```

| Threat | Control in this package | Residual risk | ASVS 5.0 |
|---|---|---|---|
| Secrets committed to the repository | Values can be stored as `encrypted:` (AES-256-GCM, a random 96-bit nonce per value); the CLI's `encrypt` changes no value the application loads and skips values with references, which encryption would freeze (ADR-004) | The key itself must stay out of the repository: `DOTENV_PRIVATE_KEY` belongs to the deployment's secret store | V11 Cryptography, V13 Configuration |
| A tampered or corrupted encrypted value | The GCM tag is verified on every decryption; a failure is `EncryptionException` (ENV_2751, ENV_2752), never a wrong plaintext | A wrong key and a tampered value fail alike, on purpose | V11 Cryptography |
| Plaintext secrets in the cache file | With a key configured, `dumpCache()` stores every value encrypted, including values interpolated from secrets; a cache written with a key is ignored without one (ADR-005) | Without a key the cache holds plaintext, like the `.env` files it replaces: protect both with file permissions | V14 Data Protection |
| A stale or foreign cache served as configuration | The cache records every file of the cascade, present or absent, with path, mtime, size and inode, plus the strategy and the requested environment; any mismatch, and any malformed field, falls back to parsing (ADR-005) | An in-place edit within the same second that keeps size and inode is not seen; deploy tools that replace files change the inode | V13 Configuration |
| Secrets in exception messages and logs | Messages name variables and files, never values, keys or payloads; the exception base escapes control and bidirectional characters, so a crafted name cannot forge a log line (ADR-010) | `debug()` returns raw values by design, for the application's own introspection; the CLI's `debug` masks encrypted values unless `--reveal` | V16 Security Logging and Error Handling |
| Secret material copied by tooling | `example:generate` writes names only and drops multiline values entirely | Values the operator pastes into an example by hand | V14 Data Protection |
| Path traversal through the environment name | An environment name must match `[A-Za-z0-9][A-Za-z0-9_-]*` before it becomes part of a file name, whether it comes from the argument, the configuration, `APP_ENV` in the process or `APP_ENV` in a file (ADR-007) | None known | V5 File Handling |
| A file silently replacing the deployment's environment | `LoadMode::Immutable`, the default, refuses a file value for a variable the process already defines (ENV_2711); `SkipExisting` keeps the process value and makes references see it (ADR-006) | `Overwrite` replaces process values by design; choose it for local development only | V13 Configuration |
| Malformed `.env` content | Syntax errors are `ParseException` with the file and line, never a partial load; allow and deny lists limit which names are loaded | A denied variable is still visible to `${NAME}` references in the same file (SPEC-001 §10) | V2 Validation and Business Logic |

## ASVS 5.0

Level 2 is the target. The controls above map to these requirements, each
with a test:

| Control | Test |
|---|---|
| Encryption round-trips, uses a fresh nonce, rejects wrong keys and corrupt payloads | `EncryptorTest::testEncryptDecryptRoundTrip`, `testDifferentNoncesProduceDifferentCiphertexts`, `testWrongKeyFailsDecryption`, `testCorruptedPayloadThrows`; `DocumentedValuesTest::aPayloadThatIsNotBase64OrTooShortIsMalformed` |
| `encrypt` never changes a loaded value and skips references | `EnvFileCipherTest::testEncryptingNeverChangesWhatTheApplicationLoads`, `EnvFileCipherLayoutTest::valuesThatInterpolateAreLeftAsWritten` |
| The cache never stores plaintext with a key, and is ignored without one | `CacheIntegrationTest::testCacheNeverStoresPlaintextWhenAKeyIsConfigured`, `testEncryptedCacheIsIgnoredWithoutAKey` |
| A stale or malformed cache is never served | `CacheIntegrationTest::testBootEnvCacheIsInvalidatedWhenACascadeFileChanges`, `testCacheIsInvalidatedWhenANewLocalFileAppears`, `PhpFileCacheMetadataTest::aFileWithOneBrokenFieldIsIgnored` |
| Messages carry no values and cannot forge log lines | `ExceptionCatalogTest::everyFactoryCarriesItsCodeFamilyAndMessage`, `controlCharactersInANameCannotForgeALogLine` |
| The CLI masks secrets and reports failures without traces | `CliTest::testDebugMasksEncryptedValuesUnlessRevealed`, `testExampleGenerateNeverCopiesMultilineBodies`, `testDecryptWithWrongKeyExitsOneWithACleanMessage` |
| Environment names cannot traverse paths | `ProcessEnvironmentTest::anInvalidNameFromAFileIsRejected`, `anInvalidProcessNameIsRejectedEvenWhenAFileNamesAnother` |
| The process environment is not overwritten in Immutable mode | `ProcessEnvironmentTest::aVariableOnlyTheProcessEnvironmentHoldsIsPreExisting` |
| No secrets committed | `tools/secret-scan.php` and its self-test |

## Supply Chain

- Runtime dependencies are `kariricode/contract`, interfaces only, and
  `kariricode/exception`, which builds on the contract alone; ext-openssl,
  bundled with PHP, is suggested for encryption.
- `composer.lock` is committed, resolved from Packagist with a clean
  Composer home, with no path repository.
- `composer security` validates the manifest and the lock, audits the
  runtime lock and the toolchain lock (`.kcode/composer.lock`) for
  advisories, and scans the tree for secrets; a reviewed sample in a test
  or a document carries the marker `secret-scan:allow` on its line.
- `composer sbom` writes a CycloneDX 1.5 document; CI keeps it as a build
  artifact.
- The toolchain is pinned in `devkit.php`; CI installs kcode with a verified
  checksum, pins every action by its full commit SHA and runs the
  dependency review on pull requests.
- Release tags are created by the maintainer (Spec V4.3.3 §32).

## Hardening checklist for applications

- Keep `DOTENV_PRIVATE_KEY` in the deployment's secret store, never in a
  committed file.
- Commit encrypted values only; keep `.env.local` and `.env.*.local`
  out of the repository.
- Configure the key before dumping the cache, and dump it on the target
  host, in the deploy step.
- Restrict read access to `.env*` files and to the cache file.
- Leave `LoadMode::Immutable` on in production.
- Do not log or print `debug()` output; it contains raw values.

## References

- OWASP ASVS 5.0: https://owasp.org/www-project-application-security-verification-standard/
- NIST SP 800-38D, Galois/Counter Mode (GCM): https://csrc.nist.gov/pubs/sp/800/38/d/final
- CWE-209, Generation of Error Message Containing Sensitive Information: https://cwe.mitre.org/data/definitions/209.html
- CWE-22, Path Traversal: https://cwe.mitre.org/data/definitions/22.html
