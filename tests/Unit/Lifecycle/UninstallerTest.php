<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Lifecycle;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Lifecycle\Uninstaller;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostStore;
use Saij\WpAppFramework\Table\TableNames;
use Saij\WpAppFramework\Table\TableStore;
use Saij\WpAppFramework\Tests\Support\Database;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Support\Tables;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * The data the uninstallation of a plugin removes – and the data of other instances it leaves alone.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class UninstallerTest extends TestCase
{
    private const string TAG = 'my_theme_cache_tag_0123456789abcdef0123456789abcdef';

    private Database $database;

    private PostStore $posts;

    /** @var ManifestData */
    private array $manifest;

    private TableStore $tables;

    private string $cacheDirectory;

    protected function setUp(): void
    {
        [$this->posts, $this->manifest] = Posts::store();
        $this->database = Database::use();
        $this->tables = Tables::store();
        $this->cacheDirectory = sys_get_temp_dir() . '/wpaf-uninstall-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Tables::reset();
        PostRegistry::reset();
        Hooks::reset();
        if (is_dir($this->cacheDirectory)) {
            Files::remove($this->cacheDirectory);
        }
        unset(
            $GLOBALS['wp_app_framework_test_posts'],
            $GLOBALS['wp_app_framework_test_meta'],
            $GLOBALS['wp_app_framework_test_transients'],
            $GLOBALS['wp_app_framework_test_cron'],
            $GLOBALS['wp_app_framework_test_cron_writes'],
            $GLOBALS['wp_app_framework_test_deleted_meta_keys'],
            $GLOBALS['wp_app_framework_test_deleted_terms'],
            $GLOBALS['wp_app_framework_test_term_failures'],
        );
    }

    private function uninstaller(): Uninstaller
    {
        $container = new Container([], []);
        $cron = new CronRegistrar('my-theme', [], [], new HookRegistrar($container, true), $container, new ValueFactory([]), true, static function (): void {});

        return new Uninstaller('my-theme', $this->posts, ['fixture_general'], $this->tables, $cron, 'my_theme_rewrite', $this->cacheDirectory);
    }

    private function runSteps(): void
    {
        foreach ($this->uninstaller()->siteSteps() as $step) {
            $step();
        }
    }

    public function testTheStepsInTheirOrder(): void
    {
        self::assertSame(['posts', 'terms', 'meta', 'settings', 'cron', 'tables', 'options'], array_keys($this->uninstaller()->siteSteps()));
    }

    public function testDeletesThePostsOfOwnPostTypesOnly(): void
    {
        Posts::add(7, 'fixture_event');
        Posts::add(8, 'fixture_venue');
        Posts::add(9, 'post');
        $this->database->answer("/^SELECT `ID` FROM `wp_posts` WHERE `post_type` = 'fixture_event'$/", [['ID' => '7']]);
        $this->database->answer("/^SELECT `ID` FROM `wp_posts` WHERE `post_type` = 'fixture_venue'$/", [['ID' => '8']]);

        $this->uninstaller()->siteSteps()['posts']();

        $remaining = $GLOBALS['wp_app_framework_test_posts'] ?? [];
        self::assertIsArray($remaining);
        self::assertSame([9], array_keys($remaining));
        $queried = array_values(array_filter($this->database->getQueries(), static fn(string $query): bool => str_starts_with($query, 'SELECT `ID`')));
        $own = array_values(array_map(
            static fn(array $post): string => sprintf("SELECT `ID` FROM `wp_posts` WHERE `post_type` = '%s'", $post['name']),
            array_filter($this->manifest['posts'], static fn(array $post): bool => !$post['builtin']),
        ));
        self::assertSame($own, $queried);
    }

    public function testAPostThatCannotBeDeletedFailsTheStepAfterTheOthers(): void
    {
        Posts::add(8, 'fixture_venue');
        // Post 7 does not exist: wp_delete_post() returns null.
        $this->database->answer("/^SELECT `ID` FROM `wp_posts` WHERE `post_type` = 'fixture_event'$/", [['ID' => '7']]);
        $this->database->answer("/^SELECT `ID` FROM `wp_posts` WHERE `post_type` = 'fixture_venue'$/", [['ID' => '8']]);

        try {
            $this->uninstaller()->siteSteps()['posts']();
            self::fail('RuntimeException expected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cannot delete the posts fixture_event 7 of my-theme.', $exception->getMessage());
        }
        self::assertSame([], $GLOBALS['wp_app_framework_test_posts'] ?? []);
    }

    public function testDeletesTheTermsOfOwnTaxonomies(): void
    {
        $this->database->answer("/^SELECT `term_id` FROM `wp_term_taxonomy` WHERE `taxonomy` = 'fixture_genre'$/", [['term_id' => '3'], ['term_id' => '4']]);
        $this->database->answer('/^SELECT `term_id` FROM /', []);

        $this->uninstaller()->siteSteps()['terms']();

        self::assertSame(['fixture_genre 3', 'fixture_genre 4'], $GLOBALS['wp_app_framework_test_deleted_terms'] ?? null);
        self::assertNotContains("SELECT `term_id` FROM `wp_term_taxonomy` WHERE `taxonomy` = 'category'", $this->database->getQueries());
    }

    public function testATermThatCannotBeDeletedFailsTheStep(): void
    {
        $GLOBALS['wp_app_framework_test_term_failures'] = [3];
        $this->database->answer("/^SELECT `term_id` FROM `wp_term_taxonomy` WHERE `taxonomy` = 'fixture_genre'$/", [['term_id' => '3'], ['term_id' => '4']]);
        $this->database->answer('/^SELECT `term_id` FROM /', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete the terms fixture_genre 3 (Term does not exist.) of my-theme.');

        $this->uninstaller()->siteSteps()['terms']();
    }

    public function testDeletesTheMetaKeysOfAllMetaClasses(): void
    {
        $keys = [];
        foreach ($this->manifest['meta'] as $meta) {
            foreach ($meta['fields'] as $field) {
                $keys[] = $field['key'];
            }
        }
        $GLOBALS['wp_app_framework_test_meta'] = [9 => [$keys[0] => 'value', 'other_key' => 'stays']];

        $this->uninstaller()->siteSteps()['meta']();

        self::assertSame($keys, $GLOBALS['wp_app_framework_test_deleted_meta_keys'] ?? null);
        self::assertSame([9 => ['other_key' => 'stays']], $GLOBALS['wp_app_framework_test_meta']);
    }

    public function testDeletesTheSettingsOptions(): void
    {
        update_option('fixture_general', ['title' => 'Hello']);
        update_option('fixture_other', 'stays');

        $this->uninstaller()->siteSteps()['settings']();

        self::assertArrayNotHasKey('fixture_general', Recorded::options());
        self::assertSame('stays', get_option('fixture_other'));
    }

    public function testClearsTheCronEvents(): void
    {
        update_option('my_theme_cron', ['wp_app_framework/cache/clean_up/my-theme' => 'event']);
        wp_schedule_event(time(), 'daily', 'wp_app_framework/cache/clean_up/my-theme');

        $this->uninstaller()->siteSteps()['cron']();

        self::assertFalse(wp_next_scheduled('wp_app_framework/cache/clean_up/my-theme'));
        self::assertFalse(get_option('my_theme_cron'));
    }

    public function testDropsTheTablesOfTheLastSyncAndOfTheRowClasses(): void
    {
        $this->tables->ensure(true);
        // A table of an earlier namespace that the last sync still manages.
        $state = get_option(TableNames::option('my-theme'));
        self::assertIsArray($state);
        update_option(TableNames::option('my-theme'), [...$state, 'namespace' => 'old', 'tables' => ['archive']]);
        $this->database->create('CREATE TABLE IF NOT EXISTS `wp_old_archive` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->database->create('CREATE TABLE IF NOT EXISTS `wp_other_log` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->database->lock(TableNames::lock('my-theme'), '1');

        self::assertSame(['wp_old_archive', 'wp_dev_test_counter', 'wp_dev_test_log'], $this->tables->siteTables());
        $this->uninstaller()->siteSteps()['tables']();

        self::assertSame(['wp_other_log'], $this->database->getTableNames());
        self::assertFalse(get_option(TableNames::option('my-theme')));
        self::assertNull($this->database->getLock(TableNames::lock('my-theme')));
    }

    public function testDeletesTheTechnicalOptionsOfTheInstanceOnly(): void
    {
        $transient = 'my_theme_cache_0123456789abcdef0123456789abcdef';
        $notice = 'my_theme_meta_errors_1_7';
        // "my-theme-cache-tag" is another instance: its options start like the cache tags of my-theme.
        $other = ['my_theme_cache_tag_cron', 'my_theme_cache_tag_cache_tag_0123456789abcdef0123456789abcdef'];
        foreach (['my_theme_rewrite', self::TAG, 'rewrite_rules', ...$other] as $option) {
            update_option($option, 'value');
        }
        set_transient($transient, ['value' => 1]);
        set_transient($notice, ['error']);
        set_transient('my_theme_cache_other', 'stays');
        $this->database->answer(
            '/^SELECT `option_name` FROM `wp_options` /',
            array_map(static fn(string $name): array => ['option_name' => $name], [
                self::TAG,
                ...$other,
                '_transient_' . $transient,
                '_transient_timeout_' . $transient,
                '_transient_' . $notice,
                '_transient_my_theme_cache_other',
            ]),
        );

        $this->uninstaller()->siteSteps()['options']();

        self::assertContains(
            "SELECT `option_name` FROM `wp_options` WHERE `option_name` LIKE 'my\\\\_theme\\\\_cache\\\\_tag\\\\_%' OR `option_name` LIKE '\\\\_transient\\\\_my\\\\_theme\\\\_%'",
            $this->database->getQueries(),
        );
        self::assertSame(array_fill_keys($other, 'value'), Recorded::options());
        self::assertSame(['my_theme_cache_other'], array_keys(Recorded::transients()));
    }

    public function testRemovesTheCacheDirectory(): void
    {
        mkdir($this->cacheDirectory . '/data/1/ab', 0777, true);
        file_put_contents($this->cacheDirectory . '/data/1/ab/entry.php', '<?php return [];');

        $this->uninstaller()->removeCache();

        self::assertDirectoryDoesNotExist($this->cacheDirectory);
        // A missing directory is nothing to remove.
        $this->uninstaller()->removeCache();
    }

    public function testAllStepsTogether(): void
    {
        $this->database->answer('/^SELECT `(ID|term_id|option_name)` FROM /', []);

        $this->runSteps();

        self::assertSame([], $this->database->getTableNames());
    }
}
