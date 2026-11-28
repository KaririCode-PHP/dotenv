<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\CodeBlock;
use KaririCode\Exception\ErrorCode;

// ── Exception: EnvCode ───────────────────────────────────────────────
// The error codes of the package: block 2700–2799, prefix ENV.
// ─────────────────────────────────────────────────────────────────────

/**
 * The error codes of kariricode/dotenv: block 2700–2799 of the ecosystem
 * registry under the prefix ENV, so a log line that starts with
 * `[ENV_2701]` names this package and one failure. Backed enum — ARFA 1.3
 * E-003 justification: the ErrorCode contract of kariricode/exception
 * reads the integer as `getCode()`.
 *
 * Error code ranges:
 *   2700-2709  .env syntax (ParseException)
 *   2710-2719  Loading: missing files, immutable variables
 *              (FileNotFoundException, ImmutableException)
 *   2720-2729  Configuration: environment names, options, patterns,
 *              keys (ConfigurationException)
 *   2730-2739  Validation of the loaded values (ValidationException)
 *   2740-2749  Type conversion (ConversionException)
 *   2750-2759  Encryption and decryption (EncryptionException)
 *   2760-2769  The OPcache cache (CacheException)
 *   2770-2799  reserved
 *
 * Releases up to 4.5.0 threw uncoded exceptions; 2700–2799 is the lowest
 * free block of 100 codes in the registry (rule 3), claimed before the
 * first coded release (ADR-010).
 *
 * Preconditions: a code is stable API (registry rule 4): a case is never
 * renumbered and a retired value is never reused.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->load();
 *     } catch (ParseException $e) {
 *         $unterminated = $e->errorCode === EnvCode::UnterminatedQuote;
 *     }
 *
 * References: ADR-010; Spec V4.3.3 §8.4; the registry of record,
 * CODE-BLOCKS.md of kariricode/exception. ARFA 1.3 P5: stable,
 * machine-readable codes.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/CODE-BLOCKS.md
 */
enum EnvCode: int implements ErrorCode
{
    case InvalidVariableName = 2700;
    case UnterminatedQuote = 2701;
    case FileNotFound = 2710;
    case AlreadyDefined = 2711;
    case InvalidEnvironmentName = 2720;
    case EmptySeparator = 2721;
    case InvalidOption = 2722;
    case InvalidRegexPattern = 2723;
    case InvalidEncryptionKey = 2724;
    case InvalidPrivateKey = 2725;
    case MissingRequired = 2730;
    case ValidationFailed = 2731;
    case SchemaViolation = 2732;
    case InvalidBase64 = 2740;
    case JsonStructureExpected = 2741;
    case EncryptionFailed = 2750;
    case MalformedPayload = 2751;
    case DecryptionFailed = 2752;
    case CacheWriteFailed = 2760;
    case CacheNotLoaded = 2761;

    #[\Override]
    public static function prefix(): string
    {
        return 'ENV';
    }

    #[\Override]
    public static function block(): CodeBlock
    {
        return new CodeBlock(2700, 2799);
    }
}
