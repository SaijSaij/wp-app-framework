<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Lifecycle;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Lifecycle\LifecycleRegistrar;
use Saij\WpAppFramework\Lifecycle\Uninstaller;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Tests\Fixtures\LifecycleValid\Journal;
use Saij\WpAppFramework\Tests\Support\Database;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Support\Tables;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Site;

/**
 * When the methods of the consumer and the framework's own work run: activation, deactivation and uninstallation of
 * plugins, themes and child themes, on one site and on every site of a network.
 */
final class LifecycleRegistrarTest extends TestCase
{
    private const string FILE = '/plugins/my-theme/my-theme.php';
    private const string PLUGIN = 'my-theme/my-theme.php';
    private const string CLEAN_UP = 'wp_app_framework/cache/clean_up/my-theme';

    private Container $container;

    protected function setUp(): void
    {
        Database::use();
        // Other tests leave registered post types and lifecycle hooks behind.
        unset(
            $GLOBALS['wp_app_framework_test_post_types'],
            $GLOBALS['wp_app_framework_test_taxonomies'],
            $GLOBALS['wp_app_framework_test_activation'],
            $GLOBALS['wp_app_framework_test_deactivation'],
            $GLOBALS['wp_app_framework_test_uninstall'],
        );
    }

    protected function tearDown(): void
    {
        // Also restores error_log(), which Tables::store() redirects.
        Tables::reset();
        PostRegistry::reset();
        Hooks::reset();
        unset(
            $GLOBALS['wp_app_framework_test_activation'],
            $GLOBALS['wp_app_framework_test_deactivation'],
            $GLOBALS['wp_app_framework_test_uninstall'],
            $GLOBALS['wp_app_framework_test_multisite'],
            $GLOBALS['wp_app_framework_test_sites'],
            $GLOBALS['wp_app_framework_test_site_queries'],
            $GLOBALS['wp_app_framework_test_site_options'],
            $GLOBALS['wp_app_framework_test_switches'],
            $GLOBALS['wp_app_framework_test_blog_stack'],
            $GLOBALS['wp_app_framework_test_post_types'],
            $GLOBALS['wp_app_framework_test_taxonomies'],
            $GLOBALS['wp_app_framework_test_cron'],
            $GLOBALS['wp_app_framework_test_cron_writes'],
            $GLOBALS['wp_app_framework_test_theme_parents'],
            $GLOBALS['wp_app_framework_test_deleted_meta_keys'],
        );
    }

    /**
     * A registered lifecycle for the instance my-theme with the methods of the fixture LifecycleValid; the cron of the
     * instance has its clean-up event scheduled.
     */
    private function register(ProjectKind $kind = ProjectKind::Plugin, bool $debug = true, bool $registered = true): LifecycleRegistrar
    {
        // Built as a plugin: themes have no #[OnUninstall], and the registrar of a theme never runs it.
        $result = new ManifestBuilder([ManifestBuilderTest::fixture('LifecycleValid')], kind: ProjectKind::Plugin)->build();
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate($result));
        $manifest = Manifest::load($file);
        unlink($file);

        $this->container = new Container($manifest['services'], []);
        $hooks = new HookRegistrar($this->container, $debug);
        [$posts] = Posts::store();
        $cron = new CronRegistrar('my-theme', [], [], $hooks, $this->container, new ValueFactory([]), $debug, static function (): void {});
        $cron->synchronize();
        $tables = Tables::store();
        $registrar = new LifecycleRegistrar(
            $kind,
            'my-theme',
            '/plugins/my-theme',
            $manifest['lifecycle'],
            $hooks,
            new PostTypeRegistrar($posts, 'my_theme_rewrite', null),
            $cron,
            $tables,
            new Uninstaller('my-theme', $posts, [], $tables, $cron, 'my_theme_rewrite', sys_get_temp_dir() . '/wpaf-lifecycle-missing'),
            $debug,
            $registered,
        );
        $registrar->register();

