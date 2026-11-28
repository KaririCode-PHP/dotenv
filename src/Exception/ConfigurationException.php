<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * The library was given an invalid argument at configuration time.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final class ConfigurationException extends DotenvException
{
    public static function invalidEnvironmentName(string $name): self
    {
        return new self(
            "Invalid environment name '{$name}'. Use letters, digits, '_' or '-' (e.g. production, staging-eu).",
        );
    }

    public static function emptySeparator(): self
    {
        return new self('CSV separator must be a non-empty string.');
    }

    public static function invalidOption(string $option, string $value, string $allowed): self
    {
        return new self("Invalid value '{$value}' for --{$option}. Allowed: {$allowed}.");
    }

    public static function invalidRegexPattern(string $pattern): self
    {
        return new self("Invalid regular expression: {$pattern}");
    }
}
