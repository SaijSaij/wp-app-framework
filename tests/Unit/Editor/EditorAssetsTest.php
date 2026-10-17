<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Editor;

use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Editor\EditorAssets;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Tests\Unit\Block\Support\SupportCollectorTest;
use WebCodingCat\WpAppFramework\Tests\Unit\View\ViewBuildTest;
use WebCodingCat\WpAppFramework\View\ViewData;

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
        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].config = ';
        self::assertStringStartsWith($prefix, $before);
        /** @var array{slug: string, namespace: string, renderPath: string, maxBlocks: int, development: bool, blocks: array<string, array{fields: array<string, array<string, mixed>>, controls: array<string, mixed>, innerBlocks: bool}>} $config */
        $config = json_decode(substr($before, strlen($prefix), -strlen(';')), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('my-theme', $config['slug']);
        self::assertSame('fixture', $config['namespace']);
        self::assertSame('/wp-app-framework/v1/my-theme/render', $config['renderPath']);
        self::assertSame(100, $config['maxBlocks']);
        self::assertTrue($config['development']);
        self::assertSame(['fixture/container', 'fixture/teaser', 'fixture/wrong-field'], array_keys($config['blocks']));
        self::assertSame(['fields' => [], 'controls' => [], 'innerBlocks' => true, 'repeater' => false, 'innerBlockOptions' => [], 'postContentField' => null, 'appenders' => [], 'supports' => [], 'view' => false], $config['blocks']['fixture/container']);
        self::assertSame(['kind' => 'rich', 'label' => 'Title', 'allowedFormats' => ['core/bold']], $config['blocks']['fixture/teaser']['fields']['title']);
    }

    public function testEditorScriptsWithoutABlockLoadAfterTheRuntimeAlsoWithoutBlocks(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', "'wp-blocks'");
        file_put_contents($this->build . '/vendor/wp-app-framework/editor.css', '');
        mkdir($this->build . '/assets/shared', 0777, true);
        $this->writeAsset('assets/highlight.editor', "'my-theme-wp-app-framework', 'my-theme-shared-format'");
        $this->writeAsset('assets/shared/format', "'wp-rich-text'");

        new EditorAssets('my-theme', $this->build, 'https://example.test/build', [], true, 'fixture', null, ['assets/highlight.editor'], ['format' => 'assets/shared/format'])->enqueue();
        $scripts = Recorded::scripts();

        self::assertSame(['my-theme-wp-app-framework', 'my-theme-shared-format', 'my-theme-document-highlight-editor'], array_keys($scripts));
        self::assertSame('https://example.test/build/assets/highlight.editor.js', $scripts['my-theme-document-highlight-editor']['src']);
        self::assertTrue($scripts['my-theme-document-highlight-editor']['enqueued']);
        self::assertSame('https://example.test/build/assets/shared/format.js', $scripts['my-theme-shared-format']['src']);
        self::assertSame(['wp-rich-text'], $scripts['my-theme-shared-format']['deps']);
        self::assertFalse($scripts['my-theme-shared-format']['enqueued'], 'Registered as dependency only.');
    }

    public function testViewDataIsPrintedAfterTheConfiguration(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', '');
        file_put_contents($this->build . '/vendor/wp-app-framework/editor.css', '');
        $this->writeAsset('Container.editor', '');
        $data = new ViewData('my-theme', true);
        $data->set('map', ['zoom' => 3]);

        $assets = $this->assets();
        $assets->enqueue();
        $assets->printData($data);

        $before = Recorded::scripts()['my-theme-wp-app-framework']['before'];
        self::assertCount(2, $before);
        self::assertStringEndsWith('.data = {"map":{"zoom":3}};', $before[1]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('(with the editor runtime on enqueue_block_editor_assets)');
        $data->set('late', true);
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

        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].config = ';
        /** @var array{blocks: array<string, array{supports: list<string>}>} $config */
        $config = json_decode(substr($assets->configScript(), strlen($prefix), -strlen(';')), true, 512, JSON_THROW_ON_ERROR);
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

        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].config = ';
        /** @var array{previewPath: string, blocks: array<string, array{view: bool}>} $config */
        $config = json_decode(substr($assets->configScript(), strlen($prefix), -strlen(';')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('/wp-app-framework/v1/my-theme/preview', $config['previewPath']);
        self::assertSame(
            ['fixture/bare' => true, 'fixture/faq' => true, 'fixture/note' => true, 'fixture/plain' => false],
            array_map(static fn(array $block): bool => $block['view'], $config['blocks']),
        );
    }
}
