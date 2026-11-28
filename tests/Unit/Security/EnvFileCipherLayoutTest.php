<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Security;

use KaririCode\Dotenv\Exception\ParseException;
use KaririCode\Dotenv\Security\Encryptor;
use KaririCode\Dotenv\Security\EnvFileCipher;
use KaririCode\Dotenv\Security\KeyPair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The text EnvFileCipher writes: decryption is deterministic, so the
 * quoting, the comments and the layout it produces are pinned exactly.
 */
final class EnvFileCipherLayoutTest extends TestCase
{
    private Encryptor $encryptor;

    private EnvFileCipher $cipher;

    protected function setUp(): void
    {
        $this->encryptor = new Encryptor(KeyPair::generate()->privateKey);
        $this->cipher = new EnvFileCipher($this->encryptor);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plaintexts(): iterable
    {
        yield 'plain' => ['plain', "A='plain'"];
        yield 'quote and backslash without a single quote' => ['a"b\c$d', "A='a\"b\\c\$d'"];
        yield 'single quote' => ["it's", "A=\"it's\""];
        yield 'line feed' => ["one\ntwo", 'A="one\ntwo"'];
        yield 'carriage return' => ["cr\r", 'A="cr\r"'];
        yield 'everything' => ["it's \"x\" \\ \$y", 'A="it\'s \"x\" \\\\ \$y"'];
    }

    #[Test]
    #[DataProvider('plaintexts')]
    public function decryptWritesTheSimplestQuotingThatReadsBack(string $plaintext, string $expected): void
    {
        $content = 'A="' . $this->encryptor->encrypt($plaintext) . '"';

        self::assertSame($expected, $this->cipher->decrypt($content)->content);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function commentedLines(): iterable
    {
        yield 'unquoted' => ['A=value # note', "A='value' # note"];
        yield 'single quoted' => ["A='x' # note", "A='x' # note"];
        yield 'empty single quoted' => ["A='' # note", "A='' # note"];
        yield 'empty double quoted' => ['A="" # note', "A='' # note"];
        yield 'double quoted with a hash inside' => ['A="a # b" # note', "A='a # b' # note"];
        yield 'double quoted with an escaped quote' => ['A="a\"b" # note', "A='a\"b' # note"];
        yield 'no space before the hash' => ['A=value#kept', "A='value#kept'"];
        yield 'exported' => ['export A=value # note', "export A='value' # note"];
    }

    #[Test]
    #[DataProvider('commentedLines')]
    public function aRoundTripKeepsTheTrailingComment(string $line, string $expected): void
    {
        $encrypted = $this->cipher->encrypt($line)->content;

        self::assertSame($expected, $this->cipher->decrypt($encrypted)->content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function references(): iterable
    {
        yield 'unquoted bare' => ['A=$B'];
        yield 'unquoted braced' => ['A=${B}'];
        yield 'double quoted at the start' => ['A="$B"'];
        yield 'double quoted one letter' => ['A="x$B"'];
        yield 'after an escaped quote' => ['A="\"$B"'];
    }

    #[Test]
    #[DataProvider('references')]
    public function valuesThatInterpolateAreLeftAsWritten(string $line): void
    {
        $result = $this->cipher->encrypt($line);

        self::assertSame($line, $result->content);
        self::assertSame(['A' => 'contains a variable reference'], $result->skipped);
        self::assertSame([], $result->changed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function literalDollars(): iterable
    {
        yield 'single quoted' => ["A='\$B'"];
        yield 'escaped in double quotes' => ['A="\$B"'];
        yield 'dollar before a digit' => ['A="$1"'];
        yield 'trailing dollar' => ['A=price$'];
        yield 'dollar in a comment' => ['A=x # $B'];
        yield 'dollar after the closing quote' => ['A="x" # $B'];
    }

    #[Test]
    #[DataProvider('literalDollars')]
    public function aDollarThatDoesNotInterpolateIsEncrypted(string $line): void
    {
        self::assertSame(['A'], $this->cipher->encrypt($line)->changed);
    }

    #[Test]
    public function everyEntryAfterAnUnchangedOneIsStillRewritten(): void
    {
        $content = "PLAIN=keep\nSECRET=\"" . $this->encryptor->encrypt('s3cr3t') . "\"\nOTHER=\"" . $this->encryptor->encrypt('two') . '"';

        $result = $this->cipher->decrypt($content);

        self::assertSame("PLAIN=keep\nSECRET='s3cr3t'\nOTHER='two'", $result->content);
        self::assertSame(['SECRET', 'OTHER'], $result->changed);
    }

    #[Test]
    public function anUnterminatedDoubleQuoteIsAParseError(): void
    {
        $this->expectException(ParseException::class);

        $this->cipher->encrypt('A="abc');
    }
}
