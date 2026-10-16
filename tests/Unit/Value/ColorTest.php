<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\ColorBroken\BrokenAttributes;
use Saij\WpAppFramework\Tests\Fixtures\ColorValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\Color;
use Saij\WpAppFramework\Value\FormAssets;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Colors (`#[Color]`): schema with pattern, control and value description, the check of the value factory, build
 * errors and the palette of the forms outside the block editor.
 */
final class ColorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_global_settings']);
    }

    private static function build(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('ColorValid')])->build();
    }

    private static function banner(BuildResult $result): BlockDefinition
    {
        return $result->getBlocks()[0];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function create(BuildResult $result, array $attributes): ColorValid\BannerAttributes
    {
        return new ValueFactory($result->getValues())
            ->createAttributes(ColorValid\BannerAttributes::class, $attributes, self::banner($result)->getArgs()['attributes']);
    }

    public function testColorsAreStringsWithAPatternAndAControlOfTheirOwn(): void
    {
        $result = self::build();
        $block = self::banner($result);
        $attributes = $block->getArgs()['attributes'];

        self::assertSame(['type' => 'string', 'pattern' => Color::PATTERN, 'default' => ''], $attributes['background']);
        self::assertSame(['type' => 'string', 'pattern' => Color::PATTERN, 'default' => '#1e1e1e80'], $attributes['text']);
        self::assertSame(
            ['type' => 'object', 'properties' => ['color' => ['type' => 'string', 'pattern' => Color::PATTERN, 'default' => '#fff']]],
            $attributes['stripes']['items'],
        );

        $controls = $block->getEditor()['controls'];
        self::assertSame(['type' => 'color', 'label' => 'Background', 'nullable' => false, 'content' => false], $controls['background']);
        self::assertSame(['type' => 'color', 'label' => 'Text color', 'nullable' => false, 'content' => false], $controls['text']);
        self::assertSame(
            ['type' => 'string', 'nullable' => false, 'color' => true],
            $result->getValues()[ColorValid\BannerAttributes::class]['background']['value'],
        );
    }

    public function testThePatternAcceptsHexColorsWithAlphaAndTheEmptyString(): void
    {
        // WordPress matches patterns like this in rest_validate_json_schema_pattern().
        $wordpress = '#' . str_replace('#', '\\#', Color::PATTERN) . '#u';
        foreach (['', '#fff', '#FFF8', '#1e4d8c', '#1E4D8C80'] as $color) {
            self::assertSame(1, preg_match($wordpress, $color), $color);
            self::assertTrue(Color::isColor($color), $color);
        }
        foreach (['red', '1e4d8c', '#1e4d8', '#1e4d8c8', '#1e4d8c800', 'rgb(0,0,0)', '#fff;background:url(x)'] as $color) {
            self::assertSame(0, preg_match($wordpress, $color), $color);
            self::assertFalse(Color::isColor($color), $color);
        }
        // Without the modifier D, $ accepts a newline at the end; the value factory does not.
        self::assertSame(1, preg_match($wordpress, "#fff\n"));
        self::assertFalse(Color::isColor("#fff\n"));
    }

    public function testTheValueFactoryChecksColorsAgain(): void
    {
        $result = self::build();

        $attrs = self::create($result, ['background' => '#1e4d8c80', 'stripes' => [['color' => '#000']]]);
        self::assertSame('#1e4d8c80', $attrs->background);
        self::assertSame('#1e1e1e80', $attrs->text);
        self::assertSame('#000', $attrs->stripes[0]->color);

        // WordPress validates block attributes before they are rendered; options and meta can be changed without it.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "stripes[0].color" must be a hex color or an empty string, \'red;background:url(x)\' given.');
        self::create($result, ['stripes' => [['color' => 'red;background:url(x)']]]);
    }

    public function testReportsAllErrors(): void
    {
        $at = static fn(string $name): string => sprintf('parameter $%s of %s::__construct()', $name, BrokenAttributes::class);
        $expected = [
            $at('number') . ' has #[Color], which is only allowed on strings (a hex color, an empty string for no color).',
            $at('nullable') . ' has #[Color] on ?string; use string, an empty string means no color.',
            $at('url') . ' has #[Color] and #[Url]; a color has a control of its own.',
            $at('plain') . ' has #[Color] and #[PlainText]; a color has a control of its own.',
            $at('named') . ': the default "red" is no color; use a hex color such as "#1e4d8c" or "#1e4d8c80", or an empty string for no color.',
            $at('noHash') . ': the default "1e4d8c" is no color; use a hex color such as "#1e4d8c" or "#1e4d8c80", or an empty string for no color.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('ColorBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }

    public function testThePaletteHasTheHexColorsOfTheFirstOriginLikeTheBlockEditor(): void
    {
        $theme = [
            ['name' => 'Primary', 'slug' => 'primary', 'color' => '#1e4d8c'],
            ['name' => 'Variable', 'slug' => 'variable', 'color' => 'var(--x)'],
            ['name' => '', 'slug' => 'transparent', 'color' => '#00000080'],
        ];
        $default = [['name' => 'Black', 'slug' => 'black', 'color' => '#000000']];
        $GLOBALS['wp_app_framework_test_global_settings'] = ['color.palette' => ['default' => $default, 'theme' => $theme]];
        self::assertSame([['name' => 'Primary', 'color' => '#1e4d8c'], ['name' => '#00000080', 'color' => '#00000080']], FormAssets::palette());

        // Colors of the site editor replace those of the theme.
        $custom = [['name' => 'Mine', 'slug' => 'mine', 'color' => '#abcdef']];
        $GLOBALS['wp_app_framework_test_global_settings'] = ['color.palette' => ['default' => $default, 'theme' => $theme, 'custom' => $custom]];
        self::assertSame([['name' => 'Mine', 'color' => '#abcdef']], FormAssets::palette());

        unset($GLOBALS['wp_app_framework_test_global_settings']);
        self::assertSame([], FormAssets::palette());
    }
}
