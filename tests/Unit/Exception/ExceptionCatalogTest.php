<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Exception;

use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use KaririCode\Dotenv\Exception\CacheException;
use KaririCode\Dotenv\Exception\ConfigurationException;
use KaririCode\Dotenv\Exception\ConversionException;
use KaririCode\Dotenv\Exception\DotenvException;
use KaririCode\Dotenv\Exception\EncryptionException;
use KaririCode\Dotenv\Exception\EnvCode;
use KaririCode\Dotenv\Exception\FileNotFoundException;
use KaririCode\Dotenv\Exception\ImmutableException;
use KaririCode\Dotenv\Exception\ParseException;
use KaririCode\Dotenv\Exception\ValidationException;
use KaririCode\Exception\CodeBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheException::class)]
#[CoversClass(ConfigurationException::class)]
#[CoversClass(ConversionException::class)]
#[CoversClass(EncryptionException::class)]
#[CoversClass(EnvCode::class)]
#[CoversClass(FileNotFoundException::class)]
#[CoversClass(ImmutableException::class)]
#[CoversClass(ParseException::class)]
#[CoversClass(ValidationException::class)]
final class ExceptionCatalogTest extends TestCase
{
    /**
     * @return iterable<string, array{DotenvException, EnvCode, class-string, string}>
     */
    public static function exceptions(): iterable
    {
        yield 'invalid variable name' => [
            ParseException::invalidVariableName('1BAD', 3, '.env'),
            EnvCode::InvalidVariableName,
            Validation::class,
            '[ENV_2700] Invalid variable name "1BAD" at line 3 in ".env". Names must match [A-Za-z_][A-Za-z0-9_.]* (letters, digits, underscores, dots).',
        ];
        yield 'invalid strict variable name' => [
            ParseException::invalidVariableName('lower', 1, '/app/.env', true),
            EnvCode::InvalidVariableName,
            Validation::class,
            '[ENV_2700] Invalid variable name "lower" at line 1 in "/app/.env". Names must match [A-Z][A-Z0-9_]* (uppercase letters, digits, underscores).',
        ];
        yield 'unterminated quote' => [
            ParseException::unterminatedQuote(7, '.env.local'),
            EnvCode::UnterminatedQuote,
            Validation::class,
            '[ENV_2701] Unterminated quoted value at line 7 in ".env.local"',
        ];
        yield 'file not found' => [
            FileNotFoundException::forPath('/app/.env'),
            EnvCode::FileNotFound,
            NotFound::class,
            '[ENV_2710] Environment file not found or not readable: "/app/.env"',
        ];
        yield 'already defined' => [
            ImmutableException::alreadyDefined('APP_ENV'),
            EnvCode::AlreadyDefined,
            Runtime::class,
            '[ENV_2711] Environment variable "APP_ENV" is already defined and cannot be overwritten in Immutable mode.',
        ];
        yield 'invalid environment name' => [
            ConfigurationException::invalidEnvironmentName('../etc'),
            EnvCode::InvalidEnvironmentName,
            Configuration::class,
            "[ENV_2720] Invalid environment name \"../etc\". Use letters, digits, '_' or '-' (e.g. production, staging-eu).",
        ];
        yield 'empty separator' => [
            ConfigurationException::emptySeparator(),
            EnvCode::EmptySeparator,
            Configuration::class,
            '[ENV_2721] CSV separator must be a non-empty string.',
        ];
        yield 'invalid option' => [
            ConfigurationException::invalidOption('mode', 'sometimes', 'immutable, overwrite'),
            EnvCode::InvalidOption,
            Configuration::class,
            "[ENV_2722] Invalid value 'sometimes' for --mode. Allowed: immutable, overwrite.",
        ];
        yield 'invalid regex pattern' => [
            ConfigurationException::invalidRegexPattern('/unterminated'),
            EnvCode::InvalidRegexPattern,
            Configuration::class,
            '[ENV_2723] Invalid regular expression: "/unterminated"',
        ];
        yield 'invalid encryption key' => [
            ConfigurationException::invalidEncryptionKey(),
            EnvCode::InvalidEncryptionKey,
            Configuration::class,
            '[ENV_2724] Encryption key must be 32 raw bytes or a 64-character hex string (256-bit).',
        ];
        yield 'invalid private key' => [
            ConfigurationException::invalidPrivateKey(),
            EnvCode::InvalidPrivateKey,
            Configuration::class,
            '[ENV_2725] Private key must be a 64-character hex string (256-bit).',
        ];
        yield 'missing required' => [
            ValidationException::missingRequired(['DB_HOST', 'DB_PORT']),
            EnvCode::MissingRequired,
            Validation::class,
            '[ENV_2730] Missing required environment variables: DB_HOST, DB_PORT',
        ];
        yield 'validation failed' => [
            ValidationException::batchErrors(['PORT must be an integer.', 'DEBUG must be a boolean.']),
            EnvCode::ValidationFailed,
            Validation::class,
            '[ENV_2731] Environment validation failed: PORT must be an integer. DEBUG must be a boolean.',
        ];
        yield 'schema violation' => [
            ValidationException::schemaViolation("Unknown type 'uuid' for [ID]."),
            EnvCode::SchemaViolation,
            Validation::class,
            "[ENV_2732] Schema validation failed: Unknown type 'uuid' for [ID].",
        ];
        yield 'invalid base64' => [
            ConversionException::invalidBase64(),
            EnvCode::InvalidBase64,
            Validation::class,
            '[ENV_2740] Invalid base64 value: cannot decode.',
        ];
        yield 'json structure expected' => [
            ConversionException::jsonStructureExpected('JSON array'),
            EnvCode::JsonStructureExpected,
            Validation::class,
            '[ENV_2741] Value is valid JSON but not a JSON array.',
        ];
        yield 'encryption failed' => [
            EncryptionException::encryptionFailed('error:1C800064:Provider routines::bad decrypt'),
            EnvCode::EncryptionFailed,
            Runtime::class,
            '[ENV_2750] Encryption failed: error:1C800064:Provider routines::bad decrypt',
        ];
        yield 'malformed payload' => [
            EncryptionException::malformedPayload(),
            EnvCode::MalformedPayload,
            Runtime::class,
            '[ENV_2751] Invalid encrypted payload: malformed base64 or too short.',
        ];
        yield 'decryption failed' => [
            EncryptionException::decryptionFailed(),
            EnvCode::DecryptionFailed,
            Runtime::class,
            '[ENV_2752] Decryption failed — wrong key or corrupted payload.',
        ];
        yield 'cache write failed' => [
            CacheException::writeFailed('/var/cache/.env.cache.php'),
            EnvCode::CacheWriteFailed,
            Runtime::class,
            '[ENV_2760] Failed to write cache file: "/var/cache/.env.cache.php"',
        ];
        yield 'cache not loaded' => [
            CacheException::notLoaded(),
            EnvCode::CacheNotLoaded,
            Runtime::class,
            '[ENV_2761] Cannot dump the cache before load(), safeLoad() or bootEnv() has run.',
        ];
    }

