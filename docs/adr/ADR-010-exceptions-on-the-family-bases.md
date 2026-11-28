# ADR-010: Exceptions on the kariricode/exception Family Bases, Block 2700–2799

## Status

Accepted — V4.5.0

## Date

2026-11-28

## Context

Up to 4.5.0 every exception of the package extended an abstract
`DotenvException`, a subclass of SPL's `RuntimeException`, with no code:
a log line said what failed but not where in the ecosystem, and an
application's error boundary could only tell the package's failures from
others, not a missing file from a wrong key. `EncryptionException` mixed
two causes, a key of the wrong size and a payload that does not decrypt.
`ValidationException` set its error list after construction and joined
its messages with newlines.

KaririCode Specification V4.3.3 §8.4 asks every concrete exception to
extend the kariricode/exception base of exactly one kariricode/contract
family and to carry a case of the component's code table. The registry of
record, CODE-BLOCKS.md of kariricode/exception, had no block for this
package; its rule 3 gives a new claim the lowest free block that fits, and
2700–2799 was the lowest free block of 100 codes.

ADR-001 kept the package free of runtime dependencies. kariricode/contract
and kariricode/exception hold interfaces and four small base classes, have
no dependencies of their own beyond PHP, and are the two packages §31
allows every component.

### Prior Art

- kariricode/runtime ADR-008, kariricode/i18n ADR-I18N-008 and
  kariricode/event-dispatcher ADR-010: the same move, with a marker
  interface per package.

## Decision

1. `EnvCode` is the code table: prefix `ENV`, block 2700–2799, laid out by
   failure.

   | Factory | Code | Case | Exception (family) |
   |---|---|---|---|
   | `ParseException::invalidVariableName()` | ENV_2700 | `InvalidVariableName` | `ParseException` (Validation) |
   | `ParseException::unterminatedQuote()` | ENV_2701 | `UnterminatedQuote` | `ParseException` (Validation) |
   | `FileNotFoundException::forPath()` | ENV_2710 | `FileNotFound` | `FileNotFoundException` (NotFound) |
   | `ImmutableException::alreadyDefined()` | ENV_2711 | `AlreadyDefined` | `ImmutableException` (Runtime) |
   | `ConfigurationException::invalidEnvironmentName()` | ENV_2720 | `InvalidEnvironmentName` | `ConfigurationException` (Configuration) |
   | `ConfigurationException::emptySeparator()` | ENV_2721 | `EmptySeparator` | `ConfigurationException` (Configuration) |
   | `ConfigurationException::invalidOption()` | ENV_2722 | `InvalidOption` | `ConfigurationException` (Configuration) |
   | `ConfigurationException::invalidRegexPattern()` | ENV_2723 | `InvalidRegexPattern` | `ConfigurationException` (Configuration) |
   | `ConfigurationException::invalidEncryptionKey()`, was `EncryptionException::invalidKey()` | ENV_2724 | `InvalidEncryptionKey` | `ConfigurationException` (Configuration) |
   | `ConfigurationException::invalidPrivateKey()`, was `EncryptionException::invalidPrivateKey()` | ENV_2725 | `InvalidPrivateKey` | `ConfigurationException` (Configuration) |
   | `ValidationException::missingRequired()` | ENV_2730 | `MissingRequired` | `ValidationException` (Validation) |
   | `ValidationException::batchErrors()` | ENV_2731 | `ValidationFailed` | `ValidationException` (Validation) |
   | `ValidationException::schemaViolation()` | ENV_2732 | `SchemaViolation` | `ValidationException` (Validation) |
   | `ConversionException::invalidBase64()` | ENV_2740 | `InvalidBase64` | `ConversionException` (Validation) |
   | `ConversionException::jsonStructureExpected()` | ENV_2741 | `JsonStructureExpected` | `ConversionException` (Validation) |
   | `EncryptionException::encryptionFailed()` | ENV_2750 | `EncryptionFailed` | `EncryptionException` (Runtime) |
   | `EncryptionException::malformedPayload()` | ENV_2751 | `MalformedPayload` | `EncryptionException` (Runtime) |
   | `EncryptionException::decryptionFailed()` | ENV_2752 | `DecryptionFailed` | `EncryptionException` (Runtime) |
   | `CacheException::writeFailed()` | ENV_2760 | `CacheWriteFailed` | `CacheException` (Runtime) |
   | `CacheException::notLoaded()` | ENV_2761 | `CacheNotLoaded` | `CacheException` (Runtime) |

2. `KaririCode\Dotenv\Exception\DotenvException` becomes the marker
   interface every exception of the package implements, with
   `$errorCode`, so a `catch` written against the 4.5.0 class still covers
   every failure the package raises.
3. Messages start with `[ENV_<code>]` (the base's format), quote file
   paths, variable and environment names through `quote()`, and never
   contain a value read from a file, a key or a payload. The base escapes
   control and bidirectional characters, so a crafted name cannot forge a
   log line; `batchErrors()` therefore joins its messages with spaces,
   and `errors()` keeps the list.
4. `ValidationException` receives its error list in a private
   constructor and keeps it in a readonly property; `errors()` stays the
   accessor, as it is released API since 2.0.0.
5. `composer.json` requires kariricode/contract ^2.11 and
   kariricode/exception ^2.0.2 and declares the range in
   `extra.kariricode.error-code-range`; ADR-001 is amended accordingly.
6. CODE-BLOCKS.md of kariricode/exception records the claim; the
   conformance suite checks that the installed registry leaves the block
   and the prefix to this package.

## Consequences

### Positive

- One code table checked against the registry; one catch point; each
  failure maps to a contract family, so a boot script or a framework's
  error boundary can decide by family: a Configuration failure is a
  deployment to fix, a NotFound failure a missing file.
- A key of the wrong size is a configuration error, apart from a payload
  that fails to decrypt.

### Negative

- Breaking changes: code that extended the abstract `DotenvException`
  must implement the interface; `EncryptionException::invalidKey()` and
  `invalidPrivateKey()` are now `ConfigurationException` factories; code
  that compared whole messages must allow the `[ENV_<code>]` prefix and
  the quoted names; the batch message is one line.
- Two runtime dependencies where there were none, both without
  dependencies of their own.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Keep the abstract SPL-based `DotenvException` | no code, no contract family; §8.4 requires both |
| Keep both key errors in `EncryptionException` | one class would span the Configuration and Runtime families, which the conformance suite forbids, and a boot script could not tell a bad deployment from a tampered value |
| One class per code | the classes already group failures by cause; the code tells the cases apart |
| A property in place of `errors()` | a released method would break for no gain in behaviour |
| A block next to a related package | rule 3 asks for the lowest free block; the prefix, not the number, names the package |

## References

- KaririCode Specification V4.3.3 §8.4, §31.
- kariricode/exception 2.0: CODE-BLOCKS.md, `ConfigurationException`, `NotFoundException`, `RuntimeException`, `ValidationException`, `CarriesErrorCode`.
- kariricode/runtime ADR-008, kariricode/i18n ADR-I18N-008, kariricode/event-dispatcher ADR-010.
- ADR-001 (amended here), ADR-004, ADR-008.
