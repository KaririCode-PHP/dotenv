<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * Key, payload and cipher failures in the AES-256-GCM layer.
 *
 * Messages never include key material or plaintext.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final class EncryptionException extends DotenvException
{
    public static function invalidKey(): self
    {
        return new self('Encryption key must be 32 raw bytes or a 64-character hex string (256-bit).');
    }

    public static function invalidPrivateKey(): self
    {
        return new self('Private key must be a 64-character hex string (256-bit).');
    }

    public static function encryptionFailed(string $reason): self
    {
        return new self("Encryption failed: {$reason}");
    }

    public static function malformedPayload(): self
    {
        return new self('Invalid encrypted payload: malformed base64 or too short.');
    }

    public static function decryptionFailed(): self
    {
        return new self('Decryption failed — wrong key or corrupted payload.');
    }
}
