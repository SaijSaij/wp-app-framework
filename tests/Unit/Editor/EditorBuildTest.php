<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Editor;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;

final class EditorBuildTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        $fixture = ManifestBuilderTest::fixture('BlockEditor');

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

    public function testInlineFieldsByPathWithListItemsAsStar(): void
    {
        self::assertSame(
            [
                'kicker' => ['kind' => 'plain', 'label' => 'Kicker line'],
                'title' => ['kind' => 'rich', 'label' => 'Title', 'allowedFormats' => ['core/bold']],
                'imageId' => ['kind' => 'image', 'label' => 'Image id'],
                'ctas.*.label' => ['kind' => 'plain', 'label' => 'Label'],
                'ctas.*.iconId' => ['kind' => 'image', 'label' => 'Icon id', 'inspector' => true],
                'caption.text' => ['kind' => 'plain', 'label' => 'Text'],
                'note' => ['kind' => 'rich', 'label' => 'Note', 'inspector' => true],
            ],
            self::blocks()['fixture/teaser']->getEditor()['fields'],
        );
    }

    public function testInspectorControlsSkipInlineHiddenAndAllInlineValues(): void
    {
        $sizeOptions = [['value' => 's', 'label' => 's'], ['value' => 'l', 'label' => 'l']];

        self::assertSame(
            [
                'tone' => [
                    'type' => 'enum',
                    'label' => 'Tone',
                    'nullable' => false,
                    'options' => [['value' => 'light', 'label' => 'Bright'], ['value' => 'dark', 'label' => 'Night']],
                    'content' => false,
                ],
                'size' => ['type' => 'enum', 'label' => 'Size', 'nullable' => true, 'options' => $sizeOptions, 'content' => false],
                'ctas' => [
                    'type' => 'list',
                    'label' => 'Ctas',
                    'nullable' => false,
                    'item' => [
                        'type' => 'object',
                        'nullable' => false,
                        'fields' => [
                            'url' => ['type' => 'string', 'label' => 'Url', 'nullable' => false, 'url' => true],
                            'size' => ['type' => 'enum', 'label' => 'Size', 'nullable' => true, 'options' => $sizeOptions],
                            'iconId' => ['type' => 'image', 'label' => 'Icon id', 'nullable' => true],
                        ],
                    ],
                    'content' => true,
                ],
                'featured' => ['type' => 'bool', 'label' => 'Featured', 'nullable' => false, 'content' => false],
                'ratio' => ['type' => 'float', 'label' => 'Ratio', 'nullable' => false, 'content' => false],
                'link' => ['type' => 'string', 'label' => 'Link', 'nullable' => false, 'url' => true, 'content' => false],
                'source' => ['type' => 'string', 'label' => 'Source', 'nullable' => false, 'content' => true],
                'note' => ['type' => 'rich', 'label' => 'Note', 'nullable' => false, 'content' => true],
            ],
            self::blocks()['fixture/teaser']->getEditor()['controls'],
        );
    }

    public function testImageAndInlineFieldsHaveTheContentRole(): void
    {
        $attributes = self::blocks()['fixture/teaser']->getArgs()['attributes'];

        self::assertSame(['type' => ['integer', 'null'], 'role' => 'content'], $attributes['imageId']);
        self::assertSame('content', $attributes['caption']['role']);
        self::assertArrayNotHasKey('role', $attributes['version']);
    }

    public function testContainerHasInnerBlocksAllowedBlocksAndItsEditorScript(): void
    {
        $blocks = self::blocks();
        $container = $blocks['fixture/container'];

        self::assertSame(['fixture/teaser'], $container->getArgs()['allowed_blocks'] ?? null);
        self::assertSame(
            ['fields' => [], 'controls' => [], 'innerBlocks' => true, 'postContentField' => null, 'editorScript' => 'Container.editor', 'supports' => []],
            $container->getEditor(),
        );
        self::assertFalse($blocks['fixture/teaser']->getEditor()['innerBlocks']);
        self::assertNull($blocks['fixture/teaser']->getEditor()['editorScript']);
        self::assertArrayNotHasKey('allowed_blocks', $blocks['fixture/teaser']->getArgs());
    }

    public function testReportsAllEditorErrorsWithClassAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('BlockEditorBroken'), ManifestBuilder::frameworkSource()])->build();
            self::fail('Expected a BuildException.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
        }

        $a = 'parameter $%s of ' . BlockEditorBroken\BrokenEditorAttributes::class . '::__construct()';
        $expected = [
            sprintf($a, 'imageOnString') . ' has #[Image], which is only allowed on ?int (the attachment ID, null without image).',
            sprintf($a, 'imageNotNullable') . ' has #[Image], which is only allowed on ?int (the attachment ID, null without image).',
            sprintf($a, 'plainAndImage') . ' has #[PlainText] and #[Image]; use only one of #[PlainText], #[RichText] and #[Image].',
            sprintf($a, 'hiddenRich') . ' has #[Hidden] and #[RichText]; the field is edited in the canvas or, with inspector: true, in the inspector, so it cannot be hidden.',
            sprintf($a, 'hiddenInspector') . ' has #[Hidden] and #[PlainText]; the field is edited in the canvas or, with inspector: true, in the inspector, so it cannot be hidden.',
            sprintf($a, 'emptyLabel') . ' has an empty #[Label].',
            sprintf($a, 'badFormats') . ': allowedFormats of #[RichText] must be a list of format names such as "core/bold", or null for all formats.',
            sprintf($a, 'emptyCaseLabel') . ': ' . BlockEditorBroken\EmptyLabel::class . '::A->label() returned an empty label.',
            sprintf($a, 'labeledValue') . ': value class ' . BlockEditorBroken\LabeledValue::class . ' implements Saij\WpAppFramework\Block\Labeled, which only labels the cases of backed enums.',
            '#[Block] on ' . BlockEditorBroken\AllowedWithoutContent::class . ' has allowedBlocks, but ' . BlockEditorBroken\AllowedWithoutContent::class . '::render() accepts no string $content, so the block has no inner blocks.',
            '#[Block] on ' . BlockEditorBroken\AllowedInvalid::class . ': allowedBlocks must contain non-empty strings only.',
            sprintf(
                'The editor script %s/ScriptOutside.editor.js of %s is outside the script directory (none), which the build of the project compiles.',
                ManifestBuilderTest::fixture('BlockEditorBroken')->getDirectory(),
                BlockEditorBroken\ScriptOutside::class,
            ),
        ];
        foreach ($expected as $message) {
            self::assertContains($message, $errors);
        }
    }
}
