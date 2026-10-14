<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Field\Field;
use Saij\WpAppFramework\Query\Field\FieldTypes;
use Saij\WpAppFramework\Value\ValueFactory;
use Throwable;
use WeakMap;
use wpdb;

/**
 * The tables of one instance at run time: keeps them in line with the row classes and loads, saves and deletes rows.
 *
 * Whether the tables are current costs no database query: the option `{slug}_tables` is autoloaded and holds the
 * hash of the schema of the last sync; the manifest holds the hash of the row classes. Only when they differ does
 * ensure() sync the tables (SchemaSync), under a lock, and store the new hash. It runs on `init` and before every
 * access to a table, once per site and request (also after switch_to_blog() and before `init`), never while
 * WordPress installs.
 *
 * The sync is skipped – and an access fails with an exception that names the reason – while another request holds
 * the lock, for five minutes after a failed sync and when the code is older than the code that synced last (a worker
 * of the previous release during a deployment).
 *
 * @internal
 *
 * @phpstan-import-type TablesData from TableCollector
 * @phpstan-import-type TableEntry from TableCollector
 * @phpstan-import-type ColumnEntry from TableCollector
 * @phpstan-type SyncState array{
 *     hash: string|null,
 *     version: string|null,
 *     namespace: non-empty-string|null,
 *     tables: list<string>,
 *     failed: array{hash: string|null, time: int}|null
 * }
 */
#[NoService]
final class TableStore
{
    /** Seconds after a failed sync before the next try. */
    public const int RETRY_AFTER = 300;

    /** Seconds after which a lock counts as left behind by a request that died. */
    public const int LOCK_TIMEOUT = 600;

    /** @var array<int, true> Sites checked in this request. */
    private array $checked = [];

    private bool $versionLogged = false;

    /** @var WeakMap<AbstractRow, array<string, int|string|null>> The stored values of row objects as last loaded or saved, by property. */
    private WeakMap $loaded;

    private readonly ValueFactory $values;

    private readonly SchemaSync $sync;

    /** @var array<class-string, Closure(object): array<string, mixed>> */
    private array $readers = [];

    /** @var array<class-string, Closure(object, string, int|null): void> */
    private array $writers = [];

    /**
     * @param non-empty-string $slug
     * @param non-empty-string $namespace
     * @param TablesData       $tables
     * @param bool             $debug     WP_DEBUG: a failed sync on `init` throws instead of being logged only.
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $namespace,
        private readonly array $tables,
        private readonly bool $debug,
    ) {
        $this->values = new ValueFactory(array_map(static fn(array $table): array => $table['values'], $tables['classes']));
        $this->sync = new SchemaSync(self::log(...));
        $this->loaded = new WeakMap();
    }

    /**
     * @return array<class-string<AbstractRow>, TableEntry>
     */
    public function getClasses(): array
    {
        return $this->tables['classes'];
    }

    /**
     * Whether `init` needs to check the tables: the instance declares tables, or it did when the option was written.
     */
    public function needsCheck(): bool
    {
        return $this->tables['schema'] !== null || array_key_exists(TableNames::option($this->slug), wp_load_alloptions());
    }

    /**
     * Checks the tables of the current site on `init`; a sync that cannot run now is logged and tried again later.
     */
    public function ensureCurrent(): void
    {
        $this->ensure(false);
    }

