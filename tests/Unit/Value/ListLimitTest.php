<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken\BrokenAttributes;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken\BrokenSettings;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken\NestedAttributes;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitValid;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitValid\Slide;
use Saij\WpAppFramework\Tests\Fixtures\ListLimitValid\Tone;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Limits of lists (`min` and `max` of #[ListOf]): never in the schema of stored values, in the value description
 * and the control; the value factory creates only the first `max` items.
 */
final class ListLimitTest extends TestCase
{
    private static function build(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('ListLimitValid')])->build();
    }

    private static function gallery(BuildResult $result): BlockDefinition
    {
        return $result->getBlocks()[0];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function create(BuildResult $result, array $attributes): ListLimitValid\GalleryAttributes
    {
        return new ValueFactory($result->getValues())
            ->createAttributes(ListLimitValid\GalleryAttributes::class, $attributes, self::gallery($result)->getArgs()['attributes']);
    }

    public function testLimitsAreNoSchemaKeywordsOfStoredValues(): void
    {
        $attributes = self::gallery(self::build())->getArgs()['attributes'];

        // WordPress drops an attribute that fails minItems or maxItems, so a changed limit would lose stored lists.
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['light', 'dark']], 'default' => ['light']], $attributes['tones']);
        /** @var array{items: array{properties: array<string, mixed>}} $slides */
        $slides = $attributes['slides'];
        self::assertSame(
            ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['light', 'dark']], 'default' => ['dark']],
            $slides['items']['properties']['tones'],
        );
        self::assertStringNotContainsString('Items', (string) json_encode($attributes));
    }

    public function testLimitsAreInTheValuesAndControls(): void
    {
        $result = self::build();
        $controls = self::gallery($result)->getEditor()['controls'];
        $tone = ['type' => 'enum', 'nullable' => false, 'options' => [['value' => 'light', 'label' => 'light'], ['value' => 'dark', 'label' => 'dark']]];

        self::assertSame(['type' => 'list', 'label' => 'Tones', 'nullable' => false, 'item' => $tone, 'min' => 1, 'max' => 2, 'itemLabel' => 'Tone', 'content' => false], $controls['tones']);
        self::assertSame(3, $controls['slides']['max']);
        self::assertArrayNotHasKey('min', $controls['slides']);
        /** @var array{item: array{fields: array<string, mixed>}} $slides */
        $slides = $controls['slides'];
        self::assertSame(['type' => 'list', 'label' => 'Tones', 'nullable' => false, 'item' => $tone, 'min' => 1, 'max' => 1, 'itemLabel' => 'Tone'], $slides['item']['fields']['tones']);
        self::assertSame(['type' => 'list', 'label' => 'Free', 'nullable' => false, 'item' => $tone, 'itemLabel' => 'Tone', 'content' => false], $controls['free']);
        self::assertSame(['type' => 'list', 'label' => 'Optional', 'nullable' => true, 'item' => $tone, 'min' => 2, 'itemLabel' => 'Tone', 'content' => false], $controls['optional']);

        $values = $result->getValues();
        self::assertSame(
            ['type' => 'list', 'items' => ['type' => 'enum', 'class' => Tone::class, 'nullable' => false], 'nullable' => false, 'min' => 1, 'max' => 2],
            $values[ListLimitValid\GalleryAttributes::class]['tones']['value'],
        );
        self::assertSame(
            ['type' => 'list', 'items' => ['type' => 'enum', 'class' => Tone::class, 'nullable' => false], 'nullable' => false],
            $values[ListLimitValid\GalleryAttributes::class]['free']['value'],
        );
    }

    public function testOnlyTheFirstMaxItemsAreCreated(): void
    {
        $result = self::build();

        $attrs = self::create($result, [
            'tones' => ['dark', 'light', 'dark'],
            'slides' => [
                ['caption' => 'A', 'tones' => ['light', 'dark']],
                ['caption' => 'B'],
                ['caption' => 'C'],
                ['caption' => 'D'],
            ],
            'free' => ['dark', 'dark', 'dark', 'dark'],
        ]);

        self::assertSame([Tone::Dark, Tone::Light], $attrs->tones);
        self::assertEquals([new Slide('A', [Tone::Light]), new Slide('B'), new Slide('C')], $attrs->slides);
        self::assertSame([Tone::Dark, Tone::Dark, Tone::Dark, Tone::Dark], $attrs->free);
    }

    public function testShorterListsThanMinAreCreatedAsTheyAre(): void
    {
        $attrs = self::create(self::build(), ['tones' => [], 'optional' => ['light']]);

        self::assertSame([], $attrs->tones);
        self::assertSame([Tone::Light], $attrs->optional);
    }

    public function testReportsAllErrors(): void
    {
        $at = static fn(string $name): string => sprintf('parameter $%s of %s::__construct()', $name, BrokenAttributes::class);
        $expected = [
            sprintf('parameter $rows of block attribute class %s::__construct(): the list "rows.*.tones" has appender in #[ListOf], but is in a list item; only lists outside of list items get a new item in the canvas.', NestedAttributes::class),
            sprintf('parameter $tones of %s::__construct(): appender of #[ListOf] adds items in the canvas of the block editor, but settings groups and post meta have no canvas.', BrokenSettings::class),
            $at('negative') . ': min of #[ListOf] must be 0 or more, -1 given.',
            $at('zero') . ': max of #[ListOf] must be 1 or more, 0 given; use null for no limit.',
            $at('crossed') . ': min of #[ListOf] (3) is greater than max (2).',
            $at('tooMany') . ': the default has 2 item(s), but #[ListOf] allows 0 to 1; a new value would start outside the limits.',
            $at('tooFew') . ': the default has 0 item(s), but #[ListOf] allows at least 1; a new value would start outside the limits.',
            $at('notExactly') . ': the default has 1 item(s), but #[ListOf] allows exactly 2; a new value would start outside the limits.',
            $at('emptyLabel') . ': itemLabel of #[ListOf] is empty; use null for the name of the class.',
            $at('hiddenAppender') . ': appender of #[ListOf] adds items in the canvas of the block editor, but the list is #[Hidden].',
            $at('postAppender') . ': appender of #[ListOf] adds items in the canvas of the block editor, but a list of posts is chosen with a search, not in the canvas.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('ListLimitBroken'), ManifestBuilder::frameworkSource()])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }
}
