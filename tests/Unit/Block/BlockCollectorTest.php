<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Saij\WpAppFramework\Block\BlockCollector;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\BlockValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;

final class BlockCollectorTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('BlockValid')])->build();
    }

    /**
     * @return array<string, BlockDefinition> By block name.
     */
    private static function blocks(): array
    {
        $blocks = [];
        foreach (self::buildValid()->getBlocks() as $block) {
            $blocks[$block->getName()] = $block;
        }

        return $blocks;
    }

    public function testRegistrationArgumentsAndSupports(): void
    {
        $blocks = self::blocks();
        self::assertSame(['fixture/failing', 'fixture/hero', 'fixture/post-title', 'fixture/section'], array_keys($blocks));

        $hero = $blocks['fixture/hero']->getArgs();
        unset($hero['attributes']);
        self::assertSame(
            [
                'api_version' => 3,
                'title' => 'Hero',
                'category' => 'design',
                'icon' => 'cover-image',
                'description' => 'A hero.',
                'keywords' => ['banner'],
                'supports' => ['html' => false, 'anchor' => true, 'multiple' => false, 'align' => ['wide', 'full']],
            ],
            $hero,
        );
        self::assertSame(
            ['api_version' => 3, 'title' => 'Section', 'category' => 'design', 'supports' => ['html' => false, 'contentRole' => true], 'attributes' => []],
            $blocks['fixture/section']->getArgs(),
        );
        self::assertSame(['postId'], $blocks['fixture/post-title']->getArgs()['uses_context']);
    }

    public function testAttributeSchemaIsDerivedFromTheAttributeClass(): void
    {
        $link = [
            'href' => ['type' => 'string', 'format' => 'uri', 'default' => ''],
            'external' => ['type' => 'boolean', 'default' => false],
        ];

        self::assertSame(
            [
                'topline' => ['type' => 'string', 'default' => '', 'role' => 'content'],
                'headline' => ['type' => 'string', 'default' => '', 'role' => 'content'],
                'imageId' => ['type' => ['integer', 'null'], 'role' => 'content'],
                'gradient' => ['type' => 'string', 'enum' => ['black', 'white'], 'default' => 'black'],
                'buttons' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'required' => true],
                            'url' => ['type' => 'string', 'format' => 'uri', 'default' => ''],
                            'size' => ['type' => ['integer', 'null'], 'enum' => [1, 2, null]],
                        ],
                    ],
                    'default' => [],
                    // Button::$label is inline text.
                    'role' => 'content',
                ],
                'link' => ['type' => 'object', 'properties' => $link, 'default' => ['href' => '', 'external' => false], 'role' => 'content'],
                'count' => ['type' => 'integer', 'default' => 3],
                'ratio' => ['type' => 'number', 'default' => 0.5],
                'highlight' => ['type' => 'boolean', 'default' => false],
                'sizes' => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => [1, 2]], 'default' => [1]],
                'secondary' => ['type' => ['object', 'null'], 'properties' => $link],
            ],
            self::blocks()['fixture/hero']->getArgs()['attributes'],
        );
    }

    public function testRenderArgumentsAreMatchedByType(): void
    {
        $blocks = self::blocks();

        self::assertSame(
            [
                ['param' => 'content', 'kind' => 'content'],
                ['param' => 'attrs', 'kind' => 'attributes', 'class' => BlockValid\HeroAttributes::class],
                ['param' => 'block', 'kind' => 'block'],
            ],
            $blocks['fixture/hero']->getArguments(),
        );
        self::assertSame([['param' => 'block', 'kind' => 'block']], $blocks['fixture/post-title']->getArguments());
        self::assertSame([], $blocks['fixture/failing']->getArguments());
    }

    public function testBlocksAreServicesAndValueClassesAreDescribed(): void
    {
        $result = self::buildValid();

        self::assertSame(
            [
                BlockValid\Failing::class => [],
                BlockValid\Formatter::class => [],
                BlockValid\Hero::class => ['formatter' => BlockValid\Formatter::class],
                BlockValid\PostTitle::class => [],
                BlockValid\Section::class => [],
            ],
            $result->getServices(),
        );
        self::assertSame([BlockValid\Button::class, BlockValid\HeroAttributes::class, BlockValid\Link::class], array_keys($result->getValues()));
        self::assertSame(
            [
                'label' => ['value' => ['type' => 'string', 'nullable' => false], 'required' => true],
                'url' => ['value' => ['type' => 'string', 'nullable' => false], 'required' => false],
                'size' => ['value' => ['type' => 'enum', 'class' => BlockValid\Size::class, 'nullable' => true], 'required' => false],
            ],
            $result->getValues()[BlockValid\Button::class],
        );
        self::assertSame(
            ['type' => 'list', 'items' => ['type' => 'object', 'class' => BlockValid\Button::class, 'nullable' => false], 'nullable' => false],
            $result->getValues()[BlockValid\HeroAttributes::class]['buttons']['value'],
        );
    }

    public function testTheFrameworkMayNotDeclareBlocks(): void
    {
        $source = ManifestBuilderTest::fixture('BlockValid');
        $classes = [];
        foreach (ClassDiscovery::classes($source) as $name) {
            $classes[$name] = new ReflectionClass($name);
        }

        $collector = new BlockCollector(ServiceResolver::forClasses($classes, []), new ValueSchemaBuilder($classes), $source->getDirectory());
        self::assertSame([], $collector->collect($classes));
        self::assertContains(BlockValid\Hero::class . ' has #[Block], but the framework registers no blocks; blocks belong to the theme.', $collector->errors());
        self::assertCount(4, $collector->errors());
    }

    public function testReportsAllErrorsWithClassAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('BlockBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\BlockBroken\\';
            $a = "parameter \$%s of {$ns}BrokenAttributes::__construct()";
            $supported = 'use int, float, string, bool, a backed enum, a value class or array with #[ListOf]';
            $render = 'is not supported: render() accepts the block attribute class, string $content and WP_Block $block, each at most once and not nullable; inject services through the constructor.';
            $expected = [
                "parameter \$number of {$ns}BadParameter::render() {$render}",
                "parameter \$block of {$ns}BadParameter::render() {$render}",
                "#[Block] on {$ns}CoreName uses the name \"core/paragraph\"; the namespace \"core\" belongs to WordPress.",
                "#[Supports] on {$ns}DuplicateAlign lists the alignment Wide twice.",
                "#[Block] on {$ns}DuplicateB uses the name \"broken/duplicate\", which {$ns}DuplicateA already declares.",
                "#[Block] on {$ns}EmptyKeyword: keywords must contain non-empty strings only.",
                "#[Block] on {$ns}EmptyTitle has an empty title.",
                "#[Block] on {$ns}InvalidName has the invalid name \"Invalid\": use \"namespace/name\" with lowercase letters, digits and \"-\", e.g. \"sections/hero\".",
                "{$ns}NoRender has #[Block], but no render() method returning the block HTML.",
                "{$ns}NotAService has #[Block], but {$ns}NotAService is marked #[NoService] and therefore not a service.",
                "{$ns}NullableReturn::render() of a #[Block] class must return string.",
                "{$ns}StaticRender::render() of a #[Block] class must be public and non-static.",
                "{$ns}SupportsOnly has #[Supports], but no #[Block].",
                "parameter \$second of {$ns}TwoContents::render(): render() accepts only one string \$content parameter.",
                sprintf($a, 'plainInt') . ' has #[PlainText], which is only allowed on strings.',
                sprintf($a, 'both') . ' has #[PlainText] and #[RichText]; use one of them.',
                sprintf($a, 'richContent') . ' has #[RichText] and #[Content]; #[RichText] already marks the field as content.',
                sprintf($a, 'urlInt') . ' has #[Url], which is only allowed on strings.',
                sprintf($a, 'urlText') . ' has #[Url] and #[PlainText]; a URL is not inline text, mark it with #[Content] instead.',
                sprintf($a, 'plainArray') . ' is an array and needs #[ListOf(Item::class)] with a backed enum or a value class.',
                sprintf($a, 'listOnString') . ' has #[ListOf], but is not an array.',
                sprintf($a, 'unitList') . ": #[ListOf]: enum {$ns}Unit is not backed; use a backed enum so its values can be stored.",
                sprintf($a, 'outside') . ': #[ListOf]: DateTimeImmutable is outside the scanned PSR-4 directories of the theme and the framework.',
                sprintf($a, 'union') . ": type string|int is not supported; {$supported}, optionally nullable.",
                sprintf($a, 'anything') . ": type mixed is not supported; {$supported}, optionally nullable.",
                "parameter \$noDefault of block attribute class {$ns}BrokenAttributes::__construct() needs a default value: a block attribute can always be missing in the content.",
                "parameter \$className of block attribute class {$ns}BrokenAttributes::__construct() uses the attribute name \"className\", which WordPress reserves (lock, metadata, className, anchor, align, style).",
                "parameter \$attrs of {$ns}NotReadonly::render(): value class {$ns}MutableAttributes must be declared readonly.",
                "parameter \$children of {$ns}Node::__construct(): #[ListOf]: value class {$ns}Node contains itself ({$ns}RecursiveAttributes -> {$ns}Node -> {$ns}Node); recursive value classes are not supported.",
            ];

            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors);
            }
            self::assertCount(count($expected), $errors);
        }
    }
}
