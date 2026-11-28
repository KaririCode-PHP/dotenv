<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Exception\ValidationException as ValidationBase;

// ── Exception: ValidationException ───────────────────────────────────
// Loaded values that break the declared rules (2730–2739).
// ─────────────────────────────────────────────────────────────────────

/**
 * Reports every rule the loaded environment breaks at once: missing
 * required variables, the failures of an `EnvironmentValidator` batch,
 * or a schema directive the parser does not know. `errors()` lists one
 * message per failure for a CLI or a log; the message joins them on one
 * line. Rule messages name variables, never their values.
 *
 * Preconditions: built only through its named constructors; every list
 * of messages has at least one entry.
 *
 * Thread-safety: immutable once built.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->validate()->required('DB_HOST', 'DB_PORT')->isInteger('DB_PORT')->assert();
 *     } catch (ValidationException $e) {
 *         foreach ($e->errors() as $error) {
 *             fwrite(STDERR, "- {$error}\n");
 *         }
 *     }
 *
 * References: ADR-008, SPEC-005, SPEC-006, ADR-010; Spec V4.3.3 §8.4
 * (Validation family). ARFA 1.3 P5: every failure reported in one pass.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
final class ValidationException extends ValidationBase implements DotenvException
{
    /** @var list<string> */
    private readonly array $errors;

    /** @param list<string> $errors */
    private function __construct(EnvCode $code, string $message, array $errors)
    {
        parent::__construct($code, $message);
        $this->errors = $errors;
    }

    /** @param list<string> $missing */
    public static function missingRequired(array $missing): self
    {
        return new self(
            code: EnvCode::MissingRequired,
            message: 'Missing required environment variables: ' . implode(', ', $missing),
            errors: array_map(static fn(string $name): string => "{$name} is required but not defined.", $missing),
        );
    }

    /** @param list<string> $errors */
    public static function batchErrors(array $errors): self
    {
        return new self(
            code: EnvCode::ValidationFailed,
            message: 'Environment validation failed: ' . implode(' ', $errors),
            errors: $errors,
        );
    }

    public static function schemaViolation(string $message): self
    {
        return new self(code: EnvCode::SchemaViolation, message: "Schema validation failed: {$message}", errors: [$message]);
    }

    /**
     * One message per failure, in the order the rules ran.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