    /**
     * Makes sure the tables of the current site match the row classes; see the class description.
     *
     * @param bool $access An access to a table: a sync that cannot run is an exception.
     */
    public function ensure(bool $access): void
    {
        if (wp_installing()) {
            return;
        }
        $site = get_current_blog_id();
        if (isset($this->checked[$site])) {
            return;
        }
        $option = TableNames::option($this->slug);
        $schema = $this->tables['schema'];
        $options = wp_load_alloptions();
        if ($schema === null && !array_key_exists($option, $options)) {
            $this->checked[$site] = true;

            return;
        }
        $state = self::state(array_key_exists($option, $options) ? get_option($option) : false);
        if ($state['hash'] === $schema) {
            $this->checked[$site] = true;

            return;
        }

        $version = $this->tables['version'];
        if ($state['version'] !== null && $version !== null && version_compare($version, $state['version'], '<')) {
            $reason = sprintf(
                'the tables were synced by version %s of %s, this code is version %s; they stay as they are until the newer code runs again',
                $state['version'],
                $this->slug,
                $version,
            );
            if (!$this->versionLogged) {
                $this->versionLogged = true;
                self::log($reason . '.');
            }
            if ($access) {
                throw new RuntimeException(sprintf('The tables of %s cannot be used: %s.', $this->slug, $reason));
            }

            return;
        }
        $failed = $state['failed'];
        if ($failed !== null && $failed['hash'] === $schema && time() - $failed['time'] < self::RETRY_AFTER) {
            if ($access) {
                throw new RuntimeException(sprintf(
                    'The tables of %s cannot be used: their sync failed %d seconds ago and is tried again after %d seconds; the error log has the reason.',
                    $this->slug,
                    time() - $failed['time'],
                    self::RETRY_AFTER,
                ));
            }

            return;
        }

        $wpdb = self::wpdb();
        $lock = TableNames::lock($this->slug);
        if (!$this->lock($wpdb, $lock)) {
            self::log(sprintf('the tables of %s are being synced by another request; this request leaves them.', $this->slug));
            if ($access) {
                throw new RuntimeException(sprintf('The tables of %s cannot be used: another request is syncing them right now.', $this->slug));
            }

            return;
        }
        try {
            $managed = $this->sync->sync($wpdb, $this->namespace, $this->tables['classes'], $state['namespace'], $state['tables']);
            if ($schema === null) {
                delete_option($option);
            } else {
                update_option($option, ['hash' => $schema, 'version' => $version, 'namespace' => $this->namespace, 'tables' => $managed, 'failed' => null], true);
            }
            $this->checked[$site] = true;
        } catch (Throwable $exception) {
            update_option($option, [...$state, 'failed' => ['hash' => $schema, 'time' => time()]], true);
            self::log(sprintf('the sync of the tables of %s failed and is tried again after %d seconds: %s', $this->slug, self::RETRY_AFTER, $exception->getMessage()));
            if ($access) {
                throw new RuntimeException(sprintf('The tables of %s cannot be used: their sync failed (%s).', $this->slug, $exception->getMessage()), 0, $exception);
            }
            if ($this->debug) {
                throw $exception;
            }
        } finally {
            $this->unlock($wpdb, $lock);
        }
    }

    /**
     * The full names of the tables of the instance on the current site: those the last sync manages (in the namespace
     * it synced them in) and those the row classes declare.
     *
     * @return list<non-empty-string>
     */
    public function siteTables(): array
    {
        $prefix = self::wpdb()->prefix;
        $state = self::state(get_option(TableNames::option($this->slug)));
        $names = [];
        if ($state['namespace'] !== null) {
            foreach ($state['tables'] as $table) {
                if ($table !== '') {
                    $names[] = TableNames::full($prefix, $state['namespace'], $table);
                }
            }
        }
        foreach ($this->tables['classes'] as $table) {
            $names[] = TableNames::full($prefix, $this->namespace, $table['name']);
        }

        return array_values(array_unique($names));
    }

