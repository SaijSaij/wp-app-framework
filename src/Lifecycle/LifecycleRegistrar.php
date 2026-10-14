<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Lifecycle;

use Closure;
use LogicException;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Table\TableStore;
use Throwable;
use WP_Site;
use WP_Theme;

/**
 * Activation, deactivation and uninstallation of an instance: the framework's
 * own work and the methods of the consumer (`#[OnActivation]`,
 * `#[OnDeactivation]`, `#[OnUninstall]`).
 *
 * Plugins use the activation and deactivation hooks of WordPress; network-wide
 * activation and deactivation run once per site of the network, and a site
 * created while the plugin is active network-wide is activated as well. The
 * uninstall hook needs a static callback, which WordPress stores in the option
 * `uninstall_plugins`; it is registered on activation and on deactivation (that
 * option is not autoloaded, and a plugin is always deactivated before it is
 * deleted). WordPress loads the main file of the plugin before it runs the
 * hook, so the instance boots – after `init` – and the callback finds it by the
 * name of the action. Uninstallation runs on every site of a multisite
 * installation.
 *
 * Themes are activated on the first request after the switch to them
 * (`after_switch_theme`) and deactivated in the request that switches away
 * (`switch_theme`); switching between the theme and its child theme is no switch
 * for the parent theme. Themes cannot be uninstalled.
 *
 * Activation and uninstallation run after `init`, when the instance has booted
 * late: they register its post types and taxonomies first. Errors of the methods
 * and steps are rethrown when debugging is enabled, otherwise logged; the next
 * step runs anyway.
 *
 * @internal
 *
 * @phpstan-import-type LifecycleData from LifecycleCollector
 */
#[NoService]
final class LifecycleRegistrar
{
    /** @var array<string, self> The booted plugins by main file relative to the plugins directory. */
    private static array $plugins = [];

    /**
     * @param non-empty-string $slug
     * @param string           $directory  Directory of the project; plugins register their hooks for `{directory}/{slug}.php`.
     * @param LifecycleData    $lifecycle
     * @param bool             $registered Whether the post types are registered on `init`, which had not fired at boot.
     */
    public function __construct(
        private readonly ProjectKind $kind,
        private readonly string $slug,
        private readonly string $directory,
        private readonly array $lifecycle,
        private readonly HookRegistrar $hooks,
        private readonly PostTypeRegistrar $posts,
        private readonly CronRegistrar $cron,
        private readonly TableStore $tables,
        private readonly Uninstaller $uninstaller,
        private readonly bool $debug,
        private bool $registered,
    ) {}

    /**
     * Runs the uninstallation of the plugin whose uninstall hook WordPress fires (action `uninstall_{plugin}`).
     */
    public static function uninstallPlugin(): void
    {
        $action = (string) current_action();
        $plugin = str_starts_with($action, 'uninstall_') ? substr($action, strlen('uninstall_')) : '';
        $registrar = self::$plugins[$plugin] ?? null;
        if ($registrar === null) {
            throw new LogicException(sprintf(
                'The uninstall hook of wp-app-framework runs on "%s", but no plugin with this main file has booted with the framework in this request.',
                $action,
            ));
        }
        $registrar->uninstall();
    }

    public function register(): void
    {
        if ($this->kind !== ProjectKind::Plugin) {
            add_action('after_switch_theme', $this->afterSwitchTheme(...), 10, 2);
            add_action('switch_theme', $this->switchTheme(...), 10, 2);

            return;
        }

        $file = $this->mainFile();
        register_activation_hook($file, $this->activate(...));
        register_deactivation_hook($file, $this->deactivate(...));
        self::$plugins[$this->basename()] = $this;
        if (is_multisite()) {
            add_action('wp_initialize_site', $this->initializeSite(...), 100);
            add_filter('wpmu_drop_tables', $this->dropTables(...));
        }
    }

    /**
     * Activates the plugin (action `activate_{plugin}`).
     */
    private function activate(bool $networkWide = false): void
    {
        $this->registerUninstallHook();
        $this->ensureRegistered();
        $this->forEachSite($networkWide, $this->activateSite(...), false);
    }

    /**
     * Deactivates the plugin (action `deactivate_{plugin}`).
     */
    private function deactivate(bool $networkDeactivating = false): void
    {
        $this->registerUninstallHook();
        $this->forEachSite($networkDeactivating, $this->deactivateSite(...), false);
    }

    private function uninstall(): void
    {
        $this->ensureRegistered();
        $this->forEachSite(is_multisite(), function (): void {
            $this->run('uninstall', 'uninstall_' . $this->basename());
            foreach ($this->uninstaller->siteSteps() as $name => $step) {
                $this->step($name, $step);
            }
        }, true);
        $this->step('cache', $this->uninstaller->removeCache(...));
    }

