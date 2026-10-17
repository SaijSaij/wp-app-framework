<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\BuildAssets;
use WebCodingCat\WpAppFramework\Block\BlockDefinition;
use WebCodingCat\WpAppFramework\Block\BlockRegistrar;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Editor\RenderController;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Tests\Support\Requests;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\Theme;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WebCodingCat\WpAppFramework\View\ViewAssets;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * #[RichText(postContent: true)] (value stored as the block's inner HTML) and
 * excerpt: true / #[Block(excerptWrapper: true)] (automatic excerpts).
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class PostContentAndExcerptTest extends TestCase
{
    private string $file;

    /** @var ManifestData */
    private array $manifest;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($this->file, new ManifestGenerator()->generate(self::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $GLOBALS['wp_app_framework_test_blocks'] = [];
        $GLOBALS['wp_app_framework_test_filters'] = [];
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        foreach (array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()) as $name) {
            WP_Block_Type_Registry::get_instance()->unregister($name);
        }
        unset(
            $GLOBALS['wp_app_framework_test_blocks'],
            $GLOBALS['wp_app_framework_test_filters'],
            $GLOBALS['wp_app_framework_test_doing_filter'],
            $GLOBALS['wp_app_framework_test_caps'],
        );
    }

    private static function buildValid(): BuildResult
    {
        $fixture = ManifestBuilderTest::fixture('BlockPostContent');

        return new ManifestBuilder(
            [$fixture, ManifestBuilder::frameworkSource()],
            [],
            [Theme::class, TemplateRenderer::class, RenderContext::class],
            $fixture->getDirectory(),
        )->build();
    }

    /**
     * @return array<string, BlockDefinition>
     */
    private static function blocks(): array
    {
        $blocks = [];
        foreach (self::buildValid()->getBlocks() as $block) {
            $blocks[$block->getName()] = $block;
        }

        return $blocks;
    }

    /**
     * Registers the blocks and, like WordPress, their types in the registry (for render_block()).
     */
    private function registrar(RenderContext $context = new RenderContext()): void
    {
        $container = new Container($this->manifest['services'], [RenderContext::class => $context]);
        new BlockRegistrar($container, new ValueFactory($this->manifest['values']), $context, true, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')))
            ->register($this->manifest['blocks']);
        foreach (Recorded::blocks() as $name => $args) {
            // @phpstan-ignore argument.type (the arguments the registrar passed to register_block_type())
            WP_Block_Type_Registry::get_instance()->register($name, $args);
        }
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private static function applyFilter(string $name, array $allowed): array
    {
        foreach (Recorded::listFilters($name) as $callback) {
            $allowed = $callback($allowed);
        }

        return $allowed;
    }

    public function testPostContentFieldIsSourcedFromTheInnerHtml(): void
    {
        $paragraph = self::blocks()['fixture/paragraph'];

        self::assertSame(
            ['type' => 'string', 'source' => 'html', 'default' => '', 'role' => 'content'],
            $paragraph->getArgs()['attributes']['text'],
        );
        self::assertSame(['type' => 'boolean', 'default' => false], $paragraph->getArgs()['attributes']['lead']);
        self::assertSame(
            ['kind' => 'rich', 'label' => 'Text', 'allowedFormats' => ['core/bold'], 'postContent' => true, 'excerpt' => true],
            $paragraph->getEditor()['fields']['text'],
        );
        self::assertSame('text', $paragraph->getEditor()['postContentField']);
        self::assertNull(self::blocks()['fixture/card']->getEditor()['postContentField']);
    }

    public function testExcerptFieldsInDeclarationOrderIncludingListItemsValueClassesAndInspectorFields(): void
    {
        $blocks = self::blocks();

        self::assertSame(['fields' => ['text' => 'rich'], 'wrapper' => false], $blocks['fixture/paragraph']->getExcerpt());
        self::assertSame(
            ['fields' => ['title' => 'plain', 'items.*.label' => 'plain', 'featured.label' => 'plain', 'summary' => 'rich'], 'wrapper' => false],
            $blocks['fixture/card']->getExcerpt(),
        );
        self::assertSame(['fields' => [], 'wrapper' => true], $blocks['fixture/wrapper']->getExcerpt());
        self::assertSame($blocks['fixture/card']->getExcerpt(), array_column($this->manifest['blocks'], 'excerpt', 'name')['fixture/card']);
    }

    public function testRegistersExcerptBlocksAndWrappers(): void
    {
        $this->registrar();

        $allowed = self::applyFilter('excerpt_allowed_blocks', ['core/paragraph']);
        sort($allowed);
        self::assertSame(['core/paragraph', 'fixture/card', 'fixture/paragraph'], $allowed);
        self::assertSame(['core/group', 'fixture/wrapper'], self::applyFilter('excerpt_allowed_wrapper_blocks', ['core/group']));
    }

    public function testRendersThePostContentValueFromTheBlockContent(): void
    {
        $this->registrar();
        $render = Recorded::renderCallback('fixture/paragraph');

        // WordPress applies the default to sourced attributes; the inner HTML wins, without the serializer's line breaks.
        self::assertSame(
            '<p class="lead">Hello <strong>world</strong></p>',
            $render(['text' => '', 'lead' => true], "\nHello <strong>world</strong>\n", new WP_Block(['blockName' => 'fixture/paragraph'])),
        );
        self::assertSame('<p class="plain"></p>', $render(['text' => ''], '', new WP_Block(['blockName' => 'fixture/paragraph'])));
        // Through WP_Block::render(), as on the front end.
        self::assertSame(
            '<p class="plain">Stored</p>',
            render_block(['blockName' => 'fixture/paragraph', 'attrs' => [], 'innerHTML' => "\nStored\n"]),
        );
    }

    public function testRendersOnlyTheExcerptFieldsWhileTheExcerptIsGenerated(): void
    {
        $this->registrar();
        $card = ['title' => 'A & B', 'kicker' => 'Not included', 'items' => [['label' => 'One', 'note' => 'n'], ['label' => '']], 'featured' => ['label' => 'Featured'], 'summary' => '<em>Sum</em>'];
        $block = new WP_Block(['blockName' => 'fixture/card']);

        self::assertSame('<div>card A & B</div>', Recorded::renderCallback('fixture/card')($card, '', $block));

        $GLOBALS['wp_app_framework_test_doing_filter'] = 'get_the_excerpt';
        self::assertSame(
            "<p>A &amp; B</p>\n<p>One</p>\n<p>Featured</p>\n<p><em>Sum</em></p>\n",
            Recorded::renderCallback('fixture/card')($card, '', $block),
        );
        self::assertSame(
            "<p>Hello <strong>world</strong></p>\n",
            Recorded::renderCallback('fixture/paragraph')(['text' => ''], "\nHello <strong>world</strong>\n", new WP_Block(['blockName' => 'fixture/paragraph'])),
        );
        self::assertSame('', Recorded::renderCallback('fixture/paragraph')(['text' => ''], '', new WP_Block(['blockName' => 'fixture/paragraph'])));
    }

    public function testEditorRenderRoutePassesThePostContentValueAsInnerHtml(): void
    {
        $context = new RenderContext();
        $this->registrar($context);
        $GLOBALS['wp_app_framework_test_caps'] = ['edit_posts'];

        $response = new RenderController('fixture', array_column($this->manifest['blocks'], 'name'), $context, true)
            ->handle(Requests::create(['blocks' => [['name' => 'fixture/paragraph', 'attributes' => ['text' => 'Hi <b>there</b>', 'lead' => true]]]]));

        self::assertSame(['results' => [['html' => '<p class="lead">Hi <b>there</b></p>']]], $response->get_data());
    }

    public function testReportsThePostContentAndExcerptRules(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('BlockPostContentBroken'), ManifestBuilder::frameworkSource()])->build();
            self::fail('Expected a BuildException.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
        }

        $nested = 'parameter $%s of ' . BlockPostContentBroken\NestedAttributes::class . '::__construct()';
        $inner = 'parameter $text of ' . BlockPostContentBroken\Inner::class . '::__construct() has #[RichText(postContent: true)], which is only allowed on a parameter of the block attribute class itself, not in value classes or list items.';
        $expected = [
            sprintf($nested, 'inner') . ': ' . $inner,
            sprintf($nested, 'list') . ': #[ListOf]: ' . $inner,
            'parameter $second of block attribute class ' . BlockPostContentBroken\TwoPostContentsAttributes::class . '::__construct() has #[RichText(postContent: true)], but the attribute "first" already does; a block stores at most one value in the post content.',
            'parameter $text of block attribute class ' . BlockPostContentBroken\PostContentDefaultAttributes::class . "::__construct() has #[RichText(postContent: true)] and must default to '': the editor stores an empty value as empty block content, which WordPress parses as ''.",
            sprintf(
                'Attribute "text" of block %1$s has #[RichText(postContent: true)], but %1$s::render() accepts string $content, so the block has inner blocks; inner blocks and a value stored in the post content cannot share the block content.',
                BlockPostContentBroken\PostContentInner::class,
            ),
            sprintf(
                'Block %1$s has inner blocks (%1$s::render() accepts string $content), but marks title with excerpt: true; WordPress never renders blocks with inner blocks for excerpts, use #[Block(excerptWrapper: true)] to include the inner blocks instead.',
                BlockPostContentBroken\ExcerptInner::class,
            ),
            sprintf(
                '#[Block] on %1$s has excerptWrapper, but %1$s::render() accepts no string $content, so the block has no inner blocks to include in excerpts.',
                BlockPostContentBroken\WrapperWithoutContent::class,
            ),
        ];
        foreach ($expected as $message) {
            self::assertContains($message, $errors);
        }
        self::assertCount(count($expected), $errors, implode("\n", $errors));
    }
}
