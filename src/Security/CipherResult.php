<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

/**
 * Outcome of encrypting or decrypting a whole .env file.
 *
 * Spec V4.0 Value Object: final readonly.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final readonly class CipherResult
{
    /**
     * @param string $content Rewritten file content.
     * @param list<string> $changed Names whose value was encrypted or decrypted.
     * @param array<string, string> $skipped Name → reason the value was left as written.
     */
    public function __construct(
        public string $content,
        public array $changed,
        public array $skipped,
    ) {}
}
