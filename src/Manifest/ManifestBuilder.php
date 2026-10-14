<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\Api\PublicApiCollector;
use Saij\WpAppFramework\Api\PublicSignatureInspector;
use Saij\WpAppFramework\Asset\AssetDirectory;
use Saij\WpAppFramework\Asset\AssetScripts;
use Saij\WpAppFramework\Asset\OrphanScripts;
use Saij\WpAppFramework\Block\BlockCollector;
use Saij\WpAppFramework\Block\Markup\BlockMarkupValidator;
use Saij\WpAppFramework\Block\Markup\MarkupFiles;
use Saij\WpAppFramework\Block\Support\SupportCollector;
use Saij\WpAppFramework\Cache\CacheCollector;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\ChildTheme;
use Saij\WpAppFramework\Cron\CronCollector;
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Hook\HookCollector;
use Saij\WpAppFramework\Lifecycle\LifecycleCollector;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Post\PostMeta;
use Saij\WpAppFramework\Post\PostStore;
use Saij\WpAppFramework\Post\PostTypeCollector;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Project\NamePrefix;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Query\Field\Field;
use Saij\WpAppFramework\Query\Field\FieldTypes;
use Saij\WpAppFramework\Query\MetaFieldsCollector;
use Saij\WpAppFramework\Rest\RestRouteCollector;
use Saij\WpAppFramework\Setting\SettingsCollector;
use Saij\WpAppFramework\Style\StyleAssets;
use Saij\WpAppFramework\Table\Table;
use Saij\WpAppFramework\Table\TableCollector;
use Saij\WpAppFramework\Table\TableFieldsCollector;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateCallableCollector;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Saij\WpAppFramework\View\ViewData;
use Saij\WpAppFramework\WpCli\CliCommandCollector;
use Throwable;

/**
 * Loads the classes of the PSR-4 sources, lets the entry point collectors find
 * their attributes and resolves the services reachable from them. Runs only in
 * the build and in development mode.
 *
 * @phpstan-import-type Value from ValueFactory
 */
#[NoService]
final class ManifestBuilder
{
    /** Namespace of builds of fixtures, which have no composer.json. */
    public const string FIXTURE_NAMESPACE = 'fixture';

    /**
     * @param list<Source>       $sources
     * @param list<string>       $trackedFiles Absolute paths of files whose content affects the build.
     * @param list<class-string> $predefined   Candidates provided at runtime instead of built.
     * @param string|null        $scriptDirectory Directory the consumer's build compiles scripts and styles from (its `src/`).
     * @param non-empty-string|null  $slug              Slug of the project; null for builds of fixtures.
     * @param list<non-empty-string> $templateProviders Plugins and parent theme whose public template callables the templates use.
     * @param non-empty-string|null  $parent            Parent theme of a child theme.
     * @param list<string>           $foreignNamespaces Namespaces of dependencies, prefixed in a release.
     * @param string|null            $name              Name of the theme or plugin (header), the title of its settings page.
     * @param string|null            $markupDirectory   Theme or child theme whose block templates, template parts and
     *                                                  patterns are checked against its blocks; null for plugins.
     * @param non-empty-string       $namespace         Namespace of the blocks, REST routes and settings options
     *                                                  (`extra.wp-app-framework.namespace`); fixtures use `fixture`.
     * @param non-empty-string|null  $version           Version of the theme or plugin (header), stored with the tables
     *                                                  so that older code leaves tables synced by newer code alone.
     * @param string|null            $pluginDirectory   Directory of a plugin, which must not have an `uninstall.php`;
     *                                                  null for themes and fixtures.
     * @param string|null            $assetDirectory    The consumer's `assets/` directory, also if it does not exist yet
     *                                                  (scripts and styles without a PHP class, static files); null for
     *                                                  builds of fixtures.
     */
    public function __construct(
        private readonly array $sources,
        private readonly array $trackedFiles = [],
        private readonly array $predefined = [],
        private readonly ?string $scriptDirectory = null,
        private readonly ProjectKind $kind = ProjectKind::Theme,
        private readonly ?string $slug = null,
        private readonly array $templateProviders = [],
        private readonly array $foreignNamespaces = [],
        private readonly ?string $parent = null,
        private readonly ?string $name = null,
        private readonly ?string $markupDirectory = null,
        private readonly string $namespace = self::FIXTURE_NAMESPACE,
        private readonly ?string $version = null,
        private readonly ?string $pluginDirectory = null,
        private readonly ?string $assetDirectory = null,
    ) {}

