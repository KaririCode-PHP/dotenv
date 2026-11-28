<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation;

use KaririCode\Dotenv\Contract\ValidationRule;
use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Validation\Rule\AllowedValuesRule;
use KaririCode\Dotenv\Validation\Rule\BetweenRule;
use KaririCode\Dotenv\Validation\Rule\CustomRule;
use KaririCode\Dotenv\Validation\Rule\EmailRule;
use KaririCode\Dotenv\Validation\Rule\IsBooleanRule;
use KaririCode\Dotenv\Validation\Rule\IsIntegerRule;
use KaririCode\Dotenv\Validation\Rule\IsNumericRule;
use KaririCode\Dotenv\Validation\Rule\MatchesRegexRule;
use KaririCode\Dotenv\Validation\Rule\NotEmptyRule;
use KaririCode\Dotenv\Validation\Rule\UrlRule;

/**
 * Fluent validation DSL for environment variables.
 *
 * Collects all failures before throwing, rather than stopping at the first.
 * Supports conditional validation via ifPresent() and custom callbacks.
 *
 * ```php
 * $dotenv->validate()
 *     ->required('DB_HOST', 'DB_PORT')
 *     ->notEmpty('DB_HOST')
 *     ->isInteger('DB_PORT')->between(1, 65535)
 *     ->isBoolean('APP_DEBUG')
 *     ->allowedValues('APP_ENV', ['local', 'staging', 'production'])
 *     ->ifPresent('REDIS_HOST')->notEmpty()
 *     ->assert();
 * ```
 *
 * Preconditions: the value resolver returns the raw string of a variable,
 * or null when it is not defined.
 *
 * Thread-safety: one instance per validation; the fluent methods change its
 * rule list.
 *
 * Side effects: assert() throws; the resolver may read the process
 * environment.
 *
 * Example:
 *
 *     $dotenv->validate()
 *         ->required('DB_HOST', 'DB_PORT')
 *         ->isInteger('DB_PORT')->between(1, 65535)
 *         ->assert();
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: every failure reported in one
 * pass.
 *
 * @since   4.1.0
 */
final class EnvironmentValidator
{
    /** @var array<string, list<ValidationRule>> Rules keyed by variable name. */
    private array $rules = [];

    /** @var list<string> Variables that must exist. */
    private array $requiredNames = [];

    /** @var list<string> Current target variable names for fluent chaining. */
    private array $currentTargets = [];

    /**
     * @param \Closure(string): ?string $valueResolver Returns raw value or null if not set.
     */
    public function __construct(
        private readonly \Closure $valueResolver,
    ) {}

    // ── Targeting ────────────────────────────────────────────────────────

    /**
     * Declares variables that must exist (non-null). Does not apply rules to them
     * unless followed by chained rule methods.
     */
    public function required(string ...$names): self
    {
        $names = array_values($names);
        $this->requiredNames = array_merge($this->requiredNames, $names);
        $this->currentTargets = $names;

        return $this;
    }

    /**
     * Targets optional variables. Rules only ever run against variables
     * that are present, so this is the explicit, self-documenting way to
     * validate something optional ("if REDIS_HOST is set, it must not be empty").
     */
    public function ifPresent(string ...$names): self
    {
        $this->currentTargets = array_values($names);

        return $this;
    }

    // ── Built-in Rules ───────────────────────────────────────────────────

    public function notEmpty(string ...$names): self
    {
        return $this->applyRule(new NotEmptyRule(), array_values($names));
    }

    public function isInteger(string ...$names): self
    {
        return $this->applyRule(new IsIntegerRule(), array_values($names));
    }

    public function isBoolean(string ...$names): self
    {
        return $this->applyRule(new IsBooleanRule(), array_values($names));
    }

    public function isNumeric(string ...$names): self
    {
        return $this->applyRule(new IsNumericRule(), array_values($names));
    }

    /**
     * Applies a numeric range constraint. Requires prior isInteger() or isNumeric().
     * Always targets the current chain (set by required/ifPresent/isInteger/etc).
     */
    public function between(int|float $min, int|float $max): self
    {
        return $this->applyRule(new BetweenRule($min, $max), []);
    }

    /**
     * @param list<string> $allowed
     */
    public function allowedValues(string $name, array $allowed): self
    {
        $this->currentTargets = [$name];

        return $this->applyRule(new AllowedValuesRule($allowed), []);
    }

    public function matchesRegex(string $name, string $pattern): self
    {
        $this->currentTargets = [$name];

        return $this->applyRule(new MatchesRegexRule($pattern), []);
    }

    public function url(string ...$names): self
    {
        return $this->applyRule(new UrlRule(), array_values($names));
    }

    public function email(string ...$names): self
    {
        return $this->applyRule(new EmailRule(), array_values($names));
    }

    /**
     * @param \Closure(string): bool $callback
     */
    public function custom(string $name, \Closure $callback, string $message = ''): self
    {
        $this->currentTargets = [$name];
        $msg = $message !== '' ? $message : '{name} failed custom validation.';

        return $this->applyRule(new CustomRule($callback, $msg), []);
    }

    /**
     * Adds an arbitrary ValidationRule implementation to the current targets.
     */
    public function rule(ValidationRule $rule, string ...$names): self
    {
        return $this->applyRule($rule, array_values($names));
    }

    // ── Execution ────────────────────────────────────────────────────────

    /**
     * Runs all collected rules and throws with ALL failures if any exist.
     *
     * @throws ValidationException Containing every failure message.
     */
    public function assert(): void
    {
        $errors = [];

        foreach (array_unique($this->requiredNames) as $name) {
            if (($this->valueResolver)($name) === null) {
                $errors[] = "{$name} is required but not defined.";
            }
        }

        // Rules only run against present values. An absent required variable
        // is already reported above; an absent optional one is not an error.
        foreach ($this->rules as $name => $ruleList) {
            $value = ($this->valueResolver)($name);

            if ($value === null) {
                continue;
            }

            foreach ($ruleList as $rule) {
                if (!$rule->passes($value)) {
                    $errors[] = str_replace(search: '{name}', replace: $name, subject: $rule->message());
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::batchErrors($errors);
        }
    }

    // ── Internal ─────────────────────────────────────────────────────────

    /**
     * @param list<string> $explicitNames Override current targets when non-empty.
     */
    private function applyRule(ValidationRule $rule, array $explicitNames): self
    {
        $targets = $explicitNames !== [] ? $explicitNames : $this->currentTargets;

        if ($explicitNames !== []) {
            $this->currentTargets = $explicitNames;
        }

        foreach ($targets as $name) {
            $this->rules[$name][] = $rule;
        }

        return $this;
    }
}
