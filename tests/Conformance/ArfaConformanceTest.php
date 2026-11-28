<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Conformance;

use JsonException;
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

    /** ARFA 1.3 E-003: no enum of the package needs a backing value. */
    #[Test]
    public function enumsArePure(): void
    {
        $checked = 0;

        foreach (self::sourceFiles() as $relative => $code) {
            $name = self::reflect($relative)->getName();

            if (enum_exists($name) && !str_starts_with($relative, 'Exception/')) {
                ++$checked;
                self::assertFalse((new \ReflectionEnum($name))->isBacked(), $relative . ': E-003');
            }
        }

        self::assertSame(2, $checked);
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

    // ── Package metadata and supply chain ────────────────────────────────

    #[Test]
    public function composerDeclaresArfaAsvsAndSpecVersions(): void
    {
        $composer = self::composer();

        self::assertSame(
            ['arfa' => ['version' => '1.3', 'level' => 1], 'asvs' => ['version' => '5.0', 'level' => 2], 'spec' => '4.3.3'],
            self::at($composer, 'extra', 'kariricode'),
            'Spec V4.3.3 E-044 and gate G4',
        );

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

    // ── Helpers ──────────────────────────────────────────────────────────

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
