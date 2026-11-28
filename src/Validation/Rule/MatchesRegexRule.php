<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Validation\Rule;

use KaririCode\Dotenv\Contract\ValidationRule;
use KaririCode\Dotenv\Exception\ConfigurationException;

/**
 * Passes a value the pattern matches; the pattern is compiled once, when the rule is built.
 *
 * Preconditions: the pattern is a non-empty PCRE pattern with delimiters.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new MatchesRegexRule('/\\A[a-f0-9]{32}\\z/')->passes($apiKey);
 *
 * References: ADR-008, SPEC-005. ARFA 1.3 P5: messages name variables, not
 * values.
 */
final readonly class MatchesRegexRule implements ValidationRule
{
    /** @var non-empty-string */
    private string $pattern;

    /**
     * @throws ConfigurationException When the pattern is empty or does not compile.
     */
    public function __construct(string $pattern)
    {
        if ($pattern === '' || !self::compiles($pattern)) {
            throw ConfigurationException::invalidRegexPattern($pattern);
        }

        $this->pattern = $pattern;
    }

    #[\Override]
    public function passes(string $value): bool
    {
        return preg_match($this->pattern, $value) === 1;
    }

    #[\Override]
    public function message(): string
    {
        return "{name} must match pattern {$this->pattern}.";
    }

    /** @param non-empty-string $pattern */
    private static function compiles(string $pattern): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
