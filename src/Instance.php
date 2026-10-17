<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use Closure;
use LogicException;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Asset\AssetDirectory;
use Saij\WpAppFramework\Asset\Assets;
use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Asset\ImageVariants;
use Saij\WpAppFramework\Asset\MediaLibrary;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\Cache\Backend\FileBackend;
use Saij\WpAppFramework\Cache\Backend\ObjectBackend;
use Saij\WpAppFramework\Cache\Backend\TransientBackend;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cache\CodeGeneration;
use Saij\WpAppFramework\Cache\TagHooks;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\Cache\TagVersions;
use Saij\WpAppFramework\Cron\CronRegistrar;
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\EditorAssets;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Hook\HookRegistrar;
use Saij\WpAppFramework\I18n\TranslationLoader;
use Saij\WpAppFramework\I18n\Translator;
use Saij\WpAppFramework\Lifecycle\LifecycleCollector;
use Saij\WpAppFramework\Lifecycle\LifecycleRegistrar;
use Saij\WpAppFramework\Lifecycle\Uninstaller;
use Saij\WpAppFramework\Manifest\DevCache\DevCache;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Post\MetaBoxes;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostStore;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Project\NamePrefix;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Setting\SettingsAccessor;
use Saij\WpAppFramework\Setting\SettingsPage;
use Saij\WpAppFramework\Setting\SettingsStore;
use Saij\WpAppFramework\Style\StyleAssets;
use Saij\WpAppFramework\Table\TableRegistry;
use Saij\WpAppFramework\Table\TableStore;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Twig\TwigCacheCleanUp;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\View\ViewAssets;
use Saij\WpAppFramework\View\ViewData;
use Saij\WpAppFramework\WpCli\CliRegistrar;
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
     * The URL of a directory of the active theme or child theme, e.g. of its overrides directory.
     */
    private static function themeUri(string $path): string
    {
        foreach ([[get_stylesheet_directory(), get_stylesheet_directory_uri()], [get_template_directory(), get_template_directory_uri()]] as [$directory, $uri]) {
            if ($path === $directory || str_starts_with($path, $directory . '/')) {
                return $uri . substr($path, strlen($directory));
            }
        }

        throw new LogicException(sprintf('%s is no directory of the active theme or child theme.', $path));
    }

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

        // Activating and uninstalling a plugin load it after `init`, so its post types are not registered on `init`.
        $afterInit = did_action('init') > 0;
        $frameworkDirectory = dirname(__DIR__);
        $releaseManifest = $this->directory . '/' . self::RELEASE_MANIFEST;
        $development = !is_file($releaseManifest);
        $cacheDirectory = WP_CONTENT_DIR . '/cache/' . $this->slug;
        $sourceEntries = [];
        if ($development) {
            $this->checkSharedClasses($frameworkDirectory);
            // The build checks it as well; the cached manifest of development mode does not notice a new file.
            $uninstallScript = $this->kind === ProjectKind::Plugin ? LifecycleCollector::uninstallScriptError($this->directory) : null;
            if ($uninstallScript !== null) {
                throw new LogicException($uninstallScript);
            }
            [$manifest, $sourceEntries] = $this->loadDevelopmentManifest($cacheDirectory . '/manifest', $frameworkDirectory);
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
        // Texts of the instance: its translation for the locale is loaded on `init`, before blocks and post types are
        // registered; a plugin activated after `init` loads it right away.
        $translator = new Translator($manifest['textDomain']);
        $translations = new TranslationLoader($manifest['textDomain'], $manifest['translations'], $this->directory, $development ? $cacheDirectory : null);
        if ($afterInit) {
            $translations->load();
        } else {
            add_action('init', $translations->load(...), 0);
        }
        $templateCallables = $manifest['templateCallables'];
        $templateProviders = $manifest['templateProviders'];
        $releaseTemplateVersion = $development ? null : self::releaseTemplateVersion($manifest['templateVersion'], $releaseManifest);
        $twigCache = $releaseTemplateVersion === null ? $cacheDirectory . '/twig' : $cacheDirectory . '/twig/' . $releaseTemplateVersion;
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
        $tableStore = new TableStore($this->slug, $manifest['namespace'], $manifest['tables'], $debug);
        TableRegistry::register($tableStore);
        // Without tables, now or at the last sync, the check costs not even a callback.
        if ($tableStore->needsCheck()) {
            add_action('init', $tableStore->ensureCurrent(...), 0);
        }
        $tagNames = new TagNames(
            $settingsNames,
            array_map(static fn(array $post): string => $post['name'], $manifest['posts']),
            array_map(static fn(array $taxonomy): string => $taxonomy['name'], $manifest['taxonomies']),
        );
        $prefix = NamePrefix::template($this->slug);
        $tagVersions = new TagVersions($prefix);
        $codeVersion = $manifest['codeVersion'];
        $templateVersion = $manifest['templateVersion'];
        $cronTasks = $manifest['cronTasks'];
        $generation = new CodeGeneration(static function () use ($development, $sourceEntries, $templateSetup, $codeVersion, $templateVersion, $releaseManifest): string {
            $setup = $templateSetup();
            if ($development) {
                $overrides = array_map(static fn(string $directory): array => [$directory, CodeGeneration::entries($directory)], $setup['overrides']);

                return CodeGeneration::ofEntries([...$sourceEntries, ...$overrides]);
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
        $viewData = new ViewData($this->slug, $debug);
        $directoryUri = $this->directoryUri;
        $assets = $manifest['assets'];
        // Image variants: generated/assets/ of a release, the cache directory in development mode (written by DevCache).
        $variants = $development
            ? [$cacheDirectory . '/' . ImageVariants::DIRECTORY, content_url('cache/' . $this->slug . '/' . ImageVariants::DIRECTORY)]
            : [$this->directory . '/generated/' . ImageVariants::DIRECTORY, $this->directoryUri . '/generated/' . ImageVariants::DIRECTORY];
        $container = new Container(
            $manifest['services'],
            [$this->consumer::class => $this->consumer, RenderContext::class => $renderContext, PostStore::class => $postStore, ViewData::class => $viewData, Translator::class => $translator],
            [
                CacheStore::class => static fn(Container $container): CacheStore => new CacheStore($slug, $backends, $tagVersions, $generation, $container, $tagNames),
                CronScheduler::class => static fn(): CronScheduler => new CronScheduler(CronRegistrar::taskHooks($slug, $cronTasks)),
                MediaLibrary::class => static fn(Container $container): MediaLibrary => new MediaLibrary($slug, $container->get(Assets::class)),
                // Created on first use, when the active theme and child theme are final.
                Assets::class => static function () use ($slug, $directory, $directoryUri, $assets, $exactNamespace, $templateSetup, $variants): Assets {
                    $overrides = [];
                    foreach ($templateSetup()['overrides'] as $override) {
                        $overrides[] = [$override . '/' . AssetDirectory::NAME, self::themeUri($override) . '/' . AssetDirectory::NAME];
                    }
                    $child = get_stylesheet() !== get_template()
                        ? [get_stylesheet_directory() . '/' . AssetDirectory::NAME, get_stylesheet_directory_uri() . '/' . AssetDirectory::NAME]
                        : null;

                    return new Assets(
                        $slug,
                        $directory . '/' . AssetDirectory::NAME,
                        $directoryUri . '/' . AssetDirectory::NAME,
                        $assets,
                        '@' . $exactNamespace . '/',
                        $overrides,
                        $child,
                        $variants,
                    );
                },
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
        $hookRegistrar = new HookRegistrar($container, $debug);
        $hookRegistrar->register($manifest['hooks']);
        $cliCommands = $manifest['cliCommands'];
        if (defined('WP_CLI') && WP_CLI && $cliCommands !== []) {
            new CliRegistrar($container, $values, $debug, $manifest['namespace'], $this->kind, $this->slug)->register($cliCommands);
        }

        $cache = $container->get(CacheStore::class);
        $settingsOptions = array_column($settingsGroups, 'name', 'option');
        new TagHooks($tagVersions, $settingsOptions)->register();
        $twigCleanUp = $releaseTemplateVersion === null
            ? null
            : new TwigCacheCleanUp($cacheDirectory . '/twig', $releaseTemplateVersion, static fn(): ?string => $templateSetup()['cacheKey'], $debug);
        $cronRegistrar = new CronRegistrar($this->slug, $manifest['cronEvents'], $cronTasks, $hookRegistrar, $container, $values, $debug, static function () use ($cache, $twigCleanUp): void {
            $cache->cleanUp();
            $twigCleanUp?->cleanUp();
        });
        $cronRegistrar->register();

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

        $scripts = $manifest['scripts'];
        $buildAssets = new BuildAssets($this->slug, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY, $scripts['shared']);
        $viewAssets = new ViewAssets($this->slug, $buildAssets);
        $blockRegistrar = new BlockRegistrar($container, $values, $renderContext, $debug, $this->slug, $viewAssets, $cache, $viewData, $translator);
        add_action('init', static function () use ($blockRegistrar, $blocks, $blockCategories): void {
            $blockRegistrar->register($blocks, $blockCategories);
        });

        $postTypeRegistrar = $this->registerPosts($postStore, $manifest['rewrite'], $prefix . 'rewrite', $translator);
        new LifecycleRegistrar(
            $this->kind,
            $this->slug,
            $this->directory,
            $manifest['lifecycle'],
            $hookRegistrar,
            $postTypeRegistrar,
            $cronRegistrar,
            $tableStore,
            new Uninstaller(
                $this->slug,
                $postStore,
                array_column($settingsGroups, 'option'),
                $tableStore,
                $cronRegistrar,
                $prefix . 'rewrite',
                $cacheDirectory,
            ),
            $debug,
            !$afterInit,
        )->register();

        $editorAssets = new EditorAssets(
            $this->slug,
            $this->directory . '/' . self::BUILD_DIRECTORY,
            $this->directoryUri . '/' . self::BUILD_DIRECTORY,
            $blocks,
            $development,
            $manifest['namespace'],
            $postStore,
            $scripts['editorScripts'],
            $scripts['shared'],
            $translator,
        );
        add_action('enqueue_block_editor_assets', $editorAssets->enqueue(...));
        add_action('enqueue_block_editor_assets', static fn() => $editorAssets->printData($viewData), PHP_INT_MAX);
        $documentViews = $scripts['documentViews'];
        if ($documentViews !== []) {
            add_action('wp_enqueue_scripts', static fn() => $viewAssets->enqueueDocumentViews($documentViews));
        }
        // Before WordPress prints the footer scripts (_wp_footer_scripts, priority 10).
        add_action('wp_print_footer_scripts', static fn() => $viewAssets->printData($viewData), 9);
        add_action('enqueue_block_assets', $editorAssets->enqueueCanvas(...));
        if ($manifest['styles']) {
            $styleAssets = new StyleAssets($this->slug, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY);
            add_action('enqueue_block_assets', $styleAssets->enqueue(...));
            if ($manifest['stylePreload']) {
                add_filter('wp_preload_resources', $styleAssets->preloadFonts(...));
            }
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
                $scripts['shared'],
                $translator,
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
     * of the classic edit screen; activating the instance resets the rewrite rules (LifecycleRegistrar).
     *
     * @param non-empty-string|null $rewrite Hash of the rewrite-relevant declarations.
     * @param non-empty-string      $option  The option with the hash of the rewrite rules last flushed for.
     */
    private function registerPosts(PostStore $store, ?string $rewrite, string $option, Translator $translator): PostTypeRegistrar
    {
        $registrar = new PostTypeRegistrar($store, $option, $rewrite, $translator);
        add_action('init', $registrar->register(...));
        add_action('init', $registrar->flushRewriteRules(...), PHP_INT_MAX);

        if ($store->getMeta() === []) {
            return $registrar;
        }
        $boxes = new MetaBoxes($this->slug, $store, $this->directory . '/' . self::BUILD_DIRECTORY, $this->directoryUri . '/' . self::BUILD_DIRECTORY, $translator);
        add_action('add_meta_boxes', $boxes->add(...));
        add_action('save_post', $boxes->save(...), 10, 2);
        add_action('admin_notices', $boxes->notices(...));
        add_action('admin_enqueue_scripts', $boxes->enqueue(...));

        return $registrar;
    }

    /**
     * Name of the WP-Cron event that deletes expired cache files and compiled templates of older releases of an instance.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function cacheCleanUpEvent(string $slug): string
    {
        return 'wp_app_framework/cache/clean_up/' . $slug;
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
            // Development mode only, so reflection is fine: the package directory of the loaded Twig, e.g. another
            // consumer or the root vendor/ of a Bedrock site.
            $file = (string) new \ReflectionClass(Environment::class)->getFileName();
            throw new LogicException(sprintf(
                'The %s %s requires Twig %s, but Twig %s is loaded from %s. In development mode, whoever loads Twig first provides it for all consumers – another consumer, or the root vendor/ of a Bedrock site; install the same Twig version in each of them.',
                $this->kind->getLabel(),
                $this->slug,
                $twig,
                Environment::VERSION,
                realpath(dirname($file, 2)) ?: dirname($file, 2),
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
     * @return array{0: ManifestData, 1: list<array{0: string, 1: list<string>}>} The manifest and the code generation
     *                                                                         entries of the directories it was built
     *                                                                         from (CodeGeneration::ofEntries()).
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
            static fn(): ManifestBuilder => ManifestBuilder::forProject($projectRoot),
        );

        $manifest = $cache->load();

        return [$manifest, $cache->getSourceEntries()];
    }
}
