<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Post;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Cache\CachePolicy;
use WebCodingCat\WpAppFramework\Cache\Tag\PostTypeTag;
use WebCodingCat\WpAppFramework\Cache\Tag\TaxonomyTag;
use WebCodingCat\WpAppFramework\Cache\TagNames;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Post\Category;
use WebCodingCat\WpAppFramework\Post\Page;
use WebCodingCat\WpAppFramework\Post\Post;
use WebCodingCat\WpAppFramework\Post\Tag;
use WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * Build of post classes (#[PostType]), meta classes (#[PostMeta]) and taxonomies (#[Taxonomy]).
 */
final class PostTypeCollectorTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('PostTypeValid'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build();
    }

    public function testPostTypesAreNamedAfterTheNamespaceWithGeneratedLabels(): void
    {
        $posts = self::buildValid()->getPosts()['posts'];

        self::assertSame([Post::class, Page::class, PostTypeValid\Event::class, PostTypeValid\Venue::class], array_keys($posts));
        self::assertSame(['post', 'posts', true, null], [$posts[Post::class]['name'], $posts[Post::class]['restBase'], $posts[Post::class]['builtin'], $posts[Post::class]['args']]);

        $event = $posts[PostTypeValid\Event::class];
        self::assertSame(['fixture_event', 'fixture_event', false, false], [$event['name'], $event['restBase'], $event['builtin'], $event['hierarchical']]);
        self::assertSame([PostTypeValid\EventDetails::class], $event['meta']);
        $args = $event['args'];
        self::assertNotNull($args);
        self::assertSame('Events', $args['labels']['name']);
        self::assertSame('Edit Event', $args['labels']['edit_item']);
        self::assertSame('No events found.', $args['labels']['not_found']);
        // Set in PostTypeLabels.
        self::assertSame('Neues Event', $args['labels']['add_new_item']);
        // custom-fields for the meta in the REST API.
        self::assertSame(['title', 'editor', 'revisions', 'custom-fields'], $args['supports']);
        self::assertTrue($args['show_in_rest']);
        self::assertSame(['slug' => 'event'], $args['rewrite']);
        self::assertSame('event', $args['has_archive']);
        self::assertSame('dashicons-calendar', $args['menu_icon'] ?? null);
        self::assertArrayNotHasKey('menu_position', $args);

        $venue = $posts[PostTypeValid\Venue::class]['args'];
        self::assertNotNull($venue);
        self::assertSame([false, true, ['title', 'page-attributes'], false, ['slug' => 'places']], [$venue['public'], $venue['show_ui'], $venue['supports'], $venue['has_archive'], $venue['rewrite']]);
        self::assertSame([PostTypeValid\Extras::class], $posts[Page::class]['meta']);
    }

    public function testMetaFieldsHaveAKeyOfTheNamespaceAndASchemaWithoutNull(): void
    {
        $meta = self::buildValid()->getPosts()['meta'][PostTypeValid\EventDetails::class];

        self::assertSame(['details', 'Event details', [PostTypeValid\Event::class]], [$meta['name'], $meta['title'], $meta['postTypes']]);
        self::assertSame(
            ['capacity', 'startDate', 'venue', 'tickets', 'density', 'note', 'photo', 'alternatives', 'related', 'registration'],
            array_keys($meta['fields']),
        );
        self::assertSame('fixture_start_date', $meta['fields']['startDate']['key']);
        self::assertSame(['type' => 'integer', 'default' => 0], $meta['fields']['capacity']['schema']);
        // IDs: the REST API leaves out types with null, so no post or image is 0.
        foreach (['venue', 'photo', 'related'] as $id) {
            self::assertSame(['integer', ['type' => 'integer', 'default' => 0], true], [$meta['fields'][$id]['type'], $meta['fields'][$id]['schema'], $meta['fields'][$id]['id']]);
        }
        self::assertSame(['type' => 'string', 'enum' => ['compact', 'normal'], 'default' => 'normal'], $meta['fields']['density']['schema']);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'integer'], 'default' => []], $meta['fields']['alternatives']['schema']);
        self::assertSame('array', $meta['fields']['tickets']['type']);
        self::assertSame([''], $meta['fields']['note']['richText']);

        self::assertSame(
            ['type' => 'reference', 'label' => 'Venue', 'nullable' => true, 'target' => 'post', 'subtypes' => ['fixture_venue'], 'restBase' => 'fixture_venue'],
            $meta['controls']['venue'],
        );
        self::assertSame('references', $meta['controls']['alternatives']['type']);
        self::assertSame(['fixture_event', 'page'], $meta['controls']['related']['subtypes']);
        self::assertSame('rich', $meta['controls']['note']['type']);
        self::assertSame('image', $meta['controls']['photo']['type']);
        self::assertSame('Start', $meta['controls']['startDate']['label']);
        // A link is an object with a default, also on the top level of the meta (the REST API leaves out null only).
        self::assertSame(['object', false], [$meta['fields']['registration']['type'], $meta['fields']['registration']['id']]);
        self::assertSame(['url' => '', 'opensInNewTab' => false, 'kind' => 'url', 'id' => 0], $meta['fields']['registration']['schema']['default']);
        self::assertSame(['type' => 'link', 'label' => 'Registration', 'nullable' => false], $meta['controls']['registration']);
    }

    public function testTaxonomiesAndTheRewriteHash(): void
    {
        $result = self::buildValid()->getPosts();
        $genre = $result['taxonomies'][PostTypeValid\Genre::class];

        // WordPress' own taxonomies come first, registered by WordPress.
        self::assertSame([Category::class, Tag::class, PostTypeValid\Genre::class], array_keys($result['taxonomies']));
        self::assertSame(['name' => 'category', 'builtin' => true, 'args' => null, 'postTypes' => ['post']], $result['taxonomies'][Category::class]);
        self::assertSame('post_tag', $result['taxonomies'][Tag::class]['name']);
        self::assertSame(['fixture_genre', false, ['fixture_event', 'post']], [$genre['name'], $genre['builtin'], $genre['postTypes']]);
        self::assertNotNull($genre['args']);
        self::assertSame(['Genres', 'Add New Genre', true, true, ['slug' => 'genre']], [
            $genre['args']['labels']['name'],
            $genre['args']['labels']['add_new_item'],
            $genre['args']['hierarchical'],
            $genre['args']['show_in_rest'],
            $genre['args']['rewrite'],
        ]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $result['rewrite']);
    }

    public function testPostClassesAreReferencesInValuesAndNamesInTags(): void
    {
        $result = self::buildValid();
        $block = $result->getBlocks()[0];

        self::assertSame(['type' => ['integer', 'null'], 'role' => 'content'], $block->getArgs()['attributes']['event']);
        self::assertSame(
            ['type' => 'int', 'nullable' => true, 'reference' => ['target' => 'post', 'subtypes' => ['fixture_event'], 'class' => PostTypeValid\Event::class]],
            $result->getValues()[PostTypeValid\EventTeaserAttributes::class]['event']['value'],
        );
        // Category is WordPress' category, by class like the taxonomies of the theme.
        self::assertSame(['post_type:fixture_event', 'taxonomy:fixture_genre', 'taxonomy:category'], $block->getCache()['tags'] ?? null);
        self::assertSame(
            ['type' => 'int', 'nullable' => true, 'reference' => ['target' => 'term', 'subtypes' => ['category', 'fixture_genre']]],
            $result->getValues()[PostTypeValid\EventTeaserAttributes::class]['topic']['value'],
        );
        self::assertArrayHasKey(PostTypeValid\EventDetails::class, $result->getValues());

        $names = new TagNames([], [PostTypeValid\Event::class => 'fixture_event'], [PostTypeValid\Genre::class => 'fixture_genre']);
        self::assertSame(['post_type:fixture_event', null], CachePolicy::tagKey(new PostTypeTag(PostTypeValid\Event::class), $names));
        self::assertSame(['post_type:page', null], CachePolicy::tagKey(new PostTypeTag('page'), $names));
        self::assertSame(
            [null, sprintf('TaxonomyTag needs a taxonomy name or a taxonomy class (#[Taxonomy] of this theme or plugin, Category or Tag), %s given', PostTypeValid\Event::class)],
            CachePolicy::tagKey(new TaxonomyTag(PostTypeValid\Event::class), $names),
        );
    }

    public function testPostClassesAreNoServices(): void
    {
        $candidates = self::buildValid()->getCandidates();

        foreach ([PostTypeValid\Event::class, PostTypeValid\EventDetails::class, PostTypeValid\Genre::class, Post::class] as $class) {
            self::assertNotContains($class, $candidates);
        }
    }

    public function testReportsAllErrorsWithClassAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('PostTypeBroken'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\PostTypeBroken\\';
            $abstract = 'WebCodingCat\\WpAppFramework\\Post\\AbstractPost';
            $expected = [
                "parameter \$post of {$ns}PostInHook::run(): {$ns}Valid is a post class; WordPress passes no such objects to hooks.",
                "#[PostType] on {$ns}BadName has the invalid name \"Bad-Name\": use lowercase letters, digits and \"_\", starting with a letter, e.g. \"event\".",
                "#[PostType] on {$ns}BadSlug has the invalid slug \"Bad Slug\": use lowercase letters, digits, \"-\" and \"_\".",
                "{$ns}Combined has #[PostType] and #[Taxonomy]; a class is a post class, a meta class or a taxonomy, not several.",
                "#[PostType] on {$ns}DuplicateB uses the name \"twice\", which {$ns}DuplicateA already uses.",
                "#[PostType] on {$ns}EmptyLabel needs a label and a singularLabel.",
                "#[PostType] on {$ns}NotAPost: the class must extend {$abstract}; it is the object of a post of the type.",
                "#[PostType] on {$ns}TooLong results in the post type \"fixture_much_too_long\", which is longer than 20 characters (the limit of register_post_type()); use a shorter name or namespace.",
                "{$ns}WithProperty declares the property \$cached; post classes keep no data of their own (it would not be stored). Declare the fields in a meta class (#[PostMeta]).",
                "{$ns}WithoutAttribute extends {$abstract}, but has no #[PostType]; post classes declare their post type (WordPress' own types are Post and Page).",
                "#[PostMeta] on {$ns}EmptyPostTypes: postTypes must be a non-empty list of post classes, e.g. [Event::class, Post::class].",
                "parameter \$shared of meta class {$ns}KeyB::__construct() uses the meta key \"fixture_shared\" on {$ns}Valid, which {$ns}KeyA::\$shared already uses.",
                "#[PostMeta] on {$ns}KeyB uses the name \"key_a\" for {$ns}Valid, which {$ns}KeyA already uses.",
                "parameter \$count of meta class {$ns}MetaNoDefault::__construct() needs a default value: a meta field is missing until it is saved.",
                "parameter \$text of meta class {$ns}MetaNullable::__construct() is nullable; the REST API of WordPress leaves out meta fields whose type allows null. Use a default value instead; only references and images (IDs) may be null.",
                "parameter \$content of meta class {$ns}MetaContent::__construct() has #[Content], which marks the content of blocks; settings groups and post meta have no block content.",
                "#[PostMeta] on {$ns}UnknownPostType: postTypes contains {$ns}NotAPost, which is no post class (#[PostType], Post or Page) of this theme or plugin.",
                "#[PostMeta] on {$ns}ReadonlyMeta: meta class {$ns}ReadonlyMeta must not be readonly: the post object changes its values, and save() stores them.",
                "#[PostMeta] on {$ns}ExtraProperty: meta class {$ns}ExtraProperty declares the property \$computed outside the constructor; it would not be stored. All fields are promoted constructor parameters.",
                "parameter \$item of meta class {$ns}BadReference::__construct(): postTypes of #[PostReference] contains {$ns}UsesPost, which is no post class (#[PostType], Post or Page) of this theme or plugin.",
                "parameter \$required of meta class {$ns}BadReference::__construct(): a reference to a Valid must be nullable (null without post).",
                "#[Taxonomy] on {$ns}TaxonomyProblems: the class has constructor parameters; taxonomy classes declare no fields (term meta is not supported yet).",
                "#[Taxonomy] on {$ns}TaxonomyTooLong results in the taxonomy \"fixture_a_taxonomy_name_that_is_too_long\", which is longer than 32 characters (the limit of register_taxonomy()); use a shorter name or namespace.",
                "Cannot autowire parameter \$valid of {$ns}UsesPost::__construct(): {$ns}Valid is a post class and cannot be injected; load posts with Valid::get(\$id).",
            ];

            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors);
            }
            self::assertCount(count($expected), $errors);
        }
    }
}
