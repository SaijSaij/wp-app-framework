<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Closure;
use LogicException;

/**
 * Minimal service container driven by the service data of the manifest:
 * per class, the services injected into its constructor parameters.
 *
 * @internal Only manifest entries use the container; theme code receives its
 *           dependencies through constructor and hook parameters.
 */
#[NoService]
final class Container
{
    /** @var array<class-string, object> */
    private array $instances;

    /**
     * @param array<class-string, array<string, class-string>> $services  Class => constructor parameter => injected service.
     * @param array<class-string, object>                       $instances Predefined instances.
     * @param array<class-string, Closure(self): object>        $factories Predefined services that depend on runtime values
     *                                                                     and are created on first use.
     */
    public function __construct(
        private readonly array $services,
        array $instances = [],
        private readonly array $factories = [],
    ) {
        $this->instances = $instances;
    }

    /**
     * Returns the shared instance of a service, creating it on first use.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T Instances are stored under their class name. */
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $instance = ($this->factories[$id])($this);
            if (!$instance instanceof $id) {
                throw new LogicException(sprintf('The factory for %s returned %s.', $id, get_debug_type($instance)));
            }

            return $this->instances[$id] = $instance;
        }

        if (!isset($this->services[$id])) {
            throw new LogicException(sprintf(
                'Service %s is not in the manifest. Only services reachable from entry points such as #[Hook] are built; rebuild the manifest after code changes.',
                $id,
            ));
        }

        $arguments = [];
        foreach ($this->services[$id] as $parameter => $service) {
            $arguments[$parameter] = $this->get($service);
        }

        return $this->instances[$id] = new $id(...$arguments);
    }
}
