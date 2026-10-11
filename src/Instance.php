<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use Closure;
use LogicException;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Style\StyleAssets;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueFactory;
use Twig\Environment;

/**
 * Boots one framework instance: a theme (Theme::boot()) or a plugin
 * (Plugin::boot()). Everything global is named after the slug of the instance:
 * REST route, script handles, JS global, cache directory, init action.
 *
 * @internal
 *
 * @phpstan-import-type ManifestData from Manifest
 * @phpstan-type TemplateSetup array{overrides: list<string>, cacheKey: non-empty-string|null}
 */
#[NoService]
final readonly class Instance
{
    /** Manifest of a release build, relative to the project directory. Its presence selects release mode. */
    public const string RELEASE_MANIFEST = 'generated/manifest.php';

    /** Output directory of the project's script build, relative to the project directory. */
    public const string BUILD_DIRECTORY = 'build';

    /** Package of the framework, as installed in the project's vendor directory. */
    private const string FRAMEWORK_PACKAGE = 'saij/wp-app-framework';

    /**
     * @param non-empty-string            $slug
     * @param string                      $directory     WordPress' path of the project (may be a symlink).
     * @param string                      $directoryUri  URL of the project directory.
     * @param Theme|ChildTheme|Plugin     $consumer      Injectable description of the project.
     * @param Closure(): TemplateSetup    $templateSetup Template directories searched before the project's `src/`, and a
     *                                                   key that separates the release cache of compiled templates by
     *                                                   them (null if they cannot change independently of the release).
     *                                                   Called on the first render, when the active theme is final.
     * @param non-empty-string|null       $parent        The active parent theme of a child theme; its manifest must name it.
     */
    public function __construct(
        private ProjectKind $kind,
        private string $slug,
        private string $directory,
        private string $directoryUri,
        private Theme|ChildTheme|Plugin $consumer,
        private Closure $templateSetup,
        private ?string $parent = null,
    ) {}

    /**
     * Separates the release cache of compiled templates that the active theme
     * and child theme override: a new theme version gets a new cache.
     *
     * @return non-empty-string
     */
    public static function themeVersionKey(): string
    {
        $themes = [
            get_template(),
            (string) wp_get_theme(get_template())->get('Version'),
            get_stylesheet(),
            (string) wp_get_theme()->get('Version'),
        ];

        return substr(hash('sha256', implode("\n", $themes)), 0, 16);
    }

    public function boot(): void
    {
        if (InstanceBridge::isBooted($this->slug)) {
            throw new LogicException(sprintf(
                'A framework instance with the slug "%s" has already booted; the slugs of the theme and of all plugins using the framework must differ, and each boots only once (%s).%s',
                $this->slug,
                $this->kind->getLabel(),
                $this->kind === ProjectKind::Theme && get_stylesheet() !== get_template()
                    ? sprintf(' The child theme %s is active: if its functions.php calls Theme::boot(), it boots the parent theme; a child theme boots with ChildTheme::boot().', get_stylesheet())
                    : '',
            ));
        }

        $frameworkDirectory = dirname(__DIR__);
        $releaseManifest = $this->directory . '/' . self::RELEASE_MANIFEST;
        $development = !is_file($releaseManifest);
        $cacheDirectory = WP_CONTENT_DIR . '/cache/' . $this->slug;
        if ($development) {
            $this->checkSharedClasses($frameworkDirectory);
            $manifest = $this->loadDevelopmentManifest($cacheDirectory . '/manifest', $frameworkDirectory);
        } else {
            $manifest = Manifest::load($releaseManifest);
        }
        $this->checkManifest($manifest, $development ? $cacheDirectory . '/manifest' : $releaseManifest);

        $renderContext = new RenderContext();
        $templateCallables = $manifest['templateCallables'];
        $templateProviders = $manifest['templateProviders'];
        $twigCache = $development
            ? $cacheDirectory . '/twig'
            : $cacheDirectory . '/twig/' . self::releaseTemplateVersion($manifest['templateVersion'], $releaseManifest);
        $projectTemplates = $this->directory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;
        $templateSetup = $this->templateSetup;
        $slug = $this->slug;
        $exactNamespace = $this->kind->getTemplateNamespace();
        $directory = $this->directory;
        $container = new Container(
            $manifest['services'],
            [$this->consumer::class => $this->consumer, RenderContext::class => $renderContext],
            [
                TemplateRenderer::class => static function (Container $container) use (
                    $slug,
                    $directory,
                    $templateSetup,
                    $projectTemplates,
                    $exactNamespace,
                    $frameworkDirectory,
                    $twigCache,
                    $development,
                    $templateCallables,
                    $templateProviders,
                ): TemplateRenderer {
                    $setup = $templateSetup();

                    return new TemplateRenderer(
                        $slug,
                        $directory,
                        $setup['overrides'],
                        $projectTemplates,
                        $exactNamespace,
                        $frameworkDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY,
                        $development || $setup['cacheKey'] === null ? $twigCache : $twigCache . '/' . $setup['cacheKey'],
                        $development,
                        $templateCallables,
                        $templateProviders,
                        $container,
                    );
                },
            ],
        );
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        new HookRegistrar($container, $debug)->register($manifest['hooks']);

        $values = new ValueFactory($manifest['values']);
        $restRegistrar = new RestRegistrar($container, $values, $debug);
        $routes = $manifest['routes'];
        $blocks = $manifest['blocks'];
        $renderController = new RenderController($this->slug, array_column($blocks, 'name'), $renderContext, $debug);
        add_action('rest_api_init', static function () use ($restRegistrar, $routes, $renderController): void {
            $restRegistrar->register($routes);
            $renderController->register();
        });

        $blockRegistrar = new BlockRegistrar($container, $values, $renderContext, $debug, $this->slug);
        add_action('init', static function () use ($blockRegistrar, $blocks): void {
            $blockRegistrar->register($blocks);
        });

        $editorAssets = new EditorAssets(
            $this->slug,
            $this->directory . '/' . self::BUILD_DIRECTORY,
            $this->directoryUri . '/' . self::BUILD_DIRECTORY,
            $blocks,
            $development,
        );
        add_action('enqueue_block_editor_assets', $editorAssets->enqueue(...));
        add_action('enqueue_block_assets', $editorAssets->enqueueCanvas(...));
        if ($manifest['styles']) {
            $styleAssets = new StyleAssets($this->slug, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY);
            add_action('enqueue_block_assets', $styleAssets->enqueue(...));
        }

        InstanceBridge::provideApis($this->slug, $manifest['publicApis'], $container);
        InstanceBridge::provideTemplateCallables(
            $this->slug,
            array_values(array_filter($templateCallables, static fn(array $callable): bool => $callable['public'])),
            $container,
        );

        do_action(InstanceBridge::initAction($this->slug));
    }

    /**
     * In development mode all consumers share the unprefixed classes of the
     * framework and its dependencies; whichever autoloader comes first provides
     * them. That is only correct if every consumer uses the same versions.
     */
    private function checkSharedClasses(string $frameworkDirectory): void
    {
        $vendor = $this->directory . '/vendor';
        $own = realpath($vendor . '/' . self::FRAMEWORK_PACKAGE);
        $loaded = realpath($frameworkDirectory);
        if ($own === false || $own !== $loaded) {
            throw new LogicException(sprintf(
                'The %s %s runs in development mode with the framework classes from %s, but its own framework is %s. Several consumers share the unprefixed classes in development mode; link the same framework checkout into each of them (e.g. WP_PACKAGE_LINKS of wp-dev-env).',
                $this->kind->getLabel(),
                $this->slug,
                $loaded === false ? $frameworkDirectory : $loaded,
                $own === false ? $vendor . '/' . self::FRAMEWORK_PACKAGE . ' (missing)' : $own,
            ));
        }

        $installedFile = $vendor . '/composer/installed.php';
        $installed = is_file($installedFile) ? require $installedFile : null;
        $twig = is_array($installed) && is_array($installed['versions'] ?? null) && is_array($installed['versions']['twig/twig'] ?? null)
            ? $installed['versions']['twig/twig']['pretty_version'] ?? null
            : null;
        if (!is_string($twig)) {
            throw new LogicException(sprintf('%s does not list twig/twig; run composer install in the %s %s.', $installedFile, $this->kind->getLabel(), $this->slug));
        }
        if (ltrim($twig, 'v') !== Environment::VERSION) {
            throw new LogicException(sprintf(
                'The %s %s requires Twig %s, but Twig %s is loaded (from another consumer). Several consumers share the unprefixed classes in development mode; install the same Twig version in each of them.',
                $this->kind->getLabel(),
                $this->slug,
                $twig,
                Environment::VERSION,
            ));
        }
    }

    /**
     * @param ManifestData $manifest
     */
    private function checkManifest(array $manifest, string $source): void
    {
        if ($manifest['kind'] !== $this->kind->value || $manifest['slug'] !== $this->slug) {
            throw new LogicException(sprintf(
                'The manifest %s was built for the %s "%s", but the %s "%s" boots with it. Check the "type" in composer.json, the "Template:" header in style.css and the directory name.',
                $source,
                ProjectKind::from($manifest['kind'])->getLabel(),
                $manifest['slug'] ?? '',
                $this->kind->getLabel(),
                $this->slug,
            ));
        }
        if ($manifest['parent'] !== $this->parent) {
            throw new LogicException(sprintf(
                'The manifest %s was built for the child theme "%s" of the parent theme "%s", but the active parent theme is "%s". The "Template:" header in style.css names the parent theme.',
                $source,
                $this->slug,
                $manifest['parent'] ?? '',
                $this->parent ?? '',
            ));
        }
    }

    /**
     * Release mode compiles templates without auto reload, so each release needs
     * its own cache directory; the release build names it after its templates.
     *
     * @param non-empty-string|null $templateVersion From the release manifest.
     * @return non-empty-string
     */
    private static function releaseTemplateVersion(?string $templateVersion, string $releaseManifest): string
    {
        if ($templateVersion === null) {
            throw new LogicException(sprintf(
                'The release manifest %s has no template version; build it with `wp-app-framework build --output=…` (done by `wp-app-framework release`).',
                $releaseManifest,
            ));
        }

        return $templateVersion;
    }

    /**
     * Builds the manifest by reflection, cached until a source changes.
     *
     * @return ManifestData
     */
    private function loadDevelopmentManifest(string $cacheDirectory, string $frameworkDirectory): array
    {
        // Reflection reports real paths (the project may be a symlink), so the cache bases are real paths too.
        $projectRoot = realpath($this->directory);
        $frameworkRoot = realpath($frameworkDirectory);
        if ($projectRoot === false || $frameworkRoot === false) {
            throw new LogicException(sprintf(
                'Cannot resolve the real paths of the project directory %s and the framework directory %s.',
                $this->directory,
                $frameworkDirectory,
            ));
        }

        $cache = new DevCache(
            $cacheDirectory,
            ['project' => $projectRoot, 'framework' => $frameworkRoot],
            ManifestBuilder::forProject($projectRoot),
        );

        return $cache->load();
    }
}
