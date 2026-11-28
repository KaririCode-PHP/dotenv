<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Contract\ValidationRule;
use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Enum\ValueType;
use KaririCode\Dotenv\Exception\FileNotFoundException;
use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Dotenv\Validation\Rule\BetweenRule;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use PHPUnit\Framework\Attributes\Test;

use function KaririCode\Dotenv\env;

/**
 * The extension points and settings of the public API that the feature
 * suites do not reach: custom types, custom rules, putenv(), a bare name,
 * a missing schema and a value env() does not cast.
 */
final class ExtensionPointsTest extends IntegrationTestCase
{
    #[Test]
    public function aCustomDetectorAndCasterTypeTheLoadedValues(): void
    {
        $this->createEnvFile('.env', 'COLOR=#ff0000');
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->addTypeDetector(new readonly class implements TypeDetector {
            #[\Override]
            public function priority(): int
            {
                return 300;
            }

            #[\Override]
            public function detect(string $value): ?ValueType
            {
                return str_starts_with($value, '#') ? ValueType::Array : null;
            }
        });
        $dotenv->addTypeCaster(ValueType::Array, new readonly class implements TypeCaster {
            /** @return list<int> */
            #[\Override]
            public function cast(string $value): array
            {
                return array_map(static fn(string $pair): int => (int) hexdec($pair), str_split(substr($value, 1), 2));
            }
        });
        $dotenv->load();

        self::assertSame([255, 0, 0], $dotenv->get('COLOR'));
    }

    #[Test]
    public function aCustomRuleRunsForTheNamesItIsGiven(): void
    {
        $this->createEnvFile('.env', "A=short\nB=long enough");
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->load();

        try {
            $dotenv->validate()->rule(new readonly class implements ValidationRule {
                #[\Override]
                public function passes(string $value): bool
                {
                    return strlen($value) >= 8;
                }

                #[\Override]
                public function message(): string
                {
                    return '{name} needs eight characters.';
                }
            }, 'A', 'B')->assert();
            self::fail('Expected a validation failure');
        } catch (ValidationException $e) {
            self::assertSame(['A needs eight characters.'], $e->errors());
        }
    }

    #[Test]
    public function putenvIsWrittenOnlyWhenAsked(): void
    {
        putenv('KC_PUTENV_ON');
        putenv('KC_PUTENV_OFF');
        $this->createEnvFile('.env', 'KC_PUTENV_ON=yes');
        new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, usePutenv: true))->load();

        $this->createEnvFile('.env', 'KC_PUTENV_OFF=yes');
        new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite))->load();

        self::assertSame('yes', getenv('KC_PUTENV_ON'));
        self::assertFalse(getenv('KC_PUTENV_OFF'));
    }

    #[Test]
    public function aBareNameIsAnEmptyValue(): void
    {
        self::assertSame(['FLAG' => ''], new DotenvParser()->parse('FLAG'));
    }

    #[Test]
    public function aMissingSchemaIsReported(): void
    {
        $this->createEnvFile('.env', 'A=1');
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));

        $this->expectException(FileNotFoundException::class);

        $dotenv->loadWithSchema($this->fixturesDir . '/.env.schema');
    }

    #[Test]
    public function aRangeRejectsWhatIsNotANumber(): void
    {
        // 'five' would read as 0.0, which this range includes.
        self::assertFalse(new BetweenRule(-5, 5)->passes('five'));
    }

    #[Test]
    public function envReturnsANonStringProcessValueAsItIs(): void
    {
        $_ENV['KC_ENV_ARRAY'] = ['a'];

        self::assertSame(['a'], env('KC_ENV_ARRAY'));
        self::assertSame('fallback', env('KC_ENV_MISSING', 'fallback'));
    }
}
