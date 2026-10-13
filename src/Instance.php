<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use Closure;
use LogicException;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\Cache\Backend\FileBackend;
use Saij\WpAppFramework\Cache\Backend\ObjectBackend;
use Saij\WpAppFramework\Cache\Backend\TransientBackend;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cache\CodeGeneration;
use Saij\WpAppFramework\Cache\TagHooks;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\Cache\TagVersions;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Post\MetaBoxes;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostStore;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Setting\SettingsAccessor;
use Saij\WpAppFramework\Setting\SettingsPage;
use Saij\WpAppFramework\Setting\SettingsStore;
use Saij\WpAppFramework\Style\StyleAssets;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\View\ViewAssets;
use Twig\Environment;

/**
 * Boots one framework instance: a theme (Theme::boot()) or a plugin
 * (Plugin::boot()). Technical global names derive from the slug of the instance
 * (script handles, JS global, cache directory, init action), names of content and
 * data from its namespace (blocks, REST routes, settings options).
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
        $sourceDirectories = [];
        if ($development) {
            $this->checkSharedClasses($frameworkDirectory);
            [$manifest, $sourceDirectories] = $this->loadDevelopmentManifest($cacheDirectory . '/manifest', $frameworkDirectory);
        } else {
            $manifest = Manifest::load($releaseManifest);
        }
        $this->checkManifest($manifest, $development ? $cacheDirectory . '/manifest' : $releaseManifest);
        if (!InstanceBridge::claimNamespace($manifest['namespace'])) {
            throw new LogicException(sprintf(
                'The %s %s uses the namespace "%s" (extra.wp-app-framework.namespace in its composer.json), but another theme or plugin using the framework has already booted with it; each needs a namespace of its own.',
                $this->kind->getLabel(),
                $this->slug,
                $manifest['namespace'],
            ));
        }

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
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $values = new ValueFactory($manifest['values'], $renderContext);
        $settingsGroups = $manifest['settings']['groups'];
        $settings = $settingsGroups === [] ? null : new SettingsStore($this->slug, $settingsGroups, $values);
        $globals = $settings === null ? [] : ['settings' => new SettingsAccessor($settings)];
        $settingsNames = array_column($settingsGroups, 'name', 'class');
        // Queries with cache() reach the cache of the instance; the container is created below.
        $container = null;
        $postStore = new PostStore($this->slug, $manifest['posts'], $manifest['taxonomies'], $manifest['meta'], $manifest['values'], $values, static function () use (&$container): CacheStore {
            /** @var Container|null $instance The container, assigned below before any query runs. */
            $instance = $container;
            if (!$instance instanceof Container) {
                throw new LogicException('The cache is not available before the container of the instance exists.');
            }

            return $instance->get(CacheStore::class);
        });
        PostRegistry::register($postStore);
        $tagNames = new TagNames(
            $settingsNames,
            array_map(static fn(array $post): string => $post['name'], $manifest['posts']),
            array_map(static fn(array $taxonomy): string => $taxonomy['name'], $manifest['taxonomies']),
        );
        $prefix = ComposerProject::templatePrefix($this->slug);
        $tagVersions = new TagVersions($prefix);
        $codeVersion = $manifest['codeVersion'];
        $templateVersion = $manifest['templateVersion'];
        $generation = new CodeGeneration(static function () use ($development, $sourceDirectories, $templateSetup, $codeVersion, $templateVersion, $releaseManifest): string {
            $setup = $templateSetup();
            if ($development) {
                return CodeGeneration::ofFiles([...$sourceDirectories, ...$setup['overrides']]);
            }
            if ($codeVersion === null || $templateVersion === null) {
                throw new LogicException(sprintf(
                    'The release manifest %s has no code or template version; build it with `wp-app-framework build --output=…` (done by `wp-app-framework release`).',
                    $releaseManifest,
                ));
            }

            return $codeVersion . ':' . $templateVersion . ':' . ($setup['cacheKey'] ?? '');
        });
        $backends = [
            'object' => new ObjectBackend($this->slug),
            'file' => new FileBackend($cacheDirectory . '/data'),
            'transient' => new TransientBackend($prefix),
        ];
        $container = new Container(
            $manifest['services'],
            [$this->consumer::class => $this->consumer, RenderContext::class => $renderContext, PostStore::class => $postStore],
            [
                CacheStore::class => static fn(Container $container): CacheStore => new CacheStore($slug, $backends, $tagVersions, $generation, $container, $tagNames),
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
                    $globals,
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
                        $globals,
                    );
                },
            ],
            $settings,
        );
        new HookRegistrar($container, $debug)->register($manifest['hooks']);

        $cache = $container->get(CacheStore::class);
        $settingsOptions = array_column($settingsGroups, 'name', 'option');
        new TagHooks($tagVersions, $settingsOptions)->register();
        $this->scheduleCacheCleanUp($cache);

        $restRegistrar = new RestRegistrar($container, $values, $debug, $cache);
        $routes = $manifest['routes'];
        $blocks = $manifest['blocks'];
        $blockCategories = $manifest['blockCategories'];
        $contexts = [];
        foreach ($blocks as $block) {
            foreach ($block['arguments'] as $argument) {
                if ($argument['kind'] === 'context') {
                    $contexts[$block['name']][$argument['name']] = $argument['schema'];
                }
            }
        }
        $renderController = new RenderController($this->slug, array_column($blocks, 'name'), $renderContext, $debug, $contexts);
        add_action('rest_api_init', static function () use ($restRegistrar, $routes, $renderController): void {
            $restRegistrar->register($routes);
            $renderController->register();
        });

        $buildAssets = new BuildAssets($this->slug, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY);
        $blockRegistrar = new BlockRegistrar($container, $values, $renderContext, $debug, $this->slug, new ViewAssets($this->slug, $buildAssets), $cache);
        add_action('init', static function () use ($blockRegistrar, $blocks, $blockCategories): void {
            $blockRegistrar->register($blocks, $blockCategories);
        });

        $this->registerPosts($postStore, $manifest['rewrite'], $prefix);

        $editorAssets = new EditorAssets(
            $this->slug,
            $this->directory . '/' . self::BUILD_DIRECTORY,
            $this->directoryUri . '/' . self::BUILD_DIRECTORY,
            $blocks,
            $development,
            $manifest['namespace'],
            $postStore,
        );
        add_action('enqueue_block_editor_assets', $editorAssets->enqueue(...));
        add_action('enqueue_block_assets', $editorAssets->enqueueCanvas(...));
        if ($manifest['styles']) {
            $styleAssets = new StyleAssets($this->slug, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY);
            add_action('enqueue_block_assets', $styleAssets->enqueue(...));
        }

        if ($settings !== null) {
            $settings->hook();
            $settingsPage = new SettingsPage(
                $this->slug,
                $this->kind,
                (string) $manifest['settings']['title'],
                $settingsGroups,
                $this->directory . '/' . self::BUILD_DIRECTORY,
                $this->directoryUri . '/' . self::BUILD_DIRECTORY,
            );
            add_action('admin_menu', $settingsPage->addPage(...));
            add_action('admin_enqueue_scripts', $settingsPage->enqueue(...));
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
     * Registers post types, taxonomies and meta on `init`, keeps the rewrite rules up to date and adds the meta boxes
     * of the classic edit screen.
     *
     * @param non-empty-string|null $rewrite Hash of the rewrite-relevant declarations.
     * @param non-empty-string      $prefix  Technical prefix of the instance's options.
     */
    private function registerPosts(PostStore $store, ?string $rewrite, string $prefix): void
    {
        $registrar = new PostTypeRegistrar($store, $prefix . 'rewrite', $rewrite);
        add_action('init', $registrar->register(...));
        add_action('init', $registrar->flushRewriteRules(...), PHP_INT_MAX);
        // Another theme or plugin may have flushed the rules without these post types in the meantime.
        if ($this->kind === ProjectKind::Plugin) {
            register_activation_hook($this->directory . '/' . $this->slug . '.php', $registrar->resetRewriteRules(...));
        } else {
            add_action('after_switch_theme', $registrar->resetRewriteRules(...));
        }

        if ($store->getMeta() === []) {
            return;
        }
        $boxes = new MetaBoxes($this->slug, $store, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY);
        add_action('add_meta_boxes', $boxes->add(...));
        add_action('save_post', $boxes->save(...), 10, 2);
        add_action('admin_notices', $boxes->notices(...));
        add_action('admin_enqueue_scripts', $boxes->enqueue(...));
    }

    /**
     * Name of the WP-Cron event that deletes expired cache files of an instance.
     *
     * @param non-empty-string $slug
     */
    public static function cacheCleanUpEvent(string $slug): string
    {
        return 'wp_app_framework/cache/clean_up/' . $slug;
    }

    /**
     * Schedules the daily clean-up of expired cache files and removes it when the theme is switched or the plugin
     * is deactivated.
     */
    private function scheduleCacheCleanUp(CacheStore $cache): void
    {
        $event = self::cacheCleanUpEvent($this->slug);
        add_action($event, $cache->cleanUp(...));
        add_action('init', static function () use ($event): void {
            if (wp_next_scheduled($event) === false) {
                wp_schedule_event(time(), 'daily', $event);
            }
        });
        $unschedule = static function () use ($event): void {
            wp_clear_scheduled_hook($event);
        };
        if ($this->kind === ProjectKind::Plugin) {
            register_deactivation_hook($this->directory . '/' . $this->slug . '.php', $unschedule);
        } else {
            add_action('switch_theme', $unschedule);
        }
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
     * @return array{0: ManifestData, 1: list<string>} The manifest and the directories it was built from.
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

        $manifest = $cache->load();

        return [$manifest, $cache->getSourceDirectories()];
    }
}
