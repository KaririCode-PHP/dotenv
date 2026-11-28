<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Cache;

use KaririCode\Dotenv\Cache\CacheSnapshot;
use KaririCode\Dotenv\Cache\PhpFileCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every field of a cache file is checked on load: a file that differs from
 * what dump() writes in any one of them is ignored, never half-trusted.
 */
final class PhpFileCacheMetadataTest extends TestCase
{
    private string $directory;

    private string $source;

    private string $path;

    private PhpFileCache $cache;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/kariricode_dotenv_meta_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o777, true);
        $this->source = $this->directory . '/.env';
        file_put_contents($this->source, 'A=1');
        $this->path = $this->directory . '/.env.cache.php';
        $this->cache = new PhpFileCache();
    }

    protected function tearDown(): void
    {
        foreach (array_diff((array) scandir($this->directory), ['.', '..']) as $entry) {
            $entry = $this->directory . '/' . $entry;
            is_dir($entry) ? rmdir($entry) : unlink($entry);
        }

        rmdir($this->directory);
    }

    #[Test]
    public function everyFieldSurvivesTheRoundTrip(): void
    {
        $this->cache->dump($this->path, new CacheSnapshot(
            strategy: CacheSnapshot::STRATEGY_BOOT_ENV,
            environment: 'staging',
            requestedEnvironment: 'staging-eu',
            sources: [$this->source, $this->directory . '/.env.local'],
            variables: ['A' => '1'],
            encrypted: true,
        ));

        self::assertEquals(
            new CacheSnapshot(
                strategy: CacheSnapshot::STRATEGY_BOOT_ENV,
                environment: 'staging',
                requestedEnvironment: 'staging-eu',
                sources: [$this->source, $this->directory . '/.env.local'],
                variables: ['A' => '1'],
                encrypted: true,
            ),
            $this->cache->load($this->path),
        );
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function brokenFields(): iterable
    {
        yield 'another format' => ['format', 1];
        yield 'strategy not a string' => ['strategy', 7];
        yield 'encrypted not a boolean' => ['encrypted', 'yes'];
        yield 'environment not a string' => ['environment', 5];
        yield 'requested environment not a string' => ['requested_environment', ['staging']];
        yield 'sources not a list' => ['sources', ['first' => '/app/.env']];
        yield 'sources not an array' => ['sources', '/app/.env'];
        yield 'a source not a string' => ['sources', [42]];
        yield 'hash not a string' => ['source_hash', 12345];
        yield 'hash of other files' => ['source_hash', str_repeat('0', 64)];
    }

    #[Test]
    #[DataProvider('brokenFields')]
    public function aFileWithOneBrokenFieldIsIgnored(string $field, mixed $value): void
    {
        $meta = $this->validMeta();
        $meta[$field] = $value;
        $this->write(['meta' => $meta, 'variables' => ['A' => '1']]);

        self::assertNull($this->cache->load($this->path));
    }

    #[Test]
    public function nullEnvironmentsAreValid(): void
    {
        $meta = $this->validMeta();
        $meta['environment'] = null;
        $meta['requested_environment'] = null;
        $this->write(['meta' => $meta, 'variables' => ['A' => '1']]);

        $snapshot = $this->cache->load($this->path);

        self::assertNotNull($snapshot);
        self::assertNull($snapshot->environment);
        self::assertNull($snapshot->requestedEnvironment);
    }

    #[Test]
    public function aFileThatIsNotACacheDocumentIsIgnored(): void
    {
        $meta = $this->validMeta();
        $documents = [
            'not an array' => 5,
            'no metadata' => ['variables' => ['A' => '1']],
            'metadata not an array' => ['meta' => 'x', 'variables' => ['A' => '1']],
            'no variables' => ['meta' => $meta],
            'variables not an array' => ['meta' => $meta, 'variables' => 'A=1'],
            'a variable keyed by a number' => ['meta' => $meta, 'variables' => ['1']],
        ];

        // One file per case: OPcache may keep a compiled file rewritten within a second.
        foreach (array_values($documents) as $index => $document) {
            $path = $this->directory . "/case-{$index}.php";
            file_put_contents($path, '<?php return ' . var_export($document, true) . ';');

            self::assertNull($this->cache->load($path), (string) array_keys($documents)[$index]);
        }
    }

    #[Test]
    public function aDirectoryIsNotACacheFile(): void
    {
        mkdir($this->path);

        self::assertNull($this->cache->load($this->path));
    }

    #[Test]
    public function theFingerprintSeesAnEditMadeEarlierInTheSameProcess(): void
    {
        $before = $this->cache->computeSourceHash([$this->source]);
        file_put_contents($this->source, 'A=1 and more');

        self::assertNotSame($before, $this->cache->computeSourceHash([$this->source]));
    }

    /** @return array<string, mixed> */
    private function validMeta(): array
    {
        return [
            'format' => 2,
            'strategy' => CacheSnapshot::STRATEGY_LOAD,
            'environment' => 'production',
            'requested_environment' => 'production',
            'encrypted' => false,
            'sources' => [$this->source],
            'source_hash' => $this->cache->computeSourceHash([$this->source]),
        ];
    }

    private function write(mixed $document): void
    {
        file_put_contents($this->path, '<?php return ' . var_export($document, true) . ';');
    }
}
