<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Hook;

use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;
use Throwable;

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
    ) {
    }

    /**
     * @param list<HookEntry> $hooks
     */
    public function register(array $hooks): void
    {
        foreach ($hooks as $hook) {
            add_filter(
                $hook['name'],
                fn (mixed ...$args): mixed => $this->invoke($hook, $args),
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
            if ($this->debug) {
                throw $exception;
            }

            error_log(sprintf(
                'wp-app-framework: %s %s on hook "%s" failed: %s: %s in %s:%d',
                $hook['filter'] ? 'filter' : 'action',
                $hook['class'] . '::' . $hook['method'] . '()',
                $hook['name'],
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            return $hook['filter'] ? ($args[0] ?? null) : null;
        }
    }
}
