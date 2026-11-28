<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Security;

use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Exception\EncryptionException;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\EnvFileCipher;
use KaririCode\Dotenv\Security\KeyPair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvFileCipherTest extends TestCase
{
    private Encryptor $encryptor;
    private EnvFileCipher $cipher;
    private DotenvParser $parser;

    protected function setUp(): void
    {
        $this->encryptor = new Encryptor(KeyPair::generate()->privateKey);
        $this->cipher = new EnvFileCipher($this->encryptor);
        $this->parser = new DotenvParser();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function assignments(): iterable
    {
        yield 'plain' => ['V=hello'];
        yield 'single quoted dollar' => ["V='pa\$word'"];
        yield 'escaped quotes and dollar' => ['V="say \"hi\" \$HOME"'];
        yield 'inline comment' => ['V=abc # note'];
        yield 'empty' => ['V='];
        yield 'backslashes' => ['V="C:\\\\path\\\\to"'];
        yield 'single quote inside' => ['V="it\'s"'];
        yield 'multiline pem' => ["V=\"-----BEGIN-----\nMIIE==\n-----END-----\""];
        yield 'escaped newline' => ['V="a\nb"'];
        yield 'spaces kept' => ["V='  padded  '"];
    }

    #[DataProvider('assignments')]
    public function testEncryptingNeverChangesWhatTheApplicationLoads(string $assignment): void
    {
        $original = $this->parser->parse($assignment)['V'];

        $encrypted = $this->cipher->encrypt($assignment)->content;
        $loaded = $this->encryptor->decrypt($this->parser->parse($encrypted)['V']);

        self::assertSame($original, $loaded);
    }

    #[DataProvider('assignments')]
    public function testDecryptRestoresAnEquivalentFile(string $assignment): void
    {
        $original = $this->parser->parse($assignment)['V'];

        $restored = $this->cipher->decrypt($this->cipher->encrypt($assignment)->content)->content;

        self::assertSame($original, $this->parser->parse($restored)['V']);
    }

    public function testValuesWithReferencesAreSkipped(): void
    {
        $result = $this->cipher->encrypt("DSN=\"pgsql:host=\${DB_HOST}\"\nRAW=\$HOME\nLIT='\$HOME'");

        self::assertSame(['LIT'], $result->changed);
        self::assertSame(
            ['DSN' => 'contains a variable reference', 'RAW' => 'contains a variable reference'],
            $result->skipped,
        );
        self::assertStringContainsString('DSN="pgsql:host=${DB_HOST}"', $result->content);
    }

    public function testAlreadyEncryptedValuesAreNotEncryptedTwice(): void
    {
        $once = $this->cipher->encrypt('A=secret')->content;

        $twice = $this->cipher->encrypt($once);

        self::assertSame($once, $twice->content);
        self::assertSame([], $twice->changed);
    }

    public function testPreservesCommentsBlankLinesAndExportPrefix(): void
    {
        $content = "# database\n\nexport DB_PASSWORD=secret # rotated monthly\n";

        $encrypted = $this->cipher->encrypt($content)->content;

        self::assertStringStartsWith("# database\n\nexport DB_PASSWORD=\"encrypted:", $encrypted);
        self::assertStringEndsWith("\" # rotated monthly\n", $encrypted);
    }

    public function testMultilineSecretBodyDisappearsFromTheFile(): void
    {
        $encrypted = $this->cipher->encrypt("KEY=\"-----BEGIN-----\nsecretbody==\n-----END-----\"\nNEXT=1")->content;

        self::assertStringNotContainsString('secretbody', $encrypted);
        self::assertSame(2, substr_count($encrypted, "\n") + 1);
    }

    public function testWrongKeyFailsWithSemanticException(): void
    {
        $encrypted = $this->cipher->encrypt('A=secret')->content;

        $this->expectException(EncryptionException::class);

        new EnvFileCipher(new Encryptor(KeyPair::generate()->privateKey))->decrypt($encrypted);
    }
}
