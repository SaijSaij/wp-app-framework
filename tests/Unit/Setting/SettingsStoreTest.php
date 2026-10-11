<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Setting;

use Closure;
use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Setting\SettingsAccessor;
use Saij\WpAppFramework\Setting\SettingsStore;
use Saij\WpAppFramework\Tests\Fixtures\SettingsValid;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Settings at runtime: registration, group objects from the stored values, injection and the template global.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class SettingsStoreTest extends TestCase
{
    private string $log;
    private string|false $previousLog;

    /** @var ManifestData */
    private array $manifest;

    protected function setUp(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(SettingsCollectorTest::buildValid()));
        $this->manifest = Manifest::load($file);
        unlink($file);

        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        Hooks::reset();
        unset($GLOBALS['wp_app_framework_test_settings'], $GLOBALS['wp_app_framework_test_options']);
    }

    private function store(): SettingsStore
    {
        return new SettingsStore('my-theme', $this->manifest['settings']['groups'], new ValueFactory($this->manifest['values']));
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function storeOption(string $option, array|string $value): void
    {
        $GLOBALS['wp_app_framework_test_options'] = [...(array) ($GLOBALS['wp_app_framework_test_options'] ?? []), $option => $value];
    }

    public function testOptionNamesUseTheSlugWithUnderscores(): void
    {
        self::assertSame('my_theme_general', SettingsStore::optionName('my-theme', 'general'));
    }

    public function testRegistersEachGroupAsOptionWithSchemaInTheRestApi(): void
    {
        $this->store()->register();

        $settings = Recorded::settings();
        self::assertSame(['my_theme_layout', 'my_theme_general'], array_keys($settings));
        $general = $settings['my_theme_general'];
        self::assertSame('my-theme', $general['group']);
        self::assertSame('object', $general['type']);
        self::assertSame('General', $general['label']);
        self::assertSame('Main settings.', $general['description']);
        self::assertSame(['tagline' => '', 'density' => 'normal', 'contactUrl' => '', 'count' => 3, 'ratio' => null, 'links' => [], 'secret' => 'x', 'logo' => null, 'footer' => '', 'notice' => '', 'privacyPage' => null, 'featured' => []], $general['default']);
        self::assertSame(['name' => 'my_theme_general', 'schema' => $this->manifest['settings']['groups'][1]['schema']], $general['show_in_rest']);
    }

    public function testSavedValuesAreCompletedWithDefaults(): void
    {
        $this->store()->register();
        /** @var Closure(mixed): array<string, mixed> $sanitize */
        $sanitize = Recorded::settings()['my_theme_layout']['sanitize_callback'];

        self::assertSame(['wide' => false], $sanitize([]));
        self::assertSame(['wide' => true], $sanitize(['wide' => true]));
        self::assertSame(['wide' => false], $sanitize('junk'));
        self::assertStringContainsString('wp-app-framework: settings: the option my_theme_layout was saved with string instead of an object; the defaults are stored instead.', (string) file_get_contents($this->log));
    }

    public function testRichTextOfUsersWithoutUnfilteredHtmlIsFiltered(): void
    {
        $this->store()->register();
        /** @var Closure(mixed): array<string, mixed> $sanitize */
        $sanitize = Recorded::settings()['my_theme_general']['sanitize_callback'];
        $html = '<b>Hi</b><script>alert(1)</script>';

        self::assertSame('<b>Hi</b>', $sanitize(['notice' => $html])['notice']);
        // Plain text is escaped on output, not filtered.
        self::assertSame($html, $sanitize(['footer' => $html])['footer']);

        $GLOBALS['wp_app_framework_test_caps'] = ['unfiltered_html'];
        try {
            self::assertSame($html, $sanitize(['notice' => $html])['notice']);
        } finally {
            unset($GLOBALS['wp_app_framework_test_caps']);
        }
    }

    public function testGroupsUseTheirDefaultsUntilSaved(): void
    {
        $general = $this->store()->get(SettingsValid\General::class);

        self::assertSame('', $general->tagline);
        self::assertSame(SettingsValid\Density::Normal, $general->density);
        self::assertSame(3, $general->count);
        self::assertNull($general->ratio);
        self::assertSame('', (string) file_get_contents($this->log));
    }

    public function testGroupsAreCreatedFromTheStoredValues(): void
    {
        self::storeOption('my_theme_general', [
            'tagline' => 'Hello',
            'density' => 'compact',
            'count' => '7',
            'ratio' => 1.5,
            'links' => [['label' => 'Docs', 'url' => 'https://example.com', 'icon' => 7]],
            'logo' => '12',
        ]);

        $general = $this->store()->get(SettingsValid\General::class);

        self::assertSame('Hello', $general->tagline);
        self::assertSame(SettingsValid\Density::Compact, $general->density);
        // Sanitized against the schema.
        self::assertSame(7, $general->count);
        self::assertSame(1.5, $general->ratio);
        self::assertEquals([new SettingsValid\Link('Docs', 'https://example.com', 7)], $general->links);
        self::assertSame(12, $general->logo);
        self::assertSame('x', $general->secret);
    }

    public function testInvalidStoredValuesFallBackToTheirDefaultAndAreLogged(): void
    {
        self::storeOption('my_theme_general', ['tagline' => 'Kept', 'density' => 'removed']);

        $general = $this->store()->get(SettingsValid\General::class);

        self::assertSame('Kept', $general->tagline);
        self::assertSame(SettingsValid\Density::Normal, $general->density);
        self::assertStringContainsString(
            'wp-app-framework: settings: the stored value of my_theme_general[density] is invalid (my_theme_general[density] is not one of compact, normal.); the setting uses its default.',
            (string) file_get_contents($this->log),
        );
    }

    public function testAnOptionThatIsNoObjectIsLogged(): void
    {
        self::storeOption('my_theme_layout', 'junk');

        self::assertFalse($this->store()->get(SettingsValid\Layout::class)->wide);
        self::assertStringContainsString('the option my_theme_layout holds string instead of an object; all settings of the group use their defaults.', (string) file_get_contents($this->log));
    }

    public function testGroupObjectsAreKeptUntilTheOptionChanges(): void
    {
        $store = $this->store();
        $store->hook();
        self::storeOption('my_theme_layout', ['wide' => true]);
        $layout = $store->get(SettingsValid\Layout::class);

        self::assertSame($layout, $store->get(SettingsValid\Layout::class));

        foreach (['add_option_my_theme_layout', 'update_option_my_theme_layout', 'delete_option_my_theme_layout'] as $action) {
            self::storeOption('my_theme_layout', ['wide' => !$layout->wide]);
            do_action($action);
            $changed = $store->get(SettingsValid\Layout::class);
            self::assertNotSame($layout, $changed, $action);
            self::assertSame(!$layout->wide, $changed->wide, $action);
            $layout = $changed;
        }
    }

    public function testRegistersOnInit(): void
    {
        $this->store()->hook();
        self::assertSame([], Recorded::settings());

        do_action('init');
        self::assertArrayHasKey('my_theme_general', Recorded::settings());
    }

    public function testUnknownGroupClassIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s is no settings group of the my-theme instance; rebuild the manifest after code changes.', SettingsValid\Link::class));

        $this->store()->get(SettingsValid\Link::class);
    }

    public function testServicesReceiveTheGroupsThroughTheirConstructor(): void
    {
        self::storeOption('my_theme_general', ['tagline' => 'Hello']);
        self::storeOption('my_theme_layout', ['wide' => true]);
        $container = new Container($this->manifest['services'], [], [], $this->store());

        self::assertSame('Banner: Hello wide', $container->get(SettingsValid\Banner::class)->text('Banner:'));
    }

    public function testContainerWithoutSettingsReportsAStaleManifest(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The manifest injects a settings group, but the instance has no settings; rebuild the manifest after code changes.');

        new Container($this->manifest['services'])->get(SettingsValid\Banner::class);
    }

    public function testTemplatesReadTheGroupsThroughTheGlobal(): void
    {
        self::storeOption('my_theme_general', ['tagline' => '<b>Hi</b>', 'density' => 'compact']);
        $cache = sys_get_temp_dir() . '/wpaf-settings-twig-' . bin2hex(random_bytes(4));
        $fixture = ManifestBuilderTest::fixture('SettingsValid')->getDirectory();
        $store = $this->store();

        try {
            $renderer = new TemplateRenderer(
                'my-theme',
                $fixture,
                [],
                $fixture,
                'theme',
                dirname($fixture) . '/Templates/framework/src',
                $cache,
                true,
                [],
                [],
                new Container([]),
                ['settings' => new SettingsAccessor($store)],
            );

            self::assertSame("&lt;b&gt;Hi&lt;/b&gt;|compact|narrow\n", $renderer->render('Settings.twig'));
        } finally {
            Files::remove($cache);
        }
    }

    public function testTheGlobalIsReadOnlyAndKnowsOnlyTheGroupsOfTheInstance(): void
    {
        $accessor = new SettingsAccessor($this->store());

        self::assertTrue(isset($accessor['general']));
        self::assertFalse(isset($accessor['other']));
        self::assertInstanceOf(SettingsValid\Layout::class, $accessor['layout']);

        try {
            $accessor->offsetGet('other');
            self::fail('LogicException expected.');
        } catch (LogicException $exception) {
            self::assertSame('There is no settings group "other"; the groups are: layout, general.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Settings are read-only in templates.');
        $accessor['general'] = new SettingsValid\Layout();
    }
}