    /**
     * Builder for a theme, child theme or plugin: its PSR-4 directories and settings from
     * composer.json plus the framework itself.
     */
    public static function forProject(string $projectDirectory): self
    {
        $project = ComposerProject::load($projectDirectory);
        $root = $project->getDirectory();

        $framework = self::frameworkSource();
        $sources = [...$project->getSources(), $framework];

        $trackedFiles = [$project->getComposerFile()];
        // The name of the theme or plugin is the title of its settings page.
        if (is_file($project->getHeaderFile())) {
            $trackedFiles[] = $project->getHeaderFile();
        }
        // Installed dependencies decide which namespaces a public signature must not use.
        $installed = $root . '/vendor/composer/installed.json';
        if (is_file($installed)) {
            $trackedFiles[] = $installed;
        }
        foreach (ClassDiscovery::files($framework->getDirectory()) as $relative) {
            $trackedFiles[] = $framework->getDirectory() . '/' . $relative;
        }

        $scriptDirectory = realpath($root . '/' . TemplateRenderer::TEMPLATE_DIRECTORY);

        return new self(
            $sources,
            $trackedFiles,
            [
                match ($project->getKind()) {
                    ProjectKind::Theme => Theme::class,
                    ProjectKind::ChildTheme => ChildTheme::class,
                    ProjectKind::Plugin => Plugin::class,
                },
                TemplateRenderer::class,
                RenderContext::class,
                CacheStore::class,
                CronScheduler::class,
                ViewData::class,
            ],
            $scriptDirectory === false ? null : $scriptDirectory,
            $project->getKind(),
            $project->getSlug(),
            $project->getTemplateProviders(),
            $project->getForeignNamespaces(),
            $project->getParent(),
            $project->getName(),
            // WordPress reads templates, template parts and patterns only from themes.
            $project->getKind() === ProjectKind::Plugin ? null : $root,
            $project->getNamespace(),
            version: $project->getVersion(),
            pluginDirectory: $project->getKind() === ProjectKind::Plugin ? $root : null,
            assetDirectory: $root . '/' . AssetDirectory::NAME,
        );
    }

    /**
     * The framework's own source directory. Derived from a framework class, so it
     * also matches the prefixed namespace and location of a release build.
     */
    public static function frameworkSource(): Source
    {
        $prefix = substr(Theme::class, 0, (int) strrpos(Theme::class, '\\') + 1);
        $directory = dirname((string) new ReflectionClass(Theme::class)->getFileName());

        return new Source($prefix, $directory);
    }

