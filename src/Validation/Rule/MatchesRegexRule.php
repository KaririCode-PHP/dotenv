<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;
use KaririCode\Dotenv\Exception\ConfigurationException;

final readonly class MatchesRegexRule implements ValidationRule
{
    /**
     * @throws ConfigurationException When the pattern does not compile.
     */
    public function __construct(
        private string $pattern,
    ) {
        set_error_handler(static fn (): bool => true);

        try {
            $compiles = preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$compiles) {
            throw ConfigurationException::invalidRegexPattern($pattern);
        }
    }

    public function passes(string $value): bool
    {
        return preg_match($this->pattern, $value) === 1;
    }

    public function message(): string
    {
        return "{name} must match pattern {$this->pattern}.";
    }
}
