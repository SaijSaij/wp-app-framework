<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;
use Saij\WpAppFramework\Tests\Fixtures\ContainerValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * The block API for containers: inner block options, references to other blocks, context, categories, examples and
 * variations.
 */
final class ContainerCollectorTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('ContainerValid')])->build();
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

    public function testCustomCategoriesAreRegisteredWithNamespaceLabelAndIcon(): void
    {
        self::assertSame(
            [
                ['slug' => 'fixture-sections', 'title' => 'Sections', 'icon' => 'layout'],
                ['slug' => 'fixture-extras', 'title' => 'Extras', 'icon' => null],
            ],
            self::buildValid()->getBlockCategories(),
        );
        $blocks = self::blocks();
        self::assertSame('fixture-sections', $blocks['fixture/tabs']->getArgs()['category'] ?? null);
        self::assertSame('text', $blocks['fixture/note']->getArgs()['category'] ?? null);
    }

    public function testInnerBlockOptionsResolveReferences(): void
    {
        $tabs = self::blocks()['fixture/tabs'];

        self::assertSame(['fixture/tab', 'core/paragraph', 'acme/slider'], $tabs->getArgs()['allowed_blocks'] ?? null);
        self::assertSame(
            [
                'template' => [
                    ['name' => 'fixture/tab', 'attributes' => ['title' => 'One'], 'innerBlocks' => [
                        ['name' => 'core/paragraph', 'attributes' => ['content' => 'Text'], 'innerBlocks' => []],
                    ]],
                    ['name' => 'fixture/tab', 'attributes' => [], 'innerBlocks' => []],
                ],
                'templateLock' => 'insert',
                'orientation' => 'horizontal',
                'defaultBlock' => ['name' => 'fixture/tab', 'attributes' => ['title' => 'New']],
                'directInsert' => true,
                'prioritizedInserterBlocks' => ['fixture/tab'],
                'appender' => 'button',
            ],
            $tabs->getEditor()['innerBlockOptions'],
        );
    }

    public function testParentAndAncestorAreBlockNames(): void
    {
        $blocks = self::blocks();

        self::assertSame(['fixture/tabs'], $blocks['fixture/tab']->getArgs()['parent'] ?? null);
        self::assertSame(['fixture/tabs', 'core/group'], $blocks['fixture/note']->getArgs()['ancestor'] ?? null);
    }

    public function testContextIsProvidedByMarkedAttributesAndReadByContextParameters(): void
    {
        $blocks = self::blocks();

        self::assertSame(
            ['fixture/tabs/active' => 'active', 'fixture/tabs/tone' => 'tone'],
            $blocks['fixture/tabs']->getArgs()['provides_context'] ?? null,
        );
        $tab = $blocks['fixture/tab'];
        self::assertSame(['fixture/tabs/active', 'fixture/tabs/tone'], $tab->getArgs()['uses_context'] ?? null);
        self::assertSame(
            [
                ['param' => 'active', 'kind' => 'context', 'name' => 'fixture/tabs/active', 'schema' => ['type' => 'integer'], 'value' => ['type' => 'int', 'nullable' => false], 'hasDefault' => true],
                ['param' => 'tone', 'kind' => 'context', 'name' => 'fixture/tabs/tone', 'schema' => ['type' => 'string', 'enum' => ['light', 'dark']], 'value' => ['type' => 'enum', 'class' => ContainerValid\Tone::class, 'nullable' => true], 'hasDefault' => true],
            ],
            array_values(array_filter($tab->getArguments(), static fn(array $argument): bool => $argument['kind'] === 'context')),
        );
    }

    public function testExamplesAreExplicitAutomaticOrOff(): void
    {
        $blocks = self::blocks();

        self::assertSame(
            [
                'attributes' => ['active' => 1, 'tone' => 'dark'],
                'innerBlocks' => [['name' => 'fixture/tab', 'attributes' => ['title' => 'A'], 'innerBlocks' => []]],
                'viewportWidth' => 800,
            ],
            $blocks['fixture/tabs']->getArgs()['example'] ?? null,
        );
        self::assertSame(['innerBlocks' => []], $blocks['fixture/tab']->getArgs()['example'] ?? null);
        self::assertArrayNotHasKey('example', $blocks['fixture/note']->getArgs());
    }

    public function testVariationsAreExportedForTheEditor(): void
    {
        $tab = ['name' => 'fixture/tab', 'attributes' => [], 'innerBlocks' => []];
        self::assertSame(
            [
                ['name' => 'dark', 'title' => 'Dark tabs', 'attributes' => ['tone' => 'dark'], 'scope' => ['inserter', 'transform'], 'isActive' => ['tone']],
                [
                    'name' => 'three',
                    'title' => 'Three tabs',
                    'description' => 'Three tabs at once.',
                    'icon' => 'columns',
                    'keywords' => ['many'],
                    'innerBlocks' => [$tab, $tab, $tab],
                    'scope' => ['inserter', 'block'],
                ],
            ],
            self::blocks()['fixture/tabs']->getArgs()['variations'] ?? null,
        );
    }

    public function testReportsAllContainerErrors(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('ContainerBroken')])->build();
            self::fail('Expected a BuildException.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
        }

        $box = ContainerBroken\Box::class;
        $item = ContainerBroken\Item::class;
        $reader = ContainerBroken\Reader::class;
        $expected = [
            '#[InnerBlocks] on ' . ContainerBroken\Direct::class . ': allowed names "core/paragraph"; use CoreBlock::Paragraph.',
            '#[InnerBlocks] on ' . $box . ': template[0]: fixture/stray is not allowed in the container (allowed: fixture/item, core/heading).',
            '#[InnerBlocks] on ' . $box . ': template[1] (fixture/item): unknown attribute "missing".',
            '#[InnerBlocks] on ' . $box . ': template[2] has inner blocks, but block fixture/item has none.',
            '#[InnerBlocks] on ' . $box . ': defaultBlock has inner blocks; the editor inserts the default block with its attributes only.',
            '#[InnerBlocks] on ' . $box . ': prioritized contains acme/other, which is not in allowed.',
            '#[Block] on ' . ContainerBroken\Stray::class . ': parent names ' . $box . ', whose #[InnerBlocks(allowed)] does not contain ' . ContainerBroken\Stray::class . '; the block could never be inserted.',
            '#[Block] on ' . $item . ': ancestor names Saij\WpAppFramework\Tests\Fixtures\ContainerBroken\Missing, which is no block class of this theme or plugin (a class with #[Block]); name core blocks by CoreBlock and other blocks in full, e.g. "acme/slider".',
            '#[Block] on ' . ContainerBroken\Peeker::class . ': usesContext contains "fixture/box/size" of this theme or plugin; read values of its blocks with a render() parameter marked #[Context(Block::class, \'attribute\')].',
            'parameter $size of ' . $reader . '::render() has #[Context(' . $box . ', "size")], but attribute "size" of ' . $box . ' is not marked with #[ProvidesContext] (marked: tone).',
            'parameter $tone of ' . $reader . '::render() has the type int, but attribute "tone" of ' . $box . ' has the type string.',
            'parameter $width of ' . $reader . '::render() has #[Context(' . $box . ', "width")], but block ' . $box . ' has no attribute "width".',
            'parameter $required of ' . $reader . '::render() needs a default value or a nullable type: the value is missing if no ' . $box . ' block surrounds the block.',
            'parameter $other of ' . $reader . '::render() has #[Context(' . ContainerBroken\Service::class . ')], but ' . ContainerBroken\Service::class . ' is no block class of this theme or plugin (a class with #[Block]).',
            '#[Example] on ' . $reader . ': attribute "tone": expected string, got integer.',
            '#[Example] on ' . $reader . ' has innerBlocks, but ' . $reader . '::render() accepts no string $content, so the block has no inner blocks.',
            '#[Example] on ' . ContainerBroken\Stray::class . ': #[Example(false)] shows no preview and takes no innerBlocks or viewportWidth.',
            '#[Variation("Bad name")] on ' . $box . ': the name must consist of lowercase letters, digits and "-".',
            '#[Variation("twice")] on ' . $box . ': the name is used by another variation of the block.',
            '#[Variation("inactive")] on ' . $box . ': isActive names "tone", which the variation does not preset in attributes; the editor compares the attributes of a block with the preset values.',
            '#[Variation("scope")] on ' . $box . ': scope must be a non-empty list of VariationScope cases.',
            'Block category ' . ContainerBroken\BadCategory::class . '::Empty: label() returned an empty label.',
            'Block category ' . ContainerBroken\BadCategory::class . '::Blank: icon() returned an empty string; return null for no icon.',
            'Block category ' . ContainerBroken\BadCategory::class . '::Upper: the value must consist of lowercase letters, digits and "-".',
            'Parameter $tone of ' . ContainerBroken\Value::class . '::__construct() has #[ProvidesContext], which only marks attributes of block attribute classes (the class of a render() parameter of a #[Block] class).',
            'Parameter $tone of ' . ContainerBroken\Service::class . '::run() has #[Context], which only marks parameters of render() of a #[Block] class.',
            ContainerBroken\Leaf::class . ' has #[InnerBlocks], but ' . ContainerBroken\Leaf::class . '::render() accepts no string $content, so the block has no inner blocks.',
            '#[InnerBlocks] on ' . ContainerBroken\Direct::class . ': directInsert needs a defaultBlock, the block the appender inserts.',
            ContainerBroken\Orphan::class . ' has #[Variation], but no #[Block].',
        ];
        foreach ($expected as $message) {
            self::assertContains($message, $errors);
        }
        self::assertCount(count($expected), $errors, implode("\n", $errors));
    }
}
