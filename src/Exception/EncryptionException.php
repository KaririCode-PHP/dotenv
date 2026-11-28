<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\RuntimeException as RuntimeBase;

// ── Exception: EncryptionException ───────────────────────────────────
// An encryption or decryption that did not complete (2750–2759).
// ─────────────────────────────────────────────────────────────────────

/**
 * Reports an AES-256-GCM operation that failed: OpenSSL refused to
 * encrypt, a stored payload is not valid base64 or too short to hold the
 * nonce and tag, or the tag does not verify, which means a wrong key or a
 * tampered value. Neither the plaintext, the payload nor the key appears
 * in the message. A key of the wrong size is a ConfigurationException.
 *
 * Preconditions: built only through its named constructors; the reason
 * of `encryptionFailed()` is OpenSSL's error string.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $plaintext = $encryptor->decrypt($stored);
 *     } catch (EncryptionException $e) {
 *         $tampered = $e->errorCode === EnvCode::DecryptionFailed;
 *     }
 *
 * References: ADR-004, SPEC-004, ADR-010; Spec V4.3.3 §8.4 (Runtime
 * family). ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class EncryptionException extends RuntimeBase implements DotenvException
{
    public static function encryptionFailed(string $reason): self
    {
        return new self(EnvCode::EncryptionFailed, "Encryption failed: {$reason}");
    }

    public static function malformedPayload(): self
    {
        return new self(EnvCode::MalformedPayload, 'Invalid encrypted payload: malformed base64 or too short.');
    }

    public static function decryptionFailed(): self
    {
        return new self(EnvCode::DecryptionFailed, 'Decryption failed — wrong key or corrupted payload.');
    }
}
