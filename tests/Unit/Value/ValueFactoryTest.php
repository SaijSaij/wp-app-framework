<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Value;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;
use WebCodingCat\WpAppFramework\Tests\Unit\Block\BlockCollectorTest;
use WebCodingCat\WpAppFramework\Value\ValueFactory;

final class ValueFactoryTest extends TestCase
{
    private ValueFactory $factory;

    /** @var array<string, array<string, mixed>> */
    private array $schemas;

    protected function setUp(): void
    {
        $result = BlockCollectorTest::buildValid();
        $this->factory = new ValueFactory($result->getValues());
        foreach ($result->getBlocks() as $block) {
            if ($block->getName() === 'fixture/hero') {
                $this->schemas = $block->getArgs()['attributes'];
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function create(array $attributes): BlockValid\HeroAttributes
    {
        return $this->factory->createAttributes(BlockValid\HeroAttributes::class, $attributes, $this->schemas);
    }

    public function testMissingAttributesUseConstructorDefaults(): void
    {
        self::assertEquals(new BlockValid\HeroAttributes(), $this->create([]));
    }

    public function testConvertsEnumsValueObjectsAndLists(): void
    {
        $attributes = $this->create([
            'topline' => 'Top',
            'imageId' => '12',
            'gradient' => 'white',
            'buttons' => [['label' => 'One', 'url' => 'https://example.org', 'size' => 2], ['label' => 'Two']],
            'link' => ['href' => 'https://example.com', 'external' => true],
            'ratio' => 1,
            'sizes' => [2, 1],
            'secondary' => null,
            // Attributes of block supports are not part of the class.
            'className' => 'extra',
        ]);

        self::assertEquals(
            new BlockValid\HeroAttributes(
                topline: 'Top',
                // Sanitized against the schema first: WordPress validates block attributes but does not sanitize them.
                imageId: 12,
                gradient: BlockValid\Gradient::White,
                buttons: [new BlockValid\Button('One', 'https://example.org', BlockValid\Size::Large), new BlockValid\Button('Two')],
                link: new BlockValid\Link('https://example.com', true),
                ratio: 1.0,
                sizes: [BlockValid\Size::Large, BlockValid\Size::Small],
            ),
            $attributes,
        );
        self::assertSame(1.0, $attributes->ratio);
    }

    public function testTypeErrorsNameThePath(): void
    {
        $this->assertFails('Value "buttons[1].url" must be string, int given.', ['buttons' => [['label' => 'One'], ['label' => 'Two', 'url' => 5]]]);
        $this->assertFails('Value "buttons[0].label" for ' . BlockValid\Button::class . '::$label is missing.', ['buttons' => [['url' => '']]]);
        $this->assertFails('Value "gradient" must be a value of ' . BlockValid\Gradient::class . ", 'red' given.", ['gradient' => 'red']);
        $this->assertFails('Value "link" must be an object of ' . BlockValid\Link::class . ', string given.', ['link' => 'https://example.com']);
        $this->assertFails('Value "sizes" must be a list, array given.', ['sizes' => ['a' => 1]]);
        $this->assertFails('Value "count" must be int, null given.', ['count' => null]);
    }

    public function testTimesAreUtcStringsOfTheDatabase(): void
    {
        $time = $this->factory->create(['type' => 'datetime', 'nullable' => true], '2026-10-14 08:30:05', 'created');
        self::assertInstanceOf(DateTimeImmutable::class, $time);
        self::assertSame('2026-10-14T08:30:05+00:00', $time->format(DATE_ATOM));
        self::assertNull($this->factory->create(['type' => 'datetime', 'nullable' => true], null, 'created'));

        foreach (['0000-00-00 00:00:00', '2026-02-31 00:00:00', '2026-10-14'] as $invalid) {
            try {
                $this->factory->create(['type' => 'datetime', 'nullable' => false], $invalid, 'created');
                self::fail('The time must be rejected: ' . $invalid);
            } catch (InvalidArgumentException $exception) {
                self::assertSame(sprintf('Value "created" must be a time in the format Y-m-d H:i:s, \'%s\' given.', $invalid), $exception->getMessage());
            }
        }
        $this->expectExceptionMessage('Value "created" must be a time, int given.');
        $this->factory->create(['type' => 'datetime', 'nullable' => false], 5, 'created');
    }

    public function testUnknownValueClassNeedsARebuild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Value class stdClass is not in the manifest; rebuild the manifest after code changes.');

        $this->factory->createAttributes(\stdClass::class, [], []);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function assertFails(string $message, array $attributes): void
    {
        try {
            $this->create($attributes);
            self::fail('InvalidArgumentException expected: ' . $message);
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }
}