    /**
     * Drops the tables of the instance on the current site and deletes the state of their sync and its lock (the
     * plugin is uninstalled).
     */
    public function drop(): void
    {
        $wpdb = self::wpdb();
        foreach ($this->siteTables() as $table) {
            $wpdb->query(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', $table)));
            self::check($wpdb, 'Dropping the table ' . $table);
        }
        delete_option(TableNames::option($this->slug));
        $this->unlock($wpdb, TableNames::lock($this->slug));
        unset($this->checked[get_current_blog_id()]);
    }

    /**
     * The full name of the table of a row class on the current site, after ensure().
     *
     * @param class-string<AbstractRow> $class
     * @return non-empty-string
     */
    public function tableName(string $class): string
    {
        $this->ensure(true);

        return $this->fullName($class);
    }

    /**
     * The full name of the table of a row class on the current site, without checking the tables.
     *
     * @param class-string<AbstractRow> $class
     * @return non-empty-string
     */
    public function fullName(string $class): string
    {
        return TableNames::full(self::wpdb()->prefix, $this->namespace, $this->table($class)['name']);
    }

    /**
     * @param class-string<AbstractRow> $class
     * @return TableEntry
     */
    public function table(string $class): array
    {
        return $this->tables['classes'][$class] ?? throw new LogicException(sprintf('%s is no row class of the %s instance; rebuild the manifest after code changes.', $class, $this->slug));
    }

    /**
     * The column of a field in a query of a row class; checks that the field belongs to the class, that the column
     * exists and that the field class fits it.
     *
     * @param class-string<AbstractRow> $class
     * @return array{ColumnEntry, bool} The column and whether it is the primary key.
     */
    public function column(string $class, Field $field): array
    {
        if ($field->getClass() !== $class) {
            throw new InvalidArgumentException(sprintf('%s::query(): the field $%s belongs to %s, not to %s.', $class, $field->getField(), $field->getClass(), $class));
        }
        $table = $this->table($class);
        $column = $table['columns'][$field->getField()] ?? throw new LogicException(sprintf(
            '%s has no column $%s (columns: %s); check the class with #[TableFields] (rebuild the manifest after code changes).',
            $class,
            $field->getField(),
            implode(', ', array_keys($table['columns'])),
        ));
        $primaryKey = $field->getField() === $table['primaryKey'];
        $error = FieldTypes::columnMismatch($field, $column, $primaryKey);
        if ($error !== null) {
            throw new LogicException($error . '; check the class with #[TableFields] (rebuild the manifest after code changes).');
        }

        return [$column, $primaryKey];
    }

    /**
     * @template T of AbstractRow
     * @param class-string<T> $class
     * @return T|null
     */
    public function find(string $class, int $id): ?AbstractRow
    {
        $this->ensure(true);
        $wpdb = self::wpdb();
        $table = $this->table($class);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE %i = %d', $this->fullName($class), $table['columns'][$table['primaryKey']]['column'], $id), ARRAY_A);
        self::check($wpdb, sprintf('Loading the row %d of %s', $id, $this->fullName($class)));

        return is_array($row) ? $this->hydrate($class, $row) : null;
    }

    /**
     * Creates the row object of a row of the table.
     *
     * @template T of AbstractRow
     * @param class-string<T>      $class
     * @param array<mixed>    $row   The columns as wpdb returns them.
     * @return T
     */
    public function hydrate(string $class, array $row): AbstractRow
    {
        $table = $this->table($class);
        $full = $this->fullName($class);
        $primary = $table['columns'][$table['primaryKey']]['column'];
        $id = is_scalar($row[$primary] ?? null) ? (string) $row[$primary] : '?';
        $data = [];
        foreach ($table['columns'] as $property => $column) {
            if (!array_key_exists($column['column'], $row)) {
                throw new RuntimeException(sprintf('The table %s has no column %s; the tables are not in sync with %s.', $full, $column['column'], $class));
            }
            $data[$property] = RowCodec::decode($column, $row[$column['column']], $full, $id);
        }
        try {
            $object = $this->values->create(['type' => 'object', 'class' => $class, 'nullable' => false], $data, $class);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(sprintf('The row %s of the table %s cannot be loaded as %s: %s', $id, $full, $class, $exception->getMessage()), 0, $exception);
        }
        if (!$object instanceof $class) {
            throw new LogicException(sprintf('The row class %s could not be created.', $class));
        }
        $this->remember($object, $this->encode($object));

        return $object;
    }

    /**
     * Inserts a new row (and sets its primary key) or updates the changed columns of a loaded one; without changes,
     * nothing is written. A changed row object with a primary key whose row does not exist (created with `new` and an
     * ID, or deleted in the meantime) is inserted with that primary key and all columns.
     */
    public function save(AbstractRow $row): void
    {
        $class = $row::class;
        $this->ensure(true);
        $wpdb = self::wpdb();
        $table = $this->table($class);
        $full = $this->fullName($class);
        $values = $this->encode($row);
        $primaryKey = $table['primaryKey'];
        $id = $values[$primaryKey][0];
        unset($values[$primaryKey]);
        $loaded = $this->loaded($row);

        if ($id === null) {
            [$data, $formats] = $this->columns($table, $values);
            if ($wpdb->insert($full, $data, $formats) === false) {
                throw self::failure($wpdb, sprintf('Inserting a row of %s into %s', $class, $full));
            }
            $this->writer($class)($row, $primaryKey, $wpdb->insert_id);
            $this->remember($row, $this->encode($row));

            return;
        }
        $changed = $loaded === null ? $values : array_filter($values, static fn(array $value, string $property): bool => !array_key_exists($property, $loaded) || $loaded[$property] !== $value[0], ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            return;
        }
        $primaryColumn = $table['columns'][$primaryKey]['column'];
        [$data, $formats] = $this->columns($table, $changed);
        $updated = $wpdb->update($full, $data, [$primaryColumn => $id], $formats, ['%d']);
        if ($updated === false) {
            throw self::failure($wpdb, sprintf('Updating the row %s of %s in %s', (string) $id, $class, $full));
        }
        // MariaDB and MySQL count changed rows only: 0 also when the row exists with these values already.
        if ($updated === 0 && !$this->exists($wpdb, $full, $primaryColumn, $id)) {
            [$data, $formats] = $this->columns($table, $this->encode($row));
            if ($wpdb->insert($full, $data, $formats) === false) {
                throw self::failure($wpdb, sprintf('Inserting the row %s of %s into %s', (string) $id, $class, $full));
            }
        }
        $this->remember($row, $this->encode($row));
    }

    public function delete(AbstractRow $row): void
    {
        $class = $row::class;
        $table = $this->table($class);
        $primaryKey = $table['primaryKey'];
        $id = $this->reader($class)($row)[$primaryKey] ?? null;
        if (!is_int($id)) {
            throw new LogicException(sprintf('%s::delete(): the row has not been saved yet.', $class));
        }
        $this->ensure(true);
        $wpdb = self::wpdb();
        $full = $this->fullName($class);
        if ($wpdb->delete($full, [$table['columns'][$primaryKey]['column'] => $id], ['%d']) === false) {
            throw self::failure($wpdb, sprintf('Deleting the row %d of %s from %s', $id, $class, $full));
        }
        $this->writer($class)($row, $primaryKey, null);
        $this->remember($row, null);
    }

    /**
     * Whether the row with the primary key exists; only asked when an UPDATE changed no row.
     */
    private function exists(wpdb $wpdb, string $full, string $primaryColumn, int|string $id): bool
    {
        $found = $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE %i = %d LIMIT 1', $full, $primaryColumn, $id));
        self::check($wpdb, sprintf('Looking up the row %s in %s', (string) $id, $full));

        return $found !== null;
    }

    /**
     * Rows of a SELECT built by RowQuery.
     *
     * @return list<array<mixed>>
     */
    public function select(string $query): array
    {
        $wpdb = self::wpdb();
        $rows = $wpdb->get_results($query, ARRAY_A);
        self::check($wpdb, 'The query ' . $query);

        return array_values(array_filter(is_array($rows) ? $rows : [], is_array(...)));
    }

    /**
     * The single value of a query built by RowQuery, e.g. COUNT(*).
     */
    public function value(string $query): int
    {
        $wpdb = self::wpdb();
        $value = $wpdb->get_var($query);
        self::check($wpdb, 'The query ' . $query);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Runs a DELETE built by RowQuery; the number of deleted rows.
     */
    public function execute(string $query): int
    {
        $wpdb = self::wpdb();
        if ($wpdb->query($query) === false) {
            throw self::failure($wpdb, 'The query ' . $query);
        }

        return $wpdb->rows_affected;
    }

    public static function wpdb(): wpdb
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!$wpdb instanceof wpdb) {
            throw new LogicException('Tables need the database connection of WordPress ($wpdb).');
        }

        return $wpdb;
    }

