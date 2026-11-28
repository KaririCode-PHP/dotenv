# ADR-008: Batch Error Validation with Fluent DSL

## Status

Accepted — introduced in 4.1.0.

## Date

2026-02-28 · revised 2026-09-29 (4.5.0)

## Context

Environment validation traditionally follows a fail-fast model: check the first variable, throw on failure, fix, re-run, discover the next failure. For applications with 20+ required variables, this creates a frustrating fix-one-discover-one loop that can require dozens of restart cycles during initial setup.

Requirements:
1. Collect **all** validation failures in a single pass.
2. Provide a fluent API that reads like a specification.
3. Support conditional validation (validate only if variable is present).
4. Allow custom validation logic without subclassing.
5. Separate validation definition from execution (define rules, then `assert()`).

## Decision

### Two-Phase Validation

**Phase 1 — Rule Collection:** The fluent API collects rules without executing them. Each method returns `$this` for chaining.

**Phase 2 — Assertion:** `assert()` executes all collected rules and throws a single `ValidationException` containing every failure message.

```php
$dotenv->validate()
    ->required('DB_HOST', 'DB_PORT')       // Phase 1: collect
    ->isInteger('DB_PORT')->between(1, 65535)
    ->allowedValues('APP_ENV', ['local', 'staging', 'production'])
    ->assert();                             // Phase 2: execute all
```

### Error Collection

```php
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
                $errors[] = str_replace('{name}', $name, $rule->message());
            }
        }
    }

    if ($errors !== []) {
        throw ValidationException::batchErrors($errors);
    }
}
```

The `ValidationException` carries both a human-readable message and a structured `errors(): array` for programmatic inspection.

### Fluent Targeting

The validator maintains a `$currentTargets` array that tracks which variables subsequent rules apply to. Targeting methods update this state:

- `required('A', 'B')` — sets targets to `['A', 'B']`, marks as required.
- `ifPresent('C')` — sets targets to `['C']` (no presence check).
- `isInteger('D')` — adds IsIntegerRule to `D`, sets target to `['D']`.
- `between(1, 65535)` — adds BetweenRule to current targets (inherits from prior call).

This allows natural chaining: `->isInteger('PORT')->between(1, 65535)` applies both rules to `PORT`.

### Optional Variables (`ifPresent`)

Rules never run against an absent variable: an absent required variable is already reported by the presence check, and an absent optional one is not an error. `ifPresent()` therefore adds no machinery — it is the explicit, self-documenting way to target an optional variable: "If REDIS_HOST is set, it must not be empty."

```php
$validator->ifPresent('REDIS_HOST')->notEmpty();   // absent → no error; present and empty → error
```

**Change in 4.5.0:** 4.4 tracked `ifPresent()` targets in a `$conditionalNames` map, but every branch of `assert()` already skipped absent variables, so the map never changed an outcome. It was removed; behavior is unchanged.

### ValidationRule Contract

```php
interface ValidationRule {
    public function passes(string $value): bool;
    public function message(): string;  // "{name}" placeholder replaced at assertion time
}
```

All 10 built-in rules implement this contract. Custom rules are added via:

```php
$validator->rule(new CustomRule(), 'VAR_NAME');
// or
$validator->custom('VAR_NAME', fn(string $v) => strlen($v) >= 8, '{name} must be at least 8 characters.');
```

### Built-in Rules

| Rule | Validation |
|---|---|
| NotEmptyRule | `trim($value) !== ''` |
| IsIntegerRule | `/\A[+-]?\d+\z/` |
| IsBooleanRule | `true/false/1/0/yes/no/on/off` |
| IsNumericRule | `is_numeric()` |
| BetweenRule | `$value >= $min && $value <= $max` |
| AllowedValuesRule | `in_array($value, $allowed, true)` |
| MatchesRegexRule | `preg_match($pattern, $value)` |
| UrlRule | `filter_var(FILTER_VALIDATE_URL)` |
| EmailRule | `filter_var(FILTER_VALIDATE_EMAIL)` |
| CustomRule | User-provided `Closure(string): bool` |

## Consequences

### Positive

- All errors surfaced in one pass — setup/debugging cycle reduced from O(n) restarts to O(1).
- Fluent API is readable as a specification document.
- Optional variables (`ifPresent()`) are validated only when set, without false positives.
- Custom rules require no subclassing — closures or `ValidationRule` implementations work equally.
- `{name}` placeholder in messages enables reusable rule instances across variables.

### Negative

- Rule execution order within a variable is insertion order, not dependency order. A `between()` rule on a non-integer value will fail with a between error rather than a type error. Mitigation: chain `isInteger()` before `between()` — both failures will be reported.
- `$currentTargets` state makes the validator stateful during construction. This is acceptable because the validator is a short-lived builder object, not a long-lived service.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Fail on the first broken rule | the fix-one-discover-one loop the decision removes |
| Attribute-based validation on a config class | needs a class per application and reflection at boot, for rules a fluent list states directly |
| A validation library dependency | breaks ADR-001 for a handful of rules the package implements in a few lines each |

## References

- ARFA 1.3 P5 (Observability).
- SPEC-005, SPEC-006.
- ADR-010 (`ValidationException`, ENV_2730–ENV_2732).
- Errata applied by the 4.5.0 review (CODE-REVIEW.md): E-011.
