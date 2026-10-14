<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\NamePrefix;
use Saij\WpAppFramework\Value\ValueFactory;
use Throwable;

/**
 * Collects and validates the row classes (#[Table]) in the build: one column per promoted constructor parameter, the
 * primary key, the indexes and the earlier names of columns and tables. The manifest gets the structure of every
 * table, a hash of all of them (the schema) and the version of the theme or plugin; the schema sync compares the
 * hash, not the database, on every request.
 *
 * @internal
 *
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type ValueField from ValueFactory
 * @phpstan-type ColumnKind 'int'|'float'|'bool'|'string'|'enum'|'datetime'
 * @phpstan-type ColumnEntry array{
 *     column: non-empty-string,
 *     kind: ColumnKind,
 *     sql: non-empty-string,
 *     nullable: bool,
 *     default: int|float|string|null,
 *     enum: class-string<BackedEnum>|null,
 *     text: bool,
 *     renamedFrom: list<non-empty-string>
 * }
 * @phpstan-type IndexEntry array{name: non-empty-string, columns: non-empty-list<non-empty-string>, unique: bool}
 * @phpstan-type TableEntry array{
 *     name: non-empty-string,
 *     renamedFrom: list<non-empty-string>,
 *     primaryKey: non-empty-string,
 *     columns: array<non-empty-string, ColumnEntry>,
 *     indexes: list<IndexEntry>,
 *     values: array<string, ValueField>
 * }
 * @phpstan-type TablesData array{
 *     schema: non-empty-string|null,
 *     version: non-empty-string|null,
 *     classes: array<class-string<AbstractRow>, TableEntry>
 * }
 */
#[NoService]
final class TableCollector
{
    /** Local names of tables, groups of indexes. */
    public const string NAME_PATTERN = '~^[a-z][a-z0-9_]*$~';

    /** `{namespace}_{name}` at most, so that the prefix of WordPress (also `wp_123_` in a multisite) fits into 64. */
    public const int MAX_NAME_LENGTH = 50;

    /** Identifiers of MariaDB and MySQL: names of tables, columns and indexes. */
    public const int MAX_IDENTIFIER_LENGTH = 64;

    /** Length of a string column without #[Length]; the longest that core WordPress indexes in utf8mb4 (schema.php). */
    public const int DEFAULT_LENGTH = 191;

    public const int MAX_LENGTH = 16383;

    /** Bytes of an index in InnoDB with the dynamic row format. */
    private const int MAX_KEY_BYTES = 3072;

    /** Bytes of a row, counted by the server without the content of long text. */
    private const int MAX_ROW_BYTES = 65535;

    /** @var list<class-string> */
    private const array MARKERS = [PrimaryKey::class, Indexed::class, RenamedFrom::class, Length::class, Text::class];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param non-empty-string $namespace Namespace of the project; tables are `{namespace}_{name}`.
     */
    public function __construct(
        private readonly string $namespace,
    ) {}

    /**
     * Row classes and classes with #[TableFields]: no services.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @return array<class-string, string> Class => what it is.
     */
    public static function nonServices(array $classes): array
    {
        $result = [];
        foreach ($classes as $class => $reflection) {
            if ($reflection->isSubclassOf(AbstractRow::class)) {
                $result[$class] = 'a row class';
            } elseif ($reflection->getAttributes(TableFields::class) !== []) {
                $result[$class] = 'a table fields class';
            }
        }

        return $result;
    }

