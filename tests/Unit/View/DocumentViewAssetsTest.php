<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\View;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\View\ViewAssets;
use Saij\WpAppFramework\View\ViewData;

/**
 * Document views (assets/*.view.js), shared modules and the view data on the front end.
 */
final class DocumentViewAssetsTest extends TestCase
{
    private string $build;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wpaf-document-views-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);
        mkdir($this->build . '/assets/shared/ui', 0777, true);
        mkdir($this->build . '/assets/nav', 0777, true);
        $this->writeAsset('vendor/wp-app-framework/view', '');
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        unset($GLOBALS['wp_app_framework_test_scripts']);
    }

    private function writeAsset(string $script, string $dependencies): void
    {
        file_put_contents($this->build . '/' . $script . '.asset.php', "<?php return ['dependencies' => [{$dependencies}], 'version' => 'v1'];");
    }

    private function views(): ViewAssets
    {
        return new ViewAssets('my-theme', new BuildAssets('my-theme', $this->build, 'https://example.test/build', [
            'counter' => 'assets/shared/counter',
            'ui/modal' => 'assets/shared/ui/modal',
            'unused' => 'assets/shared/unused',
        ]));
    }

    public function testDocumentViewsLoadDeferredInTheFooterWithTheRuntimeAndTheirSharedModules(): void
    {
        $this->writeAsset('assets/menu.view', "'my-theme-wp-app-framework-view', 'my-theme-shared-counter'");
        $this->writeAsset('assets/nav/sticky.view', "'my-theme-wp-app-framework-view'");
        $this->writeAsset('assets/shared/counter', "'my-theme-shared-ui-modal'");
        $this->writeAsset('assets/shared/ui/modal', '');

        $this->views()->enqueueDocumentViews(['assets/menu.view', 'assets/nav/sticky.view']);
        $scripts = Recorded::scripts();

        self::assertSame(
            ['my-theme-wp-app-framework-view', 'my-theme-shared-ui-modal', 'my-theme-shared-counter', 'my-theme-document-menu-view', 'my-theme-document-nav-sticky-view'],
            array_keys($scripts),
            'Shared modules are registered before the scripts that need them; unused ones are not.',
        );
        foreach (['my-theme-wp-app-framework-view', 'my-theme-document-menu-view', 'my-theme-document-nav-sticky-view'] as $handle) {
            self::assertTrue($scripts[$handle]['enqueued'], $handle);
            self::assertSame(['in_footer' => true, 'strategy' => 'defer'], $scripts[$handle]['args'], $handle);
        }
        self::assertFalse($scripts['my-theme-shared-counter']['enqueued']);
        self::assertSame(['in_footer' => true, 'strategy' => 'defer'], $scripts['my-theme-shared-counter']['args']);
        self::assertSame('https://example.test/build/assets/shared/ui/modal.js', $scripts['my-theme-shared-ui-modal']['src']);
        self::assertSame(
            ['window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].documentViews = ["assets/menu.view","assets/nav/sticky.view"];'],
            $scripts['my-theme-wp-app-framework-view']['before'],
        );
    }

    public function testAMissingBuildOfADocumentViewIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The script assets/menu.view is not built');

        $this->views()->enqueueDocumentViews(['assets/menu.view']);
    }

    public function testViewDataIsPrintedBeforeTheRuntimeOnlyIfItIsEnqueued(): void
    {
        $data = new ViewData('my-theme', true);
        $data->set('menu', ['open' => false]);
        $this->views()->printData($data);
        self::assertSame([], Recorded::scripts(), 'Without views nothing reads the data.');

        $this->writeAsset('assets/menu.view', "'my-theme-wp-app-framework-view'");
        $data = new ViewData('my-theme', true);
        $data->set('menu', ['open' => false]);
        $views = $this->views();
        $views->enqueueDocumentViews(['assets/menu.view']);
        $views->printData($data);

        $before = Recorded::scripts()['my-theme-wp-app-framework-view']['before'];
        self::assertStringEndsWith('.data = {"menu":{"open":false}};', $before[1]);
    }

    public function testViewDataIsNotPrintedInTheAdmin(): void
    {
        // The block editor runs wp_print_footer_scripts to collect the assets of its canvas.
        $GLOBALS['wp_app_framework_test_admin'] = true;
        $data = new ViewData('my-theme', true);
        $this->views()->printData($data);
        unset($GLOBALS['wp_app_framework_test_admin']);

        $data->set('editor', true);
        self::assertTrue($data->has('editor'), 'The editor runtime prints the data later.');
    }
}
