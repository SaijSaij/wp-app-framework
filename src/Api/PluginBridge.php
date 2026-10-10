<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Closure;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Twig\TemplateCallableKind;

/**
 * The only connection between framework instances: WordPress hooks named after
 * the slug of a consumer. Instances may run different, differently prefixed
 * framework versions, so the hook names, their arguments and the shape of
 * their results are a contract across versions; they may only be extended.
 *
 * - `wp_app_framework/init/{slug}` (action): fired once at the end of the boot.
 * - `wp_app_framework/api/{slug}` (filter, `null, class-string`): the public
 *   API object of a plugin, or null.
 * - `wp_app_framework/template/{slug}` (filter, `null, 'function'|'filter', name`):
 *   a public template callable of a plugin as `['callable' => Closure, 'html' => bool]`, or null.
 *
 * @internal
 *
 * @phpstan-type PublicTemplateCallable array{callable: Closure, html: bool}
 * @phpstan-import-type TemplateCallableEntry from \Saij\WpAppFramework\Twig\TemplateRenderer
 */
#[NoService]
final class PluginBridge
{
    private const string INIT_ACTION = 'wp_app_framework/init/';
    private const string API_FILTER = 'wp_app_framework/api/';
    private const string TEMPLATE_FILTER = 'wp_app_framework/template/';

    /**
     * Name of the action fired at the end of the boot of an instance.
     *
     * @return non-empty-string
     */
    public static function initAction(string $slug): string
    {
        return self::INIT_ACTION . $slug;
    }

    /**
     * Whether an instance with this slug has booted in this request.
     */
    public static function isBooted(string $slug): bool
    {
        return did_action(self::initAction($slug)) > 0;
    }

    /**
     * Provides the public APIs of a plugin to other consumers; each service is
     * created when it is first requested.
     *
     * @param list<class-string> $classes
     */
    public static function provideApis(string $slug, array $classes, Container $container): void
    {
        if ($classes === []) {
            return;
        }

        add_filter(self::API_FILTER . $slug, static function (mixed $api, mixed $class) use ($classes, $container): mixed {
            if ($api !== null || !is_string($class) || !in_array($class, $classes, true)) {
                return $api;
            }

            return $container->get($class);
        }, 10, 2);
    }

    /**
     * The public API of a plugin, or null if the plugin is not active.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public static function api(string $plugin, string $class): ?object
    {
        if (did_action('plugins_loaded') === 0) {
            throw new LogicException(sprintf(
                'The public API %s of plugin %s was requested before all plugins were loaded (plugins_loaded); resolve the service later, e.g. in a hook.',
                $class,
                $plugin,
            ));
        }

        $api = apply_filters(self::API_FILTER . $plugin, null, $class);
        if ($api === null) {
            if (self::isBooted($plugin)) {
                throw new LogicException(sprintf(
                    'Plugin %s is active but provides no public API %s; is the class marked #[PublicApi] in the installed version of the plugin?',
                    $plugin,
                    $class,
                ));
            }

            return null;
        }
        if (!$api instanceof $class) {
            throw new LogicException(sprintf('Plugin %s returned %s for its public API %s.', $plugin, get_debug_type($api), $class));
        }

        return $api;
    }

    /**
     * Provides the public template functions and filters of a plugin to the
     * templates of other consumers; the service is resolved on the first call.
     *
     * @param list<TemplateCallableEntry> $callables Public entries only.
     */
    public static function provideTemplateCallables(string $slug, array $callables, Container $container): void
    {
        if ($callables === []) {
            return;
        }

        add_filter(self::TEMPLATE_FILTER . $slug, static function (mixed $entry, mixed $kind, mixed $name) use ($callables, $container): mixed {
            if ($entry !== null) {
                return $entry;
            }
            foreach ($callables as $callable) {
                if ($callable['kind'] === $kind && $callable['name'] === $name) {
                    $class = $callable['class'];
                    $method = $callable['method'];

                    return [
                        'callable' => static fn(mixed ...$arguments): mixed => $container->get($class)->{$method}(...$arguments),
                        'html' => $callable['html'],
                    ];
                }
            }

            return $entry;
        }, 10, 3);
    }

    /**
     * A public template function or filter of a plugin.
     *
     * @return PublicTemplateCallable
     */
    public static function templateCallable(string $plugin, TemplateCallableKind $kind, string $name): array
    {
        if (!self::isBooted($plugin)) {
            throw new LogicException(sprintf(
                'Template %s "%s" belongs to plugin %s, which is not active; check with has_plugin(\'%s\') before using it.',
                $kind->value,
                $name,
                $plugin,
                $plugin,
            ));
        }

        $entry = apply_filters(self::TEMPLATE_FILTER . $plugin, null, $kind->value, $name);
        if ($entry === null) {
            throw new LogicException(sprintf('Plugin %s provides no public template %s "%s".', $plugin, $kind->value, $name));
        }
        if (!is_array($entry) || !($entry['callable'] ?? null) instanceof Closure || !is_bool($entry['html'] ?? null)) {
            throw new LogicException(sprintf('Plugin %s returned an invalid entry for its template %s "%s".', $plugin, $kind->value, $name));
        }

        return ['callable' => $entry['callable'], 'html' => $entry['html']];
    }
}
