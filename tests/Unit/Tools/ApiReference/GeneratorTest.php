<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Tools\ApiReference;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Tests\Fixtures\ApiReference\Broken\Gadget;
use Saij\WpAppFramework\Tests\Fixtures\ApiReference\Broken\Odd;
use Saij\WpAppFramework\Tests\Fixtures\ApiReference\Broken\Part;
use Saij\WpAppFramework\Tools\ApiReference\Generator;

final class GeneratorTest extends TestCase
{
    private string $content;

    protected function setUp(): void
    {
        $this->content = sys_get_temp_dir() . '/api-reference-' . bin2hex(random_bytes(4));
        mkdir($this->content . '/guides', 0o777, true);
        file_put_contents($this->content . '/guides/blocks.md', "# Blocks\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->content));
    }

    public function testWritesAPageForEachApiClassAndTwigFunction(): void
    {
        $sidebar = self::generator('Valid', $this->content)->generate();

        $widget = (string) file_get_contents($this->content . '/api/php/Widget.md');
        self::assertStringStartsWith("# #[Widget]\n\nAttribute on classes, repeatable.\n", $widget);
        self::assertStringContainsString('See [Blocks](../../guides/blocks.md#variations).', $widget);
        self::assertStringContainsString('#[Widget(string $name, array $sizes = [Size::Small])]', $widget);
        self::assertStringContainsString('| `sizes` | `list<`[`Size`](./Size.md)`>` | `[Size::Small]` | Sizes it comes in. |', $widget);

        $size = (string) file_get_contents($this->content . '/api/php/Size.md');
        self::assertStringContainsString("| `Small` | `'small'` | The smallest size. |", $size);

        $shelf = (string) file_get_contents($this->content . '/api/php/Shelf.md');
        self::assertStringContainsString('| `BOARDS` | `3` | Number of boards. |', $shelf);
        self::assertStringContainsString('### find()', $shelf);
        self::assertStringContainsString('**Returns** [`Widget`](./Widget.md)`|null`: The first widget of the size.', $shelf);
        self::assertStringNotContainsString('SECRET', $shelf);
        self::assertStringNotContainsString('hidden()', $shelf);

        $label = (string) file_get_contents($this->content . '/api/twig/shelf_label.md');
        self::assertStringContainsString("{{ shelf_label(size = null) }}", $label);
        self::assertStringContainsString('its result is HTML', $label);

        self::assertFileDoesNotExist($this->content . '/api/php/Hidden.md');
        self::assertSame(
            [
                ['text' => 'Attributes', 'collapsible' => true, 'collapsed' => true, 'items' => [
                    ['text' => 'Blocks', 'collapsible' => true, 'collapsed' => true, 'items' => [['text' => '#[Widget]', 'link' => '/api/php/Widget']]],
                ]],
                ['text' => 'Services', 'collapsible' => true, 'collapsed' => true, 'items' => [['text' => 'Shelf', 'link' => '/api/php/Shelf']]],
                ['text' => 'Enums', 'collapsible' => true, 'collapsed' => true, 'items' => [['text' => 'Size', 'link' => '/api/php/Size']]],
                ['text' => 'Twig functions', 'collapsible' => true, 'collapsed' => true, 'items' => [
                    ['text' => 'Framework', 'collapsible' => true, 'collapsed' => true, 'items' => [['text' => 'shelf_label()', 'link' => '/api/twig/shelf_label']]],
                    ['text' => 'WordPress', 'collapsible' => true, 'collapsed' => true, 'items' => []],
                ]],
            ],
            $sidebar,
        );
    }

    public function testReportsEveryGapAndWritesNothing(): void
    {
        try {
            self::generator('Broken', $this->content)->generate();
            self::fail('RuntimeException expected.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                "The API reference is incomplete:\n - " . implode("\n - ", [
                    Odd::class . ': @api names the group "Gadgets"; use one of ' . implode(', ', Generator::GROUPS) . '.',
                    Gadget::class . ' has no description.',
                    Gadget::class . ': @see guides/missing.md names no page below docs/content/.',
                    Gadget::class . '::LIMIT has no description.',
                    'Parameter $count of ' . Gadget::class . '::attach() has no description.',
                    Gadget::class . '::undocumented() has no description.',
                    'Parameter $part of ' . Gadget::class . '::attach() names the internal class ' . Part::class . '; mark it @api or change the signature.',
                ]),
                $exception->getMessage(),
            );
        }
        self::assertDirectoryDoesNotExist($this->content . '/api');
    }

    private static function generator(string $fixture, string $content): Generator
    {
        return new Generator(
            dirname(__DIR__, 3) . '/Fixtures/ApiReference/' . $fixture,
            'Saij\\WpAppFramework\\Tests\\Fixtures\\ApiReference\\' . $fixture . '\\',
            $content,
        );
    }
}
