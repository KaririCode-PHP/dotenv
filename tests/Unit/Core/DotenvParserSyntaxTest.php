<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Core;

use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Core\InterpolationContext;
use KaririCode\Dotenv\Exception\ParseException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The corners of SPEC-002: whitespace around names and values, every
 * escape of a double-quoted value, line endings, and the placeholder that
 * keeps an escaped dollar literal.
 */
final class DotenvParserSyntaxTest extends TestCase
{
    private DotenvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DotenvParser();
    }

    #[Test]
    public function indentedLinesAndCommentsAreRead(): void
    {
        self::assertSame(['KEY' => 'v'], $this->parser->parse("  # indented comment\n\t KEY=v"));
    }

    #[Test]
    public function whitespaceAroundTheNameAndTheValueIsDropped(): void
    {
        self::assertSame(
            ['KEY' => 'value', 'QUOTED' => 'a b'],
            $this->parser->parse("KEY   =   value\nQUOTED =   \"a b\""),
        );
    }

    #[Test]
    public function exportTakesAnyRunOfSpacesBeforeTheName(): void
    {
        self::assertSame(['KEY' => 'v'], $this->parser->parse('export    KEY=v'));
    }

    #[Test]
    public function everyEscapeOfADoubleQuotedValueIsDecoded(): void
    {
        self::assertSame(
            ['A' => "tab\tcr\rlf\nbs\\dq\"dollar\$x unknown\\q"],
            $this->parser->parse('A="tab\tcr\rlf\nbs\\\\dq\"dollar\$x unknown\q"'),
        );
    }

    #[Test]
    public function anEscapeAtTheEndOfAContinuedLineIsDecoded(): void
    {
        self::assertSame(['A' => "x\t\ny"], $this->parser->parse("A=\"x\\t\ny\""));
    }

    #[Test]
    public function aBackslashThatEndsALineStaysLiteral(): void
    {
        self::assertSame(['A' => "abc\\\ndef"], $this->parser->parse("A=\"abc\\\ndef\""));
    }

    #[Test]
    public function anInlineCommentNeedsWhitespaceBeforeTheHash(): void
    {
        self::assertSame(
            ['A' => 'value', 'B' => 'value#kept', 'C' => '#kept'],
            $this->parser->parse("A=value \t # comment # more\nB=value#kept\nC=#kept"),
        );
    }

    #[Test]
    public function windowsAndClassicMacLineEndingsAreNormalized(): void
    {
        self::assertSame(['A' => '1', 'B' => "x\ny"], $this->parser->parse("A=1\r\nB=\"x\r\ny\"\r\n"));
        self::assertSame(['A' => '1', 'B' => '2'], $this->parser->parse("A=1\rB=2"));
    }

    #[Test]
    public function errorsCountLinesAfterNormalizingLineEndings(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('at line 3 in ".env"');

        $this->parser->parse("A=1\r\nB=2\r\n3BAD=x");
    }

    #[Test]
    public function theEscapedDollarPlaceholderNeverCollidesWithTheContent(): void
    {
        $content = "A=\"\\\$x\"\nB=x\0kc-dollar\0y\nC=x\0kc-dollar-1\0y";

        self::assertSame(
            ['A' => '$x', 'B' => "x\0kc-dollar\0y", 'C' => "x\0kc-dollar-1\0y"],
            $this->parser->parse($content),
        );
    }

    #[Test]
    public function aProcessReferencePrefersEnvOverServer(): void
    {
        $_ENV['KC_PARSER_REF'] = 'from-env';
        $_SERVER['KC_PARSER_REF'] = 'from-server';
        $_SERVER['KC_PARSER_SERVER_ONLY'] = 'server';

        try {
            self::assertSame(
                ['A' => 'from-env', 'B' => 'server'],
                $this->parser->parse("A=\${KC_PARSER_REF}\nB=\$KC_PARSER_SERVER_ONLY"),
            );
        } finally {
            unset($_ENV['KC_PARSER_REF'], $_SERVER['KC_PARSER_REF'], $_SERVER['KC_PARSER_SERVER_ONLY']);
        }
    }

    #[Test]
    public function aLockedValueWinsOverASameFileDefinition(): void
    {
        $context = new InterpolationContext(inherited: ['B' => 'inherited'], locked: ['A' => 'locked']);

        self::assertSame(
            ['A' => 'file', 'REF' => 'locked', 'REF_B' => 'inherited'],
            $this->parser->parse(content: "A=file\nREF=\$A\nREF_B=\${B}", context: $context),
        );
    }

    #[Test]
    public function aNonStringProcessValueIsNotInterpolated(): void
    {
        $_SERVER['KC_PARSER_NUMBER'] = 42;

        try {
            self::assertSame(['A' => 'x'], $this->parser->parse('A=x$KC_PARSER_NUMBER'));
        } finally {
            unset($_SERVER['KC_PARSER_NUMBER']);
        }
    }
}
