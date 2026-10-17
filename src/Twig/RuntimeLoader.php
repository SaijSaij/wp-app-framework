<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use Twig\RuntimeLoader\RuntimeLoaderInterface;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

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
    ) {}

    public function load(string $class): ?object
    {
        if (!isset($this->classes[$class])) {
            return null;
        }
        /** @var class-string $class A key of $classes. */

        return $this->container->get($class);
    }
}
