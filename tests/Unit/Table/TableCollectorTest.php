<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Table;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Query\FieldClassGenerator;
use Saij\WpAppFramework\Table\TableCollector;
use Saij\WpAppFramework\Table\TableFields;
use Saij\WpAppFramework\Tests\Fixtures\TableValid;
use Saij\WpAppFramework\Tests\Support\Tables;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * Row classes in the build: the structure of the tables in the manifest, the hash of the schema and every
 * declaration error.
 */
final class TableCollectorTest extends TestCase
{
    public function testTheManifestDescribesEveryColumnAndIndex(): void
    {
        $tables = Tables::data();
        $log = $tables['classes'][TableValid\LogEntry::class];

        self::assertSame('1.2.0', $tables['version']);
        self::assertSame([TableValid\Counter::class, TableValid\LogEntry::class], array_keys($tables['classes']));
        self::assertSame(['log', ['journal'], 'id'], [$log['name'], $log['renamedFrom'], $log['primaryKey']]);
        self::assertSame(
            ['id', 'level', 'message', 'context', 'reference', 'attempts', 'ratio', 'flagged', 'post_id', 'priority', 'handled', 'created'],
            array_values(array_column($log['columns'], 'column')),
        );
        $sql = [];
        foreach ($log['columns'] as $property => $column) {
            $sql[$property] = [$column['kind'], $column['sql'], $column['nullable'], $column['default']];
        }
        self::assertSame([
            'id' => ['int', 'bigint(20) unsigned', false, null],
            'level' => ['enum', 'varchar(191)', false, 'info'],
            'message' => ['string', 'varchar(500)', false, ''],
            'context' => ['string', 'longtext', false, null],
            'reference' => ['string', 'varchar(191)', true, null],
            'attempts' => ['int', 'bigint(20)', false, 0],
            'ratio' => ['float', 'double', false, 0.5],
            'flagged' => ['bool', 'tinyint(1)', false, 0],
            'postId' => ['int', 'bigint(20)', true, null],
            'priority' => ['enum', 'bigint(20)', true, null],
            'handled' => ['datetime', 'datetime', true, null],
            // A default that is no constant is no default of the column.
            'created' => ['datetime', 'datetime', false, null],
        ], $sql);
        self::assertSame(['text', 'body'], $log['columns']['message']['renamedFrom']);
        self::assertSame([TableValid\Level::class, true], [$log['columns']['level']['enum'], $log['columns']['context']['text']]);
        self::assertSame([
            ['name' => 'reference', 'columns' => ['reference'], 'unique' => true],
            ['name' => 'post_id', 'columns' => ['post_id'], 'unique' => false],
            ['name' => 'level_created', 'columns' => ['level', 'created'], 'unique' => false],
        ], $log['indexes']);
        self::assertSame(['value' => ['type' => 'datetime', 'nullable' => false], 'required' => false], $log['values']['created']);
        self::assertSame(['value' => ['type' => 'enum', 'class' => TableValid\Priority::class, 'nullable' => true], 'required' => false], $log['values']['priority']);
    }

