<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Hook;

use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Manifest\Manifest;

/**
 * Registers the hooks of the manifest with WordPress.
 *
 * Services are resolved only when a hook fires. Errors are rethrown when
 * debugging is enabled; otherwise they are logged and filters return their
 * original value.
 *
 * @internal
 *
 * @phpstan-import-type HookEntry from Manifest
 */
#[NoService]
final readonly class HookRegistrar
{
    public function __construct(
        private Container $container,
        private bool $debug,
    ) {}

    /**
     * @param list<HookEntry> $hooks
     */
    public function register(array $hooks): void
    {
        foreach ($hooks as $hook) {
            add_filter(
                $hook['name'],
                fn(mixed ...$args): mixed => $this->invoke($hook, array_values($args)),
                $hook['priority'],
                $hook['acceptedArgs'],
            );
        }
    }

    /**
     * @param HookEntry   $hook
     * @param list<mixed> $args The arguments passed by WordPress.
     */
    public function invoke(array $hook, array $args): mixed
    {
        try {
            $named = [];
            foreach ($hook['arguments'] as $argument) {
                if (isset($argument['service'])) {
                    $named[$argument['param']] = $this->container->get($argument['service']);
                } elseif (array_key_exists($argument['index'], $args)) {
                    // Passed only if present, so parameter defaults apply when a hook is fired with fewer
                    // arguments, and missing required ones fail like with a plain callback.
                    $named[$argument['param']] = $args[$argument['index']];
                }
            }

            return $this->container->get($hook['class'])->{$hook['method']}(...$named);
        } catch (Throwable $exception) {
            self::rethrowOrLog($exception, $this->debug, sprintf(
                '%s %s on hook "%s"',
                $hook['filter'] ? 'filter' : 'action',
                $hook['class'] . '::' . $hook['method'] . '()',
                $hook['name'],
            ));

            return $hook['filter'] ? ($args[0] ?? null) : null;
        }
    }

    /**
     * Rethrows an exception of a callback when debugging is enabled, otherwise logs it.
     *
     * @param string $callback What failed, e.g. `action Foo::bar() on hook "init"`.
     * @throws Throwable With debugging.
     */
    public static function rethrowOrLog(Throwable $exception, bool $debug, string $callback): void
    {
        if ($debug) {
            throw $exception;
        }

        error_log(sprintf(
            'wp-app-framework: %s failed: %s: %s in %s:%d',
            $callback,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));
    }
}
