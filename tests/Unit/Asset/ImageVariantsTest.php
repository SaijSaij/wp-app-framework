<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Asset;

use LogicException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\AssetCollector;
use Saij\WpAppFramework\Asset\Assets;
use Saij\WpAppFramework\Asset\ImageVariantCollector;
use Saij\WpAppFramework\Asset\ImageVariants;
use Saij\WpAppFramework\Tests\Support\Files;

/**
 * Image variants: the calls of asset_image() the build reads, the files it writes with Imagick or GD, and the
 * srcset and <picture> of asset_image().
 */
#[RequiresPhpExtension('gd')]
final class ImageVariantsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-variants-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/assets/images', 0777, true);
        mkdir($this->root . '/src', 0777, true);
        $image = imagecreatetruecolor(1600, 800);
        imagejpeg($image, $this->root . '/assets/images/hero.jpg');
        imagepng(imagecreatetruecolor(40, 20), $this->root . '/assets/images/small.png');
        file_put_contents($this->root . '/assets/images/logo.svg', '<svg width="10" height="10"></svg>');
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
    }

    private function template(string $name, string $code): void
    {
        if (!is_dir(dirname($this->root . '/src/' . $name))) {
            mkdir(dirname($this->root . '/src/' . $name), 0777, true);
        }
        file_put_contents($this->root . '/src/' . $name, $code);
    }

    public function testTheBuildCollectsTheVariantsOfAllLiteralCalls(): void
    {
        $this->template('Block/Hero/Hero.twig', <<<'TWIG'
            {% if is_editor() %}{{ field('x') }}{% endif %}
            {{ asset_image('images/hero.jpg', attrs.alt, {widths: [640, 1280, 2400], sizes: '100vw', formats: ['webp'], class: 'w-full'}) }}
            {{ asset_image('images/small.png', '') }}
            {{ asset_image('images/logo.svg', 'Logo', {class: attrs.class|upper}) }}
            TWIG);
        $this->template('Block/Teaser.twig', "{{ asset_image('@theme/images/hero.jpg', '', {widths: [320, 640], sizes: '50vw', formats: ['avif']}) }}");

        $collector = new ImageVariantCollector();
        $assets = $collector->collect($this->root . '/src', AssetCollector::collect($this->root . '/assets'));

        self::assertSame([], $collector->errors());
        self::assertSame(['widths' => [320, 640, 1280], 'formats' => ['avif', 'webp']], $assets['images/hero.jpg']['variants']);
        self::assertNull($assets['images/small.png']['variants']);
        self::assertSame(['Image images/hero.jpg: the widths 2400 exceed its width of 1600 pixels and are left out.'], $collector->notices());
    }

    public function testCallsAskingForVariantsNeedLiteralsAndAnImageWithVariants(): void
    {
        $this->template('Errors.twig', <<<'TWIG'
            {{ asset_image(name, '', {widths: [640], sizes: '100vw'}) }}
            {{ asset_image('images/missing.jpg', '', {widths: [640], sizes: '100vw'}) }}
            {{ asset_image('images/logo.svg', '', {widths: [640], sizes: '100vw'}) }}
            {{ asset_image('images/hero.jpg', '', {widths: sizes, sizes: '100vw'}) }}
            {{ asset_image('images/hero.jpg', '', {widths: [0], sizes: '100vw'}) }}
            {{ asset_image('images/hero.jpg', '', {widths: [640]}) }}
            {{ asset_image('images/hero.jpg', '', {widths: [640], sizes: '100vw', formats: ['gif']}) }}
            {{ asset_image('images/hero.jpg', '', {formats: ['webp']}) }}
            TWIG);

        $collector = new ImageVariantCollector();
        $collector->collect($this->root . '/src', AssetCollector::collect($this->root . '/assets'));

        self::assertSame([
            'asset_image() in Errors.twig, line 1 asks for image variants, so it needs the name of the image as a literal string.',
            'asset_image() in Errors.twig, line 2 asks for variants of images/missing.jpg, which is no static asset of the project (assets/images/missing.jpg).',
            'asset_image() in Errors.twig, line 3 asks for variants of images/logo.svg; only jpg, jpeg, png, webp, avif images have variants.',
            'asset_image() in Errors.twig, line 4 needs "widths" as a literal list of positive integers, e.g. [640, 1280].',
            'asset_image() in Errors.twig, line 5 needs "widths" as a literal list of positive integers, e.g. [640, 1280].',
            'asset_image() in Errors.twig, line 6 has "widths" without "sizes"; the browser needs both to choose a width.',
            'asset_image() in Errors.twig, line 7 needs "formats" as a literal list of jpeg, png, webp, avif.',
            'asset_image() in Errors.twig, line 8 needs "widths" as a literal list of positive integers, e.g. [640, 1280].',
        ], $collector->errors());
    }

    public function testATemplateThatDoesNotParseIsANotice(): void
    {
        $this->template('Broken.twig', '{% if %}');

        $collector = new ImageVariantCollector();
        $collector->collect($this->root . '/src', []);

        self::assertCount(1, $collector->notices());
        self::assertStringStartsWith('Template Broken.twig was not searched for image variants: ', $collector->notices()[0]);
    }

    /**
     * @return array<string, array{hash: non-empty-string, width: int|null, height: int|null, variants: array{widths: list<int>, formats: list<string>}|null}>
     */
    private function assetsWithVariants(): array
    {
        $assets = AssetCollector::collect($this->root . '/assets');
        $hero = $assets['images/hero.jpg'] ?? self::fail('The hero image is listed.');
        $assets['images/hero.jpg'] = [...$hero, 'variants' => ['widths' => [320, 640], 'formats' => ['avif', 'webp']]];

        return $assets;
    }

    public function testWritesTheVariantsOnceAndPrunesOthers(): void
    {
        $assets = $this->assetsWithVariants();
        $hash = $assets['images/hero.jpg']['hash'];
        $output = $this->root . '/variants';
        mkdir($output . '/images', 0777, true);
        touch($output . '/images/stale.webp');

        $written = ImageVariants::generate($assets, $this->root . '/assets', $output, true);
        self::assertSame([
            "images/hero.{$hash}.320.avif",
            "images/hero.{$hash}.320.jpg",
            "images/hero.{$hash}.320.webp",
            "images/hero.{$hash}.640.avif",
            "images/hero.{$hash}.640.jpg",
            "images/hero.{$hash}.640.webp",
        ], $written);
        self::assertFileDoesNotExist($output . '/images/stale.webp');
        self::assertSame([320, 160, IMAGETYPE_WEBP], array_slice((array) getimagesize($output . "/images/hero.{$hash}.320.webp"), 0, 3));
        self::assertSame([640, 320, IMAGETYPE_JPEG], array_slice((array) getimagesize($output . "/images/hero.{$hash}.640.jpg"), 0, 3));
        self::assertSame([], ImageVariants::generate($assets, $this->root . '/assets', $output), 'Existing variants are kept.');
    }

    public function testWithoutImagickAndGdTheBuildFailsNamingWhatIsMissing(): void
    {
        $command = [PHP_BINARY, '-n', __DIR__ . '/../../bin/image-variants-probe.php', $this->root . '/assets/images/hero.jpg', $this->root . '/out.webp'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        proc_close($process);

        self::assertStringContainsString(sprintf('The image variant %s/out.webp needs the format WEBP, which neither the PHP extension Imagick nor GD of %s can write. Install Imagick or GD with WEBP support', $this->root, PHP_BINARY), $output);
    }

    private function assets(): Assets
    {
        return new Assets(
            'my-theme',
            $this->root . '/assets',
            'https://example.test/assets',
            $this->assetsWithVariants(),
            '@theme/',
            [],
            null,
            [$this->root . '/variants', 'https://example.test/variants'],
        );
    }

    public function testImagesWithVariantsHaveASrcsetAndSourcesPerFormat(): void
    {
        $hash = $this->assetsWithVariants()['images/hero.jpg']['hash'];
        $variant = static fn(int $width, string $extension): string => "https://example.test/variants/images/hero.{$hash}.{$width}.{$extension} {$width}w";

        self::assertSame(
            '<picture>'
            . '<source type="image/webp" srcset="' . $variant(320, 'webp') . ', ' . $variant(640, 'webp') . '" sizes="(min-width: 800px) 50vw, 100vw">'
            . '<img src="https://example.test/assets/images/hero.jpg?ver=' . $hash . '" srcset="' . $variant(320, 'jpg') . ', ' . $variant(640, 'jpg') . ', https://example.test/assets/images/hero.jpg?ver=' . $hash . ' 1600w"'
            . ' sizes="(min-width: 800px) 50vw, 100vw" width="1600" height="800" alt="Hero" decoding="async" class="w-full">'
            . '</picture>',
            $this->assets()->image('images/hero.jpg', 'Hero', ['widths' => [320, 640], 'formats' => ['webp'], 'sizes' => '(min-width: 800px) 50vw, 100vw', 'class' => 'w-full']),
        );
        self::assertStringStartsWith('<img src=', $this->assets()->image('images/hero.jpg', '', ['widths' => [640], 'sizes' => '100vw']), 'Without formats no <picture>.');
    }

    public function testVariantsThatWereNotBuiltAreAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The variants of images/hero.jpg of my-theme (widths 1280, formats webp) are not built.');

        $this->assets()->image('images/hero.jpg', '', ['widths' => [1280], 'formats' => ['webp'], 'sizes' => '100vw']);
    }

    public function testVariantsNeedWidthsAndSizes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('asset_image() of images/hero.jpg asks for variants: it needs "widths" and "sizes".');

        $this->assets()->image('images/hero.jpg', '', ['widths' => [640]]);
    }
}
