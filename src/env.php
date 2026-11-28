<?php

declare(strict_types=1);

namespace KaririCode\Dotenv;

use KaririCode\Dotenv\Type\TypeSystem;

/**
 * Retrieves an environment variable with automatic type casting.
 *
 * Resolution order: $_ENV → $_SERVER → getenv() → $default.
 *
 * The value is cast through the default TypeSystem pipeline,
 * so "true" → bool, "42" → int, "{...}" → array, etc. Each call builds
 * its own TypeSystem, so no state outlives it (ADR-009); code that reads
 * many values should use Dotenv::get().
 *
 * @param non-empty-string $key Variable name.
 * @param mixed $default Fallback when the variable is not defined.
 *
 * @return mixed Typed value, or $default if not found.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

    if ($value === null) {
        $envValue = getenv($key);
        $value = $envValue !== false ? $envValue : null;
    }

    if ($value === null) {
        return $default;
    }

    if (!is_string($value)) {
        return $value;
    }

    return (new TypeSystem())->resolve($value);
}
