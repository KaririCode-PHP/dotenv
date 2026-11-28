<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Integration;

use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Dotenv;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;

/**
 * Runs bin/kariricode-dotenv as a real process and checks the documented
 * behaviour (docs/spec/SPEC-008-cli.md): output, files and exit codes.
 */
final class CliTest extends IntegrationTestCase
{
    public function testKeygenPrintsKeyIdAndPrivateKey(): void
    {
        [$code, $out] = $this->runCli(['keygen']);

        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/^Key ID:\s+[0-9a-f]{8}$/m', $out);
        self::assertMatchesRegularExpression('/^DOTENV_PRIVATE_KEY=\s+[0-9a-f]{64}$/m', $out);
    }

    public function testEncryptWithOutputLeavesInputUntouchedAndDecryptRestoresValues(): void
    {
        $original = "PASS='pa\$word'\nNOTE=abc # keep me\nMULTI=\"line1\nline2\"\n";
        $this->createEnvFile('.env', $original);
        $key = $this->generateKey();

        [$code, $out] = $this->runCli(['encrypt', '.env', "--key={$key}", '--output=.env.enc']);
        self::assertSame(0, $code);
        self::assertStringContainsString('✓ Encrypted 3 value(s) in .env.enc', $out);
        self::assertSame($original, file_get_contents($this->fixturesDir . '/.env'));

        [$code] = $this->runCli(['decrypt', '.env.enc', "--key={$key}", '--output=.env.dec']);
        self::assertSame(0, $code);

        $parser = new DotenvParser();
        self::assertSame(
            $parser->parse($original),
            $parser->parse((string) file_get_contents($this->fixturesDir . '/.env.dec')),
        );
    }

    public function testDecryptWithWrongKeyExitsOneWithACleanMessage(): void
    {
        $this->createEnvFile('.env', 'A=secret');
        $this->runCli(['encrypt', '.env', '--key=' . $this->generateKey()]);

        [$code, $out, $err] = $this->runCli(['decrypt', '.env', '--key=' . $this->generateKey()]);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame("✗ Decryption failed — wrong key or corrupted payload.\n", $err);
    }

    public function testExampleGenerateNeverCopiesMultilineBodies(): void
    {
        $this->createEnvFile('.env', "# keys\nAPP=demo\nRSA_KEY=\"-----BEGIN-----\nsecretbody==\n-----END-----\"\n");

        [$code] = $this->runCli(['example:generate', '.env', '--output=.env.example']);

        self::assertSame(0, $code);
        self::assertSame("# keys\nAPP=\nRSA_KEY=\n", file_get_contents($this->fixturesDir . '/.env.example'));
    }

    public function testDebugMasksEncryptedValuesUnlessRevealed(): void
    {
        $key = $this->generateKey();
        $this->createEnvFile('.env', "SECRET=hunter2\nPLAIN=visible");
        $this->runCli(['encrypt', '.env', "--key={$key}"]);
        $this->createEnvFile('.env.local', 'PLAIN=visible');

        [$code, $out] = $this->runCli(['debug'], ['DOTENV_PRIVATE_KEY' => $key]);
        self::assertSame(0, $code);
        self::assertMatchesRegularExpression('/^SECRET\s+String\s+\.env\s+\[encrypted\]$/m', $out);
        self::assertStringNotContainsString('hunter2', $out);

        [, $revealed] = $this->runCli(['debug', '--reveal'], ['DOTENV_PRIVATE_KEY' => $key]);
        self::assertStringContainsString("'hunter2'", $revealed);
    }

    public function testValidateWithSchemaListsEveryError(): void
    {
        $this->createEnvFile('.env', 'DB_PORT=abc');
        $this->createEnvFile('.env.schema', "[DB_HOST]\nrequired = true\n\n[DB_PORT]\ntype = integer\n");

        [$code, , $err] = $this->runCli(['validate', '--schema=.env.schema']);

        self::assertSame(1, $code);
        self::assertSame(
            "✗ Schema validation failed:\n  - DB_HOST is required but not defined.\n  - DB_PORT must be an integer.\n",
            $err,
        );
    }

