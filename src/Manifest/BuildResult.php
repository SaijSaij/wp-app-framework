<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Hook\HookDefinition;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Rest\RestRouteDefinition;
use Saij\WpAppFramework\Twig\TemplateCallableDefinition;

/**
 * Everything the builder found; input for the manifest generator and the dev cache.
 */
#[NoService]
final readonly class BuildResult
{
    /**
     * @param list<Source>                                      $sources
     * @param list<string>                                      $trackedFiles Absolute paths of files whose content affects the build.
     * @param array<class-string, ReflectionClass<object>>      $classes      All scanned classes, interfaces, traits and enums.
     * @param list<class-string>                                $candidates   Service candidates, sorted.
     * @param array<class-string, array<string, class-string>>  $services     Reachable services: constructor parameter => injected service.
     * @param list<HookDefinition>                              $hooks
     * @param list<RestRouteDefinition>                         $routes
     * @param list<TemplateCallableDefinition>                  $templateCallables
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
    ) {
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
     * @return array<class-string, array<string, class-string>> Reachable services: constructor parameter => injected service.
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
}
