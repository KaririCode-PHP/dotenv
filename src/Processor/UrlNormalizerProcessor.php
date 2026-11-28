<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;

/**
 * Normalizes a URL value: trims it and removes the trailing slash, so
 * `APP_URL=https://example.com/` and `https://example.com` read alike.
 *
 * Preconditions: none; a value that is not a URL passes through trimmed.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv->addProcessor('*_URL', new UrlNormalizerProcessor());
 *
 * References: SPEC-007. ARFA 1.3 P1: values transform, the source stays.
 */
final readonly class UrlNormalizerProcessor implements VariableProcessor
{
    #[\Override]
    public function process(string $rawValue, mixed $typedValue): string
    {
        return rtrim($rawValue, '/') . '/';
    }
}
