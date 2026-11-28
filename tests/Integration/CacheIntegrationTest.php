<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\Exception\CacheException;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\KeyPair;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;

/**
 * Regression tests for cache freshness, strategy matching and secrecy.
 */
final class CacheIntegrationTest extends IntegrationTestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = $this->fixturesDir . '/.env.cache.php';
    }

    public function testCacheNeverStoresPlaintextWhenAKeyIsConfigured(): void
    {
        $key = KeyPair::generate()->privateKey;
        $encrypted = new Encryptor($key)->encrypt('s3cr3t-pw');
        $this->createEnvFile('.env', "DB_PASSWORD=\"{$encrypted}\"\nDATABASE_URL=\"pgsql://u:\${DB_PASSWORD}@h/db\"\nAPP_NAME=demo");

        $writer = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key));
        $writer->load();
        $writer->dumpCache($this->cachePath);

        $cacheFile = (string) file_get_contents($this->cachePath);
        self::assertStringNotContainsString('s3cr3t-pw', $cacheFile);
        self::assertStringNotContainsString('demo', $cacheFile);

        $reader = $this->reader(new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key, cachePath: $this->cachePath));
        $reader->load();

        self::assertSame('cache', $reader->debug()['DATABASE_URL']['source']);
        self::assertSame('pgsql://u:s3cr3t-pw@h/db', $reader->get('DATABASE_URL'));
        self::assertSame('demo', $reader->get('APP_NAME'));
    }

    public function testEncryptedCacheIsIgnoredWithoutAKey(): void
    {
        $key = KeyPair::generate()->privateKey;
        $this->createEnvFile('.env', 'APP_NAME=demo');
        $writer = new Dotenv($this->fixturesDir, new DotenvConfiguration(loadMode: LoadMode::Overwrite, encryptionKey: $key));
        $writer->load();
        $writer->dumpCache($this->cachePath);

        $reader = $this->reader(new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $this->cachePath));
        $reader->load();

        self::assertSame('.env', $reader->debug()['APP_NAME']['source']);
    }

    public function testBootEnvCacheIsInvalidatedWhenACascadeFileChanges(): void
    {
        $this->createEnvFile('.env', "APP_ENV=production\nDB_HOST=base");
        $this->createEnvFile('.env.production', 'DB_HOST=prod-v1');
        $config = new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $this->cachePath);

        $writer = new Dotenv($this->fixturesDir, $config);
        $writer->bootEnv();
        $writer->dumpCache($this->cachePath);

        $this->createEnvFile('.env.production', 'DB_HOST=prod-v2-changed');

        $reader = $this->reader($config);
        $reader->bootEnv();

        self::assertSame('prod-v2-changed', $reader->get('DB_HOST'));
        self::assertSame('.env.production', $reader->debug()['DB_HOST']['source']);
    }

    public function testCacheIsInvalidatedWhenANewLocalFileAppears(): void
    {
        $this->createEnvFile('.env', 'DB_HOST=base');
        $config = new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $this->cachePath);

        $writer = new Dotenv($this->fixturesDir, $config);
        $writer->bootEnv();
        $writer->dumpCache($this->cachePath);

        $this->createEnvFile('.env.local', 'DB_HOST=local-override');

        $reader = $this->reader($config);
        $reader->bootEnv();

        self::assertSame('local-override', $reader->get('DB_HOST'));
    }

    public function testCacheWrittenByLoadIsNotReusedByBootEnv(): void
    {
        $this->createEnvFile('.env', "APP_ENV=production\nDB_HOST=base");
        $this->createEnvFile('.env.production', 'DB_HOST=prod-host');
        $config = new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $this->cachePath);

        $writer = new Dotenv($this->fixturesDir, $config);
        $writer->load();
        $writer->dumpCache($this->cachePath);

        $reader = $this->reader($config);
        $reader->bootEnv();

        self::assertSame('prod-host', $reader->get('DB_HOST'));
    }

    public function testCacheIsNotReusedForAnotherRequestedEnvironment(): void
    {
        $this->createEnvFile('.env', 'DB_HOST=base');
        $this->createEnvFile('.env.staging', 'DB_HOST=staging-host');
        $this->createEnvFile('.env.production', 'DB_HOST=prod-host');
        $config = new DotenvConfiguration(loadMode: LoadMode::Overwrite, cachePath: $this->cachePath);

        $writer = new Dotenv($this->fixturesDir, $config);
        $writer->bootEnv('staging');
        $writer->dumpCache($this->cachePath);

        $reader = $this->reader($config);
        $reader->bootEnv('production');

        self::assertSame('prod-host', $reader->get('DB_HOST'));
    }

    public function testDumpingBeforeLoadingFails(): void
    {
        $this->expectException(CacheException::class);

        new Dotenv($this->fixturesDir)->dumpCache($this->cachePath);
    }

    /**
     * A fresh process: forget what the writer put into the environment.
     */
    private function reader(DotenvConfiguration $config): Dotenv
    {
        foreach (['APP_ENV', 'DB_HOST', 'DB_PASSWORD', 'DATABASE_URL', 'APP_NAME'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }

        return new Dotenv($this->fixturesDir, $config);
    }
}
