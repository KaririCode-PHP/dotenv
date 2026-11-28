<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

use KaririCode\Dotenv\Exception\EncryptionException;

/**
 * AES-256-GCM encryption for individual .env values.
 *
 * Wire format: `encrypted:` + base64(nonce[12] ‖ ciphertext[n] ‖ tag[16]).
 * A fresh random nonce is generated for every encryption, so encrypting
 * the same plaintext twice yields different payloads.
 *
 * Uses PHP's ext-openssl (bundled in standard PHP distributions).
 * No external tools or runtimes required — decryption is local and instant.
 *
 * @package KaririCode\Dotenv
 * @since   4.3.0
 */
final readonly class Encryptor
{
    private const string CIPHER = 'aes-256-gcm';
    private const int NONCE_LENGTH = 12;
    private const int TAG_LENGTH = 16;
    private const int KEY_LENGTH = 32;
    private const string PREFIX = 'encrypted:';

    private string $key;

    /**
     * @param string $key 64-char hex string (256-bit key) or 32 raw bytes.
     *
     * @throws EncryptionException When the key is not 256 bits.
     */
    public function __construct(string $key)
    {
        if (preg_match('/\A[0-9a-fA-F]{64}\z/', $key) === 1) {
            $key = (string) hex2bin($key);
        }

        if (strlen($key) !== self::KEY_LENGTH) {
            throw EncryptionException::invalidKey();
        }

        $this->key = $key;
    }

    /**
     * @throws EncryptionException When OpenSSL rejects the operation.
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw EncryptionException::encryptionFailed((string) openssl_error_string());
        }

        return self::PREFIX . base64_encode($nonce . $ciphertext . $tag);
    }

    /**
     * Decrypts an `encrypted:` payload. Any other value is returned unchanged.
     *
     * @throws EncryptionException On malformed payloads, a wrong key or tampering.
     */
    public function decrypt(string $payload): string
    {
        if (!self::isEncrypted($payload)) {
            return $payload;
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        // Empty plaintext is valid in GCM: 12-byte nonce + 0-byte ciphertext + 16-byte tag
        if ($raw === false || strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw EncryptionException::malformedPayload();
        }

        $plaintext = openssl_decrypt(
            substr($raw, self::NONCE_LENGTH, -self::TAG_LENGTH),
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE_LENGTH),
            substr($raw, -self::TAG_LENGTH),
        );

        if ($plaintext === false) {
            throw EncryptionException::decryptionFailed();
        }

        return $plaintext;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
