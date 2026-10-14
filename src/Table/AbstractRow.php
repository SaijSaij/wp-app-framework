<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use InvalidArgumentException;

/**
 * The object of a row of a table of your own: a class with #[Table] extends it, and its promoted constructor
 * parameters are the columns.
 *
 * A row object is loaded with `LogEntry::get($id)` or `LogEntry::find($id)`, found with `LogEntry::query()` and
 * created with `new LogEntry(…)`. save() inserts a new row and sets its primary key, or writes the changed columns of
 * a loaded one; a row object with a primary key whose row does not exist is inserted with that key. delete() deletes
 * it.
 *
 * Every get(), find() and query returns new objects; when two of them change the same row, the last save() of a
 * column wins. save() and delete() check no capabilities.
 */
abstract class AbstractRow
{
    /**
     * The row with the primary key.
     *
     * @throws InvalidArgumentException If it does not exist.
     */
    public static function get(int $id): static
    {
        return static::find($id) ?? throw new InvalidArgumentException(sprintf('There is no %s with the ID %d (table %s).', static::class, $id, static::tableName()));
    }

    /**
     * The row with the primary key, null if it does not exist.
     */
    public static function find(int $id): ?static
    {
        return TableRegistry::store(static::class)->find(static::class, $id);
    }

    /**
     * A query for rows of the table: all rows in the order of their primary key, see RowQuery.
     *
     * @return RowQuery<static>
     */
    public static function query(): RowQuery
    {
        return RowQuery::of(static::class);
    }

    /**
     * The full name of the table on the current site, for SQL of your own: `{$wpdb->prefix}{namespace}_{name}`.
     *
     * @return non-empty-string
     */
    public static function tableName(): string
    {
        return TableRegistry::store(static::class)->tableName(static::class);
    }

    /**
     * Inserts the row and sets its primary key, or writes the columns changed since it was loaded or saved; without
     * changes, nothing is written. With a primary key whose row does not exist (set in `new`, or the row was deleted
     * in the meantime and the object changed since), the row is inserted with that primary key and all columns.
     *
     * @throws \RuntimeException If the database refuses the row, e.g. a string longer than its column.
     */
    public function save(): void
    {
        TableRegistry::store(static::class)->save($this);
    }

    /**
     * Deletes the row; its primary key is null afterwards, so save() would insert it again.
     *
     * @throws \LogicException If the row has not been saved yet.
     */
    public function delete(): void
    {
        TableRegistry::store(static::class)->delete($this);
    }
}
