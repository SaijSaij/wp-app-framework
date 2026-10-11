<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Closure;
use LogicException;
use Saij\WpAppFramework\Api\InstanceBridge;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Setting\SettingsStore;

/**
 * Minimal service container driven by the service data of the manifest:
 * per class, the services injected into its constructor parameters.
 *
 * @internal Only manifest entries use the container; consumer code receives its
 *           dependencies through constructor and hook parameters.
 *
 * @phpstan-type ServiceArgument class-string|array{provider: non-empty-string, kind: 'plugin'|'theme', class: class-string}|array{setting: class-string}
 *               A service, the public API of a plugin (`#[PluginApi]`) or of the parent theme (`#[ThemeApi]`), or a
 *               settings group (`#[SettingsGroup]`).
 */
#[NoService]
final class Container
{
    /** @var array<class-string, object> */
    private array $instances;

    /**
     * @param array<class-string, array<string, ServiceArgument>> $services Class => constructor parameter => injected service.
     * @param array<class-string, object>                       $instances Predefined instances.
     * @param array<class-string, Closure(self): object>        $factories Predefined services that depend on runtime values
     *                                                                     and are created on first use.
     * @param SettingsStore|null                                $settings  The settings groups of the instance.
     */
    public function __construct(
        private readonly array $services,
        array $instances = [],
        private readonly array $factories = [],
        private readonly ?SettingsStore $settings = null,
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
            $arguments[$parameter] = match (true) {
                !is_array($service) => $this->get($service),
                isset($service['setting']) => $this->getSettings()->get($service['setting']),
                default => InstanceBridge::api($service['kind'] === 'plugin' ? ProjectKind::Plugin : ProjectKind::Theme, $service['provider'], $service['class']),
            };
        }

        return $this->instances[$id] = new $id(...$arguments);
    }

    private function getSettings(): SettingsStore
    {
        return $this->settings ?? throw new LogicException('The manifest injects a settings group, but the instance has no settings; rebuild the manifest after code changes.');
    }
}
