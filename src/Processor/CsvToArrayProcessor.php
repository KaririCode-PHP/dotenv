<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;
use KaririCode\Dotenv\Exception\ConfigurationException;

/**
 * Splits a separated value into a list of trimmed strings, dropping
 * empty items, so `HOSTS=a, b,,c` reads as `['a', 'b', 'c']`.
 *
 * Preconditions: the separator is a non-empty string.
 *
 * Thread-safety: immutable; safe to share between fibers and requests.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv->addProcessor('ALLOWED_HOSTS', new CsvToArrayProcessor(','));
 *
 * References: SPEC-007. ARFA 1.3 P1: values transform, the source stays.
 */
final readonly class CsvToArrayProcessor implements VariableProcessor
{
    /** @var non-empty-string */
    private string $separator;

    /**
     * @throws ConfigurationException When the separator is empty.
     */
    public function __construct(string $separator = ',')
    {
        if ($separator === '') {
            throw ConfigurationException::emptySeparator();
        }

        $this->separator = $separator;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function process(string $rawValue, mixed $typedValue): array
    {
        if (trim($rawValue) === '') {
            return [];
        }

        return array_map(trim(...), explode($this->separator, $rawValue));
    }
}
