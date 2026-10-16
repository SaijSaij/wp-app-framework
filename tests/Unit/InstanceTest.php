<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\Instance;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\ApiValid\NoteApi;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Unit\Api\PublicApiBuildTest;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
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
        unset($GLOBALS['wp_app_framework_test_styles'], $GLOBALS['wp_app_framework_test_cron'], $GLOBALS['wp_app_framework_test_deactivation'], $GLOBALS['wp_app_framework_test_uninstall'], $GLOBALS['wp_app_framework_test_options'], $GLOBALS['wp_app_framework_test_cron_writes']);
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

    private function writeReleaseManifest(bool $styles = false, ?BuildResult $build = null): void
    {
        mkdir($this->root . '/my-plugin/generated');
        $code = new ManifestGenerator()->generate($build ?? PublicApiBuildTest::buildValid(), 'abc123');
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

    public function testSettingsChangesInvalidateTheTagOfTheirGroupByTheOptionOfTheNamespace(): void
    {
        $this->writeReleaseManifest(build: new ManifestBuilder(
            [ManifestBuilderTest::fixture('SettingsValid'), ManifestBuilder::frameworkSource()],
            kind: ProjectKind::Plugin,
            slug: 'my-plugin',
            name: 'My Plugin',
            namespace: 'my-namespace',
        )->build());
        $this->instance(ProjectKind::Plugin)->boot();

        // The option is named after the namespace, not after the slug my-plugin: the settings store forgets the
        // group object, and the cache invalidates the tag of the group.
        self::assertCount(2, Recorded::listFilters('update_option_my_namespace_general'));
        self::assertSame([], Recorded::listFilters('update_option_my_plugin_general'));
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

        // Only the cron registrar's callbacks: init also resolves services of the fixture that need other plugins.
        foreach (Recorded::listFilters('init') as $callback) {
            if ($callback instanceof \Closure && new \ReflectionFunction($callback)->getClosureScopeClass()?->getName() === CronRegistrar::class) {
                $callback();
            }
        }
        $scheduled = wp_get_scheduled_event($event);
        self::assertSame('daily', $scheduled === false ? null : $scheduled->schedule);

        $hooks = $GLOBALS['wp_app_framework_test_deactivation'] ?? [];
        $deactivate = is_array($hooks) ? $hooks[$this->root . '/my-plugin/my-plugin.php'] ?? null : null;
        self::assertIsCallable($deactivate);
        $deactivate();
        self::assertFalse(wp_next_scheduled($event));
        self::assertFalse(get_option('my_plugin_cron'));
    }

    public function testTheCleanUpEventRemovesCompiledTemplatesOfOlderReleases(): void
    {
        $twig = WP_CONTENT_DIR . '/cache/my-plugin/twig';
        foreach (['abc123/ab', '1111111111111111/ab'] as $directory) {
            mkdir($twig . '/' . $directory, 0777, true);
            file_put_contents($twig . '/' . $directory . '/compiled.php', '<?php');
        }
        $this->writeReleaseManifest();
        $this->instance(ProjectKind::Plugin)->boot();

        try {
            do_action(Instance::cacheCleanUpEvent('my-plugin'));

            self::assertFileExists($twig . '/abc123/ab/compiled.php');
            self::assertDirectoryDoesNotExist($twig . '/1111111111111111');
        } finally {
            Files::remove(WP_CONTENT_DIR . '/cache/my-plugin');
        }
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
        $twig = (string) realpath(dirname((string) new \ReflectionClass(\Twig\Environment::class)->getFileName(), 2));
        $this->expectExceptionMessage('The plugin my-plugin requires Twig v1.0.0, but Twig ' . \Twig\Environment::VERSION . ' is loaded from ' . $twig . '. In development mode, whoever loads Twig first provides it for all consumers – another consumer, or the root vendor/ of a Bedrock site;');

        $this->instance(ProjectKind::Plugin)->boot();
    }

    public function testAnUninstallScriptOfAPluginIsAnErrorInDevelopmentMode(): void
    {
        mkdir($this->root . '/my-plugin/vendor/saij', 0777, true);
        mkdir($this->root . '/my-plugin/vendor/composer');
        symlink((string) realpath(__DIR__ . '/../..'), $this->root . '/my-plugin/vendor/saij/wp-app-framework');
        file_put_contents(
            $this->root . '/my-plugin/vendor/composer/installed.php',
            "<?php return ['versions' => ['twig/twig' => ['pretty_version' => 'v" . \Twig\Environment::VERSION . "']]];",
        );
        // The cached manifest of development mode would not notice the file.
        touch($this->root . '/my-plugin/uninstall.php');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($this->root . '/my-plugin/uninstall.php exists: WordPress runs it instead of the uninstall hook of the framework');

        $this->instance(ProjectKind::Plugin)->boot();
    }
}
