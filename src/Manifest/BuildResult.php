<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Block\Markup\MarkupFiles;
use Saij\WpAppFramework\Cron\CronEventDefinition;
use Saij\WpAppFramework\Cron\CronTaskDefinition;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Hook\HookDefinition;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRouteDefinition;
use Saij\WpAppFramework\Twig\TemplateCallableDefinition;
use Saij\WpAppFramework\WpCli\CliCommandDefinition;

/**
 * Everything the builder found; input for the manifest generator and the dev cache.
 *
 * @phpstan-import-type ValueField from \Saij\WpAppFramework\Value\ValueFactory
 * @phpstan-import-type ScriptsData from \Saij\WpAppFramework\Asset\AssetScripts
 * @phpstan-import-type AssetEntry from \Saij\WpAppFramework\Asset\AssetCollector
 * @phpstan-import-type ServiceArgument from \Saij\WpAppFramework\DependencyInjection\Container
 * @phpstan-import-type SettingsGroupEntry from \Saij\WpAppFramework\Setting\SettingsCollector
 * @phpstan-import-type PostClassEntry from \Saij\WpAppFramework\Post\PostTypeCollector
 * @phpstan-import-type TaxonomyEntry from \Saij\WpAppFramework\Post\PostTypeCollector
 * @phpstan-import-type MetaEntry from \Saij\WpAppFramework\Post\PostTypeCollector
 * @phpstan-import-type BlockCategoryEntry from \Saij\WpAppFramework\Manifest\Manifest
 * @phpstan-import-type TablesData from \Saij\WpAppFramework\Table\TableCollector
 * @phpstan-import-type LifecycleData from \Saij\WpAppFramework\Lifecycle\LifecycleCollector
 * @phpstan-type PostData array{
 *     posts: array<class-string<\Saij\WpAppFramework\Post\AbstractPost>, PostClassEntry>,
 *     taxonomies: array<class-string, TaxonomyEntry>,
 *     meta: array<class-string, MetaEntry>,
 *     rewrite: non-empty-string|null
 * }
 */
