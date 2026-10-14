<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Support;

use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Table\TableCollector;
use Saij\WpAppFramework\Table\TableRegistry;
use Saij\WpAppFramework\Table\TableStore;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * The table store of the TableValid fixture (namespace dev-test, slug my-theme), registered like a booted instance,
 * on the in-memory database; error_log() writes to a file of its own.
 *
 * @phpstan-import-type TablesData from TableCollector
 */
final class Tables
{
    private static ?string $log = null;

    private static string|false $previousLog = false;

    /**
     * @param non-empty-string $version
     * @return TablesData
     */
    public static function data(string $version = '1.2.0'): array
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('TableValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme', namespace: 'dev-test', version: $version)->build()->getTables();
    }

    /**
     * @param TablesData|null $tables
     */
    public static function store(?array $tables = null, bool $debug = false): TableStore
    {
        if (self::$log === null) {
            self::$log = (string) tempnam(sys_get_temp_dir(), 'log');
            self::$previousLog = ini_set('error_log', self::$log);
        }
        $store = new TableStore('my-theme', 'dev-test', $tables ?? self::data(), $debug);
        TableRegistry::reset();
        TableRegistry::register($store);

        return $store;
    }

    /**
     * The messages logged since store(), without the prefix of PHP and the framework.
     *
     * @return list<string>
     */
    public static function log(): array
    {
        $lines = file(self::$log ?? '', FILE_IGNORE_NEW_LINES) ?: [];

        return array_values(array_map(static fn(string $line): string => (string) preg_replace('/^(\[[^\]]+\] )?wp-app-framework: tables: /', '', $line), $lines));
    }

    public static function reset(): void
    {
        TableRegistry::reset();
        Database::reset();
        unset($GLOBALS['wp_app_framework_test_options'], $GLOBALS['wp_app_framework_test_autoload'], $GLOBALS['wp_app_framework_test_installing'], $GLOBALS['wp_app_framework_test_blog_id']);
        if (self::$log !== null) {
            ini_set('error_log', (string) self::$previousLog);
            unlink(self::$log);
            self::$log = null;
        }
    }
}