    public function testValidateAcceptsVariablesInjectedByTheProcessWithoutEnvSuperglobal(): void
    {
        $this->createEnvFile('.env', 'APP_NAME=demo');
        $this->createEnvFile('.env.example', "APP_NAME=\nDB_PASSWORD=");

        [$code, $out, $err] = $this->runCli(
            ['validate'],
            ['DB_PASSWORD' => 'from-orchestrator'],
            ['-d', 'variables_order=GPCS'],
        );

        self::assertSame(0, $code, $err);
        self::assertStringContainsString('All variables from .env.example are defined', $out);
    }

    public function testValidateListsEveryMissingVariable(): void
    {
        $this->createEnvFile('.env', 'APP_NAME=demo');
        $this->createEnvFile('.env.example', "APP_NAME=\nDB_HOST=\nDB_PASSWORD=");

        [$code, , $err] = $this->runCli(['validate']);

        self::assertSame(1, $code);
        self::assertStringContainsString('DB_HOST is required but not defined.', $err);
        self::assertStringContainsString('DB_PASSWORD is required but not defined.', $err);
    }

    public function testCacheDumpReportsEnvironmentResolvedFromProcess(): void
    {
        $this->createEnvFile('.env', 'DB_HOST=base');
        $this->createEnvFile('.env.production', 'DB_HOST=prod-host');

        [$code, $out] = $this->runCli(['cache:dump'], ['APP_ENV' => 'production']);
        self::assertSame(0, $code);
        self::assertStringContainsString('environment: production', $out);

        $_ENV['APP_ENV'] = 'production';
        $app = new Dotenv($this->fixturesDir, new DotenvConfiguration(
            cachePath: $this->fixturesDir . '/.env.cache.php',
        ));
        $app->bootEnv();

        self::assertSame('production', $app->environment());
        self::assertSame('prod-host', $app->get('DB_HOST'));
        self::assertSame('cache', $app->debug()['DB_HOST']['source']);
    }

    public function testCacheDumpIsUsedByAnApplicationCallingBootEnv(): void
    {
        $this->createEnvFile('.env', 'DB_HOST=base');
        $this->createEnvFile('.env.production', 'DB_HOST=prod-host');

        [$code, $out] = $this->runCli(['cache:dump', '--env=production']);
        self::assertSame(0, $code);
        self::assertStringContainsString('environment: production', $out);

        $app = new Dotenv($this->fixturesDir, new DotenvConfiguration(
            cachePath: $this->fixturesDir . '/.env.cache.php',
            environmentName: 'production',
        ));
        $app->bootEnv();

        self::assertSame('prod-host', $app->get('DB_HOST'));
        self::assertSame('cache', $app->debug()['DB_HOST']['source']);
    }

    public function testDiffComparesParsedValues(): void
    {
        $this->createEnvFile('a.env', "A=\"x\"\nB=1\nONLY_A=1");
        $this->createEnvFile('b.env', "A=x\nB=2\nONLY_B=1");

        [$code, $out] = $this->runCli(['diff', 'a.env', 'b.env']);

        self::assertSame(0, $code);
        self::assertSame(
            "Only in a.env:\n  + ONLY_A\nOnly in b.env:\n  + ONLY_B\nDifferent values:\n  ~ B\n    a.env: 1\n    b.env: 2\n",
            $out,
        );
    }

    public function testUnknownCommandAndInvalidModeExitOne(): void
    {
        self::assertSame(1, $this->runCli(['nope'])[0]);

        [$code, , $err] = $this->runCli(['debug', '--mode=sometimes']);
        self::assertSame(1, $code);
        self::assertStringContainsString("Invalid value 'sometimes' for --mode", $err);
    }

    private function generateKey(): string
    {
        preg_match('/DOTENV_PRIVATE_KEY=\s+([0-9a-f]{64})/', $this->runCli(['keygen'])[1], $matches);

        return $matches[1];
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $env
     * @param list<string> $phpArguments Interpreter flags, e.g. ['-d', 'variables_order=GPCS'].
     *
     * @return array{0: int, 1: string, 2: string} [exit code, stdout, stderr]
     */
    private function runCli(array $arguments, array $env = [], array $phpArguments = []): array
    {
        $command = array_merge([PHP_BINARY], $phpArguments, [dirname(__DIR__, 2) . '/bin/kariricode-dotenv'], $arguments);
        $baseEnv = array_filter(
            getenv(),
            static fn (string $name): bool => !in_array($name, ['APP_ENV', 'DOTENV_PRIVATE_KEY'], true),
            ARRAY_FILTER_USE_KEY,
        );

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixturesDir,
            array_merge($baseEnv, $env),
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
