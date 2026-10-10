<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\Api\PublicApiCollector;
use Saij\WpAppFramework\Api\PublicSignatureInspector;
use Saij\WpAppFramework\Block\BlockCollector;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Hook\HookCollector;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRouteCollector;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateCallableCollector;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Loads the classes of the PSR-4 sources, lets the entry point collectors find
 * their attributes and resolves the services reachable from them. Runs only in
 * the build and in development mode.
 */
#[NoService]
final class ManifestBuilder
{
    /**
     * @param list<Source>       $sources
     * @param list<string>       $trackedFiles Absolute paths of files whose content affects the build.
     * @param list<class-string> $predefined   Candidates provided at runtime instead of built.
     * @param string|null        $scriptDirectory Directory the consumer's build compiles scripts from (its `src/`).
     * @param non-empty-string|null  $slug              Slug of the project; null for builds of fixtures.
     * @param list<non-empty-string> $templatePlugins   Plugins whose public template callables the templates use.
     * @param list<string>           $foreignNamespaces Namespaces of dependencies, prefixed in a release.
     */
    public function __construct(
        private readonly array $sources,
        private readonly array $trackedFiles = [],
        private readonly array $predefined = [],
        private readonly ?string $scriptDirectory = null,
        private readonly ProjectKind $kind = ProjectKind::Theme,
        private readonly ?string $slug = null,
        private readonly array $templatePlugins = [],
        private readonly array $foreignNamespaces = [],
    ) {}

    /**
     * Builder for a theme or plugin: its PSR-4 directories and settings from
     * composer.json plus the framework itself.
     */
    public static function forProject(string $projectDirectory): self
    {
        $project = ComposerProject::load($projectDirectory);
        $root = $project->getDirectory();

        $framework = self::frameworkSource();
        $sources = [...$project->getSources(), $framework];

        $trackedFiles = [$project->getComposerFile()];
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
                $project->getKind() === ProjectKind::Plugin ? Plugin::class : Theme::class,
                TemplateRenderer::class,
                RenderContext::class,
            ],
            $scriptDirectory === false ? null : $scriptDirectory,
            $project->getKind(),
            $project->getSlug(),
            $project->getTemplatePlugins(),
            $project->getForeignNamespaces(),
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

        $resolver = ServiceResolver::forClasses($classes, $this->predefined);
        $values = new ValueSchemaBuilder($classes);
        $hookCollector = new HookCollector($resolver);
        $hooks = $hookCollector->collect($classes);
        $routeCollector = new RestRouteCollector($resolver, $values);
        $routes = $routeCollector->collect($classes);
        $blockCollector = new BlockCollector($resolver, $values, self::frameworkSource()->getDirectory(), $this->scriptDirectory);
        $blocks = $blockCollector->collect($classes);
        // The framework's namespace is prefixed in a release as well; also listed for builds without vendor/.
        $framework = self::frameworkSource();
        $signatures = new PublicSignatureInspector($classes, [...$this->foreignNamespaces, $framework->getPrefix()], $framework->getDirectory());
        $templatePlugins = [];
        foreach ($this->templatePlugins as $plugin) {
            $templatePlugins[ComposerProject::templatePrefix($plugin)] = $plugin;
        }
        $templateCallableCollector = new TemplateCallableCollector(
            $resolver,
            TemplateRenderer::getBuiltInNames(),
            $this->kind === ProjectKind::Plugin && $this->slug !== null ? ComposerProject::templatePrefix($this->slug) : null,
            $templatePlugins,
            $signatures,
        );
        $templateCallables = $templateCallableCollector->collect($classes);
        $publicApiCollector = new PublicApiCollector($resolver, $signatures, $this->kind);
        $publicApis = $publicApiCollector->collect($classes);

        $errors = [
            ...$errors,
            ...$hookCollector->errors(),
            ...$routeCollector->errors(),
            ...$templateCallableCollector->errors(),
            ...$publicApiCollector->errors(),
            ...$blockCollector->errors(),
            ...$values->errors(),
            ...$resolver->errors(),
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
            $publicApis,
            $templatePlugins,
        );
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
