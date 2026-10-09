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
     * @param list<mixed> $args
     */
    public function invoke(array $hook, array $args): mixed
    {
        try {
            return ($hook['callback'])($this->container, $args);
        } catch (Throwable $exception) {
            if ($this->debug) {
                throw $exception;
            }

            error_log(sprintf(
                'wp-app-framework: %s %s on hook "%s" failed: %s: %s in %s:%d',
                $hook['filter'] ? 'filter' : 'action',
                $hook['source'],
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