    /**
     * The column name of a constructor parameter: snake case (`postId` → `post_id`).
     *
     * @return non-empty-string
     */
    public static function columnName(string $parameter): string
    {
        /** @var non-empty-lowercase-string Not empty for a parameter name. */
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $parameter));
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @param non-empty-string|null                        $version Version of the theme or plugin (header).
     * @return TablesData
     */
    public function collect(array $classes, ?string $version): array
    {
        $tables = [];
        /** @var array<string, string> $names Local names (also earlier ones) => where they are declared. */
        $names = [];
        foreach ($classes as $class => $reflection) {
            $entry = $this->table($class, $reflection, $names);
            if ($entry !== null) {
                /** @var class-string<AbstractRow> $class Checked by table(). */
                $tables[$class] = $entry;
            }
        }

        return [
            'schema' => $tables === [] ? null : self::schema($this->namespace, $tables),
            'version' => $version,
            'classes' => $tables,
        ];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The hash of the structure of all tables: everything the schema sync creates or changes from.
     *
     * @param array<class-string<AbstractRow>, TableEntry> $tables
     * @return non-empty-string
     */
    public static function schema(string $namespace, array $tables): string
    {
        $structure = [];
        foreach ($tables as $table) {
            $structure[$table['name']] = [
                $table['renamedFrom'],
                $table['columns'][$table['primaryKey']]['column'],
                array_values(array_map(static fn(array $column): array => [$column['column'], $column['sql'], $column['nullable'], $column['default'], $column['renamedFrom']], $table['columns'])),
                $table['indexes'],
            ];
        }
        ksort($structure);

        return hash('sha256', serialize([SchemaSync::MECHANICS, $namespace, $structure]));
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<string, string>   $names
     * @return TableEntry|null
     */
    private function table(string $class, ReflectionClass $reflection, array &$names): ?array
    {
        $attribute = $reflection->getAttributes(Table::class)[0] ?? null;
        $isRow = $reflection->isSubclassOf(AbstractRow::class);
        if ($attribute === null) {
            if ($isRow) {
                $this->errors[] = sprintf('%s extends %s, but has no #[Table]; row classes declare their table.', $class, AbstractRow::class);
            }

            return null;
        }
        try {
            $table = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Table] on %s: %s', $class, $exception->getMessage());

            return null;
        }
        $error = match (true) {
            !$isRow => sprintf('#[Table] on %s: the class must extend %s; it is the object of a row of the table.', $class, AbstractRow::class),
            $reflection->isAbstract() => sprintf('#[Table] on %s: the class must not be abstract.', $class),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $valid = $this->tableNames($class, $table, $names);
        $columns = $this->columns($class, $reflection);
        if (!$valid || $columns === null) {
            return null;
        }
        [$entries, $primaryKey, $values, $indexed] = $columns;
        $indexes = $this->indexes($class, $entries, $primaryKey, $indexed);
        if ($indexes === null) {
            return null;
        }
        $bytes = self::rowBytes($entries);
        if ($bytes > self::MAX_ROW_BYTES) {
            $this->errors[] = sprintf(
                '#[Table] on %s: a row needs %d bytes without long text, more than the %d that MariaDB and MySQL allow; shorten #[Length] or use #[Text].',
                $class,
                $bytes,
                self::MAX_ROW_BYTES,
            );

            return null;
        }
        /** @var non-empty-string $name Checked by tableNames(). */
        $name = $table->name;
        /** @var list<non-empty-string> $renamedFrom Checked by tableNames(). */
        $renamedFrom = $table->renamedFrom;

        return [
            'name' => $name,
            'renamedFrom' => $renamedFrom,
            'primaryKey' => $primaryKey,
            'columns' => $entries,
            'indexes' => $indexes,
            'values' => $values,
        ];
    }

    /**
     * @param array<string, string> $names
     */
    private function tableNames(string $class, Table $table, array &$names): bool
    {
        $valid = true;
        $all = [$table->name, ...$table->renamedFrom];
        foreach ($all as $index => $name) {
            $what = $index === 0 ? 'name' : 'earlier name';
            $error = match (true) {
                !is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1 => sprintf('#[Table] on %s has the invalid %s %s: use lowercase letters, digits and "_", starting with a letter, e.g. "log".', $class, $what, var_export($name, true)),
                strlen(NamePrefix::data($this->namespace) . $name) > self::MAX_NAME_LENGTH => sprintf(
                    '#[Table] on %s: the %s "%s" is too long; "%s" may have at most %d characters, so that the prefix of WordPress fits.',
                    $class,
                    $what,
                    $name,
                    NamePrefix::data($this->namespace) . $name,
                    self::MAX_NAME_LENGTH,
                ),
                isset($names[$name]) => sprintf('#[Table] on %s uses the %s "%s", which %s already uses.', $class, $what, $name, $names[$name]),
                default => null,
            };
            if ($error !== null) {
                $this->errors[] = $error;
                $valid = false;
                continue;
            }
            /** @var string $name Checked above. */
            $names[$name] = $class;
        }

        return $valid;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array{array<non-empty-string, ColumnEntry>, non-empty-string, array<string, ValueField>, list<array{non-empty-string, Indexed}>}|null
     *     Columns by property, the property of the primary key, the value descriptions and the #[Indexed] by property.
     */
    private function columns(string $class, ReflectionClass $reflection): ?array
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getDeclaringClass()->getName() !== $class || !$constructor->isPublic()) {
            $this->errors[] = sprintf('%s needs a public constructor of its own whose promoted parameters are the columns, one of them #[PrimaryKey].', $class);

            return null;
        }
        $promoted = [];
        $valid = true;
        foreach ($constructor->getParameters() as $parameter) {
            $promoted[] = $parameter->getName();
        }
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && $property->getDeclaringClass()->getName() !== AbstractRow::class && !in_array($property->getName(), $promoted, true)) {
                $this->errors[] = sprintf('%s declares the property $%s outside the constructor; it would not be stored. All columns are promoted constructor parameters.', $class, $property->getName());
                $valid = false;
            }
        }

        $entries = [];
        $values = [];
        $indexed = [];
        $primaryKeys = [];
        /** @var array<string, string> $columns Column names => parameter. */
        $columns = [];
        foreach ($constructor->getParameters() as $parameter) {
            $location = sprintf('Parameter $%s of %s::__construct()', $parameter->getName(), $class);
            if ($parameter->getAttributes(PrimaryKey::class) !== []) {
                $primaryKeys[] = $parameter->getName();
            }
            $column = $this->parameter($parameter, $location);
            if ($column === null) {
                $valid = false;
                continue;
            }
            [$entry, $value] = $column;
            if (isset($columns[$entry['column']])) {
                $this->errors[] = sprintf('%s has the column name "%s", which $%s already has.', $location, $entry['column'], $columns[$entry['column']]);
                $valid = false;
                continue;
            }
            $columns[$entry['column']] = $parameter->getName();
            /** @var non-empty-string $name A parameter name. */
            $name = $parameter->getName();
            $entries[$name] = $entry;
            $values[$name] = ['value' => $value, 'required' => !$parameter->isDefaultValueAvailable()];
            foreach ($parameter->getAttributes(Indexed::class) as $attribute) {
                $indexed[] = [$name, $attribute->newInstance()];
            }
        }
        $earlier = [];
        foreach ($entries as $name => $entry) {
            foreach ($entry['renamedFrom'] as $old) {
                $error = match (true) {
                    isset($columns[$old]) => sprintf('names the column "%s", which $%s has now; an earlier name must not be in use.', $old, $columns[$old]),
                    isset($earlier[$old]) => sprintf('names the column "%s", an earlier name of $%s as well.', $old, $earlier[$old]),
                    default => null,
                };
                if ($error !== null) {
                    $this->errors[] = sprintf('#[RenamedFrom] on parameter $%s of %s::__construct() %s', $name, $class, $error);
                    $valid = false;
                    continue;
                }
                $earlier[$old] = $name;
            }
        }

        if (count($primaryKeys) !== 1) {
            // A primary key with errors is reported by parameter().
            $this->errors[] = $primaryKeys === []
                ? sprintf('%s has no #[PrimaryKey]; mark the parameter `#[PrimaryKey] private ?int $id = null`.', $class)
                : sprintf('%s has several #[PrimaryKey] parameters (%s); a table has one primary key.', $class, implode(', ', array_map(static fn(string $name): string => '$' . $name, $primaryKeys)));

            return null;
        }
        if (!$valid) {
            return null;
        }
        /** @var non-empty-string $primaryKey A parameter name. */
        $primaryKey = $primaryKeys[0];

        return [$entries, $primaryKey, $values, $indexed];
    }

    /**
     * @return array{ColumnEntry, Value, bool}|null The column, its value description and whether it is the primary key.
     */
    private function parameter(ReflectionParameter $parameter, string $location): ?array
    {
        $errors = [];
        foreach ($parameter->getAttributes() as $attribute) {
            if (!in_array($attribute->getName(), self::MARKERS, true)) {
                $errors[] = sprintf('%s has #[%s]; columns of tables take #[PrimaryKey], #[Indexed], #[RenamedFrom], #[Length] and #[Text] only.', $location, $attribute->getName());
            }
        }
        if (!$parameter->isPromoted()) {
            $errors[] = sprintf('%s must be a promoted property; every constructor parameter is a column.', $location);
        }
        $property = $parameter->isPromoted() ? $parameter->getDeclaringClass()?->getProperty($parameter->getName()) : null;
        if ($property !== null && $property->isPublic()) {
            $errors[] = sprintf('%s must be private or protected; give the class getters and setters for its columns.', $location);
        }
        $column = self::columnName($parameter->getName());
        if (strlen($column) > self::MAX_IDENTIFIER_LENGTH) {
            $errors[] = sprintf('%s has the column name "%s", longer than %d characters.', $location, $column, self::MAX_IDENTIFIER_LENGTH);
        }

        $type = $parameter->getType();
        $kind = null;
        $enum = null;
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $kind = match (true) {
                in_array($name, ['int', 'float', 'bool', 'string'], true) => $name,
                $name === DateTimeImmutable::class => 'datetime',
                enum_exists($name) && is_subclass_of($name, BackedEnum::class) => 'enum',
                default => null,
            };
            if ($kind === 'enum') {
                /** @var class-string<BackedEnum> $name Checked above. */
                $enum = $name;
            }
        }
        if ($kind === null) {
            $errors[] = sprintf(
                '%s has the type %s; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable%s.',
                $location,
                $type === null ? 'mixed (none)' : (string) $type,
                $type instanceof ReflectionNamedType && in_array($type->getName(), [DateTime::class, DateTimeInterface::class], true) ? ' (use DateTimeImmutable)' : '',
            );
        }
        $nullable = $type?->allowsNull() ?? true;

        $isPrimaryKey = $parameter->getAttributes(PrimaryKey::class) !== [];
        if ($isPrimaryKey && ($kind !== 'int' || !$nullable || !$parameter->isDefaultValueAvailable() || $parameter->getDefaultValue() !== null || $property?->isReadOnly() === true)) {
            $errors[] = sprintf('%s has #[PrimaryKey] and must be `?int $%s = null`, not readonly: save() sets it when it inserts the row.', $location, $parameter->getName());
        }

        $length = $parameter->getAttributes(Length::class)[0] ?? null;
        $length = $length?->newInstance()->length;
        $text = $parameter->getAttributes(Text::class) !== [];
        if (($length !== null || $text) && $kind !== 'string') {
            $errors[] = sprintf('%s has #[%s], which only string columns take.', $location, $text ? 'Text' : 'Length');
        } elseif ($length !== null && $text) {
            $errors[] = sprintf('%s has #[Length] and #[Text]; use one.', $location);
        } elseif ($length !== null && ($length < 1 || $length > self::MAX_LENGTH)) {
            $errors[] = sprintf('%s has #[Length(%d)]; the length is 1 to %d characters, use #[Text] for longer strings.', $location, $length, self::MAX_LENGTH);
        }
        $length ??= self::DEFAULT_LENGTH;

        $renamedFrom = [];
        foreach ($parameter->getAttributes(RenamedFrom::class) as $attribute) {
            $old = $attribute->newInstance()->column;
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $old) !== 1 || $old === $parameter->getName() || self::columnName($old) === $column) {
                $errors[] = sprintf('%s has #[RenamedFrom(%s)]; name an earlier parameter name, not the current one.', $location, var_export($old, true));
                continue;
            }
            if (in_array(self::columnName($old), $renamedFrom, true)) {
                $errors[] = sprintf('%s has #[RenamedFrom(%s)] twice.', $location, var_export($old, true));
                continue;
            }
            $renamedFrom[] = self::columnName($old);
        }

        if ($errors !== [] || $kind === null) {
            array_push($this->errors, ...$errors);

            return null;
        }

        // A constant default (scalar, null, enum case) is the default of the column; long text and times have none.
        $default = null;
        $phpDefault = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        if (!$isPrimaryKey && !$text && $kind !== 'datetime') {
            $default = match (true) {
                $phpDefault instanceof BackedEnum => $phpDefault->value,
                is_bool($phpDefault) => (int) $phpDefault,
                $kind === 'float' && is_int($phpDefault) => (float) $phpDefault,
                is_int($phpDefault), is_float($phpDefault), is_string($phpDefault) => $phpDefault,
                default => null,
            };
        }
        if ($kind === 'string' && !$text && is_string($default) && mb_strlen($default) > $length) {
            $this->errors[] = sprintf('%s has a default of %d characters, longer than the column (%d).', $location, mb_strlen($default), $length);

            return null;
        }
        $backing = 'string';
        if ($enum !== null) {
            $backing = (string) new ReflectionEnum($enum)->getBackingType();
            foreach ($enum::cases() as $case) {
                if (is_string($case->value) && mb_strlen($case->value) > self::DEFAULT_LENGTH) {
                    $this->errors[] = sprintf('%s: the value of %s::%s is longer than %d characters.', $location, $enum, $case->name, self::DEFAULT_LENGTH);

                    return null;
                }
            }
        }

        $sql = match ($kind) {
            'int' => $isPrimaryKey ? 'bigint(20) unsigned' : 'bigint(20)',
            'float' => 'double',
            'bool' => 'tinyint(1)',
            'string' => $text ? 'longtext' : sprintf('varchar(%d)', $length),
            'enum' => $backing === 'int' ? 'bigint(20)' : sprintf('varchar(%d)', self::DEFAULT_LENGTH),
            'datetime' => 'datetime',
        };
        $value = $enum !== null
            ? ['type' => 'enum', 'class' => $enum, 'nullable' => $nullable]
            : ['type' => $kind, 'nullable' => $nullable];

        return [
            [
                'column' => $column,
                'kind' => $kind,
                'sql' => $sql,
                // The primary key is null until the row is inserted, never in the table.
                'nullable' => $nullable && !$isPrimaryKey,
                'default' => $default,
                'enum' => $enum,
                'text' => $text,
                'renamedFrom' => $renamedFrom,
            ],
            $value,
            $isPrimaryKey,
        ];
    }

    /**
     * @param array<non-empty-string, ColumnEntry>   $columns
     * @param list<array{non-empty-string, Indexed}> $indexed
     * @return list<IndexEntry>|null
     */
    private function indexes(string $class, array $columns, string $primaryKey, array $indexed): ?array
    {
        $errors = [];
        $indexes = [];
        /** @var array<string, list<array{int, non-empty-string, bool, string}>> $groups Group => position, column, unique, parameter. */
        $groups = [];
        foreach ($indexed as [$name, $index]) {
            $column = $columns[$name];
            $location = sprintf('#[Indexed] on parameter $%s of %s::__construct()', $name, $class);
            $error = match (true) {
                $name === $primaryKey => sprintf('%s: the primary key is indexed already.', $location),
                $column['text'] => sprintf('%s: long text (#[Text]) cannot be indexed.', $location),
                str_starts_with($column['sql'], 'varchar(') && (int) substr($column['sql'], 8) > self::DEFAULT_LENGTH => sprintf('%s: strings of more than %d characters cannot be indexed.', $location, self::DEFAULT_LENGTH),
                $index->group === null && $index->position !== null => sprintf('%s has a position but no group.', $location),
                $index->group !== null && $index->position === null => sprintf('%s has the group "%s" but no position.', $location, $index->group),
                $index->group !== null && (preg_match(self::NAME_PATTERN, $index->group) !== 1 || $index->group === 'primary') => sprintf('%s has the invalid group %s: use lowercase letters, digits and "_", starting with a letter (not "primary").', $location, var_export($index->group, true)),
                $index->group !== null && strlen($index->group) > self::MAX_IDENTIFIER_LENGTH => sprintf('%s: the group "%s" is longer than %d characters.', $location, $index->group, self::MAX_IDENTIFIER_LENGTH),
                default => null,
            };
            if ($error !== null) {
                $errors[] = $error;
                continue;
            }
            if ($index->group === null) {
                if (isset($indexes[$column['column']])) {
                    $errors[] = sprintf('%s: the column has an index of its own already.', $location);
                    continue;
                }
                $indexes[$column['column']] = ['name' => $column['column'], 'columns' => [$column['column']], 'unique' => $index->unique];
                continue;
            }
            $groups[$index->group][] = [(int) $index->position, $column['column'], $index->unique, $name];
        }
        foreach ($groups as $group => $members) {
            $location = sprintf('#[Indexed] group "%s" of %s', $group, $class);
            usort($members, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
            $positions = array_column($members, 0);
            $error = match (true) {
                isset($indexes[$group]) => sprintf('%s has the name of the index of the column %s.', $location, $group),
                $positions !== range(1, count($members)) => sprintf('%s has the positions %s; number the columns 1 to %d.', $location, implode(', ', $positions), count($members)),
                count(array_unique(array_column($members, 1))) !== count($members) => sprintf('%s has a column twice.', $location),
                count(array_unique(array_map(static fn(array $member): string => $member[2] ? '1' : '0', $members))) > 1 => sprintf('%s is unique for some columns only; set unique on all or none.', $location),
                default => null,
            };
            if ($error !== null) {
                $errors[] = $error;
                continue;
            }
            /** @var non-empty-string $group Checked above. */
            $group = $group;
            /** @var non-empty-list<non-empty-string> $groupColumns A group has a member. */
            $groupColumns = array_column($members, 1);
            $indexes[$group] = ['name' => $group, 'columns' => $groupColumns, 'unique' => $members[0][2]];
        }

        $byColumns = [];
        foreach ($indexes as $index) {
            $key = implode(',', $index['columns']);
            if (isset($byColumns[$key])) {
                $errors[] = sprintf('#[Indexed] on %s: the indexes "%s" and "%s" have the same columns.', $class, $byColumns[$key], $index['name']);
            }
            $byColumns[$key] = $index['name'];
            $bytes = 0;
            foreach ($index['columns'] as $name) {
                foreach ($columns as $column) {
                    if ($column['column'] === $name) {
                        $bytes += self::columnBytes($column)[1];
                    }
                }
            }
            if ($bytes > self::MAX_KEY_BYTES) {
                $errors[] = sprintf('#[Indexed] on %s: the index "%s" needs %d bytes, more than the %d that MariaDB and MySQL allow.', $class, $index['name'], $bytes, self::MAX_KEY_BYTES);
            }
        }
        if ($errors !== []) {
            array_push($this->errors, ...$errors);

            return null;
        }

        return array_values($indexes);
    }

    /**
     * Bytes of a row as MariaDB counts them for its limit: long text by its pointer only, plus the bits for null.
     *
     * @param array<non-empty-string, ColumnEntry> $columns
     */
    private static function rowBytes(array $columns): int
    {
        $bytes = 0;
        $nullable = 0;
        foreach ($columns as $column) {
            $bytes += self::columnBytes($column)[0];
            $nullable += $column['nullable'] ? 1 : 0;
        }

        return $bytes + intdiv($nullable + 7, 8);
    }

    /**
     * @param ColumnEntry $column
     * @return array{int, int} Bytes in the row and in an index.
     */
    private static function columnBytes(array $column): array
    {
        if (str_starts_with($column['sql'], 'varchar(')) {
            $bytes = 4 * (int) substr($column['sql'], 8);

            return [$bytes + ($bytes > 255 ? 2 : 1), $bytes];
        }

        return match ($column['sql']) {
            'tinyint(1)' => [1, 1],
            'datetime' => [5, 5],
            'longtext' => [12, 0],
            default => [8, 8],
        };
    }
}
