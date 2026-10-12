<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block\Support;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\SupportValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Build of custom block supports: `#[BlockSupport]` declarations and their use with `#[UseSupport]`.
 */
final class SupportCollectorTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        $source = ManifestBuilderTest::fixture('SupportValid');

        return new ManifestBuilder(
            [$source, ManifestBuilder::frameworkSource()],
            predefined: [Theme::class, TemplateRenderer::class, RenderContext::class],
            scriptDirectory: $source->getDirectory(),
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

    public function testSupportsAddAttributesWithDefaults(): void
    {
        $attributes = self::blocks()['fixture/card']->getArgs()['attributes'];

        self::assertSame(['title', 'surface', 'framed', 'accent'], array_keys($attributes));
        // The block's default overrides the default of the support.
        self::assertSame(['type' => 'string', 'enum' => ['light', 'dark'], 'default' => 'dark'], $attributes['surface']);
        self::assertSame(['type' => 'boolean', 'default' => false], $attributes['framed']);
        self::assertSame(['type' => ['string', 'null']], $attributes['accent']);
        self::assertSame(['type' => 'boolean', 'default' => true], self::blocks()['fixture/unwrapped']->getArgs()['attributes']['framed']);
        self::assertArrayNotHasKey('framed', self::blocks()['fixture/plain']->getArgs()['attributes']);
    }

    public function testSupportsAddControlsWithLabelAndGroup(): void
    {
        $editor = self::blocks()['fixture/card']->getEditor();

        self::assertSame(['surface', 'framed', 'accent'], $editor['supports']);
        self::assertSame([
            'label' => 'Surface',
            'type' => 'enum',
            'nullable' => false,
            'options' => [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']],
            'content' => false,
            'group' => 'styles',
        ], $editor['controls']['surface']);
        self::assertSame(['label' => 'Framed', 'type' => 'bool', 'nullable' => false, 'content' => false, 'group' => 'settings'], $editor['controls']['framed']);
        self::assertSame(['label' => 'Accent', 'type' => 'string', 'nullable' => true, 'content' => false, 'group' => 'styles'], $editor['controls']['accent']);
        self::assertSame([], self::blocks()['fixture/plain']->getEditor()['supports']);
    }

    public function testSupportEntriesAndServices(): void
    {
        $result = self::buildValid();
        $card = self::blocks()['fixture/card'];

        self::assertSame([
            ['attribute' => 'surface', 'class' => SupportValid\Surface::class, 'value' => ['type' => 'enum', 'class' => SupportValid\Tone::class, 'nullable' => false], 'editorScript' => null, 'viewScript' => null],
            ['attribute' => 'framed', 'class' => SupportValid\Framed::class, 'value' => ['type' => 'bool', 'nullable' => false], 'editorScript' => null, 'viewScript' => null],
            // The editor script next to the support class.
            ['attribute' => 'accent', 'class' => SupportValid\Accent::class, 'value' => ['type' => 'string', 'nullable' => true], 'editorScript' => 'Accent.editor', 'viewScript' => null],
        ], $card->getSupports());

        $services = $result->getServices();
        self::assertSame(['naming' => SupportValid\Naming::class], $services[SupportValid\Surface::class]);
        self::assertArrayHasKey(SupportValid\Framed::class, $services);
        // Supports no block uses are not built, so their dependencies are not checked.
        self::assertArrayNotHasKey(SupportValid\Unused::class, $services);
    }

    public function testEditorScriptOutsideTheScriptDirectoryIsAnError(): void
    {
        $source = ManifestBuilderTest::fixture('SupportValid');

        try {
            new ManifestBuilder([$source, ManifestBuilder::frameworkSource()], predefined: [Theme::class, TemplateRenderer::class, RenderContext::class])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame([sprintf(
                'The editor script %s/Accent.editor.js of %s is outside the script directory (none), which the build of the project compiles.',
                $source->getDirectory(),
                SupportValid\Accent::class,
            )], $exception->getErrors());
        }
    }

    public function testReportsAllErrorsOfTheDeclarations(): void
    {
        $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\SupportBroken\\';
        $reserved = 'lock, metadata, className, anchor, align, style';
        $values = 'int, float, string, bool or a backed enum, optionally nullable';
        self::assertBuildErrors('SupportBroken', [
            "{$ns}AbstractSupport has #[BlockSupport], but {$ns}AbstractSupport is an abstract class and therefore not a service.",
            "#[BlockSupport] on {$ns}DuplicateB uses the attribute name \"duplicate\", which {$ns}DuplicateA already declares.",
            "#[BlockSupport] on {$ns}EmptyLabel has an empty label.",
            "#[BlockSupport] on {$ns}InvalidName has the invalid attribute name \"Color-Scheme\": use camelCase letters and digits, starting with a lowercase letter, e.g. \"colorScheme\".",
            "parameter \$value of {$ns}NoDefault::className() needs a default value: the attribute can always be missing in the content.",
            "{$ns}NoMethod has #[BlockSupport], but no className() method returning the class names for a value.",
            "{$ns}NotAService has #[BlockSupport], but {$ns}NotAService is marked #[NoService] and therefore not a service.",
            "parameter \$value of {$ns}ObjectValue::className(): the value of a block support must be {$values}.",
            "#[BlockSupport] on {$ns}Reserved uses the attribute name \"className\", which WordPress reserves ({$reserved}).",
            "{$ns}StaticMethod::className() of a #[BlockSupport] class must be public and non-static.",
            "{$ns}TwoParameters::className() of a #[BlockSupport] class must accept exactly one parameter, the value of the attribute.",
            "parameter \$value of {$ns}Untyped::className(): the value of a block support must be {$values}.",
            "parameter \$value of {$ns}WithAttribute::className() must not have attributes; label and group of the control come from #[BlockSupport].",
            "{$ns}WrongReturn::className() of a #[BlockSupport] class must return string or ?string.",
        ]);
    }

    public function testReportsAllErrorsOfTheUse(): void
    {
        $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\SupportUseBroken\\';
        self::assertBuildErrors('SupportUseBroken', [
            "{$ns}Broken has #[BlockSupport], but no className() method returning the class names for a value.",
            "#[UseSupport({$ns}Surface)] on {$ns}Collision adds the attribute \"surface\", which the block attribute class already declares.",
            "#[UseSupport({$ns}Flag)] on {$ns}NullDefault: default must be bool, null given.",
            "#[UseSupport] on {$ns}Twice names {$ns}Surface twice.",
            "#[UseSupport] on {$ns}UnknownSupport names {$ns}Helper, which is no class with #[BlockSupport] in the scanned PSR-4 directories of the project.",
            "{$ns}WithoutBlock has #[UseSupport], but no #[Block].",
            "#[UseSupport({$ns}Surface)] on {$ns}WrongDefault: default must be a case of {$ns}Tone, string given.",
        ]);
    }

    /**
     * @param list<string> $expected
     */
    private static function assertBuildErrors(string $fixture, array $expected): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture($fixture)])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors, implode("\n", $errors));
            }
            self::assertCount(count($expected), $errors, implode("\n", $errors));
        }
    }
}
