<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use Closure;
use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Project\NamePrefix;
use wpdb;

/**
 * Brings the tables of an instance on the current site in line with the row classes, without dbDelta():
 *
 * 1. Tables named in `renamedFrom` are renamed if the new name does not exist yet.
 * 2. Each table is created if it does not exist, read (DESCRIBE, SHOW INDEX) and changed in one ALTER TABLE: earlier
 *    columns of #[RenamedFrom] are renamed, missing columns and indexes added, different ones changed, columns and
 *    indexes that are not declared dropped. Changes that lose data (shorter strings, NOT NULL) are applied too; the
 *    number of values MariaDB cut or set is logged.
 * 3. The table is read again; a remaining difference is an error ("did not converge").
 * 4. Tables managed so far that are no longer declared are dropped – in the current namespace only.
 *
 * Every change is logged. MariaDB applies an ALTER TABLE with several clauses completely or not at all; DDL is not
 * transactional across statements, so a sync that fails half-way starts the next time by reading the tables again.
 *
 * @internal
 *
 * @phpstan-import-type TableEntry from TableCollector
 * @phpstan-import-type ColumnEntry from TableCollector
 * @phpstan-type ActualColumn array{type: string, nullable: bool, default: string|null, extra: string}
 * @phpstan-type ActualIndex array{columns: list<string>, unique: bool, partial: bool}
 */