    /** @param class-string $family */
    #[Test]
    #[DataProvider('exceptions')]
    public function everyFactoryCarriesItsCodeFamilyAndMessage(DotenvException $exception, EnvCode $code, string $family, string $message): void
    {
        self::assertSame($code, $exception->errorCode);
        self::assertSame($code->value, $exception->getCode());
        self::assertInstanceOf($family, $exception);
        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function theCatalogCoversEveryCode(): void
    {
        $covered = array_map(static fn(array $row): EnvCode => $row[1], iterator_to_array(self::exceptions()));

        self::assertSame(EnvCode::cases(), array_values(array_unique($covered, SORT_REGULAR)));
    }

    #[Test]
    public function theCodesSitInTheEnvBlock(): void
    {
        self::assertSame('ENV', EnvCode::prefix());
        self::assertEquals(new CodeBlock(2700, 2799), EnvCode::block());
    }

    #[Test]
    public function validationErrorsListOneMessagePerFailure(): void
    {
        self::assertSame(
            ['A is required but not defined.', 'B is required but not defined.'],
            ValidationException::missingRequired(['A', 'B'])->errors(),
        );
        self::assertSame(['X must be set.', 'Y must be set.'], ValidationException::batchErrors(['X must be set.', 'Y must be set.'])->errors());
        self::assertSame(['Unknown type "uuid" for ID.'], ValidationException::schemaViolation('Unknown type "uuid" for ID.')->errors());
    }

    #[Test]
    public function controlCharactersInANameCannotForgeALogLine(): void
    {
        $message = ImmutableException::alreadyDefined("APP\n[ENV_2700] forged")->getMessage();

        self::assertStringNotContainsString("\n", $message);
        self::assertStringContainsString('APP\\n[ENV_2700] forged', $message);
    }
}
