<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Contract;

/**
 * Single validation rule applied to an environment variable's raw string value.
 *
 * Preconditions: passes() receives the raw string of a variable that is
 * present; message() may use the `{name}` placeholder and never the value.
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv->validate()->rule(new UrlRule(), 'APP_URL')->assert();
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 *
 * @since   4.1.0
 */
interface ValidationRule
{
    /**
     * @return bool True when the value satisfies this rule.
     */
    public function passes(string $value): bool;

    /**
     * Human-readable failure message. The placeholder `{name}` is replaced
     * with the variable name at reporting time.
     */
    public function message(): string;
}
