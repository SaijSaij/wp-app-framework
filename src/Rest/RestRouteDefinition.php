<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[RestRoute]` method.
 *
 * @phpstan-import-type Value from \WebCodingCat\WpAppFramework\Value\ValueFactory
 * @phpstan-type RestArgument array{param: string, kind: 'service', class: class-string}
 *     |array{param: string, kind: 'request'}
 *     |array{param: string, kind: 'url'|'param', value: Value}
 * @phpstan-type RestPermission array{type: 'public'}
 *     |array{type: 'capability', capability: string}
 *     |array{type: 'callback', class: class-string, method: string, arguments: list<RestArgument>}
 * @phpstan-import-type CacheEntry from \WebCodingCat\WpAppFramework\Cache\CachePolicy
 */
#[NoService]
final readonly class RestRouteDefinition
{
    /**
     * @param non-falsy-string                    $namespace
     * @param non-falsy-string                    $route
     * @param list<string>                        $methods   HTTP methods.
     * @param array<string, array<string, mixed>> $args      Argument schemas for register_rest_route().
     * @param RestPermission                      $permission
     * @param class-string                        $class
     * @param list<RestArgument>                  $arguments Method parameters in declaration order.
     * @param CacheEntry|null                     $cache     Ttl, tags and variants of `#[Cache]`; null if not cached.
     */
    public function __construct(
        private string $namespace,
        private string $route,
        private array $methods,
        private array $args,
        private array $permission,
        private string $class,
        private string $method,
        private array $arguments,
        private bool $void,
        private ?array $cache = null,
    ) {}

    /**
     * @return non-falsy-string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * The route as regular expression for register_rest_route(), e.g. `/items/(?P<id>\d+)`.
     *
     * @return non-falsy-string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return RestPermission
     */
    public function getPermission(): array
    {
        return $this->permission;
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return list<RestArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * True if the method returns void; the route then responds with 204 No Content.
     */
    public function isVoid(): bool
    {
        return $this->void;
    }

    /**
     * @return CacheEntry|null
     */
    public function getCache(): ?array
    {
        return $this->cache;
    }
}
