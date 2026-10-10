<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueFactory;

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

    /** Output directory of the theme's script build, relative to the template directory. */
    public const string BUILD_DIRECTORY = 'build';

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

        $releaseManifest = $theme->templateDirectory . '/' . self::RELEASE_MANIFEST;
        $development = !is_file($releaseManifest);
        // Cache paths use the parent slug, so a child theme keeps the caches of its parent.
        $cacheDirectory = WP_CONTENT_DIR . '/cache/' . get_template();
        $manifest = $development
            ? $theme->loadDevelopmentManifest($cacheDirectory . '/manifest')
            : Manifest::load($releaseManifest);

        // The slug names everything global of this framework instance: REST route, script handles, JS global.
        $slug = get_template();
        $renderContext = new RenderContext();
        $templateCallables = $manifest['templateCallables'];
        $container = new Container(
            $manifest['services'],
            [self::class => $theme, RenderContext::class => $renderContext],
            [
                TemplateRenderer::class => static fn(Container $container): TemplateRenderer => new TemplateRenderer(
                    $theme->templateDirectory,
                    $theme->stylesheetDirectory,
                    __DIR__,
                    $cacheDirectory . '/twig',
                    $development,
                    $templateCallables,
                    $container,
                ),
            ],
        );
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        new HookRegistrar($container, $debug)->register($manifest['hooks']);

        $values = new ValueFactory($manifest['values']);
        $restRegistrar = new RestRegistrar($container, $values, $debug);
        $routes = $manifest['routes'];
        $blocks = $manifest['blocks'];
        $renderController = new RenderController($slug, array_column($blocks, 'name'), $renderContext, $debug);
        add_action('rest_api_init', static function () use ($restRegistrar, $routes, $renderController): void {
            $restRegistrar->register($routes);
            $renderController->register();
        });

        $blockRegistrar = new BlockRegistrar($container, $values, $renderContext, $debug);
        add_action('init', static function () use ($blockRegistrar, $blocks): void {
            $blockRegistrar->register($blocks);
        });

        $editorAssets = new EditorAssets(
            $slug,
            $theme->templateDirectory . '/' . self::BUILD_DIRECTORY,
            $theme->templateDirectoryUri . '/' . self::BUILD_DIRECTORY,
            $blocks,
            $development,
        );
        add_action('enqueue_block_editor_assets', $editorAssets->enqueue(...));
        add_action('enqueue_block_assets', $editorAssets->enqueueCanvas(...));

        do_action(self::INIT_HOOK);
    }

    /**
     * Builds the manifest by reflection, cached until a source changes.
     *
     * @return array<string, mixed>
     */
    private function loadDevelopmentManifest(string $cacheDirectory): array
    {
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
            $cacheDirectory,
            ['theme' => $templateRoot, 'framework' => $frameworkRoot],
            ManifestBuilder::forTheme($templateRoot),
        );

        return $cache->load();
    }
}
