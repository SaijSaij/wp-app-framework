<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Table;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Table\TableRegistry;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Counter;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Level;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\LogEntry;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Priority;
use Saij\WpAppFramework\Tests\Support\Database;
use Saij\WpAppFramework\Tests\Support\Tables;

/**
 * Row objects: loading, saving and deleting through the store of their instance.
 */
final class AbstractRowTest extends TestCase
{
    private const string LOG = 'wp_dev_test_log';

    private Database $database;

    protected function setUp(): void
    {
        $this->database = Database::use();
        Tables::store()->ensureCurrent();
        $this->database->forget();
    }

    protected function tearDown(): void
    {
        Tables::reset();
    }

    public function testSaveInsertsEveryColumnAndSetsThePrimaryKey(): void
    {
        $entry = new LogEntry(level: Level::Error, message: 'Import failed', ratio: 0.1, priority: Priority::High, created: new DateTimeImmutable('2026-10-14 10:30:00+02:00'));

        $entry->save();

        self::assertSame(1, $entry->getId());
        self::assertSame([[
            'id' => '1',
            'level' => 'error',
            'message' => 'Import failed',
            'context' => '',
            'reference' => null,
            'attempts' => '0',
            'ratio' => '0.1',
            'flagged' => '0',
            'post_id' => null,
            'priority' => '9',
            'handled' => null,
            'created' => '2026-10-14 08:30:00',
        ]], $this->database->rows(self::LOG));
        self::assertSame(['INSERT INTO `wp_dev_test_log` (level, message, context, reference, attempts, ratio, flagged, post_id, priority, handled, created)'], $this->database->getQueries());

        // Saved and unchanged: nothing is written.
        $entry->save();
        self::assertCount(1, $this->database->getQueries());
    }

    public function testRowsAreLoadedAsTypedObjects(): void
    {
        $this->database->insert(self::LOG, ['level' => 'error', 'message' => 'Loaded', 'context' => '', 'ratio' => '1e-300', 'flagged' => '1', 'post_id' => '12', 'priority' => '1', 'handled' => '2026-01-02 03:04:05', 'created' => '2026-10-14 08:30:00']);

        $entry = LogEntry::get(1);

        self::assertSame(
            [1, Level::Error, 'Loaded', 1.0E-300, true, 12, Priority::Low, '2026-01-02T03:04:05+00:00', '2026-10-14T08:30:00+00:00', null],
            [$entry->getId(), $entry->getLevel(), $entry->getMessage(), $entry->getRatio(), $entry->isFlagged(), $entry->getPostId(), $entry->getPriority(), $entry->getHandled()?->format(DATE_ATOM), $entry->getCreated()->format(DATE_ATOM), $entry->getReference()],
        );
        self::assertNotSame($entry, LogEntry::get(1), 'Every get() returns a new object.');
        self::assertNull(LogEntry::find(2));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('There is no %s with the ID 2 (table wp_dev_test_log).', LogEntry::class));
        LogEntry::get(2);
    }

    public function testSaveWritesTheChangedColumnsOnly(): void
    {
        $this->database->insert(self::LOG, ['message' => 'Before', 'context' => '', 'created' => '2026-10-14 08:30:00']);
        $entry = LogEntry::get(1);
        $entry->setMessage('After');
        $entry->setLevel(Level::Info);

        $entry->save();
        $entry->save();

        self::assertSame(['SELECT * FROM `wp_dev_test_log` WHERE `id` = 1', 'UPDATE `wp_dev_test_log` SET message'], $this->database->getQueries());
        self::assertSame('After', $this->database->rows(self::LOG)[0]['message']);
    }

    public function testProtectedColumnsAreReadAndWrittenToo(): void
    {
        $counter = new Counter(name: 'visits', count: 3);
        $counter->save();

        self::assertSame([1, 3], [$counter->getId(), Counter::get(1)->getCount()]);
    }

    public function testDeleteRemovesTheRowAndThePrimaryKey(): void
    {
        $entry = new LogEntry(message: 'Gone');
        $entry->save();

        $entry->delete();

        self::assertNull($entry->getId());
        self::assertSame([], $this->database->rows(self::LOG));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(LogEntry::class . '::delete(): the row has not been saved yet.');
        $entry->delete();
    }

    public function testAStoredValueThatIsNoLongerValidIsAnError(): void
    {
        $this->database->insert(self::LOG, ['level' => 'removed', 'context' => '', 'created' => '2026-10-14 08:30:00']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The row 1 of the table wp_dev_test_log cannot be loaded as %s: Value "%s.level" must be a value of %s, \'removed\' given.', LogEntry::class, LogEntry::class, Level::class));
        LogEntry::get(1);
    }

    public function testARefusedRowIsAnError(): void
    {
        $this->database->fail('/^INSERT INTO `wp_dev_test_log`/', "Data too long for column 'message'");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf("Inserting a row of %s into wp_dev_test_log failed: Data too long for column 'message'", LogEntry::class));
        new LogEntry(message: str_repeat('x', 501))->save();
    }

    public function testRowClassesNeedABootedInstance(): void
    {
        TableRegistry::reset();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s is no row class of a booted theme or plugin: row classes need #[Table] and are available once the theme or plugin has booted (rebuild the manifest after code changes).', LogEntry::class));
        LogEntry::find(1);
    }
}
