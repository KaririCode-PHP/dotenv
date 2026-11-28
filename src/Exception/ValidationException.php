<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * Thrown when environment variable validation fails.
 * Supports batch errors: collects ALL failures before throwing.
 *
 * @package KaririCode\Dotenv
 * @since   4.0.0 ARFA 1.3
 */
final class ValidationException extends DotenvException
{
    /** @var list<string> */
    private array $errors = [];

    /** @param list<string> $missing */
    public static function missingRequired(array $missing): self
    {
        $exception = new self(
            'Missing required environment variables: ' . implode(', ', $missing),
        );
        $exception->errors = array_map(
            static fn(string $name): string => "{$name} is required but not defined.",
            $missing,
        );

        return $exception;
    }

    /** @param list<string> $errors */
    public static function batchErrors(array $errors): self
    {
        $exception = new self(
            "Environment validation failed:\n- " . implode("\n- ", $errors),
        );
        $exception->errors = $errors;

        return $exception;
    }

    public static function schemaViolation(string $message): self
    {
        $exception = new self("Schema validation failed: {$message}");
        $exception->errors = [$message];

        return $exception;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
