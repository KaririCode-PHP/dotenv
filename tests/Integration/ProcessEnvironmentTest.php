<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Exception\ConfigurationException;
use KaririCode\Dotenv\Exception\EncryptionException;
use KaririCode\Dotenv\Exception\FileNotFoundException;
use KaririCode\Dotenv\Exception\ImmutableException;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\KeyPair;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use PHPUnit\Framework\Attributes\Test;

/**
 * Where the loader reads the process: the environment name, the
 * encryption key, the variables a file may not replace, and the values
 * validation sees, each with the precedence ADR-006 and ADR-007 give it.
 */
final class ProcessEnvironmentTest extends IntegrationTestCase
{
    // ── Environment name ─────────────────────────────────────────────────

    #[Test]
    public function theArgumentWinsOverTheConfigurationAndTheProcess(): void
    {
        $this->environmentFiles();
        $_ENV['APP_ENV'] = 'test';

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(environmentName: 'production'));
        $dotenv->bootEnv('staging');

        self::assertSame('staging', $dotenv->environment());
        self::assertSame('staging', $dotenv->get('X'));
    }

    #[Test]
    public function theConfigurationWinsOverAnInvalidProcessName(): void
    {
        $this->environmentFiles();
        $_ENV['APP_ENV'] = '../escape';

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(environmentName: 'production'));
        $dotenv->bootEnv();

        self::assertSame('production', $dotenv->environment());
    }

    #[Test]
    public function anInvalidProcessNameIsRejectedEvenWhenAFileNamesAnother(): void
    {
        $this->createEnvFile('.env', 'APP_ENV=production');
        $_ENV['APP_ENV'] = '../escape';

        $this->expectException(ConfigurationException::class);

        new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite))->bootEnv();
    }

    #[Test]
    public function anInvalidNameFromAFileIsRejected(): void
    {
        $this->createEnvFile('.env', 'APP_ENV=../../etc/passwd');

        $this->expectException(ConfigurationException::class);

        new Dotenv($this->fixturesDir)->bootEnv();
    }

    #[Test]
    public function bootEnvNeedsNoFileAtAll(): void
    {
        $dotenv = new Dotenv($this->fixturesDir);
        $dotenv->bootEnv();

        self::assertTrue($dotenv->isLoaded());
        self::assertSame('dev', $dotenv->environment());
        self::assertSame([], $dotenv->variables());
    }

    #[Test]
    public function theBaseFileIsPartOfTheCacheFingerprint(): void
    {
        $this->createEnvFile('.env', 'X=one');
        $cache = $this->fixturesDir . '/.env.cache.php';
        $writer = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $writer->bootEnv();
        $writer->dumpCache($cache);

        $this->createEnvFile('.env', 'X=changed');
        $reader = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $cache));
        $reader->bootEnv();

        self::assertSame('changed', $reader->get('X'));
    }

    #[Test]
    public function anUnchangedCascadeIsServedFromTheCache(): void
    {
        $this->createEnvFile('.env', 'X=one');
        $cache = $this->fixturesDir . '/.env.cache.php';
        $writer = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $writer->bootEnv();
        $writer->dumpCache($cache);

        $reader = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $cache));
        $reader->bootEnv();

        self::assertSame('cache', $reader->debug()['X']['source']);
    }

    #[Test]
    public function theCacheRemembersWhichEnvironmentWasAskedFor(): void
    {
        $this->environmentFiles();
        $cache = $this->fixturesDir . '/.env.cache.php';
        $writer = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, environmentName: 'production'));
        $writer->bootEnv('staging');
        $writer->dumpCache($cache);

        $reader = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, environmentName: 'production', cachePath: $cache));
        $reader->bootEnv();

        self::assertSame('production', $reader->environment());
        self::assertSame('production', $reader->get('X'));
    }

    #[Test]
    public function theProcessNameIsReadFromEnvThenServerThenTheProcessEnvironment(): void
    {
        $this->environmentFiles();
        $_ENV['APP_ENV'] = 'staging';
        $_SERVER['APP_ENV'] = '../escape';
        putenv('APP_ENV=../escape');

        $first = new Dotenv($this->fixturesDir);
        $first->bootEnv();
        self::assertSame('staging', $first->environment());

        unset($_ENV['APP_ENV']);
        $_SERVER['APP_ENV'] = 'production';

        $second = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $second->bootEnv();
        self::assertSame('production', $second->environment());
    }

    // ── Files ────────────────────────────────────────────────────────────

    #[Test]
    public function aTrailingSeparatorOnTheDirectoryIsDropped(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('"' . $this->fixturesDir . DIRECTORY_SEPARATOR . '.env"');

        new Dotenv($this->fixturesDir . DIRECTORY_SEPARATOR)->load();
    }

    #[Test]
    public function debugReportsTheRawValueBesideTheTypedOne(): void
    {
        $this->createEnvFile('.env', 'X=42');
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->load();

        self::assertSame(['source' => '.env', 'rawValue' => '42', 'type' => 'Integer', 'value' => 42, 'overridden' => false, 'encrypted' => false], $dotenv->debug()['X']);
    }

    #[Test]
    public function aDirectoryNamedLikeTheFileIsNotAFile(): void
    {
        mkdir($this->fixturesDir . '/.env');

        $this->expectException(FileNotFoundException::class);

        new Dotenv($this->fixturesDir)->load();
    }

    // ── Encryption key ───────────────────────────────────────────────────

    #[Test]
    public function anEncryptedValueStaysAsStoredWithoutAKey(): void
    {
        $stored = new Encryptor(KeyPair::generate()->privateKey)->encrypt('s3cr3t');
        $this->createEnvFile('.env', "SECRET=\"{$stored}\"");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->load();

        self::assertSame($stored, $dotenv->get('SECRET'));
    }

    #[Test]
    public function theServerKeyWinsOverTheEnvKey(): void
    {
        $server = KeyPair::generate()->privateKey;
        $_SERVER['DOTENV_PRIVATE_KEY'] = $server;
        $_ENV['DOTENV_PRIVATE_KEY'] = KeyPair::generate()->privateKey;
        $this->createEnvFile('.env', 'SECRET="' . new Encryptor($server)->encrypt('s3cr3t') . '"');

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->load();

        self::assertSame('s3cr3t', $dotenv->get('SECRET'));
    }

    #[Test]
    public function theEnvKeyWinsOverTheProcessEnvironment(): void
    {
        $env = KeyPair::generate()->privateKey;
        unset($_SERVER['DOTENV_PRIVATE_KEY']);
        $_ENV['DOTENV_PRIVATE_KEY'] = $env;
        putenv('DOTENV_PRIVATE_KEY=' . KeyPair::generate()->privateKey);
        $this->createEnvFile('.env', 'SECRET="' . new Encryptor($env)->encrypt('s3cr3t') . '"');

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite));
        $dotenv->load();

        self::assertSame('s3cr3t', $dotenv->get('SECRET'));
    }

    #[Test]
    public function aKeyFromTheProcessEnvironmentAloneIsUsed(): void
    {
        $key = KeyPair::generate()->privateKey;
        unset($_SERVER['DOTENV_PRIVATE_KEY'], $_ENV['DOTENV_PRIVATE_KEY']);
        putenv('DOTENV_PRIVATE_KEY=' . KeyPair::generate()->privateKey);
        $this->createEnvFile('.env', 'SECRET="' . new Encryptor($key)->encrypt('s3cr3t') . '"');

        $this->expectException(EncryptionException::class);

        new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite))->load();
    }

    // ── Pre-existing variables ───────────────────────────────────────────

    #[Test]
    public function aVariableOnlyTheProcessEnvironmentHoldsIsPreExisting(): void
    {
        unset($_ENV['KC_ONLY_GETENV'], $_SERVER['KC_ONLY_GETENV']);
        putenv('KC_ONLY_GETENV=process');
        $this->createEnvFile('.env', 'KC_ONLY_GETENV=file');

        $this->expectException(ImmutableException::class);

        new Dotenv($this->fixturesDir)->load();
    }

    #[Test]
    public function underSkipExistingAReferenceSeesTheProcessValue(): void
    {
        unset($_ENV['KC_LOCK'], $_SERVER['KC_LOCK']);
        putenv('KC_LOCK=process');
        $_SERVER['KC_NUMBER'] = 5;
        $this->createEnvFile('.env', "KC_LOCK=file\nREF=\${KC_LOCK}\nNUM=x\${KC_NUMBER}y");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
        $dotenv->load();

        self::assertSame('process', $dotenv->get('REF'));
        self::assertSame('xy', $dotenv->get('NUM'));
        self::assertArrayNotHasKey('KC_LOCK', $dotenv->variables());
    }

    #[Test]
    public function underSkipExistingAnEarlierFileIsACascadeNotALock(): void
    {
        $this->createEnvFile('.env', 'KC_LAYER=base');
        $this->createEnvFile('.env.local', "KC_LAYER=local\nKC_REF=\${KC_LAYER}");

        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
        $dotenv->bootEnv();

        self::assertSame('local', $dotenv->get('KC_LAYER'));
        self::assertSame('local', $dotenv->get('KC_REF'));
    }

    // ── Values validation sees ───────────────────────────────────────────

    #[Test]
    public function validationSeesLoadedValuesThatWereNotPublished(): void
    {
        $this->createEnvFile('.env', 'KC_HIDDEN=1');
        $dotenv = new Dotenv($this->fixturesDir, new DotenvConfiguration(populateEnv: false, populateServer: false));
        $dotenv->load();

        $dotenv->required('KC_HIDDEN');
        $dotenv->validate()->allowedValues('KC_HIDDEN', ['1'])->assert();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validationReadsEnvThenServerThenTheProcessEnvironment(): void
    {
        $dotenv = new Dotenv($this->fixturesDir);
        $dotenv->safeLoad();

        $_ENV['KC_ORDER'] = 'env';
        $_SERVER['KC_ORDER'] = 'server';
        putenv('KC_ORDER=process');
        $dotenv->validate()->allowedValues('KC_ORDER', ['env'])->assert();

        unset($_ENV['KC_ORDER']);
        $dotenv->validate()->allowedValues('KC_ORDER', ['server'])->assert();

        unset($_SERVER['KC_ORDER']);
        $dotenv->validate()->allowedValues('KC_ORDER', ['process'])->assert();

        $this->addToAssertionCount(3);
    }

    private function environmentFiles(): void
    {
        $this->createEnvFile('.env', 'X=base');
        $this->createEnvFile('.env.staging', 'X=staging');
        $this->createEnvFile('.env.production', 'X=production');
        $this->createEnvFile('.env.test', 'X=test');
    }
}
