<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\ReferenceValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ReferenceTemplateFunctions;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * References to posts, terms and users: build (schema, controls, errors) and resolution at runtime.
 */
final class ReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_app_framework_test_posts'] = [
            1 => new WP_Post((object) ['ID' => 1, 'post_type' => 'page', 'post_title' => 'About & us']),
            2 => new WP_Post((object) ['ID' => 2, 'post_type' => 'post', 'post_title' => 'News']),
            3 => new WP_Post((object) ['ID' => 3, 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Draft']),
            4 => new WP_Post((object) ['ID' => 4, 'post_type' => 'wp_template']),
        ];
        $GLOBALS['wp_app_framework_test_terms'] = [
            10 => new WP_Term((object) ['term_id' => 10, 'taxonomy' => 'category', 'name' => 'Food &amp; drink']),
            11 => new WP_Term((object) ['term_id' => 11, 'taxonomy' => 'post_tag', 'name' => 'Tag']),
        ];
        $GLOBALS['wp_app_framework_test_users'] = [20 => new WP_User((object) ['ID' => 20, 'display_name' => 'Ann &amp; Bob'])];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_posts'], $GLOBALS['wp_app_framework_test_terms'], $GLOBALS['wp_app_framework_test_users']);
    }

    private static function build(): BuildResult
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('ReferenceValid'), ManifestBuilder::frameworkSource()],
            predefined: [Theme::class, TemplateRenderer::class, RenderContext::class],
        )->build();
    }

    private static function teaser(BuildResult $result): BlockDefinition
    {
        foreach ($result->getBlocks() as $block) {
            if ($block->getName() === 'fixture/teaser') {
                return $block;
            }
        }
        self::fail('fixture/teaser is missing.');
    }

    /**
     * @param array<mixed> $attributes
     */
    private static function create(BuildResult $result, array $attributes, ?RenderContext $context): ReferenceValid\TeaserAttributes
    {
        /** @var array<string, mixed> $attributes */
        return new ValueFactory($result->getValues(), $context)
            ->createAttributes(ReferenceValid\TeaserAttributes::class, $attributes, self::teaser($result)->getArgs()['attributes']);
    }

    public function testReferencesAreIdsWithSchemaControlAndContentRole(): void
    {
        $block = self::teaser(self::build());
        $attributes = $block->getArgs()['attributes'];

        self::assertSame(['type' => ['integer', 'null'], 'role' => 'content'], $attributes['page']);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'role' => 'content'], $attributes['posts']);

        $controls = $block->getEditor()['controls'];
        self::assertSame(
            ['type' => 'reference', 'label' => 'Page', 'nullable' => true, 'target' => 'post', 'subtypes' => ['page'], 'content' => true],
            $controls['page'],
        );
        self::assertSame(
            ['type' => 'references', 'label' => 'Posts', 'nullable' => false, 'target' => 'post', 'subtypes' => null, 'content' => true],
            $controls['posts'],
        );
        self::assertSame(['category'], $controls['topic']['subtypes']);
        self::assertSame('user', $controls['author']['target']);
        // A reference in a value class makes the list content as well.
        self::assertSame('content', $attributes['cards']['role']);
    }

    public function testReportsAllErrors(): void
    {
        $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\ReferenceBroken\\';
        $at = static fn(string $name): string => "parameter \${$name} of {$ns}BrokenAttributes::__construct()";
        $expected = [
            "parameter \$id of {$ns}ReferenceRoute::post(): #[PostReference], #[TermReference] and #[UserReference] mark fields of blocks and settings groups, not REST arguments; declare the ID as int.",
            $at('notNullable') . ' has #[PostReference] on int; use ?int (null without reference) or array (a list of IDs).',
            $at('wrongType') . ' has #[PostReference], which is only allowed on ?int (one ID) or array (a list of IDs).',
            $at('withListOf') . ' has #[PostReference] and #[ListOf]; a reference on an array is a list of IDs without #[ListOf].',
            $at('withContent') . ' has #[PostReference] together with #[Content]; a reference is an ID with its own control and counts as content in blocks.',
            $at('emptyTypes') . ': postTypes of #[PostReference] must be a non-empty list of post type keys (lowercase letters, digits, "-" and "_"), or null for all.',
            $at('badTaxonomy') . ': taxonomies of #[TermReference] must be a non-empty list of taxonomy keys (lowercase letters, digits, "-" and "_"), or null for all.',
            $at('two') . ' has several references; use one of #[PostReference], #[TermReference] and #[UserReference].',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('ReferenceBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors, implode("\n", $errors));
            }
            self::assertCount(count($expected), $errors, implode("\n", $errors));
        }
    }

    public function testReferencesResolveToExistingObjectsOfAllowedTypes(): void
    {
        $result = self::build();
        $create = static fn(array $attributes, ?RenderContext $context = null): ReferenceValid\TeaserAttributes => self::create($result, $attributes, $context);

        $attrs = $create(['page' => 1, 'posts' => [2, 99, 1, 4, 3], 'topic' => 10, 'author' => 20, 'cards' => [['target' => 2], ['target' => 99]]]);
        self::assertSame(1, $attrs->page);
        // Missing (99), not viewable (wp_template) and drafts are left out of lists.
        self::assertSame([2, 1], $attrs->posts);
        self::assertSame(10, $attrs->topic);
        self::assertSame(20, $attrs->author);
        self::assertSame([2, null], array_map(static fn(ReferenceValid\Card $card): ?int => $card->target, $attrs->cards));

        // Another post type, another taxonomy, a missing user, a draft.
        $attrs = $create(['page' => 2, 'topic' => 11, 'author' => 21]);
        self::assertNull($attrs->page);
        self::assertNull($attrs->topic);
        self::assertNull($attrs->author);
        self::assertNull($create(['page' => 3])->page);

        // Renders for the block editor keep posts that are not public.
        $context = new RenderContext();
        $page = $context->renderForEditor(static fn(): ?int => $create(['page' => 3], $context)->page);
        self::assertSame(3, $page);
    }

    public function testTemplateFunctionsPrintTitlesNamesAndUrls(): void
    {
        $context = new RenderContext();
        $functions = new ReferenceTemplateFunctions($context);

        self::assertSame('About & us', $functions->postTitle(1));
        self::assertSame('https://example.test/?p=1', $functions->postUrl(1));
        self::assertSame('', $functions->postTitle(null));
        self::assertSame('', $functions->postTitle(99));
        self::assertSame('', $functions->postTitle(3));
        self::assertSame('', $functions->postUrl(3));
        self::assertSame('Draft', $context->renderForEditor(static fn(): string => $functions->postTitle(3)));
        // Stored escaped by WordPress, decoded for Twig's escaping.
        self::assertSame('Food & drink', $functions->termName(10));
        self::assertSame('https://example.test/?cat=10', $functions->termUrl(10));
        self::assertSame('', $functions->termName(99));
        self::assertSame('', $functions->termUrl(null));
        self::assertSame('Ann & Bob', $functions->userName(20));
        self::assertSame('', $functions->userName(21));
    }
}
