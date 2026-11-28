<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Conformance;

use KaririCode\Dotenv\Exception\EnvCode;
use KaririCode\Exception\ErrorCodeTable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EnvCode against the ecosystem registry of record, CODE-BLOCKS.md of
 * kariricode/exception, read from the installed package (ADR-010).
 */
#[CoversNothing]
final class CodeBlockRegistryTest extends TestCase
{
    private const string REGISTRY = __DIR__ . '/../../vendor/kariricode/exception/CODE-BLOCKS.md';

    private const int FIRST = 2700;

    private const int LAST = 2799;

    #[Test]
    public function envCodeIsAValidCodeTable(): void
    {
        $table = new ErrorCodeTable(EnvCode::class);

        self::assertSame('ENV', $table->prefix);
        self::assertSame([self::FIRST, self::LAST], [$table->block->first, $table->block->last]);
    }

    /**
     * The installed registry may predate the claim (QD-V433 open items):
     * the block is then free, and once listed it belongs to this package.
     */
    #[Test]
    public function theRegistryLeavesTheBlockToThisPackage(): void
    {
        $registry = file(self::REGISTRY, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($registry);
        $rows = 0;

        foreach ($registry as $line) {
            if (preg_match('/\A\| (\d{4})–(\d{4}) \| ([A-Z0-9—]+) \| ([^|]+?) \| (released|claimed|proposed|reserved) \|/u', $line, $row) !== 1) {
                continue;
            }

            ++$rows;
            $overlaps = (int) $row[1] <= self::LAST && (int) $row[2] >= self::FIRST;
            $ours = $row[4] === 'kariricode/dotenv';

            if ($ours) {
                self::assertSame([(string) self::FIRST, (string) self::LAST, 'ENV'], [$row[1], $row[2], $row[3]], $line);
            }

            self::assertFalse($overlaps && !$ours, 'block taken by ' . $row[4]);
            self::assertFalse(!$ours && $row[3] === 'ENV', 'prefix ENV taken by ' . $row[4]);
        }

        self::assertGreaterThan(30, $rows, 'the registry table was read');
    }

    #[Test]
    public function codesFollowTheDocumentedLayout(): void
    {
        $ranges = [
            '.env syntax' => [2700, 2709],
            'loading' => [2710, 2719],
            'configuration' => [2720, 2729],
            'validation' => [2730, 2739],
            'type conversion' => [2740, 2749],
            'encryption' => [2750, 2759],
            'cache' => [2760, 2769],
        ];

        foreach (EnvCode::cases() as $case) {
            $inRange = array_filter($ranges, static fn(array $range): bool => $case->value >= $range[0] && $case->value <= $range[1]);
            self::assertCount(1, $inRange, $case->name . ' sits in a documented range');
        }
    }
}
