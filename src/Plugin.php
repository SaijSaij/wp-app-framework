<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Boots the framework for a plugin and provides its paths.
 *
 * The instance is created only by boot() and handed to the container as a
 * predefined instance: the container never builds it, but services of a plugin
 * can inject it to read the paths.
 */
final class Plugin
{
    /** Manifest of a release build, relative to the plugin directory. Its presence selects release mode. */
    public const string RELEASE_MANIFEST = Instance::RELEASE_MANIFEST;

    /**
     * @param non-empty-string $slug               Directory name of the plugin.
     * @param string           $file               Main plugin file, as passed to boot().
     * @param string           $directory          WP_PLUGIN_DIR/{slug}.
     * @param string           $directoryUri       URL of the plugin directory (plugin_dir_url()).
     * @param string           $frameworkDirectory Filesystem path of the framework package; never derive URLs from it.
     */
    private function __construct(
        private readonly string $slug,
        private readonly string $file,
        private readonly string $directory,
        private readonly string $directoryUri,
        private readonly string $frameworkDirectory,
    ) {}

    /**
     * Directory name of the plugin; it names everything global of the framework instance.
     *
     * @return non-empty-string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * The main plugin file, as passed to boot() (`__FILE__`; a real path if the plugin is a symlink).
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Directory of the plugin below WP_PLUGIN_DIR.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * URL of the plugin directory, without trailing slash.
     */
    public function getDirectoryUri(): string
    {
        return $this->directoryUri;
    }

    /**
     * Filesystem path of the framework package; never derive URLs from it.
     */
    public function getFrameworkDirectory(): string
    {
        return $this->frameworkDirectory;
    }

    /**
     * Boots the framework. Called once from the main plugin file:
     * `Plugin::boot(__FILE__)`. The main file must be `{slug}/{slug}.php`.
     *
     * Release mode applies if the plugin contains generated/manifest.php (created
     * only by the release build). Caches live in wp-content/cache/{slug}. Templates
     * are looked up in `overrides/{slug}/` of the child theme and the theme
     * before the plugin's own `src/`. At its end, boot() fires the action
     * `wp_app_framework/init/{slug}`.
     */
    public static function boot(string $file): void
    {
        $basename = plugin_basename($file);
        $slug = dirname($basename);
        if ($slug === '.' || $slug === '' || str_contains($slug, '/')) {
            throw new LogicException(sprintf(
                'Plugin::boot() needs the main file of a plugin in its own directory below the plugins directory (e.g. my-plugin/my-plugin.php); got %s.',
                $file,
            ));
        }
        if (basename($basename) !== $slug . '.php') {
            throw new LogicException(sprintf(
                'The main file of plugin %s must be named %s.php; Plugin::boot() was called from %s.',
                $slug,
                $slug,
                basename($basename),
            ));
        }

        $plugin = new self(
            $slug,
            $file,
            WP_PLUGIN_DIR . '/' . $slug,
            untrailingslashit(plugin_dir_url($file)),
            dirname(__DIR__),
        );

        new Instance(
            ProjectKind::Plugin,
            $slug,
            $plugin->directory,
            $plugin->directoryUri,
            $plugin,
            static function () use ($slug): array {
                $overrides = self::templateOverrides($slug, get_template_directory(), get_stylesheet_directory());
                if ($overrides === []) {
                    return ['overrides' => [], 'cacheKey' => null];
                }

                // Compiled overrides are never reloaded in release mode: a new theme version gets a new cache.
                $themes = [
                    get_template(),
                    (string) wp_get_theme(get_template())->get('Version'),
                    get_stylesheet(),
                    (string) wp_get_theme()->get('Version'),
                ];

                return ['overrides' => $overrides, 'cacheKey' => substr(hash('sha256', implode("\n", $themes)), 0, 16)];
            },
        )->boot();
    }

    /**
     * Template directories searched before the plugin's own `src/`: the
     * `overrides/{slug}/` directories of the child theme and the theme, as far as they exist.
     *
     * @internal
     *
     * @return list<string>
     */
    public static function templateOverrides(string $slug, string $templateDirectory, string $stylesheetDirectory): array
    {
        $overrides = [];
        foreach (array_unique([$stylesheetDirectory, $templateDirectory]) as $directory) {
            $path = $directory . '/' . TemplateRenderer::OVERRIDE_DIRECTORY . '/' . $slug;
            if (is_dir($path)) {
                $overrides[] = $path;
            }
        }

        return $overrides;
    }
}
