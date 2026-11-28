<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

/**
 * Outcome of encrypting or decrypting a whole .env file.
 *
 * Preconditions: `$changed` and `$skipped` list variable names, never values.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $result = new EnvFileCipher(new Encryptor($key))->encrypt($content);
 *     file_put_contents('.env', $result->content);
 *
 * References: ADR-004, SPEC-008. ARFA 1.3 P1: an immutable value.
 *
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
