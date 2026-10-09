<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;

/**
 * Boots the framework for the active theme and provides its paths.
 *
 * The instance is predefined in the container and can be injected into services.
 */
final class Theme
{
    /** Fired at the end of boot; child themes attach here (their functions.php loads first). */
    public const string INIT_HOOK = 'wp_app_framework/init';

    /** Manifest of a release build, relative to the template directory. Its presence selects release mode. */
    public const string RELEASE_MANIFEST = 'generated/manifest.php';

    private static bool $booted = false;

    /**
     * @param string $templateDirectory     get_template_directory()
     * @param string $templateDirectoryUri  get_template_directory_uri()
     * @param string $stylesheetDirectory   get_stylesheet_directory() – the child theme, if active
     * @param string $stylesheetDirectoryUri get_stylesheet_directory_uri()
     * @param string $frameworkDirectory    Filesystem path of the framework package; never derive URLs from it.
     */
    private function __construct(
        public readonly string $templateDirectory,
        public readonly string $templateDirectoryUri,
        public readonly string $stylesheetDirectory,
        public readonly string $stylesheetDirectoryUri,
        public readonly string $frameworkDirectory,
    ) {
    }

    /**
     * Boots the framework. Called once from the theme's functions.php.
     *
     * Release mode applies if the theme contains generated/manifest.php (created
     * only by the release build). Otherwise development mode builds the manifest
     * by reflection and caches it in wp-content/cache/{template}/manifest.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            throw new LogicException('Theme::boot() was already called; boot the framework only once, from the theme\'s functions.php.');
        }
        self::$booted = true;

        $theme = new self(
            get_template_directory(),
            get_template_directory_uri(),
            get_stylesheet_directory(),
            get_stylesheet_directory_uri(),
            dirname(__DIR__),
        );

        $manifest = $theme->loadManifest();

        $container = new Container($manifest['services'], [self::class => $theme]);
        new HookRegistrar($container, defined('WP_DEBUG') && WP_DEBUG)->register($manifest['hooks']);

        do_action(self::INIT_HOOK);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(): array
    {
        $releaseManifest = $this->templateDirectory . '/' . self::RELEASE_MANIFEST;
        if (is_file($releaseManifest)) {
            return Manifest::load($releaseManifest);
        }

        // Reflection reports real paths (the theme may be a symlink), so the cache bases are real paths too.
        $templateRoot = realpath($this->templateDirectory);
        $frameworkRoot = realpath($this->frameworkDirectory);
        if ($templateRoot === false || $frameworkRoot === false) {
            throw new LogicException(sprintf(
                'Cannot resolve the real paths of the template directory %s and the framework directory %s.',
                $this->templateDirectory,
                $this->frameworkDirectory,
            ));
        }

        $cache = new DevCache(
            WP_CONTENT_DIR . '/cache/' . get_template() . '/manifest',
            ['theme' => $templateRoot, 'framework' => $frameworkRoot],
            ManifestBuilder::forTheme($templateRoot),
        );

        return $cache->load();
    }
}
