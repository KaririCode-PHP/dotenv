<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Temporary project directory plus full restoration of $_ENV, $_SERVER
 * and putenv() state, so integration tests never leak variables.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected string $fixturesDir;

    /** @var array<mixed> */
    private array $envSnapshot = [];

    /** @var array<mixed> */
    private array $serverSnapshot = [];

    /** @var array<string, string> */
    private array $processSnapshot = [];

    protected function setUp(): void
    {
        $this->fixturesDir = sys_get_temp_dir() . '/kariricode-dotenv-test-' . bin2hex(random_bytes(6));
        mkdir($this->fixturesDir, 0o755, true);

        $this->envSnapshot = $_ENV;
        $this->serverSnapshot = $_SERVER;
        $this->processSnapshot = getenv();
    }

    protected function tearDown(): void
    {
        foreach (getenv() as $name => $value) {
            if (!array_key_exists($name, $this->processSnapshot)) {
                putenv($name);
            } elseif ($this->processSnapshot[$name] !== $value) {
                putenv("{$name}={$this->processSnapshot[$name]}");
            }
        }

        $_ENV = $this->envSnapshot;
        $_SERVER = $this->serverSnapshot;

        $this->removeDirectory($this->fixturesDir);
    }

    protected function createEnvFile(string $filename, string $content): string
    {
        $path = $this->fixturesDir . '/' . $filename;
        file_put_contents($path, ltrim($content));

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
