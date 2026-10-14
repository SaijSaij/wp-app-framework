<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Style;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Style\StyleAssets;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Recorded;

final class StyleAssetsTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wp-app-framework-styles-' . bin2hex(random_bytes(4));
        mkdir($this->build);
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        unset(
            $GLOBALS['wp_app_framework_test_styles'],
            $GLOBALS['wp_app_framework_test_admin'],
            $GLOBALS['wp_app_framework_test_block_editor_screen'],
            $GLOBALS['wp_app_framework_test_filters'],
            $GLOBALS['wp_app_framework_test_filter_args'],
        );
    }

    private function assets(): StyleAssets
    {
        return new StyleAssets('my-theme', $this->build, 'https://example.test/build');
    }

    private function writeBuild(): void
    {
        file_put_contents($this->build . '/styles.css', '');
        file_put_contents($this->build . '/styles.asset.php', "<?php return ['dependencies' => [], 'version' => 'v1'];");
    }

    public function testLoadsTheBuiltStylesheetOnTheFrontEnd(): void
    {
        $this->writeBuild();

        $this->assets()->enqueue();

        self::assertSame(
            ['my-theme-styles' => ['src' => 'https://example.test/build/styles.css', 'deps' => [], 'ver' => 'v1', 'enqueued' => true]],
            Recorded::styles(),
            'Without right-to-left replacement.',
        );
    }

    public function testLoadsTheStylesheetForTheEditorCanvasButNotForTheEditorDocument(): void
    {
        $this->writeBuild();
        $GLOBALS['wp_app_framework_test_admin'] = true;
        $GLOBALS['wp_app_framework_test_block_editor_screen'] = true;

        $this->assets()->enqueue();
        self::assertSame([], Recorded::styles(), 'Not in the main document of the editor.');

        // WordPress collects the assets of the editor iframes with this filter (_wp_get_iframed_editor_assets()).
        add_filter('should_load_block_editor_scripts_and_styles', static fn(): bool => false);
        $this->assets()->enqueue();
        self::assertSame(
            ['my-theme-styles' => ['src' => 'https://example.test/build/styles.css', 'deps' => [], 'ver' => 'v1', 'enqueued' => true]],
            Recorded::styles(),
        );
    }

    public function testMissingBuildIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The styles of assets/styles.css are not built: %s/styles.css is missing. Run "npm run build" (or "npm run start") in the theme or plugin my-theme.',
            $this->build,
        ));

        $this->assets()->enqueue();
    }

    public function testMissingAssetFileIsAnError(): void
    {
        file_put_contents($this->build . '/styles.css', '');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s/styles.asset.php is missing.', $this->build));

        $this->assets()->enqueue();
    }
}
