<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Editor;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;

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
        foreach (['vendor/wp-app-framework/editor.asset.php', 'vendor/wp-app-framework/editor.css', 'Container.editor.asset.php'] as $file) {
            if (is_file($this->build . '/' . $file)) {
                unlink($this->build . '/' . $file);
            }
        }
        rmdir($this->build . '/vendor/wp-app-framework');
        rmdir($this->build . '/vendor');
        rmdir($this->build);
        unset($GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_styles']);
    }

    private function assets(): EditorAssets
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(EditorBuildTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        return new EditorAssets('my-theme', $this->build, 'https://example.test/build', $manifest['blocks'], true);
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
        new EditorAssets('my-theme', $this->build, 'https://example.test/build', [], true)->enqueue();

        self::assertArrayNotHasKey('wp_app_framework_test_scripts', $GLOBALS);
    }

    public function testMissingBuildIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The editor script vendor/wp-app-framework/editor is not built: %s/vendor/wp-app-framework/editor.asset.php is missing. Run "npm run build" (or "npm run start") in the theme.',
            $this->build,
        ));

        $this->assets()->enqueue();
    }

    public function testMissingStylesAreAnError(): void
    {
        $this->writeAsset('vendor/wp-app-framework/editor', '');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The styles of the editor runtime are not built: %s/vendor/wp-app-framework/editor.css is missing.',
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
        $scripts = $GLOBALS['wp_app_framework_test_scripts'];

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
            $GLOBALS['wp_app_framework_test_styles'],
        );

        [$before] = $scripts['my-theme-wp-app-framework']['before'];
        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = { config: ';
        self::assertStringStartsWith($prefix, $before);
        $config = json_decode(substr($before, strlen($prefix), -strlen(' };')), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('my-theme', $config['slug']);
        self::assertSame('/wp-app-framework/v1/my-theme/render', $config['renderPath']);
        self::assertSame(100, $config['maxBlocks']);
        self::assertTrue($config['development']);
        self::assertSame(['fixture/container', 'fixture/teaser', 'fixture/wrong-field'], array_keys($config['blocks']));
        self::assertSame(['fields' => [], 'controls' => [], 'innerBlocks' => true], $config['blocks']['fixture/container']);
        self::assertSame(['kind' => 'rich', 'label' => 'Title', 'allowedFormats' => ['core/bold']], $config['blocks']['fixture/teaser']['fields']['title']);
    }
}
