<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Rest\RestRegistrar;

/**
 * Boots the framework for the active theme and provides its paths.
 *
 * The instance is created only by boot() and handed to the container as a
 * predefined instance: the container never builds it, but services can inject
 * it to read the paths.
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
        private readonly string $templateDirectory,
        private readonly string $templateDirectoryUri,
        private readonly string $stylesheetDirectory,
        private readonly string $stylesheetDirectoryUri,
        private readonly string $frameworkDirectory,
    ) {
    }

    /**
     * Directory of the parent theme, as returned by get_template_directory().
     */
    public function getTemplateDirectory(): string
    {
        return $this->templateDirectory;
    }

    /**
     * URI of the parent theme directory, as returned by get_template_directory_uri().
     */
    public function getTemplateDirectoryUri(): string
    {
        return $this->templateDirectoryUri;
    }

    /**
     * Directory of the active (child) theme, as returned by get_stylesheet_directory().
     */
    public function getStylesheetDirectory(): string
    {
        return $this->stylesheetDirectory;
    }

    /**
     * URI of the active (child) theme directory, as returned by get_stylesheet_directory_uri().
     */
    public function getStylesheetDirectoryUri(): string
    {
        return $this->stylesheetDirectoryUri;
    }

    /**
     * Filesystem path of the framework package; never derive URLs from it.
     */
    public function getFrameworkDirectory(): string
    {
        return $this->frameworkDirectory;
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
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        new HookRegistrar($container, $debug)->register($manifest['hooks']);

        $restRegistrar = new RestRegistrar($container, $debug);
        $routes = $manifest['routes'];
        add_action('rest_api_init', static function () use ($restRegistrar, $routes): void {
            $restRegistrar->register($routes);
        });

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
