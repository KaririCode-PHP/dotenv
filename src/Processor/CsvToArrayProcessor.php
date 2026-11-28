<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Processor;

use KaririCode\Dotenv\Contract\VariableProcessor;
use KaririCode\Dotenv\Exception\ConfigurationException;

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