#[NoService]
final readonly class BuildResult
{
    /**
     * @param list<Source>                                      $sources
     * @param list<string>                                      $trackedFiles Absolute paths of files whose content affects the build.
     * @param array<class-string, ReflectionClass<object>>      $classes      All scanned classes, interfaces, traits and enums.
     * @param list<class-string>                                $candidates   Service candidates, sorted.
     * @param array<class-string, array<string, ServiceArgument>>  $services     Reachable services: constructor parameter => injected service.
     * @param list<HookDefinition>                              $hooks
     * @param list<RestRouteDefinition>                         $routes
     * @param list<TemplateCallableDefinition>                  $templateCallables
     * @param list<BlockDefinition>                             $blocks
     * @param array<class-string, array<string, ValueField>>    $values       Value classes used by blocks and REST arguments.
     * @param non-empty-string|null                             $slug         Slug of the project; null for builds of fixtures.
     * @param non-empty-string                                  $namespace    Namespace of blocks, REST routes and settings options.
     * @param list<class-string>                                $publicApis   `#[PublicApi]` services of a plugin or theme.
     * @param array<non-empty-string, non-empty-string>         $templateProviders Template prefix => slug of the plugins and
     *                                                                         the parent theme whose public template callables
     *                                                                         the templates use.
     * @param non-empty-string|null                             $parent       Parent theme of a child theme.
     * @param string|null                                       $scriptDirectory Directory the consumer's build compiles scripts and
     *                                                                         styles from (its `src/`).
     * @param bool                                              $styles       The consumer has a stylesheet (`assets/styles.css`).
     * @param list<SettingsGroupEntry>                          $settings     Settings groups, ordered for the settings page.
     * @param string|null                                       $settingsTitle Title of the settings page (name of the theme or
     *                                                                         plugin); null without settings groups.
     * @param MarkupFiles|null                                  $markup       Checked block templates, template parts and patterns;
     *                                                                         null for plugins.
     * @param PostData                                          $posts        Post classes, taxonomies, meta classes and the hash of
     *                                                                         the rewrite-relevant declarations.
     * @param list<BlockCategoryEntry>                          $blockCategories Custom block categories, in registration order.
     * @param list<CronEventDefinition>                         $cronEvents   Recurring events (#[CronEvent]).
     * @param list<CronTaskDefinition>                          $cronTasks    Handlers of one-time tasks (#[CronTask]).
     * @param list<CliCommandDefinition>                        $cliCommands  `#[CliCommand]` services with their subcommands.
     * @param TablesData                                        $tables       Row classes with their tables, the hash of their
     *                                                                         structure and the version of the project.
     * @param LifecycleData                                     $lifecycle    Methods with #[OnActivation], #[OnDeactivation] and
     *                                                                         #[OnUninstall].
     * @param string|null                                       $assetDirectory The consumer's `assets/` directory, also if it
     *                                                                         does not exist; null for builds of fixtures.
     * @param ScriptsData                                       $scripts      Document views, editor scripts without a block and
     *                                                                         shared modules in the asset directory.
     * @param array<string, AssetEntry>                         $assets       Static files of the asset directory by name.
     * @param list<string>                                      $notices      Remarks for the output of the build.
     * @param bool                                              $stylePreload The stylesheet marks fonts for preloading.
     */
    public function __construct(
        private array $sources,
        private array $trackedFiles,
        private array $classes,
        private array $candidates,
        private array $services,
        private array $hooks,
        private array $routes,
        private array $templateCallables,
        private array $blocks,
        private array $values,
        private ProjectKind $kind,
        private ?string $slug,
        private string $namespace,
        private array $publicApis,
        private array $templateProviders,
        private ?string $parent = null,
        private ?string $scriptDirectory = null,
        private bool $styles = false,
        private array $settings = [],
        private ?string $settingsTitle = null,
        private ?MarkupFiles $markup = null,
        private array $posts = ['posts' => [], 'taxonomies' => [], 'meta' => [], 'rewrite' => null],
        private array $blockCategories = [],
        private array $cronEvents = [],
        private array $cronTasks = [],
        private array $cliCommands = [],
        private array $tables = ['schema' => null, 'version' => null, 'classes' => []],
        private array $lifecycle = ['activation' => [], 'deactivation' => [], 'uninstall' => []],
        private ?string $assetDirectory = null,
        private array $scripts = ['documentViews' => [], 'editorScripts' => [], 'shared' => []],
        private array $assets = [],
        private array $notices = [],
        private bool $stylePreload = false,
    ) {}

    public function hasStylePreload(): bool
    {
        return $this->stylePreload;
    }

    /**
     * @return list<string>
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /**
     * @return array<string, AssetEntry>
     */
    public function getAssets(): array
    {
        return $this->assets;
    }

    /**
     * @return ScriptsData
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }

    public function getAssetDirectory(): ?string
    {
        return $this->assetDirectory;
    }

    /**
     * @return LifecycleData
     */
    public function getLifecycle(): array
    {
        return $this->lifecycle;
    }

    /**
     * @return TablesData
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return list<CronEventDefinition>
     */
    public function getCronEvents(): array
    {
        return $this->cronEvents;
    }

    /**
     * @return list<CronTaskDefinition>
     */
    public function getCronTasks(): array
    {
        return $this->cronTasks;
    }

    /**
     * @return list<CliCommandDefinition>
     */
    public function getCliCommands(): array
    {
        return $this->cliCommands;
    }

    /**
     * @return list<BlockCategoryEntry>
     */
    public function getBlockCategories(): array
    {
        return $this->blockCategories;
    }

    /**
     * @return PostData
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    public function getMarkup(): ?MarkupFiles
    {
        return $this->markup;
    }

    /**
     * @return list<SettingsGroupEntry>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSettingsTitle(): ?string
    {
        return $this->settingsTitle;
    }

    public function getScriptDirectory(): ?string
    {
        return $this->scriptDirectory;
    }

    public function hasStyles(): bool
    {
        return $this->styles;
    }

    public function getKind(): ProjectKind
    {
        return $this->kind;
    }

    /**
     * @return non-empty-string|null
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @return non-empty-string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return list<class-string>
     */
    public function getPublicApis(): array
    {
        return $this->publicApis;
    }

    /**
     * @return array<non-empty-string, non-empty-string> Template prefix => slug of a plugin or the parent theme.
     */
    public function getTemplateProviders(): array
    {
        return $this->templateProviders;
    }

    /**
     * @return non-empty-string|null
     */
    public function getParent(): ?string
    {
        return $this->parent;
    }

    /**
     * @return list<Source>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @return list<string> Absolute paths of files whose content affects the build.
     */
    public function getTrackedFiles(): array
    {
        return $this->trackedFiles;
    }

    /**
     * @return array<class-string, ReflectionClass<object>> All scanned classes, interfaces, traits and enums.
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @return list<class-string> Service candidates, sorted.
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    /**
     * @return array<class-string, array<string, ServiceArgument>> Reachable services: constructor parameter => injected service.
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return list<HookDefinition>
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * @return list<RestRouteDefinition>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return list<TemplateCallableDefinition>
     */
    public function getTemplateCallables(): array
    {
        return $this->templateCallables;
    }

    /**
     * @return list<BlockDefinition>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return array<class-string, array<string, ValueField>> Value classes: constructor parameter => field.
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
