<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Table;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebCodingCat\WpAppFramework\Table\TableStore;
use WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid\LogEntry;
use WebCodingCat\WpAppFramework\Tests\Support\Database;
use WebCodingCat\WpAppFramework\Tests\Support\Tables;

/**
 * The schema sync: when it runs (hash, version, lock, failures, sites) and what it changes in the tables.
 */
final class SchemaSyncTest extends TestCase
{
    private const string OPTION = 'my_theme_tables';

    private const string LOCK = 'my_theme_tables_lock';

    private const string LOG = 'wp_dev_test_log';

    private const string CHARSET = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

    private const string CREATE_LOG = 'CREATE TABLE IF NOT EXISTS `wp_dev_test_log` ('
        . '`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, '
        . "`level` varchar(191) NOT NULL DEFAULT 'info', "
        . "`message` varchar(500) NOT NULL DEFAULT '', "
        . '`context` longtext NOT NULL, '
        . '`reference` varchar(191) NULL, '
        . '`attempts` bigint(20) NOT NULL DEFAULT 0, '
        . '`ratio` double NOT NULL DEFAULT 0.5, '
        . '`flagged` tinyint(1) NOT NULL DEFAULT 0, '
        . '`post_id` bigint(20) NULL, '
        . '`priority` bigint(20) NULL, '
        . '`handled` datetime NULL, '
        . '`created` datetime NOT NULL, '
        . 'PRIMARY KEY (`id`), UNIQUE KEY `reference` (`reference`), KEY `post_id` (`post_id`), KEY `level_created` (`level`, `created`)) '
        . self::CHARSET;

    private Database $database;

    protected function setUp(): void
    {
        $this->database = Database::use();
        unset($GLOBALS['wp_app_framework_test_option_misses']);
    }

    protected function tearDown(): void
    {
        Tables::reset();
    }

    public function testTheFirstSyncCreatesTheTablesAndStoresTheHash(): void
    {
        $store = Tables::store();
        self::assertTrue($store->needsCheck());

        $store->ensureCurrent();

        self::assertSame([
            "CREATE TABLE IF NOT EXISTS `wp_dev_test_counter` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(191) NOT NULL DEFAULT '', `count` bigint(20) NOT NULL DEFAULT 0, PRIMARY KEY (`id`)) " . self::CHARSET,
            self::CREATE_LOG,
        ], $this->database->getChanges());
        self::assertSame(['wp_dev_test_counter', 'wp_dev_test_log'], $this->database->getTableNames());
        self::assertSame(['PRIMARY' => 'id!', 'reference' => 'reference!', 'post_id' => 'post_id', 'level_created' => 'level,created'], $this->database->indexes(self::LOG));
        self::assertSame([
            'hash' => Tables::data()['schema'],
            'version' => '1.2.0',
            'namespace' => 'dev-test',
            'tables' => ['counter', 'log'],
            'failed' => null,
        ], get_option(self::OPTION));
        self::assertNull($this->database->getLock(self::LOCK));
        self::assertSame(['created wp_dev_test_counter.', 'created wp_dev_test_log.'], Tables::log());
    }

    public function testAMatchingHashCostsNoQuery(): void
    {
        update_option(self::OPTION, ['hash' => Tables::data()['schema'], 'version' => '1.2.0', 'namespace' => 'dev-test', 'tables' => ['counter', 'log'], 'failed' => null], true);
        $store = Tables::store();

        $store->ensureCurrent();
        self::assertSame('wp_dev_test_log', LogEntry::tableName());

        self::assertSame([], $this->database->getQueries());
        self::assertSame([], $GLOBALS['wp_app_framework_test_option_misses'] ?? []);
    }

    public function testWithoutTablesAndOptionNothingIsChecked(): void
    {
        $store = Tables::store(['schema' => null, 'version' => null, 'classes' => []]);

        self::assertFalse($store->needsCheck());
        $store->ensureCurrent();

        self::assertSame([], $this->database->getQueries());
        self::assertSame([], $GLOBALS['wp_app_framework_test_option_misses'] ?? []);
    }

    public function testRemovedTablesAreDroppedAndTheOptionDeletedWhenNoneAreLeft(): void
    {
        $this->database->create('CREATE TABLE IF NOT EXISTS `wp_dev_test_log` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ' . self::CHARSET);
        $this->database->create('CREATE TABLE IF NOT EXISTS `wp_dev_test_foreign` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ' . self::CHARSET);
        update_option(self::OPTION, ['hash' => 'old', 'version' => '1.0.0', 'namespace' => 'dev-test', 'tables' => ['log'], 'failed' => null], true);
        $store = Tables::store(['schema' => null, 'version' => '1.2.0', 'classes' => []]);

        self::assertTrue($store->needsCheck());
        $store->ensureCurrent();

        // Only managed tables are dropped.
        self::assertSame(['wp_dev_test_foreign'], $this->database->getTableNames());
        self::assertArrayNotHasKey(self::OPTION, wp_load_alloptions());
        self::assertSame(['dropped wp_dev_test_log; no row class declares it any more.'], Tables::log());
    }