#[NoService]
final readonly class SchemaSync
{
    /** Part of the schema hash: a new version of this mechanism syncs every table once. */
    public const string MECHANICS = '1';

    /**
     * @param Closure(string): void $log
     */
    public function __construct(
        private Closure $log,
    ) {}

    /**
     * @param non-empty-string                              $namespace
     * @param array<class-string<AbstractRow>, TableEntry> $tables
     * @param non-empty-string|null                         $previousNamespace The namespace of the last sync, null without one.
     * @param list<string>                                  $managed           The local names of the tables of the last sync.
     * @return list<non-empty-string> The local names of the tables now managed.
     */
    public function sync(wpdb $wpdb, string $namespace, array $tables, ?string $previousNamespace, array $managed): array
    {
        $base = $wpdb->prefix . NamePrefix::data($namespace);
        $suppressed = $wpdb->suppress_errors(true);
        try {
            $existing = $this->existing($wpdb, $base);
            $names = [];
            $earlier = [];
            foreach ($tables as $table) {
                $full = $base . $table['name'];
                $names[] = $table['name'];
                foreach ($table['renamedFrom'] as $old) {
                    $earlier[$old] = $table['name'];
                    if (!in_array($base . $old, $existing, true)) {
                        continue;
                    }
                    if (in_array($full, $existing, true)) {
                        ($this->log)(sprintf('%s and its earlier name %s both exist; %s stays as it is, merge it by hand and drop it.', $full, $base . $old, $base . $old));
                        continue;
                    }
                    $this->run($wpdb, sprintf('RENAME TABLE %s TO %s', self::id($base . $old), self::id($full)), $full);
                    ($this->log)(sprintf('renamed %s to %s.', $base . $old, $full));
                    $existing = [...array_diff($existing, [$base . $old]), $full];
                }
                $this->table($wpdb, $full, $table, !in_array($full, $existing, true));
            }

            if ($previousNamespace !== null && $previousNamespace !== $namespace) {
                if ($managed !== []) {
                    ($this->log)(sprintf(
                        'the namespace changed from %s to %s; the tables of %s stay and are no longer managed: %s.',
                        $previousNamespace,
                        $namespace,
                        $previousNamespace,
                        implode(', ', array_map(static fn(string $name): string => $wpdb->prefix . NamePrefix::data($previousNamespace) . $name, $managed)),
                    ));
                }
            } else {
                foreach (array_diff($managed, $names, array_keys($earlier)) as $name) {
                    $this->run($wpdb, sprintf('DROP TABLE IF EXISTS %s', self::id($base . $name)), $base . $name);
                    ($this->log)(sprintf('dropped %s; no row class declares it any more.', $base . $name));
                }
            }

            return $names;
        } finally {
            $wpdb->suppress_errors($suppressed);
        }
    }

    /**
     * @param non-empty-string $full
     * @param TableEntry       $table
     */
    private function table(wpdb $wpdb, string $full, array $table, bool $create): void
    {
        if ($create) {
            $this->run($wpdb, $this->create($wpdb, $full, $table), $full);
            ($this->log)(sprintf('created %s.', $full));
        }
        [$clauses, $countRows] = $this->diff($wpdb, $full, $table, ...$this->read($wpdb, $full));
        if ($clauses === []) {
            return;
        }
        $rows = $countRows ? (int) $wpdb->get_var(sprintf('SELECT COUNT(*) FROM %s', self::id($full))) : 0;
        $this->run($wpdb, sprintf('ALTER TABLE %s %s', self::id($full), implode(', ', $clauses)), $full);
        $warnings = (int) $wpdb->get_var('SELECT @@warning_count');
        ($this->log)(sprintf('altered %s: %s.', $full, implode(', ', $clauses)));
        if ($warnings > 0) {
            ($this->log)(sprintf('%s: MariaDB cut or set %d values to fit the changed columns (shorter strings, NULL in NOT NULL columns).', $full, $warnings));
        }
        if ($rows > 0) {
            ($this->log)(sprintf('%s: %d rows got the implicit value (0, an empty string or the zero date) in new NOT NULL columns without default.', $full, $rows));
        }

        [$remaining] = $this->diff($wpdb, $full, $table, ...$this->read($wpdb, $full));
        if ($remaining !== []) {
            throw new RuntimeException(sprintf('The table %s did not converge to its row class; still different: %s.', $full, implode(', ', $remaining)));
        }
    }

    /**
     * @param TableEntry $table
     */
    private function create(wpdb $wpdb, string $full, array $table): string
    {
        $definitions = [];
        foreach ($table['columns'] as $property => $column) {
            $definitions[] = $this->definition($wpdb, $column, $property === $table['primaryKey']);
        }
        $definitions[] = sprintf('PRIMARY KEY (%s)', self::id($table['columns'][$table['primaryKey']]['column']));
        foreach ($table['indexes'] as $index) {
            $definitions[] = self::index($index['name'], $index['columns'], $index['unique']);
        }

        return sprintf('CREATE TABLE IF NOT EXISTS %s (%s) %s', self::id($full), implode(', ', $definitions), $wpdb->get_charset_collate());
    }

    /**
     * The clauses of the ALTER TABLE and whether NOT NULL columns without default are added (their rows are counted).
     *
     * @param TableEntry                 $table
     * @param array<string, ActualColumn> $columns
     * @param array<string, ActualIndex>  $indexes
     * @return array{list<string>, bool}
     */
    private function diff(wpdb $wpdb, string $full, array $table, array $columns, array $indexes): array
    {
        $before = [];
        $changes = [];
        $after = [];
        $countRows = false;
        $handled = [];
        /** @var array<string, string> $renames Old column => new column. */
        $renames = [];
        foreach ($table['columns'] as $property => $column) {
            $primaryKey = $property === $table['primaryKey'];
            $definition = $this->definition($wpdb, $column, $primaryKey);
            $name = $column['column'];
            if (isset($columns[$name])) {
                $handled[$name] = true;
                foreach ($column['renamedFrom'] as $old) {
                    if (isset($columns[$old]) && !isset($handled[$old])) {
                        $handled[$old] = true;
                        ($this->log)(sprintf('%s has the column %s and its earlier name %s; %s stays as it is, merge it by hand and drop it.', $full, $name, $old, $old));
                    }
                }
                if (!self::matches($columns[$name], $column, $primaryKey)) {
                    $changes[] = 'MODIFY COLUMN ' . $definition;
                }
                continue;
            }
            $old = null;
            foreach ($column['renamedFrom'] as $candidate) {
                if (isset($columns[$candidate]) && !isset($handled[$candidate])) {
                    $old = $candidate;
                    break;
                }
            }
            if ($old !== null) {
                $handled[$old] = true;
                $renames[$old] = $name;
                $changes[] = sprintf('CHANGE COLUMN %s %s', self::id($old), $definition);
                continue;
            }
            $changes[] = 'ADD COLUMN ' . $definition;
            $countRows = $countRows || (!$column['nullable'] && $column['default'] === null && !$primaryKey);
        }
        foreach (array_keys($columns) as $name) {
            if (!isset($handled[$name])) {
                $changes[] = 'DROP COLUMN ' . self::id($name);
            }
        }

        // Renamed columns stay in their indexes; dropped columns leave them.
        $primary = $table['columns'][$table['primaryKey']]['column'];
        $actualPrimary = isset($indexes['PRIMARY']) ? self::renamed($indexes['PRIMARY']['columns'], $renames) : null;
        if ($actualPrimary !== [$primary]) {
            if ($actualPrimary !== null) {
                $before[] = 'DROP PRIMARY KEY';
            }
            $after[] = sprintf('ADD PRIMARY KEY (%s)', self::id($primary));
        }
        $declared = [];
        foreach ($table['indexes'] as $index) {
            $declared[$index['name']] = true;
            $actual = $indexes[$index['name']] ?? null;
            if ($actual !== null && self::renamed($actual['columns'], $renames) === $index['columns'] && $actual['unique'] === $index['unique'] && !$actual['partial']) {
                continue;
            }
            if ($actual !== null) {
                $before[] = 'DROP INDEX ' . self::id($index['name']);
            }
            $after[] = 'ADD ' . self::index($index['name'], $index['columns'], $index['unique']);
        }
        foreach (array_keys($indexes) as $name) {
            if ($name !== 'PRIMARY' && !isset($declared[$name])) {
                $before[] = 'DROP INDEX ' . self::id($name);
            }
        }

        return [[...$before, ...$changes, ...$after], $countRows];
    }

    /**
     * @param list<string>          $names
     * @param array<string, string> $renames
     * @return list<string>
     */
    private static function renamed(array $names, array $renames): array
    {
        return array_map(static fn(string $name): string => $renames[$name] ?? $name, $names);
    }

    /**
     * @param ActualColumn $actual
     * @param ColumnEntry  $column
     */
    private static function matches(array $actual, array $column, bool $primaryKey): bool
    {
        // Integer types without display width: MySQL 8.0.19 and later leave it out (bigint instead of bigint(20)).
        $normalize = static fn(string $type): string => (string) preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower(trim($type)));
        $declared = $column['default'];
        $default = match (true) {
            $declared === null || $actual['default'] === null => $declared === $actual['default'],
            is_float($declared) => is_numeric($actual['default']) && (float) $actual['default'] === $declared,
            is_int($declared) => $actual['default'] === (string) $declared,
            default => $actual['default'] === $declared,
        };

        return $normalize($actual['type']) === $normalize($column['sql'])
            && $actual['nullable'] === $column['nullable']
            && $default
            && strtolower($actual['extra']) === ($primaryKey ? 'auto_increment' : '');
    }

    /**
     * @param ColumnEntry $column
     */
    private function definition(wpdb $wpdb, array $column, bool $primaryKey): string
    {
        if ($primaryKey) {
            return sprintf('%s %s NOT NULL AUTO_INCREMENT', self::id($column['column']), $column['sql']);
        }
        $default = $column['default'];
        $literal = match (true) {
            $default === null => '',
            is_int($default) => ' DEFAULT ' . $default,
            is_float($default) => ' DEFAULT ' . RowCodec::float($default, $column['column']),
            // prepare() escapes % for a later query(); the definition is also logged.
            default => ' DEFAULT ' . $wpdb->remove_placeholder_escape((string) $wpdb->prepare('%s', $default)),
        };

        return sprintf('%s %s %s%s', self::id($column['column']), $column['sql'], $column['nullable'] ? 'NULL' : 'NOT NULL', $literal);
    }

    /**
     * @param list<string> $columns
     */
    private static function index(string $name, array $columns, bool $unique): string
    {
        return sprintf('%sKEY %s (%s)', $unique ? 'UNIQUE ' : '', self::id($name), implode(', ', array_map(self::id(...), $columns)));
    }

    /**
     * @return array{array<string, ActualColumn>, array<string, ActualIndex>}
     */
    private function read(wpdb $wpdb, string $full): array
    {
        $columns = [];
        foreach ($this->results($wpdb, sprintf('DESCRIBE %s', self::id($full)), $full) as $row) {
            $columns[self::string($row['Field'] ?? null)] = [
                'type' => self::string($row['Type'] ?? null),
                'nullable' => ($row['Null'] ?? null) === 'YES',
                'default' => isset($row['Default']) ? self::string($row['Default']) : null,
                'extra' => self::string($row['Extra'] ?? ''),
            ];
        }
        $indexes = [];
        foreach ($this->results($wpdb, sprintf('SHOW INDEX FROM %s', self::id($full)), $full) as $row) {
            $name = self::string($row['Key_name'] ?? null);
            $indexes[$name] ??= ['columns' => [], 'unique' => (string) self::string($row['Non_unique'] ?? null) === '0', 'partial' => false];
            $indexes[$name]['columns'][(int) self::string($row['Seq_in_index'] ?? null)] = self::string($row['Column_name'] ?? null);
            $indexes[$name]['partial'] = $indexes[$name]['partial'] || ($row['Sub_part'] ?? null) !== null;
        }
        foreach ($indexes as $name => $index) {
            ksort($index['columns']);
            $indexes[$name]['columns'] = array_values($index['columns']);
        }

        return [$columns, $indexes];
    }

    /**
     * The full names of the existing tables of the namespace.
     *
     * @return list<string>
     */
    private function existing(wpdb $wpdb, string $base): array
    {
        $names = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($base) . '%'));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException(sprintf('The tables %s… cannot be listed: %s', $base, $wpdb->last_error));
        }

        return array_values(array_map(self::string(...), $names));
    }

    /**
     * @return list<array<mixed>>
     */
    private function results(wpdb $wpdb, string $query, string $full): array
    {
        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows) || $wpdb->last_error !== '') {
            throw new RuntimeException(sprintf('%s failed for %s: %s', $query, $full, $wpdb->last_error));
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    private function run(wpdb $wpdb, string $query, string $full): void
    {
        if ($wpdb->query($query) === false) {
            throw new RuntimeException(sprintf('The statement for %s failed: %s (%s)', $full, $wpdb->last_error, $query));
        }
    }

    /**
     * An identifier in backticks; names come from the build (letters, digits, "_") and the table prefix.
     */
    private static function id(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