        return $registrar;
    }

    /**
     * @return list<string>
     */
    private function journal(): array
    {
        return $this->container->get(Journal::class)->getEntries();
    }

    private function hook(string $kind): callable
    {
        $hooks = $GLOBALS['wp_app_framework_test_' . $kind] ?? [];
        $hook = is_array($hooks) ? $hooks[self::FILE] ?? null : null;
        self::assertIsCallable($hook);

        return $hook;
    }

    public function testActivatingAPluginRunsTheMethodsAndResetsTheRewriteRules(): void
    {
        $this->register();
        update_option('my_theme_rewrite', 'hash');

        $this->hook('activation')(false);

        self::assertSame(['alpha on 1', 'install on 1'], $this->journal());
        self::assertFalse(get_option('my_theme_rewrite'));
        self::assertSame([self::FILE => [LifecycleRegistrar::class, 'uninstallPlugin']], $GLOBALS['wp_app_framework_test_uninstall'] ?? null);
    }

    public function testAPluginBootedAfterInitRegistersItsPostTypesBeforeTheMethodsRun(): void
    {
        $this->register(registered: false);
        self::assertSame([], Recorded::postTypes());

        $this->hook('activation')(false);

        self::assertContains('fixture_event', array_keys(Recorded::postTypes()));
    }

    public function testDeactivatingAPluginRunsTheMethodsThenClearsTheCron(): void
    {
        $this->register();
        self::assertNotFalse(wp_next_scheduled(self::CLEAN_UP));

        $this->hook('deactivation')(false);

        self::assertSame(['alpha on 1', 'pause on 1'], $this->journal());
        self::assertFalse(wp_next_scheduled(self::CLEAN_UP));
        self::assertFalse(get_option('my_theme_cron'));
        // Registered again: the plugin may have been updated since its activation.
        self::assertSame([self::FILE => [LifecycleRegistrar::class, 'uninstallPlugin']], $GLOBALS['wp_app_framework_test_uninstall'] ?? null);
    }

    public function testAFailingMethodIsLoggedAndTheCronIsClearedAnyway(): void
    {
        $this->register(debug: false);
        $this->container->get(Journal::class)->failOn('alpha');

        $this->hook('deactivation')(false);

        self::assertSame(['pause on 1'], $this->journal());
        self::assertFalse(wp_next_scheduled(self::CLEAN_UP));
        $log = implode("\n", Tables::log());
        self::assertStringContainsString(
            'wp-app-framework: action Saij\WpAppFramework\Tests\Fixtures\LifecycleValid\Alpha::both() on hook "deactivate_my-theme/my-theme.php" failed: RuntimeException: Writing "alpha" failed.',
            $log,
        );
    }

    public function testAFailingMethodIsRethrownWithDebugging(): void
    {
        $this->register();
        $this->container->get(Journal::class)->failOn('install');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Writing "install" failed.');

        $this->hook('activation')(false);
    }

    public function testNetworkWideActivationAndDeactivationRunOnEverySiteOfTheNetwork(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $GLOBALS['wp_app_framework_test_sites'] = [1 => 1, 2 => 1, 3 => 2];
        $this->register();

        $this->hook('activation')(true);
        $this->hook('deactivation')(true);

        self::assertSame(
            ['alpha on 1', 'install on 1', 'alpha on 2', 'install on 2', 'alpha on 1', 'pause on 1', 'alpha on 2', 'pause on 2'],
            $this->journal(),
        );
        self::assertSame([['fields' => 'ids', 'number' => 0, 'network_id' => 1], ['fields' => 'ids', 'number' => 0, 'network_id' => 1]], $GLOBALS['wp_app_framework_test_site_queries'] ?? null);
        self::assertSame(1, get_current_blog_id());
    }

    public function testActivationOnOneSiteOfANetworkRunsOnThatSiteOnly(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $GLOBALS['wp_app_framework_test_sites'] = [1 => 1, 2 => 1];
        $this->register();

        $this->hook('activation')(false);

        self::assertSame(['alpha on 1', 'install on 1'], $this->journal());
        self::assertArrayNotHasKey('wp_app_framework_test_switches', $GLOBALS);
    }

    public function testUninstallingAPluginRunsTheMethodsThenRemovesItsData(): void
    {
        $this->register(registered: false);
        Database::use()->answer('/^SELECT `(ID|term_id|option_name)` FROM /', []);
        update_option('my_theme_rewrite', 'hash');
        add_action('uninstall_' . self::PLUGIN, [LifecycleRegistrar::class, 'uninstallPlugin']);

        do_action('uninstall_' . self::PLUGIN);

        self::assertSame(['purge on 1'], $this->journal());
        self::assertContains('fixture_event', array_keys(Recorded::postTypes()));
        self::assertFalse(wp_next_scheduled(self::CLEAN_UP));
        self::assertFalse(get_option('my_theme_rewrite'));
        self::assertNotEmpty($GLOBALS['wp_app_framework_test_deleted_meta_keys'] ?? []);
    }

    public function testUninstallingRunsOnEverySiteOfEveryNetwork(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $GLOBALS['wp_app_framework_test_sites'] = [1 => 1, 2 => 1, 3 => 2];
        $this->register();
        Database::use()->answer('/^SELECT `(ID|term_id|option_name)` FROM /', []);
        add_action('uninstall_' . self::PLUGIN, [LifecycleRegistrar::class, 'uninstallPlugin']);

        do_action('uninstall_' . self::PLUGIN);

        self::assertSame(['purge on 1', 'purge on 2', 'purge on 3'], $this->journal());
        self::assertSame([['fields' => 'ids', 'number' => 0]], $GLOBALS['wp_app_framework_test_site_queries'] ?? null);
    }

    public function testTheUninstallHookOfAPluginThatDidNotBootIsAnError(): void
    {
        $this->register();
        add_action('uninstall_other/other.php', [LifecycleRegistrar::class, 'uninstallPlugin']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The uninstall hook of wp-app-framework runs on "uninstall_other/other.php", but no plugin with this main file has booted with the framework in this request.');

        do_action('uninstall_other/other.php');
    }

    public function testANewSiteIsActivatedWhileThePluginIsActiveNetworkWide(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $this->register();
        $site = new WP_Site((object) ['blog_id' => '5']);

        do_action('wp_initialize_site', $site);
        self::assertSame([], $this->journal());

        $GLOBALS['wp_app_framework_test_site_options'] = ['active_sitewide_plugins' => [self::PLUGIN => time()]];
        do_action('wp_initialize_site', $site);
        self::assertSame(['alpha on 5', 'install on 5'], $this->journal());
        self::assertSame(1, get_current_blog_id());
    }

    public function testTheTablesOfTheInstanceAreDroppedWithASite(): void
    {
        $GLOBALS['wp_app_framework_test_multisite'] = true;
        $this->register();

        self::assertSame(['wp_options', 'wp_dev_test_counter', 'wp_dev_test_log'], apply_filters('wpmu_drop_tables', ['wp_options'], 5));
    }

    public function testSingleSitesRegisterNoNetworkHooks(): void
    {
        $this->register();

        self::assertSame([], Recorded::listFilters('wp_initialize_site'));
        self::assertSame([], Recorded::listFilters('wpmu_drop_tables'));
    }

    public function testSwitchingToTheThemeActivatesIt(): void
    {
        $this->register(ProjectKind::Theme);
        update_option('my_theme_rewrite', 'hash');

        do_action('after_switch_theme', 'Other', wp_get_theme('other'));

        self::assertSame(['alpha on 1', 'install on 1'], $this->journal());
        self::assertFalse(get_option('my_theme_rewrite'));
        self::assertArrayNotHasKey('wp_app_framework_test_activation', $GLOBALS);
    }

    public function testSwitchingFromTheOwnChildThemeIsNoActivation(): void
    {
        $this->register(ProjectKind::Theme);
        $GLOBALS['wp_app_framework_test_theme_parents'] = ['my-theme-child' => 'my-theme'];

        do_action('after_switch_theme', 'Child', wp_get_theme('my-theme-child'));

        self::assertSame([], $this->journal());
    }

    public function testSwitchingToAnotherThemeDeactivatesTheTheme(): void
    {
        $this->register(ProjectKind::Theme);

        do_action('switch_theme', 'Other', wp_get_theme('other'), wp_get_theme('my-theme'));

        self::assertSame(['alpha on 1', 'pause on 1'], $this->journal());
        self::assertFalse(wp_next_scheduled(self::CLEAN_UP));
        self::assertArrayNotHasKey('wp_app_framework_test_deactivation', $GLOBALS);
    }

    public function testSwitchingToTheOwnChildThemeKeepsTheTheme(): void
    {
        $this->register(ProjectKind::Theme);
        $GLOBALS['wp_app_framework_test_theme_parents'] = ['my-theme-child' => 'my-theme'];

        do_action('switch_theme', 'Child', wp_get_theme('my-theme-child'), wp_get_theme('my-theme'));

        self::assertSame([], $this->journal());
        self::assertNotFalse(wp_next_scheduled(self::CLEAN_UP));
    }

    public function testAChildThemeIsActivatedWhenTheSiteSwitchesFromItsParentAndDeactivatedWhenItSwitchesBack(): void
    {
        $GLOBALS['wp_app_framework_test_theme_parents'] = ['my-theme' => 'parent-theme'];
        $this->register(ProjectKind::ChildTheme);

        do_action('after_switch_theme', 'Parent', wp_get_theme('parent-theme'));
        do_action('switch_theme', 'Parent', wp_get_theme('parent-theme'), wp_get_theme('my-theme'));

        self::assertSame(['alpha on 1', 'install on 1', 'alpha on 1', 'pause on 1'], $this->journal());
    }
}
