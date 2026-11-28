<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

use KaririCode\Dotenv\Exception\ConfigurationException;

/**
 * A 256-bit encryption key plus a short, non-secret identifier.
 *
 * The public ID is the first 8 hex characters of SHA-256(raw key). It lets
 * several environments reference their keys without exposing key material.
 *
 * Preconditions: fromPrivateKey() takes 64 hex characters.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: generate() reads the CSPRNG.
 *
 * Example:
 *
 *     $pair = KeyPair::generate();
 *     echo "DOTENV_PRIVATE_KEY={$pair->privateKey}  # id {$pair->publicId}\n";
 *
 * References: ADR-004, SPEC-004. ASVS 5.0 V11 (key management).
 *
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
     * @throws ConfigurationException When the key is not 64 hex characters.
     */
    public static function fromPrivateKey(string $privateKey): self
    {
        if (preg_match('/\A[0-9a-fA-F]{64}\z/', $privateKey) !== 1) {
            throw ConfigurationException::invalidPrivateKey();
        }

        return new self($privateKey, self::deriveId((string) hex2bin($privateKey)));
    }

    private static function deriveId(string $rawKey): string
    {
        return substr(string: hash('sha256', $rawKey), offset: 0, length: 8);
    }
}
