<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Core;

use KaririCode\Dotenv\Core\EnvFileScanner;
use PHPUnit\Framework\TestCase;

final class EnvFileScannerTest extends TestCase
{
    public function testKeepsCommentsAndBlankLinesVerbatim(): void
    {
        $entries = new EnvFileScanner()->scan("# header\n\nFOO=bar");

        self::assertCount(3, $entries);
        self::assertFalse($entries[0]->isAssignment());
        self::assertSame('# header', $entries[0]->text);
        self::assertSame('', $entries[1]->text);
        self::assertSame('FOO', $entries[2]->name);
        self::assertSame('bar', $entries[2]->rawValue);
    }

    public function testMultilineDoubleQuotedValueIsOneEntry(): void
    {
        $content = "KEY=\"-----BEGIN-----\nbody==\n-----END-----\"\nNEXT=1";
        $entries = new EnvFileScanner()->scan($content);

        self::assertCount(2, $entries);
        self::assertSame('KEY', $entries[0]->name);
        self::assertSame("KEY=\"-----BEGIN-----\nbody==\n-----END-----\"", $entries[0]->text);
        self::assertSame('NEXT', $entries[1]->name);
    }

    public function testEscapedQuoteDoesNotCloseTheValue(): void
    {
        $entries = new EnvFileScanner()->scan("A=\"say \\\"hi\nthere\"\nB=2");

        self::assertCount(2, $entries);
        self::assertSame("A=\"say \\\"hi\nthere\"", $entries[0]->text);
    }

    public function testRecognizesExportPrefix(): void
    {
        $entry = new EnvFileScanner()->scan('export TOKEN="x"')[0];

        self::assertTrue($entry->exported);
        self::assertSame('TOKEN', $entry->name);
        self::assertSame('"x"', $entry->rawValue);
    }

    public function testNonAssignmentLinesAreVerbatim(): void
    {
        $entries = new EnvFileScanner()->scan("BARE_NAME\nnot an assignment");

        self::assertFalse($entries[0]->isAssignment());
        self::assertFalse($entries[1]->isAssignment());
    }

    public function testNormalizesLineEndings(): void
    {
        $entries = new EnvFileScanner()->scan("A=1\r\nB=2\rC=3");

        self::assertSame(['A', 'B', 'C'], array_map(static fn($e) => $e->name, $entries));
    }
}
