<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Closure;
use LogicException;

/**
 * Minimal service container driven by the factories of the manifest.
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
     * @param array<class-string, Closure(self): object> $factories
     * @param array<class-string, object>                $instances Predefined instances.
     */
    public function __construct(
        private readonly array $factories,
        array $instances = [],
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
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new LogicException(sprintf(
                'Service %s is not in the manifest. Only services reachable from entry points such as #[Hook] are built; rebuild the manifest after code changes.',
                $id,
            ));
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
