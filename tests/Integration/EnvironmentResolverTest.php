<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Contract\Environment\EnvironmentResolver;
use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Integration\DotenvEnvironmentResolver;
use KaririCode\Dotenv\Processor\CsvToArrayProcessor;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\KeyPair;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use PHPUnit\Framework\Attributes\Test;

final class EnvironmentResolverTest extends IntegrationTestCase
{
    #[Test]
    public function itAnswersWithTheRawStringsTheFilesHeld(): void
    {
        $this->createEnvFile('.env', "APP_DEBUG=true\nDB_PORT=5432\nHOSTS=a, b\nEMPTY=\n");
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->addProcessor('HOSTS', new CsvToArrayProcessor());
        $dotenv->load();

        $environment = self::resolverOf($dotenv);

        self::assertSame('true', $environment->get('APP_DEBUG'));
        self::assertSame('5432', $environment->get('DB_PORT'));
        self::assertSame('a, b', $environment->get('HOSTS'));
        self::assertSame('', $environment->get('EMPTY'));
        self::assertTrue($environment->has('EMPTY'));
    }

    #[Test]
    public function itDefinesExactlyWhatTheInstanceLoaded(): void
    {
        $_ENV['ONLY_IN_PROCESS'] = 'process';
        $this->createEnvFile('.env', "FROM_FILE=file\nHIDDEN=secret\n");
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, denyList: ['HIDDEN']));
        $environment = new DotenvEnvironmentResolver($dotenv);

        self::assertFalse($environment->has('FROM_FILE'), 'nothing is defined before load');

        $dotenv->load();

        self::assertSame('file', $environment->get('FROM_FILE'));
        self::assertTrue($environment->has('FROM_FILE'));

        foreach (['ONLY_IN_PROCESS', 'HIDDEN', 'MISSING'] as $name) {
            self::assertNull($environment->get($name), $name);
            self::assertFalse($environment->has($name), $name);
        }
    }

    #[Test]
    public function encryptedValuesAreReturnedDecrypted(): void
    {
        $key = KeyPair::generate()->privateKey;
        $this->createEnvFile('.env', 'API_TOKEN="' . new Encryptor($key)->encrypt('t0ken') . "\"\n");
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key));
        $dotenv->load();

        self::assertSame('t0ken', new DotenvEnvironmentResolver($dotenv)->get('API_TOKEN'));
    }

    /** Typed as the contract, which is what consumers depend on. */
    private static function resolverOf(Dotenv $dotenv): EnvironmentResolver
    {
        return new DotenvEnvironmentResolver($dotenv);
    }
}