    /**
     * Activates a new site of the network if the plugin is active network-wide (action `wp_initialize_site`).
     */
    private function initializeSite(WP_Site $site): void
    {
        $active = get_site_option('active_sitewide_plugins', []);
        if (!is_array($active) || !isset($active[$this->basename()])) {
            return;
        }
        $this->ensureRegistered();
        switch_to_blog((int) $site->blog_id);
        try {
            $this->activateSite();
        } finally {
            restore_current_blog();
        }
    }

    /**
     * Adds the tables of the instance to the tables WordPress drops with a site (filter `wpmu_drop_tables`, which
     * runs while the site is the current one).
     *
     * @param array<array-key, string> $tables
     * @return array<array-key, string>
     */
    private function dropTables(array $tables): array
    {
        return [...$tables, ...$this->tables->siteTables()];
    }

    /**
     * Activates the instance unless the previous theme was this theme or its child theme (action `after_switch_theme`,
     * on the first request with the new theme).
     */
    private function afterSwitchTheme(string $oldName, WP_Theme $oldTheme): void
    {
        if ($this->isThisTheme($oldTheme)) {
            return;
        }
        $this->activateSite();
    }

    /**
     * Deactivates the instance unless the new theme is this theme or its child theme (action `switch_theme`, in the
     * request with the old theme).
     */
    private function switchTheme(string $newName, WP_Theme $newTheme): void
    {
        if ($this->isThisTheme($newTheme)) {
            return;
        }
        $this->deactivateSite();
    }

    private function activateSite(): void
    {
        // Another theme or plugin may have flushed the rewrite rules without the post types of the instance.
        $this->posts->resetRewriteRules();
        $this->run('activation', $this->kind === ProjectKind::Plugin ? 'activate_' . $this->basename() : 'after_switch_theme');
    }

    private function deactivateSite(): void
    {
        $this->run('deactivation', $this->kind === ProjectKind::Plugin ? 'deactivate_' . $this->basename() : 'switch_theme');
        $this->cron->clear();
    }

    /**
     * Runs the methods of the consumer for a phase on the current site.
     *
     * @param 'activation'|'deactivation'|'uninstall' $phase
     */
    private function run(string $phase, string $hook): void
    {
        foreach ($this->lifecycle[$phase] as $entry) {
            $this->hooks->invoke([
                'name' => $hook,
                'priority' => 10,
                'acceptedArgs' => 0,
                'filter' => false,
                'class' => $entry['class'],
                'method' => $entry['method'],
                'arguments' => $entry['arguments'],
            ], []);
        }
    }

    /**
     * @param Closure(): void $step
     */
    private function step(string $name, Closure $step): void
    {
        try {
            $step();
        } catch (Throwable $exception) {
            HookRegistrar::rethrowOrLog($exception, $this->debug, sprintf('uninstall step "%s" of %s', $name, $this->slug));
        }
    }

    /**
     * Runs a callback on the current site or on every site.
     *
     * @param bool            $allSites     On every site (network-wide activation and deactivation, uninstallation on
     *                                      a multisite installation).
     * @param Closure(): void $callback
     * @param bool            $allNetworks  Every site of every network instead of the sites of the current network.
     */
    private function forEachSite(bool $allSites, Closure $callback, bool $allNetworks): void
    {
        if (!$allSites || !is_multisite()) {
            $callback();

            return;
        }
        $query = ['fields' => 'ids', 'number' => 0];
        if (!$allNetworks) {
            $query['network_id'] = get_current_network_id();
        }
        $sites = get_sites($query);
        foreach (is_array($sites) ? $sites : [] as $site) {
            switch_to_blog((int) $site);
            try {
                $callback();
            } finally {
                restore_current_blog();
            }
        }
    }

    private function registerUninstallHook(): void
    {
        register_uninstall_hook($this->mainFile(), [self::class, 'uninstallPlugin']);
    }

    /**
     * Registers the post types, taxonomies and meta of the instance if it booted after `init`.
     */
    private function ensureRegistered(): void
    {
        if ($this->registered) {
            return;
        }
        $this->posts->register();
        $this->registered = true;
    }

    private function isThisTheme(WP_Theme $theme): bool
    {
        return in_array($this->slug, [$theme->get_stylesheet(), $theme->get_template()], true);
    }

    private function mainFile(): string
    {
        return $this->directory . '/' . $this->slug . '.php';
    }

    /**
     * The main file relative to the plugins directory, as WordPress names the plugin in its hooks.
     */
    private function basename(): string
    {
        return $this->slug . '/' . $this->slug . '.php';
    }
}