    public function testAnOlderSchemaIsChangedInOneAlterTable(): void
    {
        $this->database->create('CREATE TABLE IF NOT EXISTS `wp_dev_test_log` ('
            . '`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, '
            . "`level` varchar(191) NOT NULL DEFAULT 'info', "
            . "`text` varchar(191) NOT NULL DEFAULT '', "
            . "`body` varchar(191) NOT NULL DEFAULT '', "
            . "`legacy` varchar(191) NOT NULL DEFAULT '', "
            . '`attempts` int(11) NULL, '
            . '`ratio` double NOT NULL DEFAULT 1.0, '
            . '`flagged` tinyint(1) NOT NULL DEFAULT 0, '
            . '`post_id` bigint NULL, '
            . '`priority` bigint(20) NULL, '
            . '`handled` datetime NULL, '
            . 'PRIMARY KEY (`id`), KEY `level_created` (`level`), KEY `legacy` (`legacy`), KEY `post_id` (`post_id`)) '
            . self::CHARSET);
        $this->database->insert(self::LOG, ['text' => 'Import failed', 'body' => 'old', 'attempts' => null]);
        $this->database->insert(self::LOG, ['text' => 'Done', 'attempts' => '2']);
        $this->database->create("CREATE TABLE IF NOT EXISTS `wp_dev_test_counter` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(191) NOT NULL DEFAULT '', `count` bigint(20) NOT NULL DEFAULT 0, PRIMARY KEY (`id`)) " . self::CHARSET);
        update_option(self::OPTION, ['hash' => 'old', 'version' => '1.1.0', 'namespace' => 'dev-test', 'tables' => ['counter', 'log'], 'failed' => null], true);
        $this->database->forget();

        Tables::store()->ensureCurrent();

        self::assertSame([
            'ALTER TABLE `wp_dev_test_log` '
                . 'DROP INDEX `level_created`, DROP INDEX `legacy`, '
                // The first earlier name that exists is renamed; the other one is no column any more.
                . "CHANGE COLUMN `text` `message` varchar(500) NOT NULL DEFAULT '', "
                . 'ADD COLUMN `context` longtext NOT NULL, '
                . 'ADD COLUMN `reference` varchar(191) NULL, '
                . 'MODIFY COLUMN `attempts` bigint(20) NOT NULL DEFAULT 0, '
                . 'MODIFY COLUMN `ratio` double NOT NULL DEFAULT 0.5, '
                // bigint is bigint(20): integers are compared without display width (MySQL 8.0.19 leaves it out).
                . 'ADD COLUMN `created` datetime NOT NULL, '
                . 'DROP COLUMN `body`, DROP COLUMN `legacy`, '
                . 'ADD UNIQUE KEY `reference` (`reference`), ADD KEY `level_created` (`level`, `created`)',
        ], $this->database->getChanges());
        $fresh = new Database();
        $fresh->create(self::CREATE_LOG);
        $expected = $fresh->describe(self::LOG);
        $actual = $this->database->describe(self::LOG);
        self::assertSame('bigint NULL', $actual['post_id']);
        // New columns come last.
        ksort($expected);
        ksort($actual);
        self::assertSame([...$expected, 'post_id' => 'bigint NULL'], $actual);
        self::assertEquals($fresh->indexes(self::LOG), $this->database->indexes(self::LOG));
        self::assertSame(['Import failed', 'Done'], array_column($this->database->rows(self::LOG), 'message'));
        self::assertSame(['0', '2'], array_column($this->database->rows(self::LOG), 'attempts'));
        self::assertSame([
            'altered wp_dev_test_log: ' . substr($this->database->getChanges()[0], strlen('ALTER TABLE `wp_dev_test_log` ')) . '.',
            'wp_dev_test_log: MariaDB cut or set 1 values to fit the changed columns (shorter strings, NULL in NOT NULL columns).',
            'wp_dev_test_log: 2 rows got the implicit value (0, an empty string or the zero date) in new NOT NULL columns without default.',
        ], Tables::log());
        self::assertSame('1.2.0', self::option()['version']);
    }

    public function testAnOldColumnNextToItsNewNameStays(): void
    {
        $this->database->create(self::log(["`message` varchar(500) NOT NULL DEFAULT ''" => "`message` varchar(500) NOT NULL DEFAULT '', `text` varchar(191) NULL"]));

        Tables::store()->ensureCurrent();

        self::assertArrayHasKey('text', $this->database->describe(self::LOG));
        self::assertSame([], array_values(array_filter($this->database->getChanges(), static fn(string $query): bool => str_starts_with($query, 'ALTER'))));
        self::assertContains('wp_dev_test_log has the column message and its earlier name text; text stays as it is, merge it by hand and drop it.', Tables::log());
    }

