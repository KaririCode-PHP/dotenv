<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Core;

use KaririCode\Dotenv\Core\EnvEntry;
use KaririCode\Dotenv\Core\EnvFileScanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Where an entry ends: the scanner keeps a double-quoted value that spans
 * lines in one entry by the parser's escape rule, and nothing more.
 */
final class EnvFileScannerLayoutTest extends TestCase
{
    #[Test]
    public function aQuoteAfterSpacesStillOpensAMultilineValue(): void
    {
        self::assertSame(
            ["A=  \"x\ny\"", 'B=1'],
            $this->texts("A=  \"x\ny\"\nB=1"),
        );
    }

    #[Test]
    public function anEmptyQuotedValueClosesOnItsLine(): void
    {
        self::assertSame(['A=""', 'B=1'], $this->texts("A=\"\"\nB=1"));
    }

    #[Test]
    public function anEscapeBeforeTheClosingQuoteDoesNotEndTheScan(): void
    {
        self::assertSame(['A="a\nb"', 'B=1'], $this->texts("A=\"a\\nb\"\nB=1"));
    }

    #[Test]
    public function anEscapedQuoteAtTheEndOfALineContinuesTheValue(): void
    {
        self::assertSame(["A=\"abc\\\"\nmore\"", 'B=1'], $this->texts("A=\"abc\\\"\nmore\"\nB=1"));
    }

    #[Test]
    public function onlyAnExportPrefixMarksAnEntryExported(): void
    {
        $entries = new EnvFileScanner()->scan("export A=1\nB=2");

        self::assertTrue($entries[0]->exported);
        self::assertFalse($entries[1]->exported);
        self::assertSame(['A', 'B'], [$entries[0]->name, $entries[1]->name]);
        self::assertSame(['1', '2'], [$entries[0]->rawValue, $entries[1]->rawValue]);
    }

    #[Test]
    public function blankLinesCommentsAndTextAreVerbatimEntries(): void
    {
        $entries = new EnvFileScanner()->scan("   \n  # KEY=commented\nnot an assignment");

        self::assertSame(['   ', '  # KEY=commented', 'not an assignment'], array_map(static fn(EnvEntry $entry): string => $entry->text, $entries));
        self::assertSame([null, null, null], array_map(static fn(EnvEntry $entry): ?string => $entry->name, $entries));
    }

    /** @return list<string> */
    private function texts(string $content): array
    {
        return array_map(static fn(EnvEntry $entry): string => $entry->text, new EnvFileScanner()->scan($content));
    }
}
