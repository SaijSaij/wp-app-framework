<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Post\Post;
use WebCodingCat\WpAppFramework\Query\Field\EnumField;
use WebCodingCat\WpAppFramework\Query\Field\FieldTypes;
use WebCodingCat\WpAppFramework\Query\Field\FloatField;
use WebCodingCat\WpAppFramework\Query\Field\IntField;
use WebCodingCat\WpAppFramework\Query\Field\PostField;
use WebCodingCat\WpAppFramework\Query\Field\StringField;
use WebCodingCat\WpAppFramework\Query\FieldClassGenerator;
use WebCodingCat\WpAppFramework\Query\FieldCondition;
use WebCodingCat\WpAppFramework\Query\MetaFields;
use WebCodingCat\WpAppFramework\Query\MetaOrderClauses;
use WebCodingCat\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;
use WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;
use WebCodingCat\WpAppFramework\Tests\Support\Posts;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\Tests\Unit\Post\PostTypeCollectorTest;
use WebCodingCat\WpAppFramework\Value\ValueFactory;

/**
 * Classes with #[MetaFields]: the check in the build, the generator and the SQL of fields.
 *
 * @phpstan-import-type Value from ValueFactory
 */
final class MetaFieldsTest extends TestCase
{
    public function testFieldClassesAreNoServices(): void
    {
        $result = PostTypeCollectorTest::buildValid();

        self::assertNotContains(PostTypeValid\EventDetailsFields::class, $result->getCandidates());
        self::assertNotContains(PostTypeValid\ExtrasFields::class, $result->getCandidates());
    }

