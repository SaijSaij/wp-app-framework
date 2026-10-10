<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Hook\HookDefinition;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Rest\RestRouteDefinition;
use Saij\WpAppFramework\Twig\TemplateCallableDefinition;

/**
 * Everything the builder found; input for the manifest generator and the dev cache.
 *
 * @phpstan-import-type ValueField from \Saij\WpAppFramework\Value\ValueFactory
 * @phpstan-import-type ServiceArgument from \Saij\WpAppFramework\DependencyInjection\Container
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
     * @param list<class-string>                                $publicApis   `#[PublicApi]` services of a plugin.
     * @param array<non-empty-string, non-empty-string>         $templatePlugins Template prefix => slug of the plugins whose
     *                                                                         public template callables the templates use.
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
        private array $publicApis,
        private array $templatePlugins,
    ) {}

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
     * @return list<class-string>
     */
    public function getPublicApis(): array
    {
        return $this->publicApis;
    }

    /**
     * @return array<non-empty-string, non-empty-string> Template prefix => plugin slug.
     */
    public function getTemplatePlugins(): array
    {
        return $this->templatePlugins;
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
