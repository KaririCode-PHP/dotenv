<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Validation;

use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Validation\EnvironmentValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What one assert() reports: every failure once, named after its variable
 * and grouped by variable in the order the variables first got a rule
 * (ADR-008).
 */
final class EnvironmentValidatorBatchTest extends TestCase
{
    #[Test]
    public function aVariableRequiredTwiceIsReportedOnce(): void
    {
        self::assertSame(['A is required but not defined.'], $this->errorsOf([], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->required('A')->required('A')));
    }

    #[Test]
    public function requiredNamesAccumulateAcrossCalls(): void
    {
        self::assertSame(
            ['A is required but not defined.', 'B is required but not defined.'],
            $this->errorsOf([], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->required('A')->required('B')),
        );
    }

    #[Test]
    public function anAbsentOptionalVariableDoesNotStopTheOthersRules(): void
    {
        self::assertSame(
            ['PORT must be an integer.'],
            $this->errorsOf(['PORT' => 'abc'], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->ifPresent('ABSENT')->notEmpty()->isInteger('PORT')),
        );
    }

    #[Test]
    public function messagesNameTheVariable(): void
    {
        self::assertSame(
            ['A must be numeric.', 'A must be a valid URL.', 'B must be numeric.', 'B must be a valid email address.'],
            $this->errorsOf(['A' => 'x', 'B' => 'y'], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->isNumeric('A', 'B')->url('A')->email('B')),
        );
    }

    #[Test]
    public function aCustomRuleWithoutAMessageGetsTheDefaultOne(): void
    {
        self::assertSame(
            ['DSN failed custom validation.'],
            $this->errorsOf(['DSN' => 'mysql:'], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->custom('DSN', static fn(string $value): bool => str_starts_with($value, 'pgsql:'))),
        );
    }

    #[Test]
    public function aCustomMessageReplacesTheDefault(): void
    {
        self::assertSame(
            ['DSN must use pgsql.'],
            $this->errorsOf(['DSN' => 'mysql:'], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->custom('DSN', static fn(string $value): bool => false, '{name} must use pgsql.')),
        );
    }

    #[Test]
    public function rulesWithoutExplicitNamesFollowTheLastTargets(): void
    {
        self::assertSame(
            ['B must be an integer.', 'B must be a boolean (true/false, yes/no, on/off, 1/0).'],
            $this->errorsOf(['A' => 'true', 'B' => 'maybe'], static fn(EnvironmentValidator $v): EnvironmentValidator => $v->required('A')->isBoolean()->isInteger('B')->isBoolean()->notEmpty('A')),
        );
    }

    /**
     * @param array<string, string> $variables
     * @param \Closure(EnvironmentValidator): EnvironmentValidator $rules
     *
     * @return list<string>
     */
    private function errorsOf(array $variables, \Closure $rules): array
    {
        $validator = $rules(new EnvironmentValidator(static fn(string $name): ?string => $variables[$name] ?? null));

        try {
            $validator->assert();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }
}
