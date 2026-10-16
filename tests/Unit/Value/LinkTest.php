<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\LinkValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\LinkKind;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Post;
use WP_Term;

/**
 * Links (`Link`) and multiline plain text: build (schema, controls, fields, errors) and resolution of links at runtime.
 */
final class LinkTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_app_framework_test_posts'] = [
            1 => new WP_Post((object) ['ID' => 1, 'post_type' => 'page']),
            3 => new WP_Post((object) ['ID' => 3, 'post_type' => 'page', 'post_status' => 'draft']),
            4 => new WP_Post((object) ['ID' => 4, 'post_type' => 'wp_template']),
            5 => new WP_Post((object) ['ID' => 5, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => 3]),
            6 => new WP_Post((object) ['ID' => 6, 'post_type' => 'attachment', 'post_status' => 'inherit']),
        ];
        $GLOBALS['wp_app_framework_test_terms'] = [
            10 => new WP_Term((object) ['term_id' => 10, 'taxonomy' => 'category']),
            12 => new WP_Term((object) ['term_id' => 12, 'taxonomy' => 'nav_menu']),
            13 => new WP_Term((object) ['term_id' => 13, 'taxonomy' => 'post_tag']),
        ];
        $GLOBALS['wp_app_framework_test_term_link_errors'] = [13];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_posts'], $GLOBALS['wp_app_framework_test_terms'], $GLOBALS['wp_app_framework_test_term_link_errors']);
    }

    private static function build(): BuildResult
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('LinkValid'), ManifestBuilder::frameworkSource()],
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
     * @param array<string, mixed> $attributes
     */
    private static function create(BuildResult $result, array $attributes, ?RenderContext $context = null): LinkValid\TeaserAttributes
    {
        return new ValueFactory($result->getValues(), $context)
            ->createAttributes(LinkValid\TeaserAttributes::class, $attributes, self::teaser($result)->getArgs()['attributes']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(string $kind, int $id, string $url = 'https://old.example/', bool $opensInNewTab = false): array
    {
        return ['url' => $url, 'opensInNewTab' => $opensInNewTab, 'kind' => $kind, 'id' => $id];
    }

    public function testLinksAreContentWithAControlOfTheirOwn(): void
    {
        $result = self::build();
        $block = self::teaser($result);
        $attributes = $block->getArgs()['attributes'];

        self::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string', 'format' => 'uri', 'default' => ''],
                    'opensInNewTab' => ['type' => 'boolean', 'default' => false],
                    'kind' => ['type' => 'string', 'enum' => ['url', 'post', 'term', 'attachment'], 'default' => 'url'],
                    'id' => ['type' => 'integer', 'default' => 0],
                ],
                'default' => ['url' => '', 'opensInNewTab' => false, 'kind' => 'url', 'id' => 0],
                'role' => 'content',
            ],
            $attributes['cta'],
        );
        self::assertSame('content', $attributes['links']['role']);
        self::assertSame('content', $attributes['card']['role']);

        $controls = $block->getEditor()['controls'];
        self::assertSame(['type' => 'link', 'label' => 'Call to action', 'nullable' => false, 'content' => true], $controls['cta']);
        self::assertSame(['type' => 'list', 'label' => 'Links', 'nullable' => false, 'item' => ['type' => 'link', 'nullable' => false], 'itemLabel' => 'Link', 'content' => true], $controls['links']);
        self::assertSame(
            ['type' => 'object', 'label' => 'Card', 'nullable' => false, 'fields' => ['link' => ['type' => 'link', 'label' => 'Link', 'nullable' => false]], 'content' => true],
            $controls['card'],
        );
        self::assertArrayHasKey(Link::class, $result->getValues());
        self::assertSame(['type' => 'enum', 'class' => LinkKind::class, 'nullable' => false], $result->getValues()[Link::class]['kind']['value']);
    }

    public function testMultilinePlainTextInTheCanvasAndTheInspector(): void
    {
        $editor = self::teaser(self::build())->getEditor();

        self::assertSame(
            [
                'card.text' => ['kind' => 'plain', 'label' => 'Text', 'multiline' => true],
                'address' => ['kind' => 'plain', 'label' => 'Address', 'multiline' => true],
                'note' => ['kind' => 'plain', 'label' => 'Note', 'inspector' => true, 'multiline' => true],
            ],
            $editor['fields'],
        );
        self::assertSame(['type' => 'plain', 'label' => 'Note', 'nullable' => false, 'multiline' => true, 'content' => true], $editor['controls']['note']);
        self::assertArrayNotHasKey('address', $editor['controls']);
    }

    public function testReportsAllErrors(): void
    {
        $at = static fn(string $name): string => sprintf('parameter $%s of %s::__construct()', $name, \Saij\WpAppFramework\Tests\Fixtures\LinkBroken\BrokenAttributes::class);
        $expected = [
            $at('nullable') . ': a link is never null; use Link $nullable = new Link(), an empty URL means no link.',
            $at('content') . ' is a Link and has #[Content]; a link is content with its own control.',
            $at('url') . ' is a Link and has #[Url]; a link is content with its own control.',
            $at('plain') . ' is a Link and has #[PlainText]; a link is content with its own control.',
            $at('rich') . ' is a Link and has #[RichText]; a link is content with its own control.',
            $at('image') . ' is a Link and has #[Image]; a link is content with its own control.',
            $at('reference') . ' is a Link and has #[PostReference]; a link is content with its own control.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('LinkBroken'), ManifestBuilder::frameworkSource()])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }

    public function testFreeUrlsStayAndLinkedObjectsGetTheirCurrentUrl(): void
    {
        $result = self::build();

        $attrs = self::create($result, [
            'cta' => self::stored('url', 0, 'https://example.com/', true),
            'links' => [self::stored('post', 1), self::stored('term', 10), self::stored('attachment', 6)],
            'card' => ['link' => self::stored('post', 1, '', true)],
        ]);

        self::assertEquals(new Link('https://example.com/', true), $attrs->cta);
        self::assertEquals(
            [
                new Link('https://example.test/?p=1', false, LinkKind::Post, 1),
                new Link('https://example.test/?cat=10', false, LinkKind::Term, 10),
                new Link('https://example.test/6.jpg', false, LinkKind::Attachment, 6),
            ],
            $attrs->links,
        );
        self::assertEquals(new Link('https://example.test/?p=1', true, LinkKind::Post, 1), $attrs->card->link);
        self::assertEquals(new Link(), self::create($result, [])->cta);
    }

    public function testLinksToMissingOrHiddenObjectsHaveNoUrl(): void
    {
        $result = self::build();
        $links = [
            // Missing, a draft, a post type that is not viewable.
            self::stored('post', 99),
            self::stored('post', 3),
            self::stored('post', 4),
            // A taxonomy that is not viewable, a term whose link fails (WP_Error).
            self::stored('term', 12),
            self::stored('term', 13),
            self::stored('term', 99),
            // An attachment of a draft, a page as attachment.
            self::stored('attachment', 5),
            self::stored('attachment', 1),
        ];

        $urls = array_map(static fn(Link $link): string => $link->url, self::create($result, ['links' => $links])->links);
        self::assertSame(['', '', '', '', '', '', '', ''], $urls);
        // Kind and ID stay.
        self::assertEquals(new Link('', false, LinkKind::Post, 3), self::create($result, ['cta' => self::stored('post', 3)])->cta);

        // Renders for the block editor keep posts and attachments that are not public; types and taxonomies still count.
        $context = new RenderContext();
        $urls = $context->renderForEditor(static fn(): array => array_map(
            static fn(Link $link): string => $link->url,
            self::create($result, ['links' => $links], $context)->links,
        ));
        self::assertSame(['', 'https://example.test/?p=3', '', '', '', '', 'https://example.test/5.jpg', ''], $urls);
    }

    public function testKindAndIdMustMatch(): void
    {
        $result = self::build();
        $cases = [
            'Value "cta" is an invalid link: kind "url" with ID 3; a free URL has the ID 0, a linked post, term or attachment its positive ID.' => ['cta' => self::stored('url', 3)],
            'Value "links[1]" is an invalid link: kind "post" with ID 0; a free URL has the ID 0, a linked post, term or attachment its positive ID.' => ['links' => [self::stored('url', 0), self::stored('post', 0)]],
            'Value "card.link" is an invalid link: kind "term" with ID -2; a free URL has the ID 0, a linked post, term or attachment its positive ID.' => ['card' => ['link' => self::stored('term', -2)]],
        ];
        foreach ($cases as $message => $attributes) {
            try {
                self::create($result, $attributes);
                self::fail('InvalidArgumentException expected: ' . $message);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }
}
