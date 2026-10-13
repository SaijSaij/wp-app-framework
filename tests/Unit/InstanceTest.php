<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Instance;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\ApiValid\NoteApi;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Unit\Api\PublicApiBuildTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateCallableKind;

/**
 * Boot of a framework instance: checks before anything is registered, and the
 * hooks a booted plugin provides.
 */
final class InstanceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-instance-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/my-plugin', 0777, true);
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        Files::remove($this->root);
        unset($GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_cron'], $GLOBALS['wp_app_framework_test_deactivation']);
    }

    private function instance(ProjectKind $kind): Instance
    {
        $consumer = new ReflectionClass($kind === ProjectKind::Plugin ? Plugin::class : Theme::class)->newInstanceWithoutConstructor();

        return new Instance(
            $kind,
            'my-plugin',
            $this->root . '/my-plugin',
            'https://example.test/wp-content/plugins/my-plugin',
            $consumer,
            static fn(): array => ['overrides' => [], 'cacheKey' => null],
        );
    }

    private function writeReleaseManifest(bool $styles = false): void
    {
        mkdir($this->root . '/my-plugin/generated');
        $code = new ManifestGenerator()->generate(PublicApiBuildTest::buildValid(), 'abc123');
        if ($styles) {
            $code = str_replace("'styles' => false,", "'styles' => true,", $code);
        }
        file_put_contents($this->root . '/my-plugin/' . Instance::RELEASE_MANIFEST, $code);
    }

    public function testASlugBootsOnlyOnce(): void
    {
        do_action('wp_app_framework/init/my-plugin');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A framework instance with the slug "my-plugin" has already booted; the slugs of the theme and of all plugins using the framework must differ');

        $this->instance(ProjectKind::Plugin)->boot();
    }

    public function testANamespaceBootsOnlyOnce(): void
    {
        $this->writeReleaseManifest();
        // Another theme or plugin booted with the namespace of the manifest.
        do_action('wp_app_framework/namespace/fixture');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The plugin my-plugin uses the namespace "fixture" (extra.wp-app-framework.namespace in its composer.json), but another theme or plugin using the framework has already booted with it; each needs a namespace of its own.');

        $this->instance(ProjectKind::Plugin)->boot();
    }

    public function testReleaseBootProvidesThePublicApiAndFiresTheInitAction(): void
    {
        $this->writeReleaseManifest();

        $this->instance(ProjectKind::Plugin)->boot();
        do_action('plugins_loaded');

        self::assertSame(1, did_action('wp_app_framework/init/my-plugin'));
        self::assertSame(1, did_action('wp_app_framework/namespace/fixture'));
        self::assertInstanceOf(NoteApi::class, InstanceBridge::api(ProjectKind::Plugin, 'my-plugin', NoteApi::class));
        self::assertSame('2', (InstanceBridge::templateCallable('my-plugin', TemplateCallableKind::Function, 'my_plugin_note_count')['callable'])());
    }

    public function testLoadsTheStylesheetOnlyIfTheManifestHasOne(): void
    {
        $this->writeReleaseManifest();
        $this->instance(ProjectKind::Plugin)->boot();
        do_action('enqueue_block_assets');
        self::assertSame([], Recorded::styles());

        Hooks::reset();
        Files::remove($this->root . '/my-plugin/generated');
        $this->writeReleaseManifest(styles: true);
        mkdir($this->root . '/my-plugin/build');
        file_put_contents($this->root . '/my-plugin/build/styles.css', '');
        file_put_contents($this->root . '/my-plugin/build/styles.asset.php', "<?php return ['dependencies' => [], 'version' => 'v1'];");
        $this->instance(ProjectKind::Plugin)->boot();
        do_action('enqueue_block_assets');

        self::assertSame(
            ['my-plugin-styles' => ['src' => 'https://example.test/wp-content/plugins/my-plugin/build/styles.css', 'deps' => [], 'ver' => 'v1', 'enqueued' => true]],
            Recorded::styles(),
        );
    }

    public function testSchedulesTheCleanUpOfCacheFilesUntilThePluginIsDeactivated(): void
    {
        $this->writeReleaseManifest();
        $this->instance(ProjectKind::Plugin)->boot();
        $event = Instance::cacheCleanUpEvent('my-plugin');

        // Only the instance's own callbacks: init also resolves services of the fixture that need other plugins.
        foreach (Recorded::listFilters('init') as $callback) {
            if ($callback instanceof \Closure && new \ReflectionFunction($callback)->getClosureScopeClass()?->getName() === Instance::class) {
                $callback();
            }
        }
        $cron = $GLOBALS['wp_app_framework_test_cron'] ?? [];
        self::assertSame('daily', is_array($cron) && is_array($cron[$event] ?? null) ? $cron[$event][1] ?? null : null);

        $hooks = $GLOBALS['wp_app_framework_test_deactivation'] ?? [];
        $deactivate = is_array($hooks) ? $hooks[$this->root . '/my-plugin/my-plugin.php'] ?? null : null;
        self::assertIsCallable($deactivate);
        $deactivate();
        self::assertFalse(wp_next_scheduled($event));
    }

    public function testManifestOfAnotherKindIsAnError(): void
    {
        $this->writeReleaseManifest();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The manifest %s/my-plugin/generated/manifest.php was built for the plugin "my-plugin", but the theme "my-plugin" boots with it.',
            $this->root,
        ));

        $this->instance(ProjectKind::Theme)->boot();
    }

    public function testDevelopmentModeNeedsTheOwnFramework(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The plugin my-plugin runs in development mode with the framework classes from %s, but its own framework is %s/my-plugin/vendor/saij/wp-app-framework (missing).',
            realpath(__DIR__ . '/../..'),
            $this->root,
        ));

        $this->instance(ProjectKind::Plugin)->boot();
    }

    public function testDevelopmentModeNeedsTheLoadedTwigVersion(): void
    {
        mkdir($this->root . '/my-plugin/vendor/saij', 0777, true);
        mkdir($this->root . '/my-plugin/vendor/composer');
        symlink((string) realpath(__DIR__ . '/../..'), $this->root . '/my-plugin/vendor/saij/wp-app-framework');
        file_put_contents(
            $this->root . '/my-plugin/vendor/composer/installed.php',
            "<?php return ['versions' => ['twig/twig' => ['pretty_version' => 'v1.0.0']]];",
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The plugin my-plugin requires Twig v1.0.0, but Twig ' . \Twig\Environment::VERSION . ' is loaded (from another consumer).');

        $this->instance(ProjectKind::Plugin)->boot();
    }
}
