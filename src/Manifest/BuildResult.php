<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;

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
     * @param array<class-string, array<string, class-string>> $services     Reachable services: constructor parameter => injected service.
     * @param list<HookDefinition>                              $hooks
     */
    public function __construct(
        public array $sources,
        public array $trackedFiles,
        public array $classes,
        public array $candidates,
        public array $services,
        public array $hooks,
    ) {
    }
}
