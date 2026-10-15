# Tables

Some data does not fit into posts: a log of imports, counters, assignments between objects, the state of a background job. Each of them has many rows with the same few columns, is rarely edited by hand and does not need revisions, URLs or the block editor. Stored as posts with meta fields, every row would cost a post and one meta row per field, and a query would join the meta table once per condition. A table of its own is faster and simpler.

In plain WordPress you would write the `CREATE TABLE` yourself, keep it up to date with `dbDelta()` and read rows as arrays of strings with `$wpdb`. The framework lets you declare a table as a class instead. It creates the table, changes it when the class changes, and loads and saves rows as objects – the same way [post classes](./posts.md) work for posts.

```php
$entry = new LogEntry(level: Level::Error, message: 'Import failed');
$entry->save();

$errors = LogEntry::query()
    ->where(LogEntryFields::level()->equals(Level::Error))
    ->orderBy(LogEntryFields::created(), Direction::Desc)
    ->limit(20)
    ->get();
```

Use posts for content that people edit, link to or translate; use a table for data your code writes and reads.

## The row class

A row class extends `AbstractRow` and carries `#[Table]` with the name of the table. Its promoted constructor parameters are the columns:

```php
use Saij\WpAppFramework\Table\{AbstractRow, Table, PrimaryKey, Indexed, Length, Text};

#[Table('log')]
final class LogEntry extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        private ?int $id = null,
        #[Indexed(group: 'level_created', position: 1)]
        private Level $level = Level::Info,
        #[Length(500)]
        private string $message = '',
        #[Text]
        private string $context = '',
        #[Indexed]
        private ?int $postId = null,
        #[Indexed(group: 'level_created', position: 2)]
        private DateTimeImmutable $created = new DateTimeImmutable(),
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setLevel(Level $level): void
    {
        $this->level = $level;
    }

    // further getters and setters …
}
```

