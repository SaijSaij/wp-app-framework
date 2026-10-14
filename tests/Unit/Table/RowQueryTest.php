<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Table;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use NoDiscard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\Query\All;
use Saij\WpAppFramework\Query\Any;
use Saij\WpAppFramework\Query\Direction;
use Saij\WpAppFramework\Query\Field\IntField;
use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Table\RowQuery;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Event;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetailsFields;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Counter;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\CounterFields;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Level;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\LogEntry;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\LogEntryFields as F;
use Saij\WpAppFramework\Tests\Fixtures\TableValid\Priority;
use Saij\WpAppFramework\Tests\Support\Database;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Tables;

/**
 * Queries of rows: their SQL, running them and the checks of their conditions.
 */
final class RowQueryTest extends TestCase
{
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
        Posts::reset();
    }

    public function testWithoutConditionsAllRowsComeInTheOrderOfThePrimaryKey(): void
    {
        self::assertSame('SELECT * FROM `wp_dev_test_log` ORDER BY `id` ASC', LogEntry::query()->toSql());
        self::assertSame('SELECT * FROM `wp_dev_test_log` ORDER BY `created` DESC, `id` ASC LIMIT 20 OFFSET 40', LogEntry::query()->orderBy(F::created(), Direction::Desc)->orderBy(F::id())->limit(20)->offset(40)->toSql());
    }

    public function testEveryFieldClassAndOperatorWritesItsCondition(): void
    {
        $sql = LogEntry::query()->where(
            F::level()->equals(Level::Error),
            F::level()->notEquals(Level::Info),
            F::attempts()->between(1, 3),
            F::ratio()->greaterThan(0.1),
            F::flagged()->equals(true),
            F::message()->contains("50%_off'"),
            F::message()->in('a', 'b'),
            F::reference()->notEquals('a'),
            F::postId()->notIn(1, 2),
            F::postId()->isNotNull(),
            F::created()->lessThan(new DateTimeImmutable('2026-10-14 10:00:00+02:00')),
            F::priority()->in(Priority::Low, Priority::High),
            F::handled()->isNull(),
            F::id()->notEquals(5),
        )->toSql();

        self::assertSame(
            'SELECT * FROM `wp_dev_test_log` WHERE '
            . "`level` = 'error' AND `level` != 'info' AND `attempts` BETWEEN 1 AND 3 AND `ratio` > '0.1' AND `flagged` = 1 AND "
            . "`message` LIKE '%50\\\\%\\\\_off\\'%' AND `message` IN ('a', 'b') AND "
            // Nullable columns: a row without value is not equal to any value, like null !== $value in PHP.
            . "(`reference` != 'a' OR `reference` IS NULL) AND (`post_id` NOT IN (1, 2) OR `post_id` IS NULL) AND `post_id` IS NOT NULL AND "
            . "`created` < '2026-10-14 08:00:00' AND `priority` IN (1, 9) AND `handled` IS NULL AND `id` != 5 "
            . 'ORDER BY `id` ASC',
            $sql,
        );
    }

    public function testGroupsNestInParentheses(): void
    {
        $sql = LogEntry::query()
            ->where(F::created()->greaterOrEqual(new DateTimeImmutable('2026-01-01 00:00:00+00:00')))
            ->where(Any::of(F::level()->equals(Level::Error), All::of(F::attempts()->greaterThan(3), F::postId()->isNotNull())))
            ->toSql();

        self::assertSame("SELECT * FROM `wp_dev_test_log` WHERE `created` >= '2026-01-01 00:00:00' AND (`level` = 'error' OR (`attempts` > 3 AND `post_id` IS NOT NULL)) ORDER BY `id` ASC", $sql);
    }

    public function testTheQueryNeverChanges(): void
    {
        $query = LogEntry::query();
        $limited = $query->limit(3);

        self::assertNotSame($query, $limited);
        self::assertSame('SELECT * FROM `wp_dev_test_log` ORDER BY `id` ASC', $query->toSql());
        foreach (new ReflectionClass(RowQuery::class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'static') {
                self::assertNotSame([], $method->getAttributes(NoDiscard::class), $method->getName());
            }
        }
    }

    public function testRunningAQueryReturnsRowObjects(): void
    {
        $this->database->insert('wp_dev_test_log', ['message' => 'first', 'context' => '', 'created' => '2026-10-14 08:30:00']);
        $this->database->insert('wp_dev_test_log', ['message' => 'second', 'context' => '', 'created' => '2026-10-14 08:30:00']);
        $this->database->answer('/^SELECT COUNT\(\*\) FROM `wp_dev_test_log` WHERE/', '7');
        $this->database->answer('/^DELETE FROM `wp_dev_test_log`/', 4);

        $rows = LogEntry::query()->get();
        self::assertSame(['first', 'second'], array_map(static fn(LogEntry $entry): string => $entry->getMessage(), $rows));
        self::assertSame('first', LogEntry::query()->first()?->getMessage());
        self::assertSame(7, LogEntry::query()->where(F::flagged()->equals(false))->limit(2)->count());
        self::assertSame(4, LogEntry::query()->where(F::created()->lessThan(new DateTimeImmutable('2026-01-01 00:00:00+00:00')))->limit(10)->delete());
        self::assertSame([
            'SELECT * FROM `wp_dev_test_log` ORDER BY `id` ASC',
            'SELECT * FROM `wp_dev_test_log` ORDER BY `id` ASC LIMIT 1',
            // limit() and offset() do not count.
            'SELECT COUNT(*) FROM `wp_dev_test_log` WHERE `flagged` = 0',
            "DELETE FROM `wp_dev_test_log` WHERE `created` < '2026-01-01 00:00:00' ORDER BY `id` ASC LIMIT 10",
        ], $this->database->getQueries());
    }

    public function testDeleteCannotSkipRows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(LogEntry::class . '::query()->delete() cannot skip rows; remove offset().');
        LogEntry::query()->limit(1)->offset(1)->delete();
    }

    public function testOffsetNeedsALimit(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(LogEntry::class . '::query()->offset() needs limit().');
        LogEntry::query()->offset(1)->toSql();
    }

    public function testFieldsOfAnotherRowClassAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('%s::query(): the field $name belongs to %s, not to %s.', LogEntry::class, Counter::class, LogEntry::class));
        $query = LogEntry::query()->where(Any::of(CounterFields::name()->equals('visits')));
    }

    public function testAFieldThatDoesNotFitItsColumnIsRejected(): void
    {
        try {
            $query = LogEntry::query()->where(new IntField(LogEntry::class, 'postId')->equals(1));
            self::fail('The field class must be rejected.');
        } catch (LogicException $exception) {
            self::assertSame(sprintf('%s::$postId needs %s, %s given; check the class with #[TableFields] (rebuild the manifest after code changes).', LogEntry::class, \Saij\WpAppFramework\Query\Field\NullableIntField::class, IntField::class), $exception->getMessage());
        }
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s has no column $title (columns: id, level, message, context, reference, attempts, ratio, flagged, postId, priority, handled, created); check the class with #[TableFields]', LogEntry::class));
        $query = LogEntry::query()->orderBy(new StringField(LogEntry::class, 'title'));
    }

    public function testConditionsOnMetaFieldsBelongToPostQueries(): void
    {
        Posts::store();
        try {
            $query = LogEntry::query()->where(EventDetailsFields::capacity()->greaterThan(3));
            self::fail('A meta field must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(LogEntry::class . '::query()->where() takes conditions on the columns of the table; conditions on meta fields and terms belong to post queries.', $exception->getMessage());
        }
        try {
            $query = Event::query()->where(F::attempts()->greaterThan(3));
            self::fail('A column must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(Event::class . '::query()->where() takes conditions on meta fields and terms; conditions on the columns of a table belong to its row query.', $exception->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(Any::class . '::of() mixes conditions of different kinds (meta fields, terms, columns of a table); a group holds conditions of one kind.');
        Any::of(F::attempts()->greaterThan(3), EventDetailsFields::capacity()->greaterThan(3));
    }
}
