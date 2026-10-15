<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use InvalidArgumentException;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\All;
use Saij\WpAppFramework\Query\Condition;
use Saij\WpAppFramework\Query\Direction;
use Saij\WpAppFramework\Query\Field\Field;
use Saij\WpAppFramework\Query\FieldCondition;
use Saij\WpAppFramework\Query\Group;

/**
 * A query for rows of a table, started with `LogEntry::query()` and returning row objects; it writes its SQL itself.
 *
 * A query never changes: every method returns a changed copy, so a query can be passed on and varied safely. Keep
 * the result – `$query->limit(3);` alone does nothing, and PHP warns about it (#[\NoDiscard]).
 *
 *     $rows = LogEntry::query()
 *         ->where(LogEntryFields::level()->equals(Level::Error))
 *         ->orderBy(LogEntryFields::created(), Direction::Desc)
 *         ->limit(20)
 *         ->get();
 *
 * Defaults: all rows, in the order of the primary key. Columns are compared as stored: strings without regard to
 * case (the collation of WordPress), NULL like PHP compares null – notEquals() and notIn() of a nullable column also
 * match rows without value. NULL sorts first in ascending order (MariaDB).
 *
 * @template T of AbstractRow
 *
 * @api Tables
 */
#[NoService]
final readonly class RowQuery
{
    /**
     * @param class-string<T>                     $class
     * @param list<Condition>                     $conditions
     * @param list<array{Field, Direction}>       $order
     */
    private function __construct(
        private string $class,
        private array $conditions = [],
        private array $order = [],
        private ?int $limit = null,
        private int $offset = 0,
    ) {}

    /**
     * @internal Start queries with the row class: `LogEntry::query()`.
     *
     * @template R of AbstractRow
     * @param class-string<R> $class
     * @return self<R>
     */
    public static function of(string $class): self
    {
        // The class must belong to a booted instance.
        TableRegistry::store($class)->table($class);

        return new self($class);
    }

    /**
     * Rows that meet all conditions (columns, Any, All); adds to the conditions of earlier calls.
     *
     * @param Condition ...$conditions Conditions created by the field classes of this table (a class with
     *                                 `#[TableFields]`), or groups of them with Any and All. Throws an
     *                                 InvalidArgumentException for conditions on meta fields or terms, or on a column
     *                                 of another table.
     */
    #[\NoDiscard]
    public function where(Condition ...$conditions): static
    {
        foreach ($conditions as $condition) {
            if (Group::kindOf($condition) !== 'row') {
                throw new InvalidArgumentException(sprintf('%s::query()->where() takes conditions on the columns of the table; conditions on meta fields and terms belong to post queries.', $this->class));
            }
            $this->check($condition);
        }

        return clone($this, ['conditions' => [...$this->conditions, ...array_values($conditions)]]);
    }

    /**
     * Sorts by a column; further calls sort rows that are equal so far. Without orderBy(), rows come in the order of
     * their primary key.
     *
     * @param Field     $field     A field of this table, from its class with `#[TableFields]`; a field of another table
     *                             throws an InvalidArgumentException.
     * @param Direction $direction Ascending (default) or descending.
     */
    #[\NoDiscard]
    public function orderBy(Field $field, Direction $direction = Direction::Asc): static
    {
        TableRegistry::store($this->class)->column($this->class, $field);

        return clone($this, ['order' => [...$this->order, [$field, $direction]]]);
    }

    /**
     * At most this many rows.
     *
     * @param int $limit The maximum number of rows, at least 1; a smaller number throws an InvalidArgumentException.
     */
    #[\NoDiscard]
    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(sprintf('%s::query()->limit() needs at least 1, %d given.', $this->class, $limit));
        }

        return clone($this, ['limit' => $limit]);
    }

    /**
     * Skips this many rows (with limit()).
     *
     * @param int $offset The number of rows to skip, at least 0; a negative number throws an InvalidArgumentException.
     *                    Takes effect only together with limit(); delete() does not allow it.
     */
    #[\NoDiscard]
    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('%s::query()->offset() needs at least 0, %d given.', $this->class, $offset));
        }

        return clone($this, ['offset' => $offset]);
    }

    /**
     * The matching rows.
     *
     * @return list<T>
     */
    public function get(): array
    {
        $store = TableRegistry::store($this->class);
        $store->ensure(true);

        return array_map(fn(array $row): AbstractRow => $store->hydrate($this->class, $row), $store->select($this->toSql()));
    }

    /**
     * The first matching row, null without one.
     *
     * @return T|null
     */
    public function first(): ?AbstractRow
    {
        return (clone($this, ['limit' => 1]))->get()[0] ?? null;
    }

    /**
     * The number of all matching rows; limit() and offset() do not count.
     */
    public function count(): int
    {
        $store = TableRegistry::store($this->class);
        $store->ensure(true);

        return $store->value($this->sql('SELECT COUNT(*) FROM %i', false, false));
    }

    /**
     * Deletes the matching rows (with limit(), the first ones in the order) and returns how many. Row objects loaded
     * before keep their primary key.
     */
    public function delete(): int
    {
        if ($this->offset > 0) {
            throw new LogicException(sprintf('%s::query()->delete() cannot skip rows; remove offset().', $this->class));
        }
        $store = TableRegistry::store($this->class);
        $store->ensure(true);

        return $store->execute($this->sql('DELETE FROM %i', $this->order !== [] || $this->limit !== null, true));
    }

    /**
     * @internal The SELECT of get().
     */
    public function toSql(): string
    {
        if ($this->offset > 0 && $this->limit === null) {
            throw new LogicException(sprintf('%s::query()->offset() needs limit().', $this->class));
        }

        return $this->sql('SELECT * FROM %i', true, true);
    }

    /**
     * @param literal-string $start The statement up to the table (`%i`).
     */
    private function sql(string $start, bool $order, bool $limit): string
    {
        $store = TableRegistry::store($this->class);
        $wpdb = TableStore::wpdb();
        $sql = $start;
        $args = [$store->fullName($this->class)];
        if ($this->conditions !== []) {
            [$where, $whereArgs] = $this->clause(All::of(...$this->conditions), true);
            $sql .= ' WHERE ' . $where;
            array_push($args, ...$whereArgs);
        }
        if ($order) {
            $orders = [];
            foreach ($this->order as [$field, $direction]) {
                $orders[] = $direction === Direction::Asc ? '%i ASC' : '%i DESC';
                $args[] = $store->column($this->class, $field)[0]['column'];
            }
            if ($orders === []) {
                $table = $store->table($this->class);
                $orders[] = '%i ASC';
                $args[] = $table['columns'][$table['primaryKey']]['column'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }
        if ($limit && $this->limit !== null) {
            $sql .= ' LIMIT %d';
            $args[] = $this->limit;
            if ($this->offset > 0 && !str_starts_with($start, 'DELETE')) {
                $sql .= ' OFFSET %d';
                $args[] = $this->offset;
            }
        }

        /** @var literal-string $sql Built from the fragments in this class; values and names are arguments. */
        return (string) $wpdb->prepare($sql, ...$args);
    }

    /**
     * SQL with placeholders and its arguments.
     *
     * @return array{string, list<int|string>}
     */
    private function clause(Condition $condition, bool $top = false): array
    {
        if ($condition instanceof Group) {
            $parts = [];
            $args = [];
            foreach ($condition->getConditions() as $inner) {
                [$sql, $innerArgs] = $this->clause($inner);
                $parts[] = $sql;
                array_push($args, ...$innerArgs);
            }
            $sql = implode(' ' . $condition->getRelation() . ' ', $parts);

            return [$top || count($parts) === 1 ? $sql : '(' . $sql . ')', $args];
        }
        if (!$condition instanceof FieldCondition) {
            throw new LogicException(sprintf('%s is no condition on a column.', $condition::class));
        }
        [$column, $primaryKey] = TableRegistry::store($this->class)->column($this->class, $condition->getField());
        $name = $column['column'];
        $nullable = $column['nullable'] && !$primaryKey;
        $values = $condition->getValues();
        $placeholders = [];
        $args = [$name];
        foreach ($values as $value) {
            $placeholders[] = is_int($value) ? '%d' : '%s';
            $args[] = match (true) {
                is_float($value) => RowCodec::float($value, $this->class . '::$' . $condition->getField()->getField()),
                // contains(): LIKE with the text escaped, so that % and _ match literally.
                $condition->getOperator() === 'LIKE' => '%' . TableStore::wpdb()->esc_like((string) $value) . '%',
                default => $value,
            };
        }
        $operator = $condition->getOperator();
        $sql = match ($operator) {
            'IS NULL', 'IS NOT NULL' => '%i ' . $operator,
            'IN', 'NOT IN' => sprintf('%%i %s (%s)', $operator, implode(', ', $placeholders)),
            'BETWEEN' => sprintf('%%i BETWEEN %s AND %s', $placeholders[0], $placeholders[1] ?? $placeholders[0]),
            default => sprintf('%%i %s %s', $operator, $placeholders[0]),
        };
        if ($operator === 'BETWEEN' && count($values) === 1) {
            $args[] = $args[1];
        }
        // Like `null !== $value` in PHP: a row without value is not equal to any value.
        if ($nullable && in_array($operator, ['!=', 'NOT IN'], true)) {
            $sql = sprintf('(%s OR %%i IS NULL)', $sql);
            $args[] = $name;
        }

        return [$sql, $args];
    }

    /**
     * Checks every field of a condition against the row class.
     */
    private function check(Condition $condition): void
    {
        if ($condition instanceof Group) {
            array_map($this->check(...), $condition->getConditions());

            return;
        }
        if ($condition instanceof FieldCondition) {
            TableRegistry::store($this->class)->column($this->class, $condition->getField());
        }
    }
}