    public function build(): BuildResult
    {
        $errors = [];
        $classes = $this->loadClasses($errors);

        // The post store of the instance is provided at run time, also for the framework's own template function post().
        $resolver = ServiceResolver::forClasses(
            $classes,
            [...$this->predefined, PostStore::class],
            $this->parent,
            SettingsCollector::groupClasses($classes),
            // Task classes first: a post class keeps its own, more telling description.
            [...CronCollector::taskClasses($classes), ...PostTypeCollector::nonServices($classes), ...TableCollector::nonServices($classes)],
        );
        $postClasses = PostTypeCollector::postClasses($classes, $this->namespace);
        $taxonomyClasses = PostTypeCollector::taxonomyClasses($classes, $this->namespace);
        $values = new ValueSchemaBuilder($classes, $postClasses, $taxonomyClasses);
        $postTypeCollector = new PostTypeCollector($values, $this->namespace, self::frameworkSource()->getDirectory());
        $posts = $postTypeCollector->collect($classes);
        $metaFieldsCollector = new MetaFieldsCollector($posts['meta'], $values->values());
        $metaFieldsCollector->collect($classes);
        $tableCollector = new TableCollector($this->namespace);
        $tables = $tableCollector->collect($classes, $this->version);
        $tableFieldsCollector = new TableFieldsCollector($tables['classes']);
        $tableFieldsCollector->collect($classes);
        $settingsCollector = new SettingsCollector($values, self::frameworkSource()->getDirectory(), $this->namespace, $this->name);
        $settings = $settingsCollector->collect($classes);
        $hookCollector = new HookCollector($resolver);
        $hooks = $hookCollector->collect($classes);
        $cronCollector = new CronCollector($resolver, $values);
        $cronCollector->collect($classes);
        $lifecycleCollector = new LifecycleCollector($resolver, $this->kind);
        $lifecycle = $lifecycleCollector->collect($classes, $this->pluginDirectory);
        $cacheCollector = new CacheCollector($resolver, new TagNames(
            CacheCollector::settingsGroups($classes),
            array_map(static fn(array $post): string => $post['name'], $postClasses),
            $taxonomyClasses,
        ));
        $cacheCollector->collect($classes);
        $routeCollector = new RestRouteCollector($resolver, $values, $this->namespace, $cacheCollector);
        $routes = $routeCollector->collect($classes);
        $cliCommandCollector = new CliCommandCollector($resolver, $values, $this->namespace);
        $cliCommands = $cliCommandCollector->collect($classes);
        $supportCollector = new SupportCollector($resolver, $values, self::frameworkSource()->getDirectory(), $this->scriptDirectory);
        $supportCollector->collect($classes);
        $blockCollector = new BlockCollector($resolver, $values, $supportCollector, $this->namespace, self::frameworkSource()->getDirectory(), $this->scriptDirectory, $cacheCollector);
        $blocks = $blockCollector->collect($classes);
        $markup = $this->markupDirectory !== null ? MarkupFiles::scan($this->markupDirectory) : null;
        $markupValidator = new BlockMarkupValidator($this->namespace, $blocks);
        // Checked only against complete block definitions; otherwise the markup would report follow-up errors.
        if ($markup !== null && $blockCollector->errors() === [] && $supportCollector->errors() === [] && $values->errors() === []) {
            $markupValidator->validate($markup);
        }
        // The framework's namespace is prefixed in a release as well; also listed for builds without vendor/.
        $framework = self::frameworkSource();
        $signatures = new PublicSignatureInspector($classes, [...$this->foreignNamespaces, $framework->getPrefix()], $framework->getDirectory());
        $templateProviders = [];
        foreach ($this->templateProviders as $provider) {
            $templateProviders[NamePrefix::template($provider)] = $provider;
        }
        $templateCallableCollector = new TemplateCallableCollector(
            $resolver,
            TemplateRenderer::getBuiltInNames(),
            $this->kind->isProvider() && $this->slug !== null ? NamePrefix::template($this->slug) : null,
            $templateProviders,
            $signatures,
        );
        $templateCallables = $templateCallableCollector->collect($classes);
        $publicApiCollector = new PublicApiCollector($resolver, $signatures, $this->kind);
        $publicApis = $publicApiCollector->collect($classes);
        $assetScripts = new AssetScripts($this->slug);
        $scripts = $assetScripts->collect($this->assetDirectory);

        $errors = [
            ...$errors,
            ...$hookCollector->errors(),
            ...$cronCollector->errors(),
            ...$lifecycleCollector->errors(),
            ...$routeCollector->errors(),
            ...$cliCommandCollector->errors(),
            ...$cacheCollector->errors(),
            ...$templateCallableCollector->errors(),
            ...$publicApiCollector->errors(),
            ...$supportCollector->errors(),
            ...$blockCollector->errors(),
            ...$markupValidator->errors(),
            ...$settingsCollector->errors(),
            ...$postTypeCollector->errors(),
            ...$metaFieldsCollector->errors(),
            ...$tableCollector->errors(),
            ...$tableFieldsCollector->errors(),
            ...$values->errors(),
            ...$resolver->errors(),
            ...$assetScripts->errors(),
            ...StyleAssets::sourceErrors($this->scriptDirectory),
            ...OrphanScripts::errors($classes, $this->scriptDirectory),
        ];
        if ($errors !== []) {
            throw new BuildException($errors);
        }

        return new BuildResult(
            $this->sources,
            $this->trackedFiles,
            $classes,
            $resolver->candidates(),
            $resolver->resolved(),
            $hooks,
            $routes,
            $templateCallables,
            $blocks,
            $values->values(),
            $this->kind,
            $this->slug,
            $this->namespace,
            $publicApis,
            $templateProviders,
            $this->parent,
            $this->scriptDirectory,
            $this->assetDirectory !== null && is_file($this->assetDirectory . '/' . StyleAssets::SOURCE),
            $settings,
            $settings === [] ? null : $this->name,
            $markup,
            $posts,
            $blockCollector->categories(),
            cronEvents: $cronCollector->events(),
            cronTasks: $cronCollector->tasks(),
            cliCommands: $cliCommands,
            tables: $tables,
            lifecycle: $lifecycle,
            assetDirectory: $this->assetDirectory,
            scripts: $scripts,
        );
    }

