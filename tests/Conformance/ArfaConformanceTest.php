<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Conformance;

use JsonException;
use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\ContainerFailure;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Protocol;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use KaririCode\Dotenv\Exception\DotenvException;
use KaririCode\Dotenv\Exception\EnvCode;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpToken;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Executable checks of the ARFA 1.3 and Spec V4.3.3 rules the package
 * claims, so conformance is measured rather than asserted in prose.
 */
#[CoversNothing]
final class ArfaConformanceTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    private const string SOURCE_NAMESPACE = 'KaririCode\\Dotenv\\';

    /** The one file of functions: the global `env()` helper, loaded by Composer's `files` autoload. */
    private const string FUNCTIONS_FILE = 'env.php';

    private const int ERROR_CODE_MIN = 2700;

    private const int ERROR_CODE_MAX = 2799;

    private const array FAMILIES = [Validation::class, NotFound::class, Configuration::class, Protocol::class, ContainerFailure::class, Runtime::class];

    /** The concrete exceptions of the package, each built by named constructors only. */
    private const array EXCEPTIONS = [
        'CacheException',
        'ConfigurationException',
        'ConversionException',
        'EncryptionException',
        'FileNotFoundException',
        'ImmutableException',
        'ParseException',
        'ValidationException',
    ];

    private const int MARKER_WIDTH = 72;

    /** Comment criteria C2–C5 of Spec V4.3.3 §17; C1 is the summary sentence that opens the docblock. */
    private const array COMMENT_MARKERS = ['Preconditions:', 'Thread-safety:', 'Side effects:', 'Example', 'References:'];

    private const array ADR_SECTIONS = [
        '## Status',
        '## Date',
        '## Context',
        '## Decision',
        '## Consequences',
        '## Alternatives Considered',
        '## References',
    ];

    /** Records of the 2026-09-29 review, which cited the Spec of its time. */
    private const array HISTORICAL_RECORDS = ['CHANGELOG.md', 'docs/CODE-REVIEW.md'];

    /** The eight gates of Spec V4.3.3 §29.1. */
    private const array QUALITY_GATES = ['test', 'test-conformance', 'phpstan', 'psalm', 'cs-check', 'complexity', 'mutation', 'security'];

    /** Values, snapshots and configuration: immutable once built. */
    private const string VALUE_TYPES = '#^(ValueObject/|Cache/CacheSnapshot\.php\z|Core/(EnvEntry|InterpolationContext|ValueContext)\.php\z|Security/CipherResult\.php\z)#';

    // ── Files ────────────────────────────────────────────────────────────

    #[Test]
    public function everySourceFileIsStrictAndDeclaresExactlyOneNamedType(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $code, $relative);
            self::assertSame(1, self::namedTypeDeclarations($code), $relative);
            self::assertSame(basename($relative, '.php'), self::reflect($relative)->getShortName(), $relative);
        }
    }

    #[Test]
    public function theFunctionsFileDeclaresOnlyTheEnvHelper(): void
    {
        $code = self::document('src/' . self::FUNCTIONS_FILE);

        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $code);
        self::assertSame(0, self::namedTypeDeclarations($code));
        self::assertSame(1, preg_match_all('/^function \w+\(/m', $code));
        self::assertTrue(function_exists('KaririCode\\Dotenv\\env'));
        self::assertSame(0, preg_match('/^\s*static \$/m', $code), 'static variable');
    }

    #[Test]
    public function docblocksMeetTheFiveCommentCriteria(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            $doc = (string) self::reflect($relative)->getDocComment();
            $summary = trim((string) preg_replace('#^/\*\*\s*\*\s*#', '', $doc));

            self::assertNotSame('', $doc, $relative . ': C1 role');
            self::assertDoesNotMatchRegularExpression('/^(Represents|This (class|interface|enum)|Class|Interface)\b/', $summary, $relative . ': C1 states a role, not a restatement');

            foreach (self::COMMENT_MARKERS as $marker) {
                self::assertStringContainsString($marker, $doc, $relative);
            }

            self::assertDoesNotMatchRegularExpression('/@(package|author|license)\b/', $code, $relative);
        }
    }

    #[Test]
    public function sectionMarkersAreSeventyTwoColumnsWide(): void
    {
        $files = [...self::sourceFiles(), ...self::phpFiles(self::ROOT . '/tests'), 'bin/kariricode-dotenv' => self::document('bin/kariricode-dotenv')];

        foreach ($files as $relative => $code) {
            foreach (explode("\n", $code) as $number => $line) {
                if (str_starts_with(ltrim($line), '// ─')) {
                    self::assertSame(self::MARKER_WIDTH, mb_strlen(ltrim($line)), sprintf('%s:%d', $relative, $number + 1));
                }
            }
        }
    }

    // ── Types ────────────────────────────────────────────────────────────

    #[Test]
    public function concreteClassesAreFinal(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            $type = self::reflect($relative);

            if ($type->isInterface() || $type->isEnum() || $type->isAbstract()) {
                continue;
            }

            self::assertTrue($type->isFinal(), $relative);
        }
    }

    #[Test]
    public function valuesSnapshotsAndConfigurationAreFinalReadonly(): void
    {
        $checked = 0;

        foreach (self::sourceFiles() as $relative => $code) {
            if (preg_match(self::VALUE_TYPES, $relative) !== 1) {
                continue;
            }

            ++$checked;
            $type = self::reflect($relative);
            self::assertTrue($type->isFinal() && $type->isReadOnly(), $relative);
        }

        self::assertSame(7, $checked);
    }

    #[Test]
    public function readonlyClassesDeclareNoPropertyHooksPerErrataE040(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            $type = self::reflect($relative);

            if (!$type->isReadOnly()) {
                continue;
            }

            foreach ($type->getProperties() as $property) {
                self::assertFalse($property->hasHooks(), $relative . '::$' . $property->getName());
            }

            self::assertSame(1, self::countTokens($code, T_READONLY), $relative . ': readonly repeated inside a readonly class');
        }
    }

    #[Test]
    public function classConstantsAreTyped(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            foreach (self::reflect($relative)->getReflectionConstants() as $constant) {
                if ($constant->isEnumCase()) {
                    continue;
                }

                self::assertTrue($constant->hasType(), $relative . '::' . $constant->getName());
            }
        }
    }

    #[Test]
    public function inheritanceInsideThePackageIsAtMostTwoLevelsDeep(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            $depth = 1;

            for ($parent = self::reflect($relative)->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
                if (str_starts_with($parent->getName(), self::SOURCE_NAMESPACE)) {
                    ++$depth;
                }
            }

            self::assertLessThanOrEqual(2, $depth, $relative);
        }
    }

    #[Test]
    public function noTypeKeepsMutableStaticState(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            foreach (self::reflect($relative)->getProperties() as $property) {
                self::assertFalse($property->isStatic(), $relative . '::$' . $property->getName());
            }

            self::assertSame(0, preg_match('/^\s*static \$/m', $code), $relative . ': static variable');
        }
    }

    #[Test]
    public function namesAndMethodsAvoidTheForbiddenPatterns(): void
    {
        $forbiddenMethods = ['toarray', 'fromarray', 'jsonserialize', 'tojson', '__get', '__set', '__isset', '__unset', '__call', '__callstatic'];

        foreach (self::sourceFiles() as $relative => $code) {
            $type = self::reflect($relative);
            self::assertDoesNotMatchRegularExpression('/(Interface|Manager|Helper|Util|Utils)\z/', $type->getShortName(), $relative);
            self::assertNotContains('JsonSerializable', $type->getInterfaceNames(), $relative);

            foreach ($type->getMethods() as $method) {
                self::assertNotContains(strtolower($method->getName()), $forbiddenMethods, $relative);
            }
        }
    }

    /** Public data is read through properties, not get/set methods. */
    #[Test]
    public function publicMethodsAreNotAccessors(): void
    {
        foreach (self::sourceFiles() as $relative => $code) {
            $type = self::reflect($relative);

            foreach ($type->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $type->getName()) {
                    self::assertDoesNotMatchRegularExpression('/\A(get|set)[A-Z]/', $method->getName(), $relative . '::' . $method->getName());
                }
            }
        }
    }

    /** A pattern anchors its end with `\z`: `$` also matches before a trailing newline. */
    #[Test]
    public function regexesAnchorTheEndWithBackslashZ(): void
    {
        $patterns = 0;

        foreach ([...self::sourceFiles(), self::FUNCTIONS_FILE => self::document('src/' . self::FUNCTIONS_FILE)] as $relative => $code) {
            foreach (PhpToken::tokenize($code) as $token) {
                // Whole patterns only; the fragments of a pattern built at run time are left out.
                if ($token->is(T_CONSTANT_ENCAPSED_STRING) && preg_match("#\\A'/.*/[a-z]*'\\z#s", $token->text) === 1) {
                    ++$patterns;
                    self::assertDoesNotMatchRegularExpression("#\\$/[a-z]*'\\z#", $token->text, $relative);
                }
            }
        }

        self::assertGreaterThan(0, $patterns);
    }

    #[Test]
    public function callSitesWithThreeOrMoreArgumentsNameThem(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();
        $offenders = [];
        $checked = 0;
        $files = [...self::sourceFiles(), self::FUNCTIONS_FILE => self::document('src/' . self::FUNCTIONS_FILE), 'bin/kariricode-dotenv' => self::document('bin/kariricode-dotenv')];

        foreach ($files as $relative => $code) {
            foreach ($finder->findInstanceOf($parser->parse($code) ?? [], Node\Expr\CallLike::class) as $call) {
                if ($call->isFirstClassCallable() || count($call->getArgs()) < 3 || self::isVariadicFormatting($call)) {
                    continue;
                }

                ++$checked;

                foreach ($call->getArgs() as $argument) {
                    if ($argument->name === null && !$argument->unpack) {
                        $offenders[] = sprintf('%s:%d', $relative, $call->getStartLine());

                        break;
                    }
                }
            }
        }

        self::assertGreaterThan(0, $checked);
        self::assertSame([], $offenders, 'Spec V4.3.3 §30: name the arguments of every call with three or more.');
    }

    #[Test]
    public function contractsAreInterfaces(): void
    {
        $checked = 0;

        foreach (self::sourceFiles() as $relative => $code) {
            if (str_starts_with($relative, 'Contract/')) {
                ++$checked;
                self::assertTrue(self::reflect($relative)->isInterface(), $relative);
            }
        }

        self::assertSame(4, $checked);
    }

    /** ARFA 1.3 E-003: only the code table is backed, because getCode() reads its integer. */
    #[Test]
    public function enumsArePureUnlessAnExternalContractNeedsABackingType(): void
    {
        $checked = 0;

        foreach (self::sourceFiles() as $relative => $code) {
            $name = self::reflect($relative)->getName();

            if (enum_exists($name)) {
                ++$checked;
                $backed = $relative === 'Exception/EnvCode.php';
                self::assertSame($backed, (new \ReflectionEnum($name))->isBacked(), $relative . ': E-003');

                if ($backed) {
                    self::assertStringContainsString('E-003', $code, $relative . ': the justification is written down');
                }
            }
        }

        self::assertSame(3, $checked);
    }

    #[Test]
    public function everyDirectoryIsANamespaceSegment(): void
    {
        foreach (['src', 'tests'] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(self::ROOT . '/' . $root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $entry) {
                if ($entry instanceof \SplFileInfo && $entry->isDir()) {
                    self::assertMatchesRegularExpression('/\A[A-Z][A-Za-z0-9]*\z/', $entry->getFilename(), $entry->getPathname());
                }
            }
        }
    }

    // ── Errors ───────────────────────────────────────────────────────────

    #[Test]
    public function errorCodesAreUniqueAndInsideTheReservedBlock(): void
    {
        $codes = [];

        foreach (EnvCode::cases() as $case) {
            self::assertGreaterThanOrEqual(self::ERROR_CODE_MIN, $case->value);
            self::assertLessThanOrEqual(self::ERROR_CODE_MAX, $case->value);
            $codes[$case->value] = $case->name;
        }

        self::assertCount(20, $codes);
        self::assertSame('ENV', EnvCode::prefix());
        self::assertSame([self::ERROR_CODE_MIN, self::ERROR_CODE_MAX], [EnvCode::block()->first, EnvCode::block()->last]);
    }

    #[Test]
    public function exceptionsAreBuiltOnlyByTheirNamedConstructors(): void
    {
        foreach ([...self::sourceFiles(), 'bin/kariricode-dotenv' => self::document('bin/kariricode-dotenv')] as $relative => $code) {
            foreach (self::EXCEPTIONS as $exception) {
                if ($relative === 'Exception/' . $exception . '.php') {
                    continue;
                }

                self::assertDoesNotMatchRegularExpression('/\bnew\s+' . $exception . '\s*\(/', $code, $relative . ' builds ' . $exception . ' directly');
            }

            self::assertDoesNotMatchRegularExpression('/\bthrow\s+new\s+\\?[A-Za-z]*(Exception|Error)\b/', $code, $relative . ': throws an uncoded exception');
        }
    }

    #[Test]
    public function everyConcreteExceptionBelongsToExactlyOneContractFamilyAndThePackage(): void
    {
        $checked = 0;

        foreach (self::sourceFiles() as $relative => $code) {
            $type = self::reflect($relative);

            if (!str_starts_with($relative, 'Exception/') || $type->isAbstract() || $type->isEnum() || $type->isInterface()) {
                continue;
            }

            ++$checked;
            $families = array_filter(self::FAMILIES, static fn(string $family): bool => $type->implementsInterface($family));
            self::assertCount(1, $families, $relative);
            self::assertTrue($type->implementsInterface(DotenvException::class), $relative);
            $parent = $type->getParentClass();
            self::assertNotFalse($parent, $relative);
            self::assertStringStartsWith('KaririCode\\Exception\\', $parent->getName(), $relative . ': built on a kariricode/exception base');
        }

        self::assertSame(count(self::EXCEPTIONS), $checked);
    }

    // ── Package metadata and supply chain ────────────────────────────────

    #[Test]
    public function composerDeclaresArfaAsvsAndSpecVersions(): void
    {
        $composer = self::composer();

        self::assertSame(
            ['arfa' => ['version' => '1.3', 'level' => 1], 'asvs' => ['version' => '5.0', 'level' => 2], 'spec' => '4.3.3', 'error-code-range' => 'ENV 2700–2799'],
            self::at($composer, 'extra', 'kariricode'),
            'Spec V4.3.3 E-044 and gate G4',
        );

        self::assertSame('^2.11', self::at($composer, 'require', 'kariricode/contract'));
        self::assertSame('^2.0.2', self::at($composer, 'require', 'kariricode/exception'));

        // Spec V4.3.3 §31: PHP, kariricode/contract, kariricode/exception and PSR interfaces only.
        $runtime = array_keys((array) self::at($composer, 'require'));
        self::assertSame([], array_values(array_filter($runtime, static fn(string $package): bool => preg_match('#\A(php|psr/[a-z-]+|kariricode/(contract|exception))\z#', $package) !== 1)));

        foreach (['require', 'require-dev', 'suggest'] as $section) {
            self::assertNull(self::at($composer, $section, 'kariricode/framework'), $section);
        }
    }

    #[Test]
    public function composerExposesTheQualityGates(): void
    {
        $scripts = self::at(self::composer(), 'scripts');
        self::assertIsArray($scripts);

        foreach ([...self::QUALITY_GATES, 'sbom', 'check'] as $gate) {
            self::assertArrayHasKey($gate, $scripts, 'composer ' . $gate);
        }
    }

    #[Test]
    public function supplyChainIsPinned(): void
    {
        self::assertDoesNotMatchRegularExpression('#^/?composer\.lock$#m', self::document('.gitignore'), 'composer.lock is committed (Spec V4.3.3 §32)');
        $workflows = self::glob('/.github/workflows/*.yml');
        self::assertNotEmpty($workflows);
        $actions = 0;

        foreach ($workflows as $workflow) {
            foreach (self::lines($workflow) as $number => $line) {
                $where = basename($workflow) . ':' . ($number + 1);

                if (preg_match('/^\s*(?:-\s*)?uses:\s*(\S+)/', $line, $match) === 1) {
                    ++$actions;
                    self::assertMatchesRegularExpression('/@[0-9a-f]{40}\z/', $match[1], $where . ': action pinned by commit SHA');
                }

                if (preg_match('/^\s*image:\s*(\S+)/', $line, $match) === 1) {
                    self::assertMatchesRegularExpression('/@sha256:[0-9a-f]{64}\z/', $match[1], $where . ': image pinned by digest');
                }
            }
        }

        self::assertGreaterThan(0, $actions);
    }

    // ── Documentation ────────────────────────────────────────────────────

    #[Test]
    public function documentationFollowsTheCanonicalLayout(): void
    {
        $adrs = self::glob('/docs/adr/ADR-*.md');
        self::assertCount(11, $adrs);

        foreach ($adrs as $path) {
            $adr = (string) file_get_contents($path);

            foreach (self::ADR_SECTIONS as $section) {
                self::assertMatchesRegularExpression('/^' . preg_quote($section, '/') . '$/m', $adr, basename($path));
            }
        }

        self::assertMatchesRegularExpression('/^- \*\*Status: (PENDING|PASS|FAIL)\*\*/m', self::document('docs/quality/QD-V433-COMPLIANCE.md'));

        foreach (['## Threat Model', '## ASVS 5.0', '## Supply Chain'] as $section) {
            self::assertStringContainsString($section, self::document('SECURITY.md'));
        }

        foreach (['## [4.5.0]', '### Spec V4.3.3 conformance (2026-11-28)', '**Breaking**', '**Fixed**'] as $heading) {
            self::assertStringContainsString($heading, self::document('CHANGELOG.md'));
        }
    }

    #[Test]
    public function documentationAndSourcesCiteTheCurrentSpec(): void
    {
        $texts = self::sourceFiles();

        foreach (['README.md', 'SECURITY.md', 'CONTRIBUTING.md', ...self::markdownUnder('docs')] as $relative) {
            if (!in_array(needle: $relative, haystack: self::HISTORICAL_RECORDS, strict: true)) {
                $texts[$relative] = self::document($relative);
            }
        }

        foreach ($texts as $relative => $text) {
            self::assertDoesNotMatchRegularExpression('/Spec V4\.0\b|Specification V4\.0\b|\bE-013\b/', $text, $relative . ': cite Spec V4.3.3 and its errata numbers');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return list<string> */
    private static function markdownUnder(string $directory): array
    {
        $root = (string) realpath(self::ROOT);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'md') {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($files);

        return $files;
    }

    /** printf-family values are variadic and have no names. */
    private static function isVariadicFormatting(Node\Expr\CallLike $call): bool
    {
        return $call instanceof Node\Expr\FuncCall
            && $call->name instanceof Node\Name
            && in_array(needle: $call->name->toLowerString(), haystack: ['sprintf', 'printf', 'vsprintf'], strict: true);
    }

    /** @return list<string> */
    private static function glob(string $pattern): array
    {
        $paths = glob(self::ROOT . $pattern);

        return $paths === false ? [] : $paths;
    }

    /** @return list<string> */
    private static function lines(string $path): array
    {
        $lines = file($path);

        return $lines === false ? [] : $lines;
    }

    /** @return array<mixed> */
    private static function composer(): array
    {
        try {
            $composer = json_decode(self::document('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            self::fail('composer.json is not valid JSON: ' . $error->getMessage());
        }

        self::assertIsArray($composer);

        return $composer;
    }

    private static function at(mixed $data, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }

    private static function document(string $relative): string
    {
        $path = self::ROOT . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return array<string, string> path relative to src/ => contents, without the functions file */
    private static function sourceFiles(): array
    {
        $files = self::phpFiles(self::ROOT . '/src');
        unset($files[self::FUNCTIONS_FILE]);

        return $files;
    }

    /** @return array<string, string> */
    private static function phpFiles(string $directory): array
    {
        $files = [];
        $root = (string) realpath($directory);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($files);
        self::assertNotEmpty($files);

        return $files;
    }

    /** @return ReflectionClass<object> */
    private static function reflect(string $relative): ReflectionClass
    {
        $name = self::SOURCE_NAMESPACE . str_replace('/', '\\', substr($relative, 0, -4));
        self::assertTrue(class_exists($name) || interface_exists($name) || enum_exists($name), $name);

        return new ReflectionClass($name);
    }

    private static function namedTypeDeclarations(string $code): int
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($code), static fn(PhpToken $token): bool => !$token->isIgnorable()));
        $count = 0;

        foreach ($tokens as $index => $token) {
            $declares = $token->is([T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT]);
            $previous = $tokens[$index - 1] ?? null;
            $next = $tokens[$index + 1] ?? null;

            if ($declares && $previous?->is([T_DOUBLE_COLON, T_NEW]) !== true && $next?->is(T_STRING) === true) {
                ++$count;
            }
        }

        return $count;
    }

    private static function countTokens(string $code, int $kind): int
    {
        return count(array_filter(PhpToken::tokenize($code), static fn(PhpToken $token): bool => $token->is($kind)));
    }
}
