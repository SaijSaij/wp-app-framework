<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * Lets Twig resolve the services behind template functions and filters from the
 * container when a template calls them for the first time.
 *
 * @internal
 */
#[NoService]
final readonly class RuntimeLoader implements RuntimeLoaderInterface
{
    /**
     * @param array<class-string, true> $classes Services that provide template callables.
     */
    public function __construct(
        private Container $container,
        private array $classes,
    ) {
    }

    public function load(string $class): ?object
    {
        if (!isset($this->classes[$class])) {
            return null;
        }

        return $this->container->get($class);
    }
}