    /**
     * The values of the fields of a meta class (#[PostMeta]) by field, for `wp-app-framework meta-fields`. Checks the
     * meta class only, so the fields of a changed meta class can be generated while the build reports other errors.
     *
     * @param class-string $metaClass
     * @return array<string, Value>
     * @throws BuildException If the class is no valid meta class.
     */
    public function metaFieldValues(string $metaClass): array
    {
        $errors = [];
        $classes = $this->loadClasses($errors);
        $reflection = $classes[$metaClass] ?? null;
        if ($reflection === null || $reflection->getAttributes(PostMeta::class) === []) {
            throw new BuildException([sprintf('%s is no meta class (#[PostMeta]) of the PSR-4 sources of this theme or plugin.', $metaClass)]);
        }
        $values = new ValueSchemaBuilder(
            $classes,
            PostTypeCollector::postClasses($classes, $this->namespace),
            PostTypeCollector::taxonomyClasses($classes, $this->namespace),
        );
        $fields = $values->metaFields($metaClass, sprintf('#[PostMeta] on %s', $metaClass));
        if ($fields === null) {
            $errors = $values->errors();
            throw new BuildException($errors !== [] ? $errors : [sprintf('%s is no valid meta class.', $metaClass)]);
        }

        return array_map(static fn(array $field): array => $field['value'], $fields);
    }

    /**
     * The field class and enum of every column of a row class (#[Table]) by property, for `wp-app-framework
     * table-fields`. Checks the tables only, so the fields of a changed row class can be generated while the build
     * reports other errors.
     *
     * @param class-string $rowClass
     * @return array<string, array{class-string<Field>, class-string|null}>
     * @throws BuildException If the class is no valid row class.
     */
    public function tableFieldValues(string $rowClass): array
    {
        $errors = [];
        $classes = $this->loadClasses($errors);
        $reflection = $classes[$rowClass] ?? null;
        if ($reflection === null || $reflection->getAttributes(Table::class) === []) {
            throw new BuildException([sprintf('%s is no row class (#[Table]) of the PSR-4 sources of this theme or plugin.', $rowClass)]);
        }
        $collector = new TableCollector($this->namespace);
        $table = $collector->collect([$rowClass => $reflection], $this->version)['classes'][$rowClass] ?? null;
        if ($table === null) {
            $errors = $collector->errors();
            throw new BuildException($errors !== [] ? $errors : [sprintf('%s is no valid row class.', $rowClass)]);
        }
        $fields = [];
        foreach ($table['columns'] as $property => $column) {
            $fields[$property] = FieldTypes::column($column, $property === $table['primaryKey']);
        }

        return $fields;
    }

    /**
     * @param list<string> $errors
     * @return array<class-string, ReflectionClass<object>>
     */
    private function loadClasses(array &$errors): array
    {
        $classes = [];

        foreach ($this->sources as $source) {
            try {
                $found = ClassDiscovery::classes($source);
            } catch (BuildException $exception) {
                array_push($errors, ...$exception->getErrors());
                continue;
            }

            foreach ($found as $file => $name) {
                if (isset($classes[$name])) {
                    continue;
                }

                try {
                    $exists = class_exists($name)
                        || interface_exists($name, false)
                        || trait_exists($name, false)
                        || enum_exists($name, false);
                } catch (Throwable $exception) {
                    $errors[] = sprintf('Cannot load %s from %s: %s', $name, $file, $exception->getMessage());
                    continue;
                }

                if (!$exists) {
                    $errors[] = sprintf('%s does not declare %s as expected by PSR-4.', $file, $name);
                    continue;
                }

                $classes[$name] = new ReflectionClass($name);
            }
        }

        return $classes;
    }
}
