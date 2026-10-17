<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Block\BlockDefinition;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\Theme;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

/**
 * View scripts next to block and block support classes during the build.
 */
final class ViewBuildTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        $fixture = ManifestBuilderTest::fixture('BlockView');

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

    public function testBlocksAndBlockSupportsHaveTheirViewScripts(): void
    {
        $blocks = self::blocks();

        self::assertSame('Faq.view', $blocks['fixture/faq']->getViewScript());
        self::assertSame('Reveal.view', $blocks['fixture/faq']->getSupports()[0]['viewScript']);
        self::assertNull($blocks['fixture/note']->getViewScript());
        self::assertSame('Reveal.view', $blocks['fixture/note']->getSupports()[0]['viewScript']);
        self::assertSame('Bare.view', $blocks['fixture/bare']->getViewScript());
        self::assertNull($blocks['fixture/plain']->getViewScript());
        self::assertSame([], $blocks['fixture/plain']->getSupports());
    }

    public function testViewScriptsOutsideTheScriptDirectoryAreErrors(): void
    {
        $source = ManifestBuilderTest::fixture('BlockView');

        try {
            new ManifestBuilder([$source, ManifestBuilder::frameworkSource()], predefined: [Theme::class, TemplateRenderer::class, RenderContext::class])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
        }

        $message = 'The view script %s/%s of %s is outside the script directory (none), which the build of the project compiles.';
        self::assertContains(sprintf($message, $source->getDirectory(), 'Bare.view.js', BlockView\Bare::class), $errors);
        self::assertContains(sprintf($message, $source->getDirectory(), 'Reveal.view.js', BlockView\Reveal::class), $errors);
    }
}