    public function testEveryMethodThatReturnsAFieldIsChecked(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('MetaFieldsBroken'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build();
            self::fail('The build must fail.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
        }

        $fields = MetaFieldsBroken\ShowDetailsFields::class;
        $meta = MetaFieldsBroken\ShowDetails::class;
        self::assertSame([
            sprintf('#[MetaFields] on %s names %s, which is no meta class (#[PostMeta]) of this theme or plugin.', MetaFieldsBroken\NoMeta::class, MetaFieldsBroken\Kind::class),
            sprintf('%s::wrongClass(): %s::$seats needs %s, %s given.', $fields, $meta, IntField::class, StringField::class),
            sprintf('%s::wrongEnum(): %s::$kind needs %s<%s>, %s<%s> given.', $fields, $meta, EnumField::class, MetaFieldsBroken\Kind::class, EnumField::class, PostTypeValid\Density::class),
            sprintf('%s::wrongPost(): %s::$next needs %s<%s>, %s<%s> given.', $fields, $meta, PostField::class, MetaFieldsBroken\Show::class, PostField::class, Post::class),
            sprintf('%s::unknown() returns the field $sits, which %s does not have (fields: seats, kind, next, tags).', $fields, $meta),
            sprintf('%s::list(): %s::$tags is a list or value class, stored serialized; only fields with one int, float, string, bool, enum, ID or post can be queried.', $fields, $meta),
            sprintf('%s::otherMeta() returns a field of %s; the class provides the fields of %s (#[MetaFields]).', $fields, MetaFieldsBroken\ShowExtras::class, $meta),
            sprintf('%s::withParameter() returns a field and must have no parameters.', $fields),
            sprintf('%s::failing() failed: Not today.', $fields),
            sprintf('#[MetaFields] on %s names %s, whose fields %s already provides; one class per meta class.', MetaFieldsBroken\ShowDetailsMoreFields::class, $meta, $fields),
        ], $errors);
    }

    public function testTheGeneratorWritesOneMethodPerQueryableField(): void
    {
        $builder = new ManifestBuilder([ManifestBuilderTest::fixture('PostTypeValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme');

        // The fixture is the generated class (lists and value classes are left out).
        self::assertSame(
            file_get_contents(__DIR__ . '/../../Fixtures/PostTypeValid/EventDetailsFields.php'),
            FieldClassGenerator::generate(PostTypeValid\EventDetails::class, MetaFields::class, 'meta-fields', FieldTypes::queryable($builder->metaFieldValues(PostTypeValid\EventDetails::class))),
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage(sprintf('%s is no meta class (#[PostMeta]) of the PSR-4 sources of this theme or plugin.', PostTypeValid\Event::class));
        $builder->metaFieldValues(PostTypeValid\Event::class);
    }

    public function testTheGeneratorImportsClassesOfOtherNamespaces(): void
    {
        /** @var array<string, Value> $values As the build describes them; Other\Venue stands for a class of another package. */
        $values = [
            'kind' => ['type' => 'enum', 'class' => PostTypeValid\Density::class, 'nullable' => false],
            'rate' => ['type' => 'float', 'nullable' => false],
            // Same short name as the post class Post of the framework: written in full.
            'venue' => ['type' => 'int', 'nullable' => true, 'reference' => ['target' => 'post', 'subtypes' => null, 'class' => PostTypeValid\Venue::class]],
            'page' => ['type' => 'int', 'nullable' => true, 'reference' => ['target' => 'post', 'subtypes' => null, 'class' => 'Other\Venue']],
        ];
        $source = FieldClassGenerator::generate(MetaFieldsBroken\ShowDetails::class, MetaFields::class, 'meta-fields', FieldTypes::queryable($values));

        self::assertStringContainsString("namespace WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\MetaFieldsBroken;\n\nuse WebCodingCat\\WpAppFramework\\Query\\Field\\EnumField;\nuse WebCodingCat\\WpAppFramework\\Query\\Field\\FloatField;\nuse WebCodingCat\\WpAppFramework\\Query\\Field\\PostField;\nuse WebCodingCat\\WpAppFramework\\Query\\MetaFields;\nuse WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\PostTypeValid\\Density;\nuse WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\PostTypeValid\\Venue;\n", $source);
        self::assertStringContainsString("        return new EnumField(ShowDetails::class, 'kind', Density::class);\n", $source);
        self::assertStringContainsString("    public static function rate(): FloatField\n", $source);
        self::assertStringContainsString("     * @return PostField<\\Other\\Venue>\n", $source);
        self::assertStringContainsString("        return new PostField(ShowDetails::class, 'page', \\Other\\Venue::class);\n", $source);
    }

    public function testDefaultsAreComparedLikeTheDatabaseComparesThem(): void
    {
        Posts::store();
        try {
            $between = PostTypeValid\EventDetailsFields::capacity()->between(-1, 1);
            self::assertInstanceOf(FieldCondition::class, $between);
            self::assertSame('BETWEEN', $between->getOperator());
            self::assertSame([-1, 1], $between->getValues());
            $missing = static fn(string $key): array => ['key' => $key, 'compare' => 'NOT EXISTS'];

            self::assertSame([
                'relation' => 'AND',
                // The default 0 is between -1 and 1, so a missing value is too.
                ['relation' => 'OR', ['key' => 'fixture_capacity', 'compare' => 'BETWEEN', 'value' => [-1, 1], 'type' => 'SIGNED'], $missing('fixture_capacity')],
                ['key' => 'fixture_capacity', 'compare' => '>', 'value' => 0, 'type' => 'SIGNED'],
                ['relation' => 'OR', ['key' => 'fixture_start_date', 'compare' => 'NOT IN', 'value' => ['a', 'b'], 'type' => 'CHAR'], $missing('fixture_start_date')],
                ['key' => 'fixture_start_date', 'compare' => 'LIKE', 'value' => 'x', 'type' => 'CHAR'],
            ], PostTypeValid\Event::query()->where(
                $between,
                PostTypeValid\EventDetailsFields::capacity()->greaterThan(0),
                PostTypeValid\EventDetailsFields::startDate()->notIn('a', 'b'),
                PostTypeValid\EventDetailsFields::startDate()->contains('x'),
            )->toArgs()['meta_query']);
        } finally {
            Posts::reset();
        }

        // Without regard to case, like the default collations.
        self::assertSame('jazz', new StringField(PostTypeValid\EventDetails::class, 'title')->normalize('Jazz'));
        self::assertSame(2.0, new FloatField(PostTypeValid\EventDetails::class, 'rate')->normalize('2'));
    }

    public function testSortClausesKeepTheGroupingAndOrderOfWordPress(): void
    {
        global $wpdb;
        self::assertInstanceOf(\wpdb::class, $wpdb);

        $clauses = MetaOrderClauses::apply(
            ['join' => ' INNER JOIN wp_postmeta ON (x)', 'groupby' => 'wp_posts.ID', 'orderby' => 'wp_posts.menu_order ASC', 'where' => ' AND 1=1'],
            [['meta' => "it's", 'default' => "o'clock", 'cast' => 'CHAR', 'direction' => 'DESC'], ['column' => 'RAND()', 'direction' => 'ASC']],
            $wpdb,
        );

        self::assertSame([
            'join' => " INNER JOIN wp_postmeta ON (x) LEFT JOIN wp_postmeta AS wpaf_order_0 ON (wpaf_order_0.post_id = wp_posts.ID AND wpaf_order_0.meta_key = 'it\\'s')",
            'groupby' => 'wp_posts.ID',
            'orderby' => "COALESCE(MIN(CAST(wpaf_order_0.meta_value AS CHAR)), CAST('o\\'clock' AS CHAR)) DESC, RAND(), wp_posts.menu_order ASC",
            'where' => ' AND 1=1',
        ], $clauses);
    }
}
