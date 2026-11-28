<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Type;

use KaririCode\Dotenv\Contract\TypeCaster;
use KaririCode\Dotenv\Contract\TypeDetector;
use KaririCode\Dotenv\Enum\ValueType;
use KaririCode\Dotenv\Type\TypeSystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Detectors run by priority, highest first, whenever they were added; the
 * string fallback never reaches a caster (ADR-003).
 */
final class TypeSystemOrderTest extends TestCase
{
    #[Test]
    public function aDetectorAddedAfterADetectionStillRunsByPriority(): void
    {
        $types = new TypeSystem();
        self::assertSame(ValueType::Boolean, $types->detect('true'));

        $types->addDetector(self::claiming(ValueType::Json, 300));

        self::assertSame(ValueType::Json, $types->detect('true'));
    }

    #[Test]
    public function aHigherPriorityWinsWhateverTheInsertionOrder(): void
    {
        $types = new TypeSystem();
        $types->addDetector(self::claiming(ValueType::Integer, 100));
        $types->addDetector(self::claiming(ValueType::Json, 300));

        self::assertSame(ValueType::Json, $types->detect('anything'));
    }

    #[Test]
    public function aLowerPriorityRunsAfterTheDefaults(): void
    {
        $types = new TypeSystem();
        $types->addDetector(self::claiming(ValueType::Json, 100));

        self::assertSame(ValueType::Integer, $types->detect('42'));
        self::assertSame(ValueType::Json, $types->detect('plain text'));
    }

    #[Test]
    public function theStringFallbackNeverReachesACaster(): void
    {
        $types = new TypeSystem();
        $types->addCaster(ValueType::String, new class implements TypeCaster {
            #[\Override]
            public function cast(string $value): mixed
            {
                return 'cast:' . $value;
            }
        });

        self::assertSame('plain', $types->cast('plain', ValueType::String));
        self::assertSame('plain', $types->resolve('plain'));
    }

    private static function claiming(ValueType $type, int $priority): TypeDetector
    {
        return new readonly class ($type, $priority) implements TypeDetector {
            public function __construct(
                private ValueType $type,
                private int $level,
            ) {}

            #[\Override]
            public function priority(): int
            {
                return $this->level;
            }

            #[\Override]
            public function detect(string $value): ValueType
            {
                return $this->type;
            }
        };
    }
}