    /**
     * The encoded values of the columns of a row object, by property.
     *
     * @return array<string, array{int|string|null, '%d'|'%s'}>
     */
    private function encode(AbstractRow $row): array
    {
        $class = $row::class;
        $properties = $this->reader($class)($row);
        $values = [];
        foreach ($this->table($class)['columns'] as $property => $column) {
            $values[$property] = RowCodec::encode($column, $properties[$property] ?? null, $class . '::$' . $property);
        }

        return $values;
    }

    /**
     * @param TableEntry                                       $table
     * @param array<string, array{int|string|null, '%d'|'%s'}> $values
     * @return array{array<string, int|string|null>, list<'%d'|'%s'>}
     */
    private function columns(array $table, array $values): array
    {
        $data = [];
        $formats = [];
        foreach ($values as $property => [$value, $format]) {
            $data[$table['columns'][$property]['column']] = $value;
            $formats[] = $format;
        }

        return [$data, $formats];
    }

    /**
     * Bound to the row class, get_object_vars() also returns its private and protected properties; no reflection.
     *
     * @param class-string $class
     * @return Closure(object): array<string, mixed>
     */
    private function reader(string $class): Closure
    {
        /** @var Closure(object): array<string, mixed> Bound to the row class. */
        return $this->readers[$class] ??= Closure::bind(static fn(object $row): array => get_object_vars($row), null, $class);
    }

