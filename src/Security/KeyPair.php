<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

use KaririCode\Dotenv\Exception\EncryptionException;

/**
 * A 256-bit encryption key plus a short, non-secret identifier.
 *
 * The public ID is the first 8 hex characters of SHA-256(raw key). It lets
 * several environments reference their keys without exposing key material.
 *
 * Spec V4.0 Value Object: final readonly, created only via named constructors.
 *
 * @package KaririCode\Dotenv
 * @since   4.3.0
 */
final readonly class KeyPair
{
    private function __construct(
        public string $privateKey,
        public string $publicId,
    ) {}

    public static function generate(): self
    {
        $rawKey = random_bytes(32);

        return new self(bin2hex($rawKey), self::deriveId($rawKey));
    }

    /**
     * @throws EncryptionException When the key is not 64 hex characters.
     */
    public static function fromPrivateKey(string $privateKey): self
    {
        if (preg_match('/\A[0-9a-fA-F]{64}\z/', $privateKey) !== 1) {
            throw EncryptionException::invalidPrivateKey();
        }

        return new self($privateKey, self::deriveId((string) hex2bin($privateKey)));
    }

    private static function deriveId(string $rawKey): string
    {
        return substr(hash('sha256', $rawKey), 0, 8);
    }
}
