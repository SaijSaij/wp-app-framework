<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Support;

use RuntimeException;

/**
 * An in-memory database behind the wpdb test double: tables with columns, indexes and rows, and the statements the
 * framework writes – in the exact form it writes them, not as a SQL parser. It behaves like MariaDB where the schema
 * sync depends on it: DESCRIBE and SHOW INDEX, an ALTER TABLE applied completely or not at all, values cut or set
 * implicitly with a warning each, lost columns leaving their indexes.
 *
 * Install it with use(); every statement is recorded.
 *
 * @phpstan-type Column array{type: string, nullable: bool, default: string|null, extra: string}
 * @phpstan-type Index array{columns: list<string>, unique: bool}
 * @phpstan-type Table array{columns: array<string, Column>, indexes: array<string, Index>, rows: list<array<string, string|null>>, next: int}
 */
final class Database
{
    /** @var array<string, Table> */
    private array $tables = [];

    /** @var list<string> */
    private array $queries = [];

    /** @var array<string, string> Rows of the options table written by SQL (the lock), by name. */
    private array $options = [];

    /** @var array<string, string> Pattern => error of statements that fail. */
    private array $failures = [];

    /** @var array<string, list<array<string, string|null>>|int|string> Pattern => result of SELECT and DELETE queries of rows. */
    private array $answers = [];

    /** @var list<string> Tables whose ALTER TABLE succeeds without changing anything. */
    private array $frozen = [];

    private int $warnings = 0;

    public static function use(): self
    {
        $database = new self();
        $GLOBALS['wp_app_framework_test_database'] = $database;

        return $database;
    }

    public static function reset(): void
    {
        unset($GLOBALS['wp_app_framework_test_database']);
    }

    /**
     * Creates a table as it would be after the statement.
     */
    public function create(string $statement): self
    {
        $this->run($statement);

        return $this;
    }

    /**
     * @param array<string, string|null> $row
     */
    public function insert(string $table, array $row): int
    {
        $name = $this->table($table);
        $table = $this->tables[$name];
        $id = $table['next'];
        $full = [];
        foreach ($table['columns'] as $column => $definition) {
            $full[$column] = array_key_exists($column, $row) ? $row[$column] : ($definition['extra'] === 'auto_increment' ? (string) $id : $definition['default']);
        }
        $table['rows'][] = $full;
        $table['next'] = $id + 1;
        $this->tables[$name] = $table;

        return $id;
    }

    /**
     * @param array<string, string|null> $data
     * @param array<string, string>      $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $table = $this->table($table);
        $count = 0;
        foreach ($this->tables[$table]['rows'] as $index => $row) {
            if (array_intersect_assoc($where, array_map(strval(...), $row)) === $where) {
                $this->tables[$table]['rows'][$index] = [...$row, ...$data];
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, string> $where
     */
    public function delete(string $table, array $where): int
    {
        $table = $this->table($table);
        $before = count($this->tables[$table]['rows']);
        $this->tables[$table]['rows'] = array_values(array_filter(
            $this->tables[$table]['rows'],
            static fn(array $row): bool => array_intersect_assoc($where, array_map(strval(...), $row)) !== $where,
        ));

        return $before - count($this->tables[$table]['rows']);
    }

    /**
     * Statements matching the pattern fail with the error.
     */
    public function fail(string $pattern, string $error): self
    {
        $this->failures[$pattern] = $error;

        return $this;
    }

    /**
     * The result of row queries (SELECT … FROM, DELETE FROM) matching the pattern: rows, a count or a value.
     *
     * @param list<array<string, string|null>>|int|string $result
     */
    public function answer(string $pattern, array|int|string $result): self
    {
        $this->answers[$pattern] = $result;

        return $this;
    }

    /**
     * ALTER TABLE of the table succeeds without changing anything.
     */
    public function freeze(string $table): self
    {
        $this->frozen[] = $table;

        return $this;
    }

