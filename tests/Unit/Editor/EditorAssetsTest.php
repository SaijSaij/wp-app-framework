<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Editor;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Unit\Block\Support\SupportCollectorTest;
use Saij\WpAppFramework\Tests\Unit\View\ViewBuildTest;

final class EditorAssetsTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wp-app-framework-build-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        unset($GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_admin']);
    }

    private function assets(?BuildResult $result = null): EditorAssets
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate($result ?? EditorBuildTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        return new EditorAssets('my-theme', $this->build, 'https://example.test/build', $manifest['blocks'], true, 'fixture');
    }

    private function writeAsset(string $script, string $dependencies): void
    {
        file_put_contents(
            $this->build . '/' . $script . '.asset.php',
            "<?php return ['dependencies' => [{$dependencies}], 'version' => 'v1'];",
        );
    }

    public function testWithoutBlocksNothingIsLoaded(): void
    {
        new EditorAssets('my-theme', $this->build, 'https://example.test/build', [], true, 'fixture')->enqueue();

        self::assertArrayNotHasKey('wp_app_framework_test_scripts', $GLOBALS);
    }

    public function testMissingBuildIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The script vendor/wp-app-framework/editor is not built: %s/vendor/wp-app-framework/editor.asset.php is missing. Run "npm run build" (or "npm run start") in the theme or plugin my-theme.',
            $this->build,
        ));

        $this->assets()->enqueue();
    }

    public function testCanvasStylesAreLoadedInTheEditorOnly(): void
    {
        $this->writeAsset('vendor/wp-app-framework/canvas', '');
        file_put_contents($this->build . '/vendor/wp-app-framework/canvas.css', '');

        $this->assets()->enqueueCanvas();
        self::assertArrayNotHasKey('wp_app_framework_test_styles', $GLOBALS, 'Not on the front end.');

        $GLOBALS['wp_app_framework_test_admin'] = true;
        new EditorAssets('my-theme', $this->build, 'https://example.test/build', [], true, 'fixture')->enqueueCanvas();
        self::assertArrayNotHasKey('wp_app_framework_test_styles', $GLOBALS, 'Not without blocks.');

        $this->assets()->enqueueCanvas();
        self::assertSame(
            ['my-theme-wp-app-framework-canvas' => ['src' => 'https://example.test/build/vendor/wp-app-framework/canvas.css', 'deps' => [], 'ver' => 'v1', 'enqueued' => true, 'rtl' => 'replace']],
            Recorded::styles(),
        );
    }

    public function testMissingCanvasStylesAreAnError(): void
    {
        $GLOBALS['wp_app_framework_test_admin'] = true;
        $this->writeAsset('vendor/wp-app-framework/canvas', '');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The styles vendor/wp-app-framework/canvas are not built: %s/vendor/wp-app-framework/canvas.css is missing.',
            $this->build,
        ));

        $this->assets()->enqueueCanvas();
    }

    public function testMissingStylesAreAnError(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', '');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The styles vendor/wp-app-framework/editor are not built: %s/vendor/wp-app-framework/editor.css is missing.',
            $this->build,
        ));

        $this->assets()->enqueue();
    }

    public function testEnqueuesTheRuntimeWithItsConfigurationAndTheBlockEditorScripts(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', "'wp-blocks', 'react-jsx-runtime'");
        file_put_contents($this->build . '/vendor/wp-app-framework/editor.css', '');
        $this->writeAsset('Container.editor', "'my-theme-wp-app-framework'");

        $this->assets()->enqueue();
        $scripts = Recorded::scripts();

        self::assertSame(['my-theme-wp-app-framework', 'my-theme-fixture-container-editor'], array_keys($scripts));
        self::assertSame('https://example.test/build/vendor/wp-app-framework/editor.js', $scripts['my-theme-wp-app-framework']['src']);
        self::assertSame(['wp-blocks', 'react-jsx-runtime'], $scripts['my-theme-wp-app-framework']['deps']);
        self::assertSame('v1', $scripts['my-theme-wp-app-framework']['ver']);
        self::assertTrue($scripts['my-theme-wp-app-framework']['enqueued']);
        self::assertSame('https://example.test/build/Container.editor.js', $scripts['my-theme-fixture-container-editor']['src']);
        self::assertSame(['my-theme-wp-app-framework'], $scripts['my-theme-fixture-container-editor']['deps']);
        self::assertTrue($scripts['my-theme-fixture-container-editor']['enqueued']);

        self::assertSame(
            ['my-theme-wp-app-framework' => ['src' => 'https://example.test/build/vendor/wp-app-framework/editor.css', 'deps' => ['wp-components'], 'ver' => 'v1', 'enqueued' => true, 'rtl' => 'replace']],
            Recorded::styles(),
        );

        [$before] = $scripts['my-theme-wp-app-framework']['before'];
        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = { config: ';
        self::assertStringStartsWith($prefix, $before);
        /** @var array{slug: string, namespace: string, renderPath: string, maxBlocks: int, development: bool, blocks: array<string, array{fields: array<string, array<string, mixed>>, controls: array<string, mixed>, innerBlocks: bool}>} $config */
        $config = json_decode(substr($before, strlen($prefix), -strlen(' };')), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('my-theme', $config['slug']);
        self::assertSame('fixture', $config['namespace']);
        self::assertSame('/wp-app-framework/v1/my-theme/render', $config['renderPath']);
        self::assertSame(100, $config['maxBlocks']);
        self::assertTrue($config['development']);
        self::assertSame(['fixture/container', 'fixture/teaser', 'fixture/wrong-field'], array_keys($config['blocks']));
        self::assertSame(['fields' => [], 'controls' => [], 'innerBlocks' => true, 'innerBlockOptions' => [], 'postContentField' => null, 'supports' => [], 'view' => false], $config['blocks']['fixture/container']);
        self::assertSame(['kind' => 'rich', 'label' => 'Title', 'allowedFormats' => ['core/bold']], $config['blocks']['fixture/teaser']['fields']['title']);
    }

    public function testEnqueuesTheEditorScriptsOfUsedSupportsAndPassesTheirAttributes(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', "'wp-blocks'");
        file_put_contents($this->build . '/vendor/wp-app-framework/editor.css', '');
        $this->writeAsset('Accent.editor', "'my-theme-wp-app-framework'");

        $assets = $this->assets(SupportCollectorTest::buildValid());
        $assets->enqueue();
        $scripts = Recorded::scripts();

        self::assertSame(['my-theme-wp-app-framework', 'my-theme-support-accent-editor'], array_keys($scripts));
        self::assertSame('https://example.test/build/Accent.editor.js', $scripts['my-theme-support-accent-editor']['src']);
        self::assertTrue($scripts['my-theme-support-accent-editor']['enqueued']);

        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = { config: ';
        /** @var array{blocks: array<string, array{supports: list<string>}>} $config */
        $config = json_decode(substr($assets->configScript(), strlen($prefix), -strlen(' };')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['surface', 'framed', 'accent'], $config['blocks']['fixture/card']['supports']);
        self::assertSame([], $config['blocks']['fixture/plain']['supports']);
    }

    public function testLoadsTheViewScriptsForThePreviewAndMarksTheBlocksWithViews(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', "'wp-blocks'");
        file_put_contents($this->build . '/vendor/wp-app-framework/editor.css', '');
        $this->writeAsset('vendor/wp-app-framework/view', '');
        $this->writeAsset('Faq.view', "'my-theme-wp-app-framework-view'");
        $this->writeAsset('Reveal.view', "'my-theme-wp-app-framework-view'");
        $this->writeAsset('Bare.view', "'my-theme-wp-app-framework-view'");

        $assets = $this->assets(ViewBuildTest::buildValid());
        $assets->enqueue();
        $scripts = Recorded::scripts();

        self::assertSame(
            ['my-theme-wp-app-framework', 'my-theme-wp-app-framework-view', 'my-theme-fixture-bare-view', 'my-theme-fixture-faq-view', 'my-theme-support-reveal-on-scroll-view'],
            array_keys($scripts),
        );
        foreach ($scripts as $handle => $script) {
            self::assertTrue($script['enqueued'], $handle);
        }

        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = { config: ';
        /** @var array{previewPath: string, blocks: array<string, array{view: bool}>} $config */
        $config = json_decode(substr($assets->configScript(), strlen($prefix), -strlen(' };')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('/wp-app-framework/v1/my-theme/preview', $config['previewPath']);
        self::assertSame(
            ['fixture/bare' => true, 'fixture/faq' => true, 'fixture/note' => true, 'fixture/plain' => false],
            array_map(static fn(array $block): bool => $block['view'], $config['blocks']),
        );
    }
}
