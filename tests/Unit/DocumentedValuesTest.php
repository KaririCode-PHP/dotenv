<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit;

use KaririCode\Dotenv\Cache\CacheSnapshot;
use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Core\EnvEntry;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Enum\ValueType;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\KeyPair;
use KaririCode\Dotenv\Exception\EncryptionException;
use KaririCode\Dotenv\Exception\EnvCode;
use KaririCode\Dotenv\Type\Caster\ArrayCaster;
use KaririCode\Dotenv\Type\Caster\JsonCaster;
use KaririCode\Dotenv\Type\Detector\ArrayDetector;
use KaririCode\Dotenv\Type\Detector\BooleanDetector;
use KaririCode\Dotenv\Type\Detector\FloatDetector;
use KaririCode\Dotenv\Type\Detector\IntegerDetector;
use KaririCode\Dotenv\Type\Detector\JsonDetector;
use KaririCode\Dotenv\Type\Detector\NullDetector;
use KaririCode\Dotenv\Validation\Rule\BetweenRule;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use KaririCode\Dotenv\ValueObject\EnvironmentVariable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Values the documentation promises: default settings, the priorities of
 * the default detectors (ADR-003), inclusive ranges and the error codes of
 * a malformed payload.
 */
final class DocumentedValuesTest extends TestCase
{
    #[Test]
    public function theDefaultDetectorsKeepTheirDocumentedPriorities(): void
    {
        self::assertSame(
            [200, 190, 180, 170, 160, 150],
            array_map(
                static fn(object $detector): int => $detector->priority(),
                [new NullDetector(), new BooleanDetector(), new IntegerDetector(), new FloatDetector(), new JsonDetector(), new ArrayDetector()],
            ),
        );
    }

    #[Test]
    public function detectorsReadTheirLiterals(): void
    {
        self::assertSame(ValueType::Float, new FloatDetector()->detect('1E5'));
        self::assertSame(ValueType::Float, new FloatDetector()->detect('2.5e-3'));
        self::assertNull(new FloatDetector()->detect('42'));
        self::assertNull(new IntegerDetector()->detect('-'));
        self::assertNull(new IntegerDetector()->detect('+'));
        self::assertSame(ValueType::Integer, new IntegerDetector()->detect('+7'));
        self::assertSame(ValueType::Array, new ArrayDetector()->detect(' [1, 2] '));
        self::assertSame(ValueType::Json, new JsonDetector()->detect(' {"a": 1} '));
        self::assertNull(new JsonDetector()->detect('{"a": 1'));
    }

    #[Test]
    public function castersDecodeJsonWithSurroundingWhitespace(): void
    {
        self::assertSame([1, 2], new ArrayCaster()->cast(" [1, 2]\t"));
        self::assertSame(['a' => 1], new JsonCaster()->cast(' {"a": 1} '));
    }

    #[Test]
    public function settingsDefaultToTheSafeChoices(): void
    {
        $configuration = new DotenvConfiguration();

        self::assertSame(LoadMode::Immutable, $configuration->loadMode);
        self::assertFalse($configuration->strictNames);
        self::assertTrue($configuration->typeCasting);
        self::assertTrue($configuration->populateEnv);
        self::assertTrue($configuration->populateServer);
        self::assertFalse($configuration->usePutenv);
        self::assertNull($configuration->encryptionKey);
        self::assertNull($configuration->cachePath);
    }

    #[Test]
    public function valuesDefaultToPlainUnchangedEntries(): void
    {
        $variable = new EnvironmentVariable(name: 'A', rawValue: '1', type: ValueType::Integer, value: 1);
        $snapshot = new CacheSnapshot(strategy: CacheSnapshot::STRATEGY_LOAD, environment: null, requestedEnvironment: null, sources: [], variables: []);

        self::assertSame(['', false, false], [$variable->source, $variable->overridden, $variable->encrypted]);
        self::assertFalse($snapshot->encrypted);
        self::assertFalse(new EnvEntry('A=1', 'A')->exported);
        self::assertSame(['lower.case' => '1'], new DotenvParser()->parse('lower.case=1'));
    }

    #[Test]
    public function aRangeIncludesItsBounds(): void
    {
        $rule = new BetweenRule(1, 10);

        self::assertTrue($rule->passes('1'));
        self::assertTrue($rule->passes('10'));
        self::assertFalse($rule->passes('0.5'));
        self::assertFalse($rule->passes('10.5'));
    }

    #[Test]
    public function aPayloadThatIsNotBase64OrTooShortIsMalformed(): void
    {
        $encryptor = new Encryptor(KeyPair::generate()->privateKey);

        foreach (['encrypted:!!!not base64!!!', 'encrypted:' . base64_encode(str_repeat('x', 27))] as $payload) {
            try {
                $encryptor->decrypt($payload);
                self::fail('Expected a malformed payload');
            } catch (EncryptionException $e) {
                self::assertSame(EnvCode::MalformedPayload, $e->errorCode, $payload);
            }
        }
    }

    #[Test]
    public function thePublicIdIsTheStartOfTheKeysHash(): void
    {
        $hex = str_repeat('ab', 32);

        self::assertSame(substr(hash('sha256', (string) hex2bin($hex)), 0, 8), KeyPair::fromPrivateKey($hex)->publicId);
    }
}