    public function testTheSchemaHashFollowsTheStructureAndNamespace(): void
    {
        $tables = Tables::data();
        $schema = $tables['schema'];

        self::assertSame($schema, Tables::data('2.0.0')['schema'], 'The version is not part of the schema.');
        self::assertSame($schema, TableCollector::schema('dev-test', $tables['classes']));
        self::assertNotSame($schema, TableCollector::schema('other', $tables['classes']));
        $counter = $tables['classes'][TableValid\Counter::class];
        $log = $tables['classes'][TableValid\LogEntry::class];
        $count = [...$counter['columns']['count'], 'default' => 1];
        self::assertNotSame($schema, TableCollector::schema('dev-test', [...$tables['classes'], TableValid\Counter::class => [...$counter, 'columns' => [...$counter['columns'], 'count' => $count]]]));
        $message = [...$log['columns']['message'], 'renamedFrom' => ['text']];
        self::assertNotSame($schema, TableCollector::schema('dev-test', [...$tables['classes'], TableValid\LogEntry::class => [...$log, 'columns' => [...$log['columns'], 'message' => $message]]]));
        // A change of the PHP side only, e.g. a required parameter, is no change of the tables.
        $level = [...$log['values']['level'], 'required' => true];
        self::assertSame($schema, TableCollector::schema('dev-test', [...$tables['classes'], TableValid\LogEntry::class => [...$log, 'values' => [...$log['values'], 'level' => $level]]]));
        self::assertNull(new ManifestBuilder([ManifestBuilderTest::fixture('PostTypeValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build()->getTables()['schema']);
    }

    public function testColumnNamesAreSnakeCase(): void
    {
        self::assertSame(['post_id', 'url', 'u_r_l', 'already_snake'], array_map(TableCollector::columnName(...), ['postId', 'url', 'URL', 'already_snake']));
    }

    public function testRowAndFieldClassesAreNoServices(): void
    {
        $candidates = new ManifestBuilder([ManifestBuilderTest::fixture('TableValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build()->getCandidates();

        self::assertContains(TableValid\LogService::class, $candidates);
        foreach ([TableValid\LogEntry::class, TableValid\LogEntryFields::class, TableValid\CounterFields::class] as $class) {
            self::assertNotContains($class, $candidates);
        }
    }

    public function testEveryDeclarationErrorIsABuildError(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('TableBroken'), ManifestBuilder::frameworkSource()], slug: 'my-theme', namespace: 'dev-test')->build();
            self::fail('The build must fail.');
        } catch (BuildException $exception) {
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\TableBroken\\';
            $fw = 'Saij\\WpAppFramework\\';
            self::assertSame([
                "parameter \$fields of {$ns}RowInService::run(): {$ns}ValidFields is a table fields class; WordPress passes no such objects to hooks.",
                "#[Table] on {$ns}AbstractTable: the class must not be abstract.",
                "Parameter \$post_id of {$ns}Columns::__construct() has the column name \"post_id\", which \$postId already has.",
                "Parameter \$aParameterNameThatIsMuchTooLongForAColumnOfMariaDbAndMySqlTables of {$ns}Columns::__construct() has the column name \"a_parameter_name_that_is_much_too_long_for_a_column_of_maria_db_and_my_sql_tables\", longer than 64 characters.",
                "Parameter \$both of {$ns}Columns::__construct() has #[Length] and #[Text]; use one.",
                "Parameter \$empty of {$ns}Columns::__construct() has #[Length(0)]; the length is 1 to 16383 characters, use #[Text] for longer strings.",
                "Parameter \$number of {$ns}Columns::__construct() has #[Length], which only string columns take.",
                "Parameter \$short of {$ns}Columns::__construct() has a default of 6 characters, longer than the column (3).",
                "Parameter \$renamed of {$ns}Columns::__construct() has #[RenamedFrom('')]; name an earlier parameter name, not the current one.",
                "Parameter \$renamed of {$ns}Columns::__construct() has #[RenamedFrom('renamed')]; name an earlier parameter name, not the current one.",
                "Parameter \$title of {$ns}Columns::__construct() has #[RenamedFrom('name')] twice.",
                "#[RenamedFrom] on parameter \$taken of {$ns}Columns::__construct() names the column \"post_id\", which \$postId has now; an earlier name must not be in use.",
                "#[RenamedFrom] on parameter \$again of {$ns}Columns::__construct() names the column \"caption\", an earlier name of \$subtitle as well.",
                "#[Indexed] on parameter \$id of {$ns}Indexes::__construct(): the primary key is indexed already.",
                "#[Indexed] on parameter \$text of {$ns}Indexes::__construct(): long text (#[Text]) cannot be indexed.",
                "#[Indexed] on parameter \$wide of {$ns}Indexes::__construct(): strings of more than 191 characters cannot be indexed.",
                "#[Indexed] on parameter \$a of {$ns}Indexes::__construct() has a position but no group.",
                "#[Indexed] on parameter \$a of {$ns}Indexes::__construct() has the group \"nowhere\" but no position.",
                "#[Indexed] on parameter \$a of {$ns}Indexes::__construct() has the invalid group 'Bad-Group': use lowercase letters, digits and \"_\", starting with a letter (not \"primary\").",
                "#[Indexed] on parameter \$a of {$ns}Indexes::__construct() has the invalid group 'primary': use lowercase letters, digits and \"_\", starting with a letter (not \"primary\").",
                "#[Indexed] on parameter \$b of {$ns}Indexes::__construct(): the column has an index of its own already.",
                "#[Indexed] on parameter \$d of {$ns}Indexes::__construct(): the group \"a_group_name_that_is_longer_than_the_sixty_four_characters_of_mariadb\" is longer than 64 characters.",
                "#[Indexed] group \"gap\" of {$ns}Indexes has the positions 1, 3; number the columns 1 to 2.",
                "#[Indexed] group \"mixed\" of {$ns}Indexes is unique for some columns only; set unique on all or none.",
                "#[Indexed] group \"b\" of {$ns}Indexes has the name of the index of the column b.",
                "#[Indexed] group \"same\" of {$ns}Indexes has a column twice.",
                "#[Indexed] on {$ns}Indexes: the indexes \"d\" and \"only_d\" have the same columns.",
                "Parameter \$case of {$ns}LongEnum::__construct(): the value of {$ns}LongCase::Long is longer than 191 characters.",
                "#[Table] on {$ns}Names has the invalid name 'Bad-Name': use lowercase letters, digits and \"_\", starting with a letter, e.g. \"log\".",
                "#[Table] on {$ns}Names: the earlier name \"a_name_that_is_far_too_long_for_the_prefix_of_wordpress\" is too long; \"dev_test_a_name_that_is_far_too_long_for_the_prefix_of_wordpress\" may have at most 50 characters, so that the prefix of WordPress fits.",
                "{$ns}NoPrimaryKey has no #[PrimaryKey]; mark the parameter `#[PrimaryKey] private ?int \$id = null`.",
                "#[Table] on {$ns}NotARow: the class must extend {$fw}Table\\AbstractRow; it is the object of a row of the table.",
                "{$ns}PrimaryKeys has several #[PrimaryKey] parameters (\$id, \$other); a table has one primary key.",
                "{$ns}Properties declares the property \$cache outside the constructor; it would not be stored. All columns are promoted constructor parameters.",
                "Parameter \$title of {$ns}Properties::__construct() must be private or protected; give the class getters and setters for its columns.",
                "Parameter \$plain of {$ns}Properties::__construct() must be a promoted property; every constructor parameter is a column.",
                "#[Indexed] on {$ns}Sizes: the index \"wide\" needs 3820 bytes, more than the 3072 that MariaDB and MySQL allow.",
                "Parameter \$list of {$ns}Types::__construct() has the type array; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable.",
                "Parameter \$other of {$ns}Types::__construct() has the type ?{$ns}Valid; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable.",
                "Parameter \$union of {$ns}Types::__construct() has the type string|int; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable.",
                "Parameter \$anything of {$ns}Types::__construct() has the type mixed; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable.",
                "Parameter \$time of {$ns}Types::__construct() has the type ?DateTime; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable (use DateTimeImmutable).",
                "Parameter \$moment of {$ns}Types::__construct() has the type ?DateTimeInterface; columns are int, float, bool, string, backed enums and DateTimeImmutable, each also nullable (use DateTimeImmutable).",
                "Parameter \$marked of {$ns}Types::__construct() has #[{$fw}Value\\PlainText]; columns of tables take #[PrimaryKey], #[Indexed], #[RenamedFrom], #[Length] and #[Text] only.",
                "#[Table] on {$ns}Valid uses the name \"valid\", which {$ns}Names already uses.",
                "#[Table] on {$ns}Wide: a row needs 65538 bytes without long text, more than the 65535 that MariaDB and MySQL allow; shorten #[Length] or use #[Text].",
                "{$ns}WithoutTable extends {$fw}Table\\AbstractRow, but has no #[Table]; row classes declare their table.",
                "Parameter \$id of {$ns}WrongPrimaryKey::__construct() has #[PrimaryKey] and must be `?int \$id = null`, not readonly: save() sets it when it inserts the row.",
                "Cannot autowire parameter \$valid of {$ns}UsesRow::__construct(): {$ns}Valid is a row class and cannot be injected; load rows with Valid::get(\$id).",
            ], $exception->getErrors());
        }
    }

    public function testTheGeneratorWritesOneMethodPerColumn(): void
    {
        $builder = new ManifestBuilder([ManifestBuilderTest::fixture('TableValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme');

        // The fixture is the generated class.
        self::assertSame(
            file_get_contents(__DIR__ . '/../../Fixtures/TableValid/LogEntryFields.php'),
            FieldClassGenerator::generate(TableValid\LogEntry::class, TableFields::class, 'table-fields', $builder->tableFieldValues(TableValid\LogEntry::class)),
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage(sprintf('%s is no row class (#[Table]) of the PSR-4 sources of this theme or plugin.', TableValid\Level::class));
        $builder->tableFieldValues(TableValid\Level::class);
    }

    public function testTheGeneratorReportsAnInvalidRowClass(): void
    {
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('has no #[PrimaryKey]');
        new ManifestBuilder([ManifestBuilderTest::fixture('TableBroken'), ManifestBuilder::frameworkSource()], slug: 'my-theme')
            ->tableFieldValues('Saij\\WpAppFramework\\Tests\\Fixtures\\TableBroken\\NoPrimaryKey');
    }
}
