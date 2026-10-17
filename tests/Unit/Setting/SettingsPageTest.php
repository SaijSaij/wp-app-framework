<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Setting;

use Closure;
use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Setting\SettingsPage;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;

/**
 * The settings page of an instance: menu entry, script, styles and configuration.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class SettingsPageTest extends TestCase
{
    private string $build;

    /** @var ManifestData */
    private array $manifest;

    protected function setUp(): void
    {
        $this->build = sys_get_temp_dir() . '/wp-app-framework-build-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);

        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(SettingsCollectorTest::buildValid()));
        $this->manifest = Manifest::load($file);
        unlink($file);
    }

    protected function tearDown(): void
    {
        Files::remove($this->build);
        unset($GLOBALS['wp_app_framework_test_scripts'], $GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_pages'], $GLOBALS['wp_app_framework_test_media'], $GLOBALS['wp_app_framework_test_editor'], $GLOBALS['wp_app_framework_test_global_settings']);
    }

    private function page(ProjectKind $kind = ProjectKind::Theme): SettingsPage
    {
        return new SettingsPage(
            'my-theme',
            $kind,
            (string) $this->manifest['settings']['title'],
            $this->manifest['settings']['groups'],
            $this->build,
            'https://example.test/build',
        );
    }

    private function writeBuild(): void
    {
        file_put_contents($this->build . '/vendor/wp-app-framework/settings.asset.php', "<?php return ['dependencies' => ['wp-components', 'wp-core-data'], 'version' => 'v1'];");
        file_put_contents($this->build . '/vendor/wp-app-framework/settings.css', '');
    }

    public function testThemesGetOnePageUnderAppearance(): void
    {
        $this->page()->addPage();

        $page = Recorded::pages()['my-theme-settings'];
        self::assertSame(['themes.php', 'Fixture Theme', 'Fixture Theme', 'manage_options'], [$page['parent'], $page['title'], $page['menu'], $page['capability']]);
        self::assertInstanceOf(Closure::class, $page['callback']);

        ob_start();
        $page['callback']();
        self::assertSame('<div class="wrap"><h1>Fixture Theme</h1><div id="my-theme-settings"></div></div>', ob_get_clean());
    }

    public function testPluginsGetOnePageUnderSettings(): void
    {
        $this->page(ProjectKind::Plugin)->addPage();

        self::assertSame('options-general.php', Recorded::pages()['my-theme-settings']['parent']);
    }

    public function testScriptAndStylesLoadOnTheSettingsPageOnly(): void
    {
        $this->writeBuild();
        $GLOBALS['wp_app_framework_test_global_settings'] = ['color.palette' => ['theme' => [['name' => 'Primary', 'slug' => 'primary', 'color' => '#1e4d8c']]]];
        $page = $this->page();

        // Before the menu exists and on other pages, nothing is loaded.
        $page->enqueue('appearance_page_my-theme-settings');
        $page->addPage();
        $page->enqueue('index.php');
        self::assertSame([], Recorded::scripts());

        $page->enqueue('appearance_page_my-theme-settings');
        $script = Recorded::scripts()['my-theme-wp-app-framework-settings'];
        self::assertSame('https://example.test/build/vendor/wp-app-framework/settings.js', $script['src']);
        self::assertSame(['wp-components', 'wp-core-data'], $script['deps']);
        self::assertTrue($script['enqueued']);
        // The fixture has a color setting: the hex colors of the palette follow the configuration.
        self::assertSame(
            [
                $page->configScript(),
                'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].palette = [{"name":"Primary","color":"#1e4d8c"}];',
            ],
            $script['before'],
        );

        // The fixture has image and rich text settings: media library and classic editor.
        self::assertSame(1, $GLOBALS['wp_app_framework_test_media'] ?? 0);
        self::assertSame(1, $GLOBALS['wp_app_framework_test_editor'] ?? 0);

        $style = Recorded::styles()['my-theme-wp-app-framework-settings'];
        self::assertSame(['wp-components'], $style['deps']);
        self::assertSame('replace', $style['rtl']);
        self::assertTrue($style['enqueued']);
    }

    public function testConfigurationListsTheGroupsInOrder(): void
    {
        $script = $this->page()->configScript();
        $prefix = 'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework["my-theme"] = window.wpAppFramework["my-theme"] || {}; window.wpAppFramework["my-theme"].settings = ';
        self::assertStringStartsWith($prefix, $script);

        /** @var array{root: string, groups: list<array{name: string, option: string, default: array<string, mixed>, controls: array<string, mixed>}>} $config */
        $config = json_decode(substr($script, strlen($prefix), -1), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('my-theme-settings', $config['root']);
        self::assertSame(['layout', 'general'], array_column($config['groups'], 'name'));
        self::assertSame(['my_theme_layout', 'my_theme_general'], array_column($config['groups'], 'option'));
        self::assertSame(['wide' => false], $config['groups'][0]['default']);
        self::assertSame(['type' => 'bool', 'label' => 'Wide', 'nullable' => false], $config['groups'][0]['controls']['wide']);
    }

    public function testMissingBuildIsAnError(): void
    {
        $page = $this->page();
        $page->addPage();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The script vendor/wp-app-framework/settings is not built: %s/vendor/wp-app-framework/settings.asset.php is missing. Run "npm run build" (or "npm run start") in the theme or plugin my-theme.',
            $this->build,
        ));
        $page->enqueue('appearance_page_my-theme-settings');
    }
}