    public function testShorterColumnsAndNotNullAreAppliedAndLogged(): void
    {
        $this->database->create(self::log(["`message` varchar(500) NOT NULL DEFAULT ''" => '`message` varchar(800) NULL']));
        $this->database->insert(self::LOG, ['message' => str_repeat('x', 600), 'context' => '', 'created' => '2026-01-01 00:00:00']);
        $this->database->insert(self::LOG, ['message' => null, 'context' => '', 'created' => '2026-01-01 00:00:00']);

        Tables::store()->ensureCurrent();

        self::assertSame(["ALTER TABLE `wp_dev_test_log` MODIFY COLUMN `message` varchar(500) NOT NULL DEFAULT ''"], array_values(array_filter($this->database->getChanges(), static fn(string $query): bool => str_starts_with($query, 'ALTER'))));
        self::assertSame([500, 0], array_map(static fn(?string $message): int => strlen((string) $message), array_column($this->database->rows(self::LOG), 'message')));
        self::assertContains('wp_dev_test_log: MariaDB cut or set 2 values to fit the changed columns (shorter strings, NULL in NOT NULL columns).', Tables::log());
    }

    public function testAWrongPrimaryKeyIsReplaced(): void
    {
        $this->database->create("CREATE TABLE IF NOT EXISTS `wp_dev_test_counter` (`id` bigint(20) unsigned NOT NULL, `name` varchar(191) NOT NULL DEFAULT '', `count` bigint(20) NOT NULL DEFAULT 0, PRIMARY KEY (`name`)) " . self::CHARSET);

        Tables::store()->ensureCurrent();

        self::assertContains(
            'ALTER TABLE `wp_dev_test_counter` DROP PRIMARY KEY, MODIFY COLUMN `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)',
            $this->database->getChanges(),
        );
        self::assertSame(['PRIMARY' => 'id!'], $this->database->indexes('wp_dev_test_counter'));
    }

    public function testTablesAreRenamedFromAnEarlierName(): void
    {
        $this->database->create(str_replace('`wp_dev_test_log`', '`wp_dev_test_journal`', self::log()));
        $this->database->insert('wp_dev_test_journal', ['message' => 'kept', 'context' => '', 'created' => '2026-01-01 00:00:00']);
        $this->database->forget();

        Tables::store()->ensureCurrent();

        self::assertSame('RENAME TABLE `wp_dev_test_journal` TO `wp_dev_test_log`', $this->database->getChanges()[1]);
        self::assertCount(2, $this->database->getChanges());
        self::assertSame(['wp_dev_test_counter', 'wp_dev_test_log'], $this->database->getTableNames());
        self::assertSame(['kept'], array_column($this->database->rows(self::LOG), 'message'));
        self::assertContains('renamed wp_dev_test_journal to wp_dev_test_log.', Tables::log());
    }

    public function testATableNextToItsEarlierNameAndTablesOfAnotherNamespaceStay(): void
    {
        $this->database->create(self::log());
        $this->database->create(str_replace('`wp_dev_test_log`', '`wp_dev_test_journal`', self::log()));
        $this->database->create(str_replace('`wp_dev_test_log`', '`wp_old_ns_log`', self::log()));
        update_option(self::OPTION, ['hash' => 'old', 'version' => '1.2.0', 'namespace' => 'old-ns', 'tables' => ['log'], 'failed' => null], true);

        Tables::store()->ensureCurrent();

        self::assertSame(['wp_dev_test_log', 'wp_dev_test_journal', 'wp_old_ns_log', 'wp_dev_test_counter'], $this->database->getTableNames());
        self::assertSame([
            'created wp_dev_test_counter.',
            'wp_dev_test_log and its earlier name wp_dev_test_journal both exist; wp_dev_test_journal stays as it is, merge it by hand and drop it.',
            'the namespace changed from old-ns to dev-test; the tables of old-ns stay and are no longer managed: wp_old_ns_log.',
        ], Tables::log());
        self::assertSame('dev-test', self::option()['namespace']);
    }

