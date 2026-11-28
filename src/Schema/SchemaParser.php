<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Schema;

use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Validation\EnvironmentValidator;

/**
 * Parses `.env.schema` files and applies declarative validation rules.
 *
 * Schema format (INI-like):
 *
 * ```ini
 * [DB_HOST]
 * required = true
 * type = string
 * notEmpty = true
 *
 * [DB_PORT]
 * required = true
 * type = integer
 * min = 1
 * max = 65535
 * default = 5432
 *
 * [APP_ENV]
 * required = true
 * allowed = local, staging, production
 *
 * [API_KEY]
 * regex = /^[a-f0-9]{32}$/
 * ```
 *
 * Supported directives:
 * - `required = true|false`
 * - `type = string|integer|boolean|numeric|email|url`
 * - `notEmpty = true`
 * - `min = N` / `max = N` (numeric range, requires type=integer or type=numeric)
 * - `allowed = val1, val2, val3`
 * - `regex = /pattern/`
 * - `default = value` (informational — actual default injection is out of scope)
 *
 * @package KaririCode\Dotenv
 * @since   4.4.0
 */
final readonly class SchemaParser
{
    /**
     * Parses a schema file and returns structured rules per variable.
     *
     * @return array<string, array<string, string>> Variable name → directive → value.
     */
    public function parse(string $content): array
    {
        $schema = [];
        $currentSection = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            // Section header: [VAR_NAME]
            if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\]\z/', $line, $matches) === 1) {
                $currentSection = $matches[1];
                $schema[$currentSection] ??= [];
                continue;
            }

            // Directive: key = value
            $eqPos = strpos($line, '=');

            if ($currentSection !== null && $eqPos !== false) {
                $key = trim(substr($line, 0, $eqPos));
                $value = trim(substr($line, $eqPos + 1));
                $schema[$currentSection][$key] = $value;
            }
        }

        return $schema;
    }

    /**
     * Applies parsed schema rules to an EnvironmentValidator.
     *
     * @param array<string, array<string, string>> $schema
     */
    public function applyToValidator(
        array $schema,
        EnvironmentValidator $validator,
    ): void {
        foreach ($schema as $variableName => $directives) {
            $this->applyVariable($variableName, $directives, $validator);
        }
    }

    /**
     * @param array<string, string> $directives
     *
     * @throws ValidationException When the type is unknown.
     */
    private function applyVariable(string $name, array $directives, EnvironmentValidator $validator): void
    {
        if ($this->boolDirective($directives, 'required')) {
            $validator->required($name);
        } else {
            $validator->ifPresent($name);
        }

        if ($this->boolDirective($directives, 'notEmpty')) {
            $validator->notEmpty($name);
        }

        $this->applyType($name, $directives['type'] ?? 'string', $validator);

        if (isset($directives['min'], $directives['max'])) {
            $validator->between((float) $directives['min'], (float) $directives['max']);
        }

        if (isset($directives['allowed'])) {
            $validator->allowedValues($name, array_map(trim(...), explode(',', $directives['allowed'])));
        }

        if (isset($directives['regex'])) {
            $validator->matchesRegex($name, $directives['regex']);
        }
    }

    /**
     * @throws ValidationException When the type is unknown.
     */
    private function applyType(string $name, string $type, EnvironmentValidator $validator): void
    {
        match ($type) {
            'integer' => $validator->isInteger($name),
            'boolean' => $validator->isBoolean($name),
            'numeric' => $validator->isNumeric($name),
            'email' => $validator->email($name),
            'url' => $validator->url($name),
            'string' => null, // no rule beyond presence
            default => throw ValidationException::schemaViolation("Unknown type '{$type}' for [{$name}]."),
        };
    }

    /**
     * @param array<string, string> $directives
     */
    private function boolDirective(array $directives, string $key): bool
    {
        $value = $directives[$key] ?? 'false';

        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
}
