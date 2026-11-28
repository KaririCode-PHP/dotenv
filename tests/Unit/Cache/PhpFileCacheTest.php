<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Cache;

use KaririCode\Dotenv\Cache\CacheSnapshot;
use KaririCode\Dotenv\Cache\PhpFileCache;
use PHPUnit\Framework\TestCase;

final class PhpFileCacheTest extends TestCase
{
    private string $tempDir;
    private PhpFileCache $cache;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/kariricode_dotenv_cache_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0o777, true);
        $this->cache = new PhpFileCache();
    }

    protected function tearDown(): void
    {
        // scandir() also returns dotfiles (.env, .env.cache.php), which glob('*') skips
        foreach (array_diff((array) scandir($this->tempDir), ['.', '..']) as $entry) {
            unlink($this->tempDir . '/' . $entry);
        }

        rmdir($this->tempDir);
    }

    // ── Round Trip ───────────────────────────────────────────────────────

    public function testDumpAndLoadRoundTrip(): void
    {
        $source = $this->writeSource('.env', 'A=1');
        $path = $this->tempDir . '/.env.cache.php';
        $snapshot = new CacheSnapshot('bootEnv', 'production', null, [$source], ['A' => '1', 'B' => 'two'], false);

        $this->cache->dump($path, $snapshot);
        $loaded = $this->cache->load($path);

        self::assertNotNull($loaded);
        self::assertSame('bootEnv', $loaded->strategy);
        self::assertSame('production', $loaded->environment);
        self::assertNull($loaded->requestedEnvironment);
        self::assertSame([$source], $loaded->sources);
        self::assertSame(['A' => '1', 'B' => 'two'], $loaded->variables);
        self::assertFalse($loaded->encrypted);
    }

    public function testDumpHandlesSpecialCharacters(): void
    {
        $variables = [
            'QUOTES' => "it's \"quoted\"",
            'BACKSLASH' => 'C:\\path\\to',
            'MULTILINE' => "line1\nline2",
            'PHP_TAG' => '<?php echo 1; ?>',
            'DOLLAR' => 'pa$word ${X}',
        ];

        $path = $this->tempDir . '/.env.cache.php';
        $this->cache->dump($path, new CacheSnapshot('load', null, null, [], $variables));

        self::assertSame($variables, $this->cache->load($path)?->variables);
    }

    public function testVariableNamesCannotCollideWithMetadata(): void
    {
        $path = $this->tempDir . '/.env.cache.php';
        $variables = ['meta' => 'x', '__metadata' => 'y', 'variables' => 'z'];
        $this->cache->dump($path, new CacheSnapshot('load', null, null, [], $variables));

        self::assertSame($variables, $this->cache->load($path)?->variables);
    }

    public function testDumpLeavesNoTemporaryFiles(): void
    {
        $this->cache->dump($this->tempDir . '/.env.cache.php', new CacheSnapshot('load', null, null, [], ['A' => '1']));

        self::assertSame(['.env.cache.php'], array_values(array_diff((array) scandir($this->tempDir), ['.', '..'])));
    }

    // ── Staleness ────────────────────────────────────────────────────────

    public function testLoadReturnsNullWhenFileMissing(): void
    {
        self::assertNull($this->cache->load($this->tempDir . '/nonexistent.php'));
    }

    public function testLoadReturnsNullWhenASourceChanges(): void
    {
        $source = $this->writeSource('.env.production', 'DB_HOST=v1');
        $path = $this->tempDir . '/.env.cache.php';
        $this->cache->dump($path, new CacheSnapshot('bootEnv', 'production', null, [$source], ['DB_HOST' => 'v1']));

        $this->writeSource('.env.production', 'DB_HOST=version-2');

        self::assertNull($this->cache->load($path));
    }

    public function testLoadReturnsNullWhenAnAbsentSourceAppears(): void
    {
        $absent = $this->tempDir . '/.env.local';
        $path = $this->tempDir . '/.env.cache.php';
        $this->cache->dump($path, new CacheSnapshot('bootEnv', 'dev', null, [$absent], ['A' => '1']));

        $this->writeSource('.env.local', 'A=2');

        self::assertNull($this->cache->load($path));
    }

    public function testLoadRejectsTheLegacyFormat(): void
    {
        $path = $this->tempDir . '/.env.cache.php';
        file_put_contents($path, "<?php return ['__metadata' => ['source_hash' => ''], 'A' => 'B'];");

        self::assertNull($this->cache->load($path));
    }

    public function testLoadRejectsMalformedPayloads(): void
    {
        $path = $this->tempDir . '/.env.cache.php';

        file_put_contents($path, "<?php return 'not an array';");
        self::assertNull($this->cache->load($path));

        $this->cache->dump($path, new CacheSnapshot('load', null, null, [], ['A' => '1']));
        file_put_contents($path, str_replace("'A' => '1'", "'A' => 1", (string) file_get_contents($path)));
        self::assertNull($this->cache->load($path));
    }

    // ── Clear ────────────────────────────────────────────────────────────

    public function testClearRemovesFile(): void
    {
        $path = $this->tempDir . '/.env.cache.php';
        $this->cache->dump($path, new CacheSnapshot('load', null, null, [], ['KEY' => 'value']));

        $this->cache->clear($path);

        self::assertFileDoesNotExist($path);
    }

    public function testClearSilentlyIgnoresMissingFile(): void
    {
        $this->cache->clear($this->tempDir . '/missing.php');

        self::assertFileDoesNotExist($this->tempDir . '/missing.php');
    }

    // ── Source Hash ──────────────────────────────────────────────────────

    public function testComputeSourceHashIsDeterministic(): void
    {
        $source = $this->writeSource('.env', 'A=1');

        self::assertSame($this->cache->computeSourceHash([$source]), $this->cache->computeSourceHash([$source]));
    }

    public function testComputeSourceHashDistinguishesAbsentFromEmptyFile(): void
    {
        $path = $this->tempDir . '/.env';
        $absent = $this->cache->computeSourceHash([$path]);

        touch($path);

        self::assertNotSame($absent, $this->cache->computeSourceHash([$path]));
    }

    private function writeSource(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
