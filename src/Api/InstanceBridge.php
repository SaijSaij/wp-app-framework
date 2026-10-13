<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Closure;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Twig\TemplateCallableKind;

/**
 * The only connection between framework instances: WordPress hooks named after
 * the slug of a consumer. Instances may run different, differently prefixed
 * framework versions, so the hook names, their arguments and the shape of
 * their results are a contract across versions; they may only be extended.
 *
 * - `wp_app_framework/init/{slug}` (action): fired once at the end of the boot.
 * - `wp_app_framework/namespace/{namespace}` (action): fired once when an instance with that namespace boots.
 * - `wp_app_framework/api/{slug}` (filter, `null, class-string`): the public
 *   API object of a plugin or theme, or null.
 * - `wp_app_framework/template/{slug}` (filter, `null, 'function'|'filter', name`):
 *   a public template callable of a plugin or theme as `['callable' => Closure, 'html' => bool]`, or null.
 *
 * @internal
 *
 * @phpstan-type PublicTemplateCallable array{callable: Closure, html: bool}
 * @phpstan-import-type TemplateCallableEntry from \Saij\WpAppFramework\Twig\TemplateRenderer
 */
#[NoService]
final class InstanceBridge
{
    private const string INIT_ACTION = 'wp_app_framework/init/';
    private const string NAMESPACE_ACTION = 'wp_app_framework/namespace/';
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
     * Claims a namespace for the booting instance: fires its action, unless an instance with the
     * namespace has already booted in this request.
     *
     * @return bool False if the namespace was claimed before.
     */
    public static function claimNamespace(string $namespace): bool
    {
        if (did_action(self::NAMESPACE_ACTION . $namespace) > 0) {
            return false;
        }
        do_action(self::NAMESPACE_ACTION . $namespace);

        return true;
    }

    /**
     * Provides the public APIs of a plugin or theme to other consumers; each service is
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
     * The public API of a plugin or theme, or null if it is not active.
     *
     * Plugins have all booted at `plugins_loaded`; a theme boots after its child
     * theme, so its API is available from `after_setup_theme` on.
     *
     * @template T of object
     * @param ProjectKind::Plugin|ProjectKind::Theme $kind     Kind of the provider.
     * @param non-empty-string                       $provider Slug of the plugin or theme.
     * @param class-string<T>                        $class
     * @return T|null
     */
    public static function api(ProjectKind $kind, string $provider, string $class): ?object
    {
        $ready = $kind === ProjectKind::Plugin ? 'plugins_loaded' : 'after_setup_theme';
        if (did_action($ready) === 0) {
            throw new LogicException(sprintf(
                'The public API %s of %s %s was requested before %s; resolve the service later, e.g. in a hook.',
                $class,
                $kind->getLabel(),
                $provider,
                $kind === ProjectKind::Plugin ? 'all plugins were loaded (plugins_loaded)' : 'the theme was loaded (after_setup_theme)',
            ));
        }

        $api = apply_filters(self::API_FILTER . $provider, null, $class);
        if ($api === null) {
            if (self::isBooted($provider)) {
                throw new LogicException(sprintf(
                    'The %s %s is active but provides no public API %s; is the class marked #[PublicApi] in the installed version of the %s?',
                    $kind->getLabel(),
                    $provider,
                    $class,
                    $kind->getLabel(),
                ));
            }

            return null;
        }
        if (!$api instanceof $class) {
            throw new LogicException(sprintf('The %s %s returned %s for its public API %s.', $kind->getLabel(), $provider, get_debug_type($api), $class));
        }

        return $api;
    }

    /**
     * Provides the public template functions and filters of a plugin or theme to the
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
     * A public template function or filter of a plugin or theme.
     *
     * @return PublicTemplateCallable
     */
    public static function templateCallable(string $provider, TemplateCallableKind $kind, string $name): array
    {
        if (!self::isBooted($provider)) {
            throw new LogicException(sprintf(
                'Template %s "%s" belongs to %s, which is not active; for a plugin, check with has_plugin(\'%s\') before using it.',
                $kind->value,
                $name,
                $provider,
                $provider,
            ));
        }

        $entry = apply_filters(self::TEMPLATE_FILTER . $provider, null, $kind->value, $name);
        if ($entry === null) {
            throw new LogicException(sprintf('%s provides no public template %s "%s".', $provider, $kind->value, $name));
        }
        if (!is_array($entry) || !($entry['callable'] ?? null) instanceof Closure || !is_bool($entry['html'] ?? null)) {
            throw new LogicException(sprintf('%s returned an invalid entry for its template %s "%s".', $provider, $kind->value, $name));
        }

        return ['callable' => $entry['callable'], 'html' => $entry['html']];
    }
}
