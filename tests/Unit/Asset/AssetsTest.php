<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Asset;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\AssetCollector;
use Saij\WpAppFramework\Asset\Assets;
use Saij\WpAppFramework\Tests\Support\Files;

/**
 * Static assets: the list of the build and their URLs, images and inline SVGs at run time, with overrides and the
 * child theme's own files.
 */
final class AssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-assets-' . bin2hex(random_bytes(4));
        $this->write('theme/assets/images/logo.svg', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" class="logo" width="120" height="40px"><path d="M0 0"/></svg>');
        $this->write('theme/assets/icons/menu.svg', '<svg viewBox="0 0 24 24.4"><path/></svg>');
        $this->write('theme/assets/icons/plain.svg', '<p>no svg</p>');
        $this->write('theme/assets/images/dot.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAYAAAC56t6BAAAAEElEQVR42mNkYPhfz4AEABH4AgFgRBvRAAAAAElFTkSuQmCC'));
        $this->write('theme/assets/docs/My File.pdf', 'pdf');
        $this->write('theme/assets/menu.view.js', '');
        $this->write('theme/assets/styles.css', '');
        $this->write('theme/assets/.gitkeep', '');
        $this->write('theme/assets/fonts/.hidden/x.woff2', '');
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
    }

    private function write(string $file, string $content): void
    {
        if (!is_dir(dirname($this->root . '/' . $file))) {
            mkdir(dirname($this->root . '/' . $file), 0777, true);
        }
        file_put_contents($this->root . '/' . $file, $content);
    }

    /**
     * @param list<array{string, string}> $overrides
     * @param array{string, string}|null  $child
     */
    private function assets(array $overrides = [], ?array $child = null): Assets
    {
        return new Assets(
            'my-theme',
            $this->root . '/theme/assets',
            'https://example.test/themes/my-theme/assets',
            AssetCollector::collect($this->root . '/theme/assets'),
            '@theme/',
            $overrides,
            $child,
        );
    }

    public function testTheBuildListsStaticFilesWithHashAndSize(): void
    {
        $assets = AssetCollector::collect($this->root . '/theme/assets');

        self::assertSame(['docs/My File.pdf', 'icons/menu.svg', 'icons/plain.svg', 'images/dot.png', 'images/logo.svg'], array_keys($assets));
        self::assertSame(['width' => 120, 'height' => 40, 'variants' => null], array_slice($assets['images/logo.svg'], 1));
        self::assertSame(['width' => 24, 'height' => 24, 'variants' => null], array_slice($assets['icons/menu.svg'], 1));
        self::assertSame(['width' => 2, 'height' => 3, 'variants' => null], array_slice($assets['images/dot.png'], 1));
        self::assertSame(['width' => null, 'height' => null, 'variants' => null], array_slice($assets['docs/My File.pdf'], 1));
        self::assertSame(substr((string) hash_file('xxh128', $this->root . '/theme/assets/images/logo.svg'), 0, 12), $assets['images/logo.svg']['hash']);
        self::assertSame([], AssetCollector::collect(null));
        self::assertSame([], AssetCollector::collect($this->root . '/missing'));
    }

    public function testUrlsAndPathsAreVersionedByContent(): void
    {
        $assets = $this->assets();
        $hash = AssetCollector::collect($this->root . '/theme/assets')['docs/My File.pdf']['hash'];

        self::assertSame('https://example.test/themes/my-theme/assets/docs/My%20File.pdf?ver=' . $hash, $assets->url('docs/My File.pdf'));
        self::assertSame($this->root . '/theme/assets/docs/My File.pdf', $assets->path('docs/My File.pdf'));
    }

    public function testImagesHaveTheirSizeAndFurtherAttributes(): void
    {
        $html = $this->assets()->image('images/logo.svg', 'Acme "Inc"', ['class' => 'h-8', 'loading' => 'lazy', 'hidden' => true, 'draggable' => false]);

        self::assertMatchesRegularExpression(
            '#^<img src="https://example.test/themes/my-theme/assets/images/logo.svg\?ver=[0-9a-f]{12}" width="120" height="40" alt="Acme &quot;Inc&quot;" decoding="async" class="h-8" loading="lazy" hidden>$#',
            $html,
        );
        self::assertStringNotContainsString('width=', $this->assets()->image('docs/My File.pdf', ''), 'Without a known size no width and height.');
    }

    public function testSvgsAreInlinedWithAttributesOnTheirRootElement(): void
    {
        self::assertSame(
            '<svg aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" class="logo size-4 text-primary" width="120" height="40px"><path d="M0 0"/></svg>',
            $this->assets()->svg('images/logo.svg', ['class' => 'size-4  text-primary', 'aria-hidden' => 'true', 'role' => 'img']),
        );
    }

    public function testInlineSvgsMustBeSvgImagesWithARootElement(): void
    {
        foreach ([
            'docs/My File.pdf' => 'asset_svg() needs an SVG image, docs/My File.pdf of my-theme is none.',
            'icons/plain.svg' => sprintf('The asset icons/plain.svg of my-theme (%s/theme/assets/icons/plain.svg) has no root element <svg>.', $this->root),
        ] as $name => $message) {
            try {
                $this->assets()->svg($name);
                self::fail('Expected an exception for ' . $name);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testUnknownAndInvalidNamesAreErrors(): void
    {
        foreach ([
            'images/missing.png' => 'does not exist',
            'menu.view.js' => 'does not exist',
            'styles.css' => 'does not exist',
            '../style.css' => 'is invalid',
            '/etc/passwd' => 'is invalid',
            'images/../images/logo.svg' => 'is invalid',
            '@plugin/images/logo.svg' => 'is invalid',
        ] as $name => $message) {
            try {
                $this->assets()->url($name);
                self::fail('Expected an exception for ' . $name);
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage(), $name);
            }
        }
    }

    public function testOverridesOfHigherLayersReplaceAssetsUnlessNamedExactly(): void
    {
        $this->write('child/overrides/my-theme/assets/images/logo.svg', '<svg width="10" height="10"></svg>');
        $overrides = [
            [$this->root . '/child/overrides/my-theme/assets', 'https://example.test/themes/child/overrides/my-theme/assets'],
            [$this->root . '/missing/overrides/my-theme/assets', 'https://example.test/missing'],
        ];
        $assets = $this->assets($overrides);
        $mtime = (string) filemtime($this->root . '/child/overrides/my-theme/assets/images/logo.svg');

        self::assertSame('https://example.test/themes/child/overrides/my-theme/assets/images/logo.svg?ver=' . $mtime, $assets->url('images/logo.svg'));
        self::assertStringContainsString('width="10" height="10"', $assets->image('images/logo.svg', ''));
        self::assertStringStartsWith('https://example.test/themes/my-theme/assets/images/logo.svg?ver=', $assets->url('@theme/images/logo.svg'));
        self::assertStringStartsWith('https://example.test/themes/my-theme/assets/icons/menu.svg?ver=', $assets->url('icons/menu.svg'), 'Not overridden.');

        $this->expectException(InvalidArgumentException::class);
        $this->write('child/overrides/my-theme/assets/images/new.png', '');
        $assets->url('images/new.png');
    }

    public function testTheChildThemesOwnAssets(): void
    {
        $this->write('child/assets/team.jpg', 'jpg');
        $assets = $this->assets([], [$this->root . '/child/assets', 'https://example.test/themes/child/assets']);

        self::assertSame('https://example.test/themes/child/assets/team.jpg?ver=' . filemtime($this->root . '/child/assets/team.jpg'), $assets->url('@child/team.jpg'));

        try {
            $assets->url('@child/missing.jpg');
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(sprintf('The asset @child/missing.jpg of my-theme does not exist: %s/child/assets/missing.jpg is missing.', $this->root), $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The asset @child/team.jpg of my-theme names a file of the child theme, but no child theme is active.');
        $this->assets()->url('@child/team.jpg');
    }
}
