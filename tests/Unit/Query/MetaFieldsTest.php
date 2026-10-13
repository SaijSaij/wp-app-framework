<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Post\Post;
use Saij\WpAppFramework\Query\Field\EnumField;
use Saij\WpAppFramework\Query\Field\FloatField;
use Saij\WpAppFramework\Query\Field\IntField;
use Saij\WpAppFramework\Query\Field\PostField;
use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Query\MetaCondition;
use Saij\WpAppFramework\Query\MetaFieldsGenerator;
use Saij\WpAppFramework\Query\MetaOrderClauses;
use Saij\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Tests\Unit\Post\PostTypeCollectorTest;
use Saij\WpAppFramework\Value\ValueFactory;

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
            MetaFieldsGenerator::generate(PostTypeValid\EventDetails::class, $builder->metaFieldValues(PostTypeValid\EventDetails::class)),
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
        $source = MetaFieldsGenerator::generate(MetaFieldsBroken\ShowDetails::class, $values);

        self::assertStringContainsString("namespace Saij\\WpAppFramework\\Tests\\Fixtures\\MetaFieldsBroken;\n\nuse Saij\\WpAppFramework\\Query\\Field\\EnumField;\nuse Saij\\WpAppFramework\\Query\\Field\\FloatField;\nuse Saij\\WpAppFramework\\Query\\Field\\PostField;\nuse Saij\\WpAppFramework\\Query\\MetaFields;\nuse Saij\\WpAppFramework\\Tests\\Fixtures\\PostTypeValid\\Density;\nuse Saij\\WpAppFramework\\Tests\\Fixtures\\PostTypeValid\\Venue;\n", $source);
        self::assertStringContainsString("        return new EnumField(ShowDetails::class, 'kind', Density::class);\n", $source);
        self::assertStringContainsString("    public static function rate(): FloatField\n", $source);
        self::assertStringContainsString("     * @return PostField<\\Other\\Venue>\n", $source);
        self::assertStringContainsString("        return new PostField(ShowDetails::class, 'page', \\Other\\Venue::class);\n", $source);
    }

    public function testDefaultsAreComparedLikeTheDatabaseComparesThem(): void
    {
        $rate = new FloatField(PostTypeValid\EventDetails::class, 'rate');
        $between = $rate->between(1.5, 2.5);
        self::assertInstanceOf(MetaCondition::class, $between);
        self::assertSame(['key' => 'k', 'compare' => 'BETWEEN', 'value' => [1.5, 2.5], 'type' => 'DECIMAL(65,30)'], $between->clause('k', 0.0));
        self::assertTrue($between->matches('2'));

        $title = new StringField(PostTypeValid\EventDetails::class, 'title');
        $equals = $title->equals('Jazz');
        self::assertInstanceOf(MetaCondition::class, $equals);
        // Without regard to case, like the default collations.
        self::assertTrue($equals->matches('jazz'));
        $contains = $title->contains('AZ');
        self::assertInstanceOf(MetaCondition::class, $contains);
        self::assertTrue($contains->matches('Jazz'));
        $notIn = $title->notIn('a', 'b');
        self::assertInstanceOf(MetaCondition::class, $notIn);
        self::assertTrue($notIn->matches(''));
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