    public function testATableThatDoesNotConvergeFailsAndPausesTheSync(): void
    {
        $this->database->create(self::log(['`attempts` bigint(20) NOT NULL DEFAULT 0' => '`attempts` int(11) NULL']))->freeze(self::LOG);
        $store = Tables::store();

        $store->ensureCurrent();

        $option = self::option();
        self::assertNull($option['hash']);
        self::assertSame(Tables::data()['schema'], $option['failed']['hash'] ?? null);
        self::assertNull($this->database->getLock(self::LOCK));
        self::assertContains('the sync of the tables of my-theme failed and is tried again after 300 seconds: The table wp_dev_test_log did not converge to its row class; still different: MODIFY COLUMN `attempts` bigint(20) NOT NULL DEFAULT 0.', Tables::log());

        // Within five minutes, an access fails without trying again.
        $queries = count($this->database->getQueries());
        try {
            LogEntry::tableName();
            self::fail('The access must fail.');
        } catch (RuntimeException $exception) {
            self::assertMatchesRegularExpression('/^The tables of my-theme cannot be used: their sync failed \d+ seconds ago and is tried again after 300 seconds; the error log has the reason\.$/', $exception->getMessage());
        }
        self::assertCount($queries, $this->database->getQueries());

        // Afterwards it tries again, and an access reports the failure.
        update_option(self::OPTION, [...$option, 'failed' => ['hash' => $option['failed']['hash'] ?? null, 'time' => time() - TableStore::RETRY_AFTER]], true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The tables of my-theme cannot be used: their sync failed (The table wp_dev_test_log did not converge');
        LogEntry::tableName();
    }

    public function testAFailedSyncOnInitIsThrownWithDebug(): void
    {
        $this->database->fail('/^CREATE TABLE IF NOT EXISTS `wp_dev_test_log`/', 'Row size too large');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The statement for wp_dev_test_log failed: Row size too large');
        Tables::store(debug: true)->ensureCurrent();
    }

    public function testALockedSyncIsLeftToTheOtherRequest(): void
    {
        $this->database->lock(self::LOCK, (string) (time() - 10));
        $store = Tables::store();

        $store->ensureCurrent();

        self::assertSame([], $this->database->getChanges());
        self::assertSame(['the tables of my-theme are being synced by another request; this request leaves them.'], Tables::log());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The tables of my-theme cannot be used: another request is syncing them right now.');
        $store->ensure(true);
    }

    public function testALockOfARequestThatDiedIsTakenOver(): void
    {
        $this->database->lock(self::LOCK, (string) (time() - TableStore::LOCK_TIMEOUT - 1));

        Tables::store()->ensureCurrent();

        self::assertCount(2, $this->database->getChanges());
        self::assertNull($this->database->getLock(self::LOCK));
    }

    public function testOlderCodeLeavesTablesOfNewerCodeAlone(): void
    {
        update_option(self::OPTION, ['hash' => 'newer', 'version' => '1.10.0', 'namespace' => 'dev-test', 'tables' => ['counter', 'log'], 'failed' => null], true);
        $store = Tables::store();

        $store->ensureCurrent();
        $store->ensureCurrent();

        self::assertSame([], $this->database->getQueries());
        self::assertSame(['the tables were synced by version 1.10.0 of my-theme, this code is version 1.2.0; they stay as they are until the newer code runs again.'], Tables::log());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The tables of my-theme cannot be used: the tables were synced by version 1.10.0 of my-theme, this code is version 1.2.0');
        LogEntry::find(1);
    }

    public function testEachSiteIsCheckedOncePerRequest(): void
    {
        $store = Tables::store();
        $store->ensureCurrent();
        $queries = count($this->database->getQueries());
        $store->ensure(true);
        self::assertCount($queries, $this->database->getQueries());

        // Another site after switch_to_blog(): its own option, its own tables.
        $GLOBALS['wp_app_framework_test_blog_id'] = 2;
        $site = self::option();
        delete_option(self::OPTION);
        $store->ensure(true);
        self::assertGreaterThan($queries, count($this->database->getQueries()));

        $GLOBALS['wp_app_framework_test_blog_id'] = 1;
        update_option(self::OPTION, $site, true);
        $queries = count($this->database->getQueries());
        $store->ensure(true);
        self::assertCount($queries, $this->database->getQueries());
    }

    public function testNothingIsCheckedWhileWordPressInstalls(): void
    {
        $GLOBALS['wp_app_framework_test_installing'] = true;

        Tables::store()->ensureCurrent();

        self::assertSame([], $this->database->getQueries());
    }

    /**
     * The CREATE TABLE of the log table as the first sync writes it, with replacements.
     *
     * @param array<string, string> $replace
     */
    private static function log(array $replace = []): string
    {
        return strtr(self::CREATE_LOG, $replace);
    }

    /**
     * @return array{hash: string|null, version: string|null, namespace: string|null, tables: list<string>, failed: array{hash: string|null, time: int}|null}
     */
    private static function option(): array
    {
        /** @var array{hash: string|null, version: string|null, namespace: string|null, tables: list<string>, failed: array{hash: string|null, time: int}|null} Written by TableStore. */
        return get_option(self::OPTION);
    }
}
