<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Security;

use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Core\EnvEntry;
use KaririCode\Dotenv\Core\EnvFileScanner;
use KaririCode\Dotenv\Exception\EncryptionException;
use KaririCode\Dotenv\Exception\ParseException;

/**
 * Encrypts or decrypts every value of a .env file in place.
 *
 * Invariant: the values an application loads are identical before and
 * after encrypt() (and after decrypt()). Each value is taken exactly as
 * DotenvParser reads it — escapes resolved, inline comment removed — and
 * written back in a form the parser reads identically. Comments, blank
 * lines and multiline values are preserved.
 *
 * Values containing `${VAR}` / `$VAR` references are left untouched:
 * encrypting them would freeze the reference at encryption time.
 *
 * Preconditions: the content is a .env file the parser reads without errors.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $result = new EnvFileCipher(new Encryptor($key))->encrypt((string) file_get_contents('.env'));
 *
 * References: ADR-004, SPEC-004, SPEC-008. ASVS 5.0 V11 (cryptography).
 *
 * @since   4.5.0
 */
final readonly class EnvFileCipher
{
    public function __construct(
        private Encryptor $encryptor,
        private DotenvParser $parser = new DotenvParser(),
        private EnvFileScanner $scanner = new EnvFileScanner(),
    ) {}

    /**
     * @throws ParseException When an assignment is malformed.
     * @throws EncryptionException When OpenSSL rejects the operation.
     */
    public function encrypt(string $content): CipherResult
    {
        return $this->transform($content, function (string $name, string $value, EnvEntry $entry): ?string {
            if (Encryptor::isEncrypted($value)) {
                return null;
            }

            return $this->render(entry: $entry, name: $name, quotedValue: '"' . $this->encryptor->encrypt($value) . '"');
        });
    }

    /**
     * @throws ParseException When an assignment is malformed.
     * @throws EncryptionException On a wrong key or corrupted payload.
     */
    public function decrypt(string $content): CipherResult
    {
        return $this->transform($content, function (string $name, string $value, EnvEntry $entry): ?string {
            if (!Encryptor::isEncrypted($value)) {
                return null;
            }

            return $this->render(entry: $entry, name: $name, quotedValue: $this->quote($this->encryptor->decrypt($value)));
        });
    }

    /**
     * @param \Closure(string $name, string $value, EnvEntry $entry): ?string $rewrite Returns new text, or null to keep the entry.
     */
    private function transform(string $content, \Closure $rewrite): CipherResult
    {
        $blocks = [];
        $changed = [];
        $skipped = [];

        foreach ($this->scanner->scan($content) as $entry) {
            $name = $entry->name;

            if ($name === null) {
                $blocks[] = $entry->text;

                continue;
            }

            if ($this->hasReference($entry->rawValue)) {
                $skipped[$name] = 'contains a variable reference';
                $blocks[] = $entry->text;

                continue;
            }

            $value = $this->parser->parse($entry->text)[$name] ?? '';
            $rewritten = $rewrite(name: $name, value: $value, entry: $entry);

            if ($rewritten === null) {
                $blocks[] = $entry->text;

                continue;
            }

            $blocks[] = $rewritten;
            $changed[] = $name;
        }

        return new CipherResult(content: implode("\n", $blocks), changed: $changed, skipped: $skipped);
    }

    private function render(EnvEntry $entry, string $name, string $quotedValue): string
    {
        return ($entry->exported ? 'export ' : '') . $name . '=' . $quotedValue . $this->trailingComment($entry->rawValue);
    }

    /**
     * Quotes a plaintext so DotenvParser reads it back byte-for-byte.
     * Single quotes are literal; double quotes are used only when the
     * value contains a single quote or a line break.
     */
    private function quote(string $value): string
    {
        if (!str_contains($value, "'") && !str_contains($value, "\n") && !str_contains($value, "\r")) {
            return "'" . $value . "'";
        }

        return '"' . strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            '$' => '\\$',
            "\n" => '\\n',
            "\r" => '\\r',
        ]) . '"';
    }

    /**
     * True when DotenvParser would interpolate something in this raw value.
     */
    private function hasReference(string $rawValue): bool
    {
        if ($rawValue === '' || $rawValue[0] === "'") {
            return false;
        }

        if ($rawValue[0] !== '"') {
            $unquoted = preg_replace(pattern: '/\s+#.*\z/', replacement: '', subject: $rawValue) ?? $rawValue;

            return preg_match('/\$(\{[A-Za-z_]|[A-Za-z_])/', $unquoted) === 1;
        }

        $length = strlen($rawValue);

        for ($i = 1; $i < $length; ++$i) {
            $char = $rawValue[$i];

            if ($char === '\\') {
                ++$i;

                continue;
            }

            if ($char === '"') {
                return false;
            }

            if ($char === '$' && preg_match('/\A(\{[A-Za-z_]|[A-Za-z_])/', substr(string: $rawValue, offset: $i + 1, length: 2)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns ` # comment` written after the value, so it survives a rewrite.
     */
    private function trailingComment(string $rawValue): string
    {
        $first = $rawValue[0] ?? '';

        if ($first === '"' || $first === "'") {
            $close = $first === '"' ? $this->closingDoubleQuote($rawValue) : strpos(haystack: $rawValue, needle: "'", offset: 1);
            $rest = $close === false ? '' : substr($rawValue, $close + 1);

            return preg_match(pattern: '/\A\s+#.*\z/', subject: $rest, matches: $matches) === 1 ? $matches[0] : '';
        }

        // An unquoted value is one line: the comment runs to its end.
        return preg_match(pattern: '/\s+#.*\z/', subject: $rawValue, matches: $matches) === 1 ? $matches[0] : '';
    }

    private function closingDoubleQuote(string $rawValue): int|false
    {
        $length = strlen($rawValue);

        for ($i = 1; $i < $length; ++$i) {
            if ($rawValue[$i] === '\\') {
                ++$i;

                continue;
            }

            if ($rawValue[$i] === '"') {
                return $i;
            }
        }

        return false;
    }
}
