<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Exception\ConfigurationException;
use KaririCode\Dotenv\Exception\DotenvException;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\KeyPair;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;

/**
 * Regression tests for load-mode, cascade and interpolation semantics.
 */
final class LoadingSemanticsTest extends IntegrationTestCase
{
    public function testSkipExistingStillAppliesCascadeOverrides(): void
    {
        // ADR-006 decision matrix, "Cascade" row
        $this->createEnvFile('.env', 'X=a');
        $this->createEnvFile('.env.local', 'X=b');

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
        $dotenv->bootEnv();

        self::assertSame('b', $dotenv->get('X'));
    }

    public function testSkipExistingInterpolatesTheEffectiveValue(): void
    {
        $_ENV['DB_HOST'] = 'real-host';
        $this->createEnvFile('.env', "DB_HOST=file-host\nDSN=\"pgsql:host=\${DB_HOST}\"");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
        $dotenv->load();

        self::assertSame('real-host', $_ENV['DB_HOST']);
        self::assertSame('pgsql:host=real-host', $dotenv->get('DSN'));
    }

    public function testCrossFileReferencesWorkWithoutPopulatingTheEnvironment(): void
    {
        $this->createEnvFile('.env', 'APP_NAME=Kariri');
        $this->createEnvFile('.env.local', 'GREETING="Hi ${APP_NAME}"');

        $config = new DotenvConfiguration(loadMode: LoadMode::Overwrite, populateEnv: false, populateServer: false);
        $dotenv = new Dotenv($this->fixturesDir, $config);
        $dotenv->bootEnv();

        self::assertSame('Hi Kariri', $dotenv->get('GREETING'));
        self::assertArrayNotHasKey('GREETING', $_ENV);
    }

    public function testReferenceToAnEncryptedValueSeesThePlaintext(): void
    {
        $key = KeyPair::generate()->privateKey;
        $password = new Encryptor($key)->encrypt('s3cr3t');
        $this->createEnvFile('.env', "DB_PASSWORD=\"{$password}\"\nDATABASE_URL=\"pgsql://app:\${DB_PASSWORD}@db/app\"");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key));
        $dotenv->load();

        self::assertSame('s3cr3t', $dotenv->get('DB_PASSWORD'));
        self::assertSame('pgsql://app:s3cr3t@db/app', $dotenv->get('DATABASE_URL'));
    }

    public function testDebugReportsWhichValuesWereEncrypted(): void
    {
        $key = KeyPair::generate()->privateKey;
        $this->createEnvFile('.env', 'SECRET="' . new Encryptor($key)->encrypt('x') . "\"\nPLAIN=y");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key));
        $dotenv->load();

        self::assertTrue($dotenv->debug()['SECRET']['encrypted']);
        self::assertFalse($dotenv->debug()['PLAIN']['encrypted']);
    }

    public function testRejectsUnsafeEnvironmentNames(): void
    {
        $this->expectException(ConfigurationException::class);

        new Dotenv($this->fixturesDir)->bootEnv('../../etc/passwd');
    }

    public function testEmptyAppEnvFallsBackToDev(): void
    {
        $this->createEnvFile('.env', 'APP_ENV=');
        $this->createEnvFile('.env.dev', 'FROM_DEV=yes-dev');

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->bootEnv();

        self::assertSame('yes-dev', $dotenv->variables()['FROM_DEV']->rawValue);
    }

    public function testRequiredSeesVariablesSetOnlyThroughPutenv(): void
    {
        putenv('KC_ONLY_PUTENV=1');

        new Dotenv($this->fixturesDir)->required('KC_ONLY_PUTENV');

        $this->addToAssertionCount(1);
    }

    public function testEveryLibraryErrorIsADotenvException(): void
    {
        try {
            new Dotenv($this->fixturesDir, new DotenvConfiguration(encryptionKey: 'short'));
            self::fail('Expected an exception');
        } catch (DotenvException $e) {
            self::assertStringContainsString('256-bit', $e->getMessage());
        }
    }
}
