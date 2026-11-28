<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Schema;

use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Schema\SchemaParser;
use KaririCode\Dotenv\Validation\EnvironmentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The schema file as SPEC-006 reads it, and each directive as it reaches
 * the validator.
 */
final class SchemaDirectivesTest extends TestCase
{
    #[Test]
    public function theFileIsReadSectionBySection(): void
    {
        $schema = new SchemaParser()->parse(<<<'SCHEMA'
            orphan = ignored
              [A]
              required=true
            # note = a comment, not a directive
            ; old = an INI comment
            not a section [B]
            junk without an equals sign
            type = integer
            [C]
            notEmpty = true
            [A]
            min = 1
            SCHEMA);

        self::assertSame(
            [
                'A' => ['required' => 'true', 'type' => 'integer', 'min' => '1'],
                'C' => ['notEmpty' => 'true'],
            ],
            $schema,
        );
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function failingValues(): iterable
    {
        yield 'boolean' => ["type = boolean", 'maybe', ['V must be a boolean (true/false, yes/no, on/off, 1/0).']];
        yield 'numeric' => ["type = numeric", 'abc', ['V must be numeric.']];
        yield 'email' => ["type = email", 'not-an-email', ['V must be a valid email address.']];
        yield 'url' => ["type = url", 'not-a-url', ['V must be a valid URL.']];
        yield 'not empty' => ["notEmpty = true", '   ', ['V must not be empty.']];
        yield 'range' => ["type = integer\nmin = 1\nmax = 10", '11', ['V must be between 1 and 10.']];
        yield 'regex' => ['regex = /\A[a-z]+\z/', 'ABC', ['V must match pattern /\A[a-z]+\z/.']];
    }

    /** @param list<string> $errors */
    #[Test]
    #[DataProvider('failingValues')]
    public function eachDirectiveAddsItsRule(string $directives, string $value, array $errors): void
    {
        self::assertSame($errors, $this->errorsOf("[V]\nrequired = true\n{$directives}", ['V' => $value]));
    }

    #[Test]
    public function directivesAreCaseInsensitiveBooleans(): void
    {
        self::assertSame(['V is required but not defined.'], $this->errorsOf("[V]\nrequired = TRUE", []));
        self::assertSame([], $this->errorsOf("[V]\nnotEmpty = Off", ['V' => '']));
    }

    #[Test]
    public function rulesOfAnOptionalVariableApplyToItAlone(): void
    {
        $schema = "[A]\nrequired = true\n\n[B]\ntype = integer";

        self::assertSame(['B must be an integer.'], $this->errorsOf($schema, ['A' => 'hello', 'B' => 'x']));
        self::assertSame([], $this->errorsOf($schema, ['A' => 'hello']));
        self::assertSame([], $this->errorsOf("[A]\nrequired = true\n\n[B]\nmin = 1\nmax = 10", ['A' => 'hello', 'B' => '5']));
    }

    /**
     * @param array<string, string> $variables
     *
     * @return list<string>
     */
    private function errorsOf(string $schemaText, array $variables): array
    {
        $parser = new SchemaParser();
        $validator = new EnvironmentValidator(static fn(string $name): ?string => $variables[$name] ?? null);
        $parser->applyToValidator($parser->parse($schemaText), $validator);

        try {
            $validator->assert();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }
}
