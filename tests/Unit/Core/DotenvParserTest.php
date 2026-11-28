<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Core;

use KaririCode\Dotenv\Core\DotenvParser;
use KaririCode\Dotenv\Core\InterpolationContext;
use KaririCode\Dotenv\Exception\ParseException;
use PHPUnit\Framework\TestCase;

final class DotenvParserTest extends TestCase
{
    private DotenvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DotenvParser();
    }

    // ── Basic Parsing ────────────────────────────────────────────────────

    public function testParsesSimpleKeyValue(): void
    {
        $result = $this->parser->parse("FOO=bar\nBAZ=qux");

        self::assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testSkipsEmptyLines(): void
    {
        $result = $this->parser->parse("FOO=bar\n\n\nBAZ=qux\n");

        self::assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testSkipsCommentLines(): void
    {
        $result = $this->parser->parse("# This is a comment\nFOO=bar\n# Another comment\nBAZ=qux");

        self::assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testStripsExportPrefix(): void
    {
        $result = $this->parser->parse("export FOO=bar\nexport BAZ=qux");

        self::assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testEmptyValueForBareKey(): void
    {
        $result = $this->parser->parse('FOO=');

        self::assertSame(['FOO' => ''], $result);
    }

    // ── Quoted Values ────────────────────────────────────────────────────

    public function testParsesDoubleQuotedValues(): void
    {
        $result = $this->parser->parse('FOO="hello world"');

        self::assertSame(['FOO' => 'hello world'], $result);
    }

    public function testParsesSingleQuotedValues(): void
    {
        $result = $this->parser->parse("FOO='hello world'");

        self::assertSame(['FOO' => 'hello world'], $result);
    }

    public function testSingleQuotesAreRawLiteral(): void
    {
        $result = $this->parser->parse("FOO='hello \$BAR \${BAZ}'");

        self::assertSame(['FOO' => 'hello $BAR ${BAZ}'], $result);
    }

    public function testDoubleQuotedEscapeSequences(): void
    {
        $result = $this->parser->parse('FOO="hello\nworld\ttab"');

        self::assertSame(['FOO' => "hello\nworld\ttab"], $result);
    }

    public function testDoubleQuotedEscapedQuote(): void
    {
        $result = $this->parser->parse('FOO="say \"hello\""');

        self::assertSame(['FOO' => 'say "hello"'], $result);
    }

    public function testUnterminatedDoubleQuoteThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unterminated');

        $this->parser->parse('FOO="unterminated');
    }

    public function testUnterminatedSingleQuoteThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unterminated');

        $this->parser->parse("FOO='unterminated");
    }

    // ── Inline Comments ──────────────────────────────────────────────────

    public function testStripsInlineComments(): void
    {
        $result = $this->parser->parse('FOO=bar # this is a comment');

        self::assertSame(['FOO' => 'bar'], $result);
    }

    public function testHashInsideQuotesIsNotComment(): void
    {
        $result = $this->parser->parse('FOO="bar # not a comment"');

        self::assertSame(['FOO' => 'bar # not a comment'], $result);
    }

    // ── Variable Interpolation ───────────────────────────────────────────

    public function testExpandsBraceVariables(): void
    {
        $result = $this->parser->parse("FOO=hello\nBAR=\${FOO} world");

        self::assertSame(['FOO' => 'hello', 'BAR' => 'hello world'], $result);
    }

    public function testExpandsBareVariables(): void
    {
        $result = $this->parser->parse("FOO=hello\nBAR=\$FOO world");

        self::assertSame(['FOO' => 'hello', 'BAR' => 'hello world'], $result);
    }

    public function testExpandsVariablesInDoubleQuotes(): void
    {
        $result = $this->parser->parse("APP=KaririCode\nGREET=\"Welcome to \${APP}\"");

        self::assertSame(['APP' => 'KaririCode', 'GREET' => 'Welcome to KaririCode'], $result);
    }

    public function testDefaultValueSyntax(): void
    {
        $result = $this->parser->parse('FOO=${UNDEFINED:-fallback}');

        self::assertSame(['FOO' => 'fallback'], $result);
    }

    public function testDefaultValueIgnoredWhenDefined(): void
    {
        $result = $this->parser->parse("VAR=hello\nFOO=\${VAR:-fallback}");

        self::assertSame(['VAR' => 'hello', 'FOO' => 'hello'], $result);
    }

    public function testAlternateSyntaxUsesAlternateWhenDefined(): void
    {
        $result = $this->parser->parse("HOST=redis.local\nHAS_CACHE=\${HOST:+yes}");

        self::assertSame([
            'HOST' => 'redis.local',
            'HAS_CACHE' => 'yes',
        ], $result);
    }

    public function testAlternateSyntaxReturnsEmptyWhenUndefined(): void
    {
        $result = $this->parser->parse('CACHE=${UNDEFINED_HOST:+redis://fallback}');

        self::assertSame(['CACHE' => ''], $result);
    }

    public function testAlternateSyntaxReturnsEmptyWhenEmpty(): void
    {
        $result = $this->parser->parse("HOST=\nHAS_CACHE=\${HOST:+yes}");

        self::assertSame(['HOST' => '', 'HAS_CACHE' => ''], $result);
    }

    public function testUndefinedVariableResolvesToEmpty(): void
    {
        $result = $this->parser->parse('FOO=$NONEXISTENT');

        self::assertSame(['FOO' => ''], $result);
    }

    // ── Multiline Values ─────────────────────────────────────────────────

    public function testMultilineDoubleQuotedValue(): void
    {
        $content = "FOO=\"line1\nline2\nline3\"";
        $result = $this->parser->parse($content);

        self::assertSame(['FOO' => "line1\nline2\nline3"], $result);
    }

    // ── Strict Name Validation ───────────────────────────────────────────

    public function testStrictNamesRejectsLowercase(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid variable name');

        $this->parser->parse('foo_bar=value', '.env', strictNames: true);
    }

    public function testStrictNamesAcceptsUppercase(): void
    {
        $result = $this->parser->parse('FOO_BAR=value', '.env', strictNames: true);

        self::assertSame(['FOO_BAR' => 'value'], $result);
    }

    public function testNonStrictAcceptsLowercase(): void
    {
        $result = $this->parser->parse('foo_bar=value', '.env', strictNames: false);

        self::assertSame(['foo_bar' => 'value'], $result);
    }

    // ── Edge Cases ───────────────────────────────────────────────────────

    public function testEmptyContent(): void
    {
        $result = $this->parser->parse('');

        self::assertSame([], $result);
    }

    public function testCommentOnlyContent(): void
    {
        $result = $this->parser->parse("# Just comments\n# Nothing else");

        self::assertSame([], $result);
    }

    public function testValueWithEqualsSign(): void
    {
        $result = $this->parser->parse('CONNECTION=postgresql://user:pass@host/db?sslmode=require'); // secret-scan:allow

        self::assertSame(['CONNECTION' => 'postgresql://user:pass@host/db?sslmode=require'], $result); // secret-scan:allow
    }

    public function testWindowsLineEndings(): void
    {
        $result = $this->parser->parse("FOO=bar\r\nBAZ=qux\r\n");

        self::assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    public function testValueWithSpacesUnquoted(): void
    {
        $result = $this->parser->parse('FOO=hello world');

        self::assertSame(['FOO' => 'hello world'], $result);
    }

    public function testEscapedDollarInDoubleQuotes(): void
    {
        $result = $this->parser->parse('PRICE="\\$100"');

        self::assertSame(['PRICE' => '$100'], $result);
    }

    // ── Regression: Escaping & Single-Pass Expansion ─────────────────────

    public function testEscapedDollarInDoubleQuotesIsNeverInterpolated(): void
    {
        $result = $this->parser->parse("HOME_DIR=/root\nPRICE=\"costs \\\$HOME_DIR\"");

        self::assertSame('costs $HOME_DIR', $result['PRICE']);
    }

    public function testReferencedValueContainingDollarIsInsertedVerbatim(): void
    {
        $result = $this->parser->parse("PASSWORD='pa\$word'\nDSN=\"user:\${PASSWORD}@host\"");

        self::assertSame('user:pa$word@host', $result['DSN']);
    }

    public function testDefaultOperandIsLiteralText(): void
    {
        $result = $this->parser->parse("HOME_DIR=/root\nA=\${UNSET_KC:-\$HOME_DIR}\nB=\${UNSET_KC:-\${HOME_DIR}}");

        self::assertSame('$HOME_DIR', $result['A']);
        self::assertSame('${HOME_DIR}', $result['B']);
    }

    public function testContentContainingThePlaceholderTokenIsSafe(): void
    {
        $result = $this->parser->parse("RAW='\0kc-dollar\0'\nA=\"p\\\$q\"");

        self::assertSame("\0kc-dollar\0", $result['RAW']);
        self::assertSame('p$q', $result['A']);
    }

    // ── Interpolation Context ────────────────────────────────────────────

    public function testLockedValuesWinOverSameFileDefinitions(): void
    {
        $context = new InterpolationContext(locked: ['DB_HOST' => 'real-host']);

        $result = $this->parser->parse("DB_HOST=file-host\nDSN=pgsql:host=\${DB_HOST}", context: $context);

        self::assertSame('file-host', $result['DB_HOST']);
        self::assertSame('pgsql:host=real-host', $result['DSN']);
    }

    public function testInheritedValuesAreVisibleButSameFileWins(): void
    {
        $context = new InterpolationContext(inherited: ['APP_NAME' => 'Kariri', 'REGION' => 'eu']);

        $result = $this->parser->parse("REGION=us\nLABEL=\"\${APP_NAME}-\${REGION}\"", context: $context);

        self::assertSame('Kariri-us', $result['LABEL']);
    }

    public function testValueFilterAppliesToSameFileReferencesOnly(): void
    {
        $context = new InterpolationContext(valueFilter: static fn(string $v): string => strtoupper($v));

        $result = $this->parser->parse("SECRET=abc\nURL=\"x:\${SECRET}\"", context: $context);

        self::assertSame('abc', $result['SECRET']);
        self::assertSame('x:ABC', $result['URL']);
    }
}
