<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Setting;

use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Value\FormAssets;

/**
 * The settings page of one framework instance: one admin page with the forms
 * of all its settings groups (tabs if there are several), under "Appearance"
 * for themes and child themes, under "Settings" for plugins.
 *
 * The consumer's build compiles the page script with the framework's webpack
 * helper to build/vendor/wp-app-framework/settings.js. It saves through
 * /wp/v2/settings, which requires `manage_options`; the page requires the same.
 *
 * @internal
 *
 * @phpstan-import-type SettingsGroupEntry from SettingsCollector
 */
#[NoService]
final class SettingsPage
{
    /** The page script below the build directory, without extension. */
    public const string SCRIPT = 'vendor/wp-app-framework/settings';

    /** Capability of the page; /wp/v2/settings requires it for reading and saving. */
    public const string CAPABILITY = 'manage_options';

    private readonly BuildAssets $assets;

    /** Hook suffix of the registered page, false if WordPress did not add it. */
    private string|false|null $hookSuffix = null;

    /**
     * @param non-empty-string         $slug
     * @param string                   $title  Name of the theme or plugin.
     * @param list<SettingsGroupEntry> $groups
     * @param array<string, string>    $shared Shared modules (assets/shared/) by name, registered when a settings
     *                                         script imports them.
     */
    public function __construct(
        private readonly string $slug,
        private readonly ProjectKind $kind,
        private readonly string $title,
        private readonly array $groups,
        string $buildDirectory,
        string $buildUri,
        array $shared = [],
    ) {
        $this->assets = new BuildAssets($slug, $buildDirectory, $buildUri, $shared);
    }

    /**
     * @return non-empty-string
     */
    public static function pageSlug(string $slug): string
    {
        return $slug . '-settings';
    }

    /**
     * @return non-empty-string
     */
    public static function handle(string $slug): string
    {
        return $slug . '-wp-app-framework-settings';
    }

    /**
     * Adds the page to the admin menu (admin_menu).
     */
    public function addPage(): void
    {
        $render = $this->render(...);
        $this->hookSuffix = $this->kind === ProjectKind::Plugin
            ? add_options_page($this->title, $this->title, self::CAPABILITY, self::pageSlug($this->slug), $render)
            : add_theme_page($this->title, $this->title, self::CAPABILITY, self::pageSlug($this->slug), $render);
    }

    public function render(): void
    {
        printf(
            '<div class="wrap"><h1>%s</h1><div id="%s"></div></div>',
            esc_html($this->title),
            esc_attr(self::pageSlug($this->slug)),
        );
    }

    /**
     * Loads script and styles on the settings page only (admin_enqueue_scripts).
     */
    public function enqueue(string $hookSuffix): void
    {
        if ($this->hookSuffix === null || $this->hookSuffix === false || $hookSuffix !== $this->hookSuffix) {
            return;
        }

        $handle = self::handle($this->slug);
        $version = $this->assets->registerScript($handle, self::SCRIPT);
        wp_add_inline_script($handle, $this->configScript(), 'before');
        wp_enqueue_script($handle);
        // The settings scripts of the groups depend on the page script (their import of
        // @saij/wp-app-framework/settings), so they run after it, in the head like it: the page renders on
        // DOMContentLoaded, after their controls are registered.
        foreach ($this->groups as $group) {
            if ($group['script'] !== null) {
                $groupHandle = ScriptKind::settingsHandle($this->slug, $group['name']);
                $this->assets->registerScript($groupHandle, $group['script']);
                wp_enqueue_script($groupHandle);
            }
        }

        $this->assets->registerStyle($handle, self::SCRIPT, ['wp-components'], $version);
        wp_enqueue_style($handle);

        // The media library for #[Image] settings, the classic editor for #[RichText] settings, the palette for #[Color].
        FormAssets::enqueue(array_column($this->groups, 'controls'), $handle, $this->slug);
    }

    /**
     * The page configuration in the JS global `wpAppFramework[slug].settings`.
     */
    public function configScript(): string
    {
        $groups = [];
        foreach ($this->groups as $group) {
            $groups[] = [
                'name' => $group['name'],
                'option' => $group['option'],
                'title' => $group['title'],
                'description' => $group['description'],
                'schema' => $group['schema'],
                'default' => (object) $group['default'],
                'controls' => (object) $group['controls'],
            ];
        }

        return sprintf(
            'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework[%1$s] = window.wpAppFramework[%1$s] || {}; window.wpAppFramework[%1$s].settings = %2$s;',
            wp_json_encode($this->slug),
            wp_json_encode([
                'slug' => $this->slug,
                'root' => self::pageSlug($this->slug),
                'groups' => $groups,
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        );
    }
}
