<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\ValueObject;

use KaririCode\Dotenv\Enum\LoadMode;
use KaririCode\Dotenv\ValueObject\DotenvConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every with*() returns a new configuration that differs in one setting
 * and leaves the original as it was (ADR-002).
 */
final class DotenvConfigurationTest extends TestCase
{
    #[Test]
    public function eachWitherChangesOneSettingOfACopy(): void
    {
        $original = new DotenvConfiguration(
            loadMode: LoadMode::SkipExisting,
            strictNames: true,
            typeCasting: false,
            populateEnv: false,
            populateServer: false,
            usePutenv: true,
            encryptionKey: 'key',
            cachePath: '/cache.php',
            allowList: ['APP_*'],
            denyList: ['SECRET'],
            environmentName: 'staging',
        );
        $before = clone $original;

        $changes = [
            [$original->withLoadMode(LoadMode::Overwrite), 'loadMode', LoadMode::Overwrite],
            [$original->withStrictNames(false), 'strictNames', false],
            [$original->withTypeCasting(true), 'typeCasting', true],
            [$original->withEncryptionKey(null), 'encryptionKey', null],
            [$original->withCachePath(null), 'cachePath', null],
            [$original->withEnvironmentName('production'), 'environmentName', 'production'],
        ];

        foreach ($changes as [$copy, $setting, $value]) {
            $settings = get_object_vars($copy);

            self::assertNotSame($original, $copy, $setting);
            self::assertSame($value, $settings[$setting], $setting);

            foreach (get_object_vars($original) as $name => $kept) {
                if ($name !== $setting) {
                    self::assertSame($kept, $settings[$name], "{$setting} kept {$name}");
                }
            }
        }

        self::assertEquals($before, $original);
    }
}