    public function lock(string $name, string $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function getLock(string $name): ?string
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Forgets the statements recorded so far, e.g. of the setup of a test.
     */
    public function forget(): self
    {
        $this->queries = [];

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * The statements that change the schema.
     *
     * @return list<string>
     */
    public function getChanges(): array
    {
        return array_values(array_filter($this->queries, static fn(string $query): bool => preg_match('/^(CREATE|ALTER|DROP|RENAME)/', $query) === 1));
    }

    /**
     * @return list<string>
     */
    public function getTableNames(): array
    {
        return array_keys($this->tables);
    }

    /**
     * The columns as DESCRIBE shows them: name => type, NULL/NOT NULL, default.
     *
     * @return array<string, string>
     */
    public function describe(string $table): array
    {
        $result = [];
        foreach ($this->tables[$this->table($table)]['columns'] as $name => $column) {
            $result[$name] = trim(sprintf('%s %s%s %s', $column['type'], $column['nullable'] ? 'NULL' : 'NOT NULL', $column['default'] === null ? '' : ' DEFAULT ' . $column['default'], $column['extra']));
        }

        return $result;
    }

    /**
     * The indexes: name => columns, with "!" for unique ones.
     *
     * @return array<string, string>
     */
    public function indexes(string $table): array
    {
        return array_map(
            static fn(array $index): string => implode(',', $index['columns']) . ($index['unique'] ? '!' : ''),
            $this->tables[$this->table($table)]['indexes'],
        );
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function rows(string $table): array
    {
        return $this->tables[$this->table($table)]['rows'];
    }

    /**
     * Runs a statement.
     *
     * @return array{result: list<array<string, string|null>>|int|bool|string|null, error: string, affected: int}
     */
    public function run(string $sql): array
    {
        $this->queries[] = $sql;
        foreach ($this->failures as $pattern => $error) {
            if (preg_match($pattern, $sql) === 1) {
                return ['result' => false, 'error' => $error, 'affected' => 0];
            }
        }
        try {
            return $this->statement($sql);
        } catch (RuntimeException $exception) {
            return ['result' => false, 'error' => $exception->getMessage(), 'affected' => 0];
        }
    }

    /**
     * @return array{result: list<array<string, string|null>>|int|bool|string|null, error: string, affected: int}
     */
    private function statement(string $sql): array
    {
        $ok = self::ok(...);
        if (preg_match("/^SHOW TABLES LIKE '(.*)'$/", $sql, $match) === 1) {
            $pattern = '/^' . self::like($match[1]) . '$/';

            return $ok(array_map(static fn(string $name): array => ['name' => $name], array_values(array_filter(array_keys($this->tables), static fn(string $name): bool => preg_match($pattern, $name) === 1))));
        }
        if (preg_match('/^CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*)\) DEFAULT CHARACTER SET \S+ COLLATE \S+$/s', $sql, $match) === 1) {
            if (!isset($this->tables[$match[1]])) {
                $table = ['columns' => [], 'indexes' => [], 'rows' => [], 'next' => 1];
                foreach (self::split($match[2]) as $part) {
                    $table = self::clause($table, str_starts_with($part, '`') ? 'ADD COLUMN ' . $part : 'ADD ' . $part);
                }
                $this->tables[$match[1]] = $table;
            }

            return $ok(true);
        }
        if (preg_match('/^DESCRIBE `([^`]+)`$/', $sql, $match) === 1) {
            $rows = [];
            foreach ($this->tables[$this->table($match[1])]['columns'] as $name => $column) {
                $rows[] = ['Field' => $name, 'Type' => $column['type'], 'Null' => $column['nullable'] ? 'YES' : 'NO', 'Key' => '', 'Default' => $column['default'], 'Extra' => $column['extra']];
            }

            return $ok($rows);
        }
        if (preg_match('/^SHOW INDEX FROM `([^`]+)`$/', $sql, $match) === 1) {
            $rows = [];
            foreach ($this->tables[$this->table($match[1])]['indexes'] as $name => $index) {
                foreach ($index['columns'] as $position => $column) {
                    $rows[] = ['Table' => $match[1], 'Non_unique' => $index['unique'] ? '0' : '1', 'Key_name' => $name, 'Seq_in_index' => (string) ($position + 1), 'Column_name' => $column, 'Sub_part' => null];
                }
            }

            return $ok($rows);
        }
        if (preg_match('/^ALTER TABLE `([^`]+)` (.*)$/s', $sql, $match) === 1) {
            $name = $this->table($match[1]);
            if (in_array($name, $this->frozen, true)) {
                return $ok(true);
            }
            $this->warnings = 0;
            $table = $this->tables[$name];
            foreach (self::split($match[2]) as $clause) {
                $table = $this->alter($table, $clause);
            }
            $this->tables[$name] = $table;

            return $ok(true);
        }
        if ($sql === 'SELECT @@warning_count') {
            return $ok((string) $this->warnings);
        }
        if (preg_match('/^RENAME TABLE `([^`]+)` TO `([^`]+)`$/', $sql, $match) === 1) {
            $this->tables[$match[2]] = $this->tables[$this->table($match[1])];
            unset($this->tables[$match[1]]);

            return $ok(0);
        }
        if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`$/', $sql, $match) === 1) {
            unset($this->tables[$match[1]]);

            return $ok(true);
        }
        if (preg_match("/^INSERT IGNORE INTO `wp_options` \\(`option_name`, `option_value`, `autoload`\\) VALUES \\('([^']+)', '([^']+)', 'off'\\)$/", $sql, $match) === 1) {
            if (isset($this->options[$match[1]])) {
                return $ok(0);
            }
            $this->options[$match[1]] = $match[2];

            return $ok(1, 1);
        }
        if (preg_match("/^SELECT `option_value` FROM `wp_options` WHERE `option_name` = '([^']+)'$/", $sql, $match) === 1) {
            return $ok(isset($this->options[$match[1]]) ? [['option_value' => $this->options[$match[1]]]] : []);
        }
        if (preg_match("/^UPDATE `wp_options` SET `option_value` = '([^']+)' WHERE `option_name` = '([^']+)' AND `option_value` = '([^']*)'$/", $sql, $match) === 1) {
            if (($this->options[$match[2]] ?? null) !== $match[3]) {
                return $ok(0);
            }
            $this->options[$match[2]] = $match[1];

            return $ok(1, 1);
        }
        if (preg_match("/^DELETE FROM `wp_options` WHERE `option_name` = '([^']+)'$/", $sql, $match) === 1) {
            $found = isset($this->options[$match[1]]);
            unset($this->options[$match[1]]);

            return $ok($found ? 1 : 0, $found ? 1 : 0);
        }
        // Recorded by the wpdb test double before it calls insert(), update() or delete().
        if (preg_match('/^(INSERT INTO|UPDATE|DELETE FROM) `[^`]+` (\(|SET |BY )/', $sql) === 1) {
            return $ok(true);
        }
        foreach ($this->answers as $pattern => $result) {
            if (preg_match($pattern, $sql) === 1) {
                return $ok($result, is_int($result) ? $result : 0);
            }
        }
        if (preg_match('/^SELECT COUNT\(\*\) FROM `([^`]+)`$/', $sql, $match) === 1) {
            return $ok((string) count($this->tables[$this->table($match[1])]['rows']));
        }
        if (preg_match("/^SELECT \\* FROM `([^`]+)` WHERE `([^`]+)` = (\\d+)$/", $sql, $match) === 1) {
            return $ok(array_values(array_filter($this->tables[$this->table($match[1])]['rows'], static fn(array $row): bool => $row[$match[2]] === $match[3])));
        }
        if (preg_match('/^SELECT \* FROM `([^`]+)`/', $sql, $match) === 1) {
            return $ok($this->tables[$this->table($match[1])]['rows']);
        }

        throw new RuntimeException('The test database does not know the statement: ' . $sql);
    }

    /**
     * @param list<array<string, string|null>>|int|bool|string|null $result
     * @return array{result: list<array<string, string|null>>|int|bool|string|null, error: string, affected: int}
     */
    private static function ok(array|int|bool|string|null $result, int $affected = 0): array
    {
        return ['result' => $result, 'error' => '', 'affected' => $affected];
    }

    /**
     * @param Table $table
     * @return Table
     */
    private function alter(array $table, string $clause): array
    {
        if (preg_match('/^MODIFY COLUMN `([^`]+)` /', $clause, $match) === 1 || preg_match('/^CHANGE COLUMN `([^`]+)` /', $clause, $match) === 1) {
            if (!isset($table['columns'][$match[1]])) {
                throw new RuntimeException(sprintf("Unknown column '%s'", $match[1]));
            }
            [$name, $column] = self::column(str_starts_with($clause, 'MODIFY') ? substr($clause, strlen('MODIFY COLUMN ')) : substr($clause, strlen($match[0])));
            $old = $match[1];
            $columns = [];
            foreach ($table['columns'] as $existing => $definition) {
                $columns[$existing === $old ? $name : $existing] = $existing === $old ? $column : $definition;
            }
            $table['columns'] = $columns;
            foreach ($table['rows'] as $index => $row) {
                $value = $row[$old];
                unset($row[$old]);
                $row[$name] = $this->fit($value, $column);
                $table['rows'][$index] = $row;
            }
            foreach ($table['indexes'] as $key => $index) {
                $table['indexes'][$key]['columns'] = array_map(static fn(string $each): string => $each === $old ? $name : $each, $index['columns']);
            }

            return $table;
        }
        if (str_starts_with($clause, 'ADD COLUMN ')) {
            return self::clause($table, $clause, $this);
        }
        if (preg_match('/^DROP COLUMN `([^`]+)`$/', $clause, $match) === 1) {
            if (!isset($table['columns'][$match[1]])) {
                throw new RuntimeException(sprintf("Can't DROP COLUMN `%s`; check that it exists", $match[1]));
            }
            unset($table['columns'][$match[1]]);
            foreach ($table['rows'] as $index => $row) {
                unset($table['rows'][$index][$match[1]]);
            }
            foreach ($table['indexes'] as $key => $index) {
                $table['indexes'][$key]['columns'] = array_values(array_diff($index['columns'], [$match[1]]));
                if ($table['indexes'][$key]['columns'] === []) {
                    unset($table['indexes'][$key]);
                }
            }

            return $table;
        }
        if (preg_match('/^DROP INDEX `([^`]+)`$/', $clause, $match) === 1 || $clause === 'DROP PRIMARY KEY') {
            $key = $match[1] ?? 'PRIMARY';
            if (!isset($table['indexes'][$key])) {
                throw new RuntimeException(sprintf("Can't DROP INDEX `%s`; check that it exists", $key));
            }
            unset($table['indexes'][$key]);

            return $table;
        }

        return self::clause($table, $clause);
    }

    /**
     * ADD COLUMN and ADD … KEY, also for CREATE TABLE.
     *
     * @param Table $table
     * @return Table
     */
    private static function clause(array $table, string $clause, ?self $database = null): array
    {
        if (str_starts_with($clause, 'ADD COLUMN ')) {
            [$name, $column] = self::column(substr($clause, 11));
            if (isset($table['columns'][$name])) {
                throw new RuntimeException(sprintf("Duplicate column name '%s'", $name));
            }
            $table['columns'][$name] = $column;
            foreach ($table['rows'] as $index => $row) {
                $table['rows'][$index][$name] = $column['extra'] === 'auto_increment' ? (string) ($index + 1) : ($column['default'] ?? ($column['nullable'] ? null : self::implicit($column['type'])));
            }

            return $table;
        }
        if (preg_match('/^ADD (PRIMARY KEY|UNIQUE KEY `([^`]+)`|KEY `([^`]+)`) \((.+)\)$/', $clause, $match) === 1) {
            $name = $match[1] === 'PRIMARY KEY' ? 'PRIMARY' : ($match[2] !== '' ? $match[2] : $match[3]);
            if (isset($table['indexes'][$name])) {
                throw new RuntimeException(sprintf("Duplicate key name '%s'", $name));
            }
            $columns = array_map(static fn(string $column): string => trim($column, ' `'), explode(',', $match[4]));
            foreach ($columns as $column) {
                if (!isset($table['columns'][$column])) {
                    throw new RuntimeException(sprintf("Key column '%s' doesn't exist in table", $column));
                }
            }
            $table['indexes'][$name] = ['columns' => $columns, 'unique' => $name === 'PRIMARY' || $match[2] !== ''];

            return $table;
        }

        throw new RuntimeException('The test database does not know the clause: ' . $clause);
    }

    /**
     * @return array{string, Column}
     */
    private static function column(string $definition): array
    {
        if (preg_match("/^`([^`]+)` (.+?) (NOT NULL|NULL)(?: DEFAULT ('(?:[^'\\\\]|\\\\.)*'|\\S+))?( AUTO_INCREMENT)?$/", $definition, $match) !== 1) {
            throw new RuntimeException('The test database does not know the column: ' . $definition);
        }
        $default = $match[4] ?? '';

        return [$match[1], [
            'type' => $match[2],
            'nullable' => $match[3] === 'NULL',
            'default' => $default === '' ? null : (str_starts_with($default, "'") ? stripslashes(substr($default, 1, -1)) : $default),
            'extra' => ($match[5] ?? '') !== '' ? 'auto_increment' : '',
        ]];
    }

    /**
     * A value after MODIFY or CHANGE: cut to a shorter string, the implicit value instead of NULL in a NOT NULL
     * column – each with a warning, like MariaDB without strict mode.
     *
     * @param Column $column
     */
    private function fit(?string $value, array $column): ?string
    {
        if ($value === null) {
            if ($column['nullable']) {
                return null;
            }
            $this->warnings++;

            return self::implicit($column['type']);
        }
        if (preg_match('/^varchar\((\d+)\)$/', $column['type'], $match) === 1 && mb_strlen($value) > (int) $match[1]) {
            $this->warnings++;

            return mb_substr($value, 0, (int) $match[1]);
        }

        return $value;
    }

    private static function implicit(string $type): string
    {
        return match (true) {
            $type === 'datetime' => '0000-00-00 00:00:00',
            str_starts_with($type, 'varchar'), $type === 'longtext' => '',
            default => '0',
        };
    }

    /**
     * Splits a list of clauses or definitions at commas outside of parentheses and quotes.
     *
     * @return list<string>
     */
    private static function split(string $list): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quoted = false;
        $length = strlen($list);
        for ($i = 0; $i < $length; $i++) {
            $char = $list[$i];
            if ($quoted) {
                $current .= $char;
                if ($char === '\\') {
                    $current .= $list[++$i];
                } elseif ($char === "'") {
                    $quoted = false;
                }
                continue;
            }
            if ($char === "'") {
                $quoted = true;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);

        return $parts;
    }

    /**
     * A LIKE pattern as regular expression; wpdb::esc_like() escapes % and _ with a backslash.
     */
    private static function like(string $pattern): string
    {
        $pattern = stripslashes(str_replace(['\\\\%', '\\\\_'], ["\0", "\1"], $pattern));
        $regex = preg_quote($pattern, '/');

        return str_replace(['%', '_', "\0", "\1"], ['.*', '.', '%', '_'], $regex);
    }

    private function table(string $name): string
    {
        if (!isset($this->tables[$name])) {
            throw new RuntimeException(sprintf("Table '%s' doesn't exist", $name));
        }

        return $name;
    }
}