    /**
     * Sets the primary key of a row object (private or protected in the row class).
     *
     * @param class-string $class
     * @return Closure(object, string, int|null): void
     */
    private function writer(string $class): Closure
    {
        /** @var Closure(object, string, int|null): void Bound to the row class. */
        return $this->writers[$class] ??= Closure::bind(static function (object $row, string $property, ?int $id): void {
            $row->{$property} = $id;
        }, null, $class);
    }

    /**
     * Takes the lock of the sync: a row in the options table, inserted only if it does not exist (like the lock of
     * WP_Upgrader). A lock older than LOCK_TIMEOUT is taken over, unless another request was faster.
     */
    private function lock(wpdb $wpdb, string $name): bool
    {
        $now = (string) time();
        $wpdb->query((string) $wpdb->prepare("INSERT IGNORE INTO %i (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'off')", $wpdb->options, $name, $now));
        if (self::affected($wpdb) === 1) {
            return true;
        }
        $since = $wpdb->get_var($wpdb->prepare('SELECT `option_value` FROM %i WHERE `option_name` = %s', $wpdb->options, $name));
        if (!is_string($since) || (is_numeric($since) && time() - (int) $since < self::LOCK_TIMEOUT)) {
            return false;
        }
        $wpdb->query((string) $wpdb->prepare('UPDATE %i SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s', $wpdb->options, $now, $name, $since));

        return self::affected($wpdb) === 1;
    }

    /**
     * The rows changed by the last query.
     *
     * @phpstan-impure
     */
    private static function affected(wpdb $wpdb): int
    {
        return $wpdb->rows_affected;
    }

    private function unlock(wpdb $wpdb, string $name): void
    {
        $wpdb->query((string) $wpdb->prepare('DELETE FROM %i WHERE `option_name` = %s', $wpdb->options, $name));
    }

    /**
     * The values of a row object as last loaded or saved, by property; null for a row object created with `new`.
     *
     * @return array<string, int|string|null>|null
     */
    private function loaded(AbstractRow $row): ?array
    {
        return $this->loaded[$row] ?? null;
    }

    /**
     * @param array<string, array{int|string|null, '%d'|'%s'}>|null $values
     */
    private function remember(AbstractRow $row, ?array $values): void
    {
        if ($values === null) {
            unset($this->loaded[$row]);

            return;
        }
        $this->loaded[$row] = array_map(static fn(array $value): int|string|null => $value[0], $values);
    }

    /**
     * @return SyncState
     */
    private static function state(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $string = static fn(string $key): ?string => isset($stored[$key]) && is_string($stored[$key]) && $stored[$key] !== '' ? $stored[$key] : null;
        $failed = $stored['failed'] ?? null;

        return [
            'hash' => $string('hash'),
            'version' => $string('version'),
            'namespace' => $string('namespace'),
            'tables' => is_array($stored['tables'] ?? null) ? array_values(array_filter($stored['tables'], is_string(...))) : [],
            'failed' => is_array($failed) && is_int($failed['time'] ?? null)
                ? ['hash' => is_string($failed['hash'] ?? null) ? $failed['hash'] : null, 'time' => $failed['time']]
                : null,
        ];
    }

    private static function check(wpdb $wpdb, string $what): void
    {
        if ($wpdb->last_error !== '') {
            throw self::failure($wpdb, $what);
        }
    }

    private static function failure(wpdb $wpdb, string $what): RuntimeException
    {
        return new RuntimeException(sprintf('%s failed: %s', $what, $wpdb->last_error !== '' ? $wpdb->last_error : 'no error message'));
    }

    private static function log(string $message): void
    {
        error_log('wp-app-framework: tables: ' . $message);
    }
}
