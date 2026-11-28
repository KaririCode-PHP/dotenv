<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\ConfigurationException as ConfigurationBase;

// ── Exception: ConfigurationException ────────────────────────────────
// Settings the loader cannot work with (2720–2729).
// ─────────────────────────────────────────────────────────────────────

/**
 * Rejects a setting before any value is read: an environment name that
 * could escape the project directory, an empty CSV separator, a CLI
 * option outside its allowed values, a validation pattern that does not
 * compile, or an encryption key of the wrong size. A key is described,
 * never echoed. It extends InvalidArgumentException through the
 * Configuration base.
 *
 * Preconditions: built only through its named constructors.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv = new Dotenv(__DIR__, new DotenvConfiguration(encryptionKey: $key));
 *     } catch (ConfigurationException $e) {
 *         $badKey = $e->errorCode === EnvCode::InvalidEncryptionKey;
 *     }
 *
 * References: ADR-004, ADR-007, ADR-010; Spec V4.3.3 §8.4 (Configuration
 * family). ARFA 1.3 P5: coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class ConfigurationException extends ConfigurationBase implements DotenvException
{
    public static function invalidEnvironmentName(string $name): self
    {
        return new self(
            EnvCode::InvalidEnvironmentName,
            'Invalid environment name ' . self::quote($name) . ". Use letters, digits, '_' or '-' (e.g. production, staging-eu).",
        );
    }

    public static function emptySeparator(): self
    {
        return new self(EnvCode::EmptySeparator, 'CSV separator must be a non-empty string.');
    }

    public static function invalidOption(string $option, string $value, string $allowed): self
    {
        return new self(EnvCode::InvalidOption, "Invalid value '{$value}' for --{$option}. Allowed: {$allowed}.");
    }

    public static function invalidRegexPattern(string $pattern): self
    {
        return new self(EnvCode::InvalidRegexPattern, 'Invalid regular expression: ' . self::quote($pattern));
    }

    public static function invalidEncryptionKey(): self
    {
        return new self(EnvCode::InvalidEncryptionKey, 'Encryption key must be 32 raw bytes or a 64-character hex string (256-bit).');
    }

    public static function invalidPrivateKey(): self
    {
        return new self(EnvCode::InvalidPrivateKey, 'Private key must be a 64-character hex string (256-bit).');
    }
}