The table is called `{prefix}{namespace}_{name}`: with the table prefix `wp_` and the namespace `dev-test` of your [composer.json](../concepts.md#consumers-and-instances), `wp_dev_test_log`. A theme and a plugin therefore never share a table, even if both name it `log`.

Each column is named after its parameter in snake case: `$postId` becomes the column `post_id`. The parameters are private or protected; you decide with getters and setters what the rest of your code may read and change.

### Types

The type of a parameter decides the type of its column. A nullable type (`?int`) allows `NULL`; otherwise the column is `NOT NULL`.

| PHP | Column |
|---|---|
| `int` | `bigint(20)` |
| `float` | `double` |
| `bool` | `tinyint(1)` |
| `string` | `varchar(191)`; with `#[Length(n)]` `varchar(n)`; with `#[Text]` `longtext` |
| backed enum | the value of the case: `varchar(191)` for string enums, `bigint(20)` for int enums |
| `DateTimeImmutable` | `datetime`, in UTC |
| `#[PrimaryKey] ?int` | `bigint(20) unsigned` with auto increment |

A constant default of the parameter – a number, a string, `null` or an enum case – is the default of the column, too. Long text and times have no default in the table; `new DateTimeImmutable()` is evaluated by PHP when you create the object.

Floats are written with every digit, so a float you save is the float you load. Times are stored in UTC to the second; a time of another time zone is converted when it is saved.

### Indexes

`#[Indexed]` gives the column an index of its own, named after the column. Add `unique: true` for a unique index. An index over several columns is a group: give each column the group name and its position.

```php
#[Indexed(group: 'level_created', position: 1)]
private Level $level = Level::Info,
// …
#[Indexed(group: 'level_created', position: 2)]
private DateTimeImmutable $created = new DateTimeImmutable(),
```

The primary key has its index already. Long text and strings longer than 191 characters cannot be indexed: MariaDB and MySQL limit an index to 3072 bytes, and a character takes up to four.

## Loading, saving and deleting

A new row is an object you create with `new`. `save()` inserts it and sets its primary key:

```php
$entry = new LogEntry(level: Level::Error, message: 'Import failed', postId: $post->getId());
$entry->save();
$id = $entry->getId();
```

`get()` loads a row by its primary key and throws an exception if there is none; `find()` returns null instead. After a change, `save()` writes only the columns that changed. Without a change, it writes nothing.

```php
$entry = LogEntry::get($id);
$entry->setLevel(Level::Info);
$entry->save();

$entry->delete();   // its primary key is null afterwards
```

You can also create a row object with a primary key of your own, for example to restore a row you exported. `save()` writes it like a loaded row: it updates the row with that key, and if there is none, it inserts the row with that key and all columns.

```php
new LogEntry(id: 42, level: Level::Error, message: 'Restored')->save();
```

The same happens when the row of a loaded object was deleted in the meantime and you change and save the object: the row comes back with its old primary key. Finding out whether the row exists costs a query of its own, but only when the update changed nothing. MariaDB and MySQL count only the rows an `UPDATE` really changed, so an update of a row that already has these values looks the same as an update of a missing row.

Every `get()`, `find()` and query returns new objects. When two of them change the same row, each `save()` writes its changed columns; for a column changed by both, the last one wins. `save()` and `delete()` check no capabilities.

If the database refuses a row – a string longer than its column, for example – `save()` throws a `RuntimeException` with the error of the database. A stored value that no longer fits the class, such as the value of a removed enum case, makes loading the row throw an exception; the framework does not quietly use the default instead.

## Queries

`LogEntry::query()` starts a query of rows. It works like a [query of posts](./queries.md): it never changes, so keep the result of each method, and it takes conditions from field objects and the groups `Any` and `All`.

```php
$rows = LogEntry::query()
    ->where(LogEntryFields::created()->greaterThan(new DateTimeImmutable('-1 day')))
    ->where(Any::of(
        LogEntryFields::level()->equals(Level::Error),
        All::of(LogEntryFields::attempts()->greaterThan(3), LogEntryFields::postId()->isNotNull()),
    ))
    ->orderBy(LogEntryFields::created(), Direction::Desc)
    ->limit(20)
    ->get();
```

The framework writes the SQL of the query itself, with every value passed through `$wpdb->prepare()`. Groups can contain groups to any depth. Without `orderBy()`, rows come in the order of their primary key.

`count()` counts all matching rows. `delete()` deletes them and returns how many – with `limit()` only the first ones in the order of the query:

```php
$removed = LogEntry::query()->where(LogEntryFields::created()->lessThan($cutoff))->delete();
```

### Field classes

As with meta fields, the field objects come from a class of their own, marked `#[TableFields]`. Let the framework write it:

```sh
vendor/bin/wp-app-framework table-fields 'Sections\Table\LogEntry'
```

The command writes `LogEntryFields.php` next to the row class, with one method per column:

```php
#[TableFields(LogEntry::class)]
final class LogEntryFields
{
    /**
     * @return EnumField<Level>
     */
    public static function level(): EnumField
    {
        return new EnumField(LogEntry::class, 'level', Level::class);
    }

    public static function postId(): NullableIntField
    {
        return new NullableIntField(LogEntry::class, 'postId');
    }

    // …
}
```

The file is ordinary source code, like the one of `meta-fields`: commit it, shorten it, or write it by hand. The build checks every method that returns a field. A column that allows `NULL` needs the `Nullable…` variant of its field class, which adds `isNull()` and `isNotNull()`; any other column needs the plain class.

### Null

A nullable column without a value holds `NULL`. Conditions on it behave like comparisons in PHP: `equals(5)` does not find a row without value, and `notEquals(5)` and `notIn(…)` do find it, because `null !== 5`. Plain SQL would leave such rows out of both; the framework adds `OR … IS NULL` for you. Use `isNull()` and `isNotNull()` to ask for the missing value itself.

When you sort by a nullable column, rows without value come first in ascending order and last in descending order.

Strings are compared without regard to case, like the collation of WordPress' tables.

## SQL of your own

For anything a query cannot express – joins, sums, groups – write SQL yourself. `LogEntry::tableName()` returns the full name of the table on the current site:

```php
global $wpdb;
$counts = $wpdb->get_results($wpdb->prepare(
    'SELECT level, COUNT(*) AS count FROM %i GROUP BY level',
    LogEntry::tableName(),
));
```

Rows you change this way bypass the row objects; objects loaded before keep their values.

## Keeping tables up to date

The framework creates and changes the tables itself; you never write `CREATE TABLE` or `ALTER TABLE`. The build stores the structure of all tables of your theme or plugin in its manifest, together with a hash of it. On `init`, the framework compares that hash with the one stored after the last change of the tables. This comparison reads an option WordPress loads anyway, so a request whose tables are current costs no database query.

When the hashes differ, the framework syncs the tables of the current site:

1. It creates every table that does not exist.
2. It compares each table with its class and changes it in one `ALTER TABLE`: it adds missing columns and indexes, changes columns whose type, `NULL` or default differ, renames columns, and drops columns and indexes the class does not declare.
3. It reads the table again; a remaining difference is an error.
4. It drops the tables of row classes you removed.

Every change goes to the error log with the prefix `wp-app-framework: tables:`.

:::warning
The sync makes the tables match your classes, also when that loses data, and also in development mode – switching to a branch with fewer columns drops them:

- A removed parameter drops its column; a removed row class drops its table.
- A renamed parameter is a new column; the old one and its data are dropped. Rename with `#[RenamedFrom]`, a table with `renamedFrom` of `#[Table]`.
- A shorter `#[Length]` cuts longer values. A column that becomes `NOT NULL` gets 0, an empty string or the zero date where it held `NULL`; the log says how many values changed.
- Keep `#[RenamedFrom]` as long as an installation may still have the old column.
:::

### Renaming

`#[RenamedFrom('text')]` names an earlier name of the parameter. If the table has a column of that name and none of the new name, the sync renames it, and the data stays. For a parameter renamed several times, add one attribute per earlier name, newest first.

```php
#[RenamedFrom('text')]
private string $message = '',
```

If both columns exist – after a manual change, for example – the sync leaves the old one alone and logs it, so you can merge them by hand.

A table is renamed the same way: `#[Table('entries', renamedFrom: ['log'])]` renames `wp_dev_test_log` if `wp_dev_test_entries` does not exist yet.

The sync drops only tables it created itself, and only in the current namespace. If you change the namespace of your theme or plugin, the tables of the old namespace stay and the log names them.

### When the sync does not run

A sync changes tables that other requests use at the same time, so it takes a lock first. A request that finds the tables locked leaves them to the request that holds the lock.

During a deployment, an older version of your code may still run next to the new one. That code must not undo the changes of the new one. The framework therefore stores the version of your theme or plugin (the `Version:` header) with the tables and does not sync them for code with a lower version. If you roll back to an older version on purpose, the tables stay as the newer version left them.

If a sync fails, the framework writes the error to the log and tries again after five minutes. With `WP_DEBUG`, a failure on `init` is thrown.

Code that uses a table while its sync cannot run – locked, failed or blocked by the version – gets a `RuntimeException` that names the reason. Code that runs before `init`, or after `switch_to_blog()` on another site, syncs the tables before it uses them. While WordPress installs, nothing is synced.

### Multisite

Each site has its own tables, with the table prefix of the site. The sync runs for a site on the first request to it, or when code switches to it with `switch_to_blog()` and uses a table. There are no tables shared by the network. When a site is deleted, its tables of your plugin are dropped with it.

### Removing tables

The tables of a theme stay when the site switches to another theme, so they are there again when it switches back. When a plugin is deleted, the framework drops its tables on every site – also tables an earlier namespace of the plugin left – and deletes the state of their sync. See [Activation, deactivation and uninstallation](./lifecycle.md).

## Rules

[`#[Table(name, renamedFrom: [])]`](../api/php/Table/Table.md) on a class that extends `AbstractRow`:

- `name` and each earlier name: lowercase letters, digits and `_`, starting with a letter; unique among the tables of the theme or plugin. `{namespace}_{name}` has at most 50 characters.
- The class is concrete and has a public constructor of its own. Every constructor parameter is a promoted property, private or protected; the class declares no other properties.
- Exactly one parameter has `#[PrimaryKey]`: `?int` with the default `null`, not readonly.
- Types: `int`, `float`, `bool`, `string`, backed enums and `DateTimeImmutable`, each also nullable. Not: arrays, other objects, `DateTime`, `DateTimeInterface`, union types, `mixed`.
- Parameters take `#[PrimaryKey]`, `#[Indexed]`, `#[RenamedFrom]`, `#[Length]` and `#[Text]` only. Column names are unique and have at most 64 characters.
- `#[Length(n)]`: string columns, 1 to 16383; a default must fit. `#[Text]`: string columns, not together with `#[Length]`. Values of string enums have at most 191 characters.
- `#[Indexed(group, position, unique)]`: not on the primary key, long text or strings longer than 191 characters. A group needs a position and vice versa; positions are 1 to n without gaps; `unique` is the same for every column of a group. Group names follow the rules of table names (not `primary`), have at most 64 characters and differ from the columns with an index of their own. Two indexes with the same columns are an error, and so is an index of more than 3072 bytes.
- A row has at most 65535 bytes without long text, as MariaDB and MySQL count them.
- `#[RenamedFrom(column)]`: an earlier parameter name; not the current one, not the name of another column, not twice in the class.
- A class that extends `AbstractRow` without `#[Table]` is an error. Row classes are no services and cannot be injected or passed to hooks or routes.

`AbstractRow`:

| Method | Effect |
|---|---|
| `static get(int $id)` | The row; `InvalidArgumentException` if there is none. |
| `static find(int $id)` | The row or null. |
| `static query()` | A `RowQuery` of the class. |
| `static tableName()` | The full table name on the current site. |
| `save()` | Inserts the row and sets the primary key, or writes the changed columns; without changes, nothing. With a primary key whose row does not exist, inserts the row with that key and all columns. `RuntimeException` if the database refuses it, e.g. a duplicate key when another request inserted the row first. |
| `delete()` | Deletes the row; the primary key becomes null. `LogicException` for a row that was never saved. |

All of them work once the theme or plugin has booted; before, they throw a `LogicException`.

`RowQuery` (every method that returns a query is `#[\NoDiscard]`):

| Method | Effect |
|---|---|
| `where(Condition ...$conditions)` | All conditions must be met; adds to earlier calls. Conditions on columns of the class only. |
| `orderBy(Field $field, Direction = Asc)` | Sorts; further calls sort what is still equal. Without it: by primary key. |
| `limit(int)`, `offset(int)` | At most this many rows (at least 1), skipping some (with `limit()`). |
| `get()` | The rows, as objects of the row class. |
| `first()` | The first row or null. |
| `count()` | The number of all matching rows, regardless of `limit()` and `offset()`. |
| `delete()` | Deletes the matching rows (with `limit()` the first ones) and returns their number; not with `offset()`. |

Columns and their field classes:

| Column | Field class | Conditions |
|---|---|---|
| `string` | `StringField` | `equals`, `notEquals`, `in`, `notIn`, `greaterThan`, `greaterOrEqual`, `lessThan`, `lessOrEqual`, `between`, `contains` |
| `int`, primary key | `IntField` | as `StringField`, without `contains` |
| `float` | `FloatField` | as `IntField` |
| `bool` | `BoolField` | `equals` |
| backed enum | `EnumField<E>` | `equals`, `notEquals`, `in`, `notIn` |
| `DateTimeImmutable` | `DateTimeField` | as `IntField`, with `DateTimeInterface` |
| nullable (`?string`, …) | `NullableStringField`, `NullableIntField`, `NullableFloatField`, `NullableBoolField`, `NullableEnumField<E>`, `NullableDateTimeField` | as above, and `isNull`, `isNotNull` |

[`#[TableFields(rowClass)]`](../api/php/Table/TableFields.md) on a class of your own: one class per row class; every public static method that returns a field has no parameters and returns a field of `rowClass` with an existing column and its field class (with the enum of the column). The class is no service. In development mode the build runs again when signatures change, not when only a method body changes; a query checks its fields again when it runs.

`wp-app-framework table-fields <row class> [--force]` writes `{RowClass}Fields.php` next to the row class. An existing file is replaced only with `--force`.
