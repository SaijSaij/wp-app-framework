<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[RestRoute]` method.
 *
 * @phpstan-type RestArgument array{param: string, kind: 'service', class: class-string}
 *     |array{param: string, kind: 'request'}
 *     |array{param: string, kind: 'url'|'param', enum: class-string<\BackedEnum>|null, nullable: bool}
 * @phpstan-type RestPermission array{type: 'public'}
 *     |array{type: 'capability', capability: string}
 *     |array{type: 'callback', class: class-string, method: string, arguments: list<RestArgument>}
 */
#[NoService]
final readonly class RestRouteDefinition
{
    /**
     * @param list<string>                        $methods   HTTP methods.
     * @param array<string, array<string, mixed>> $args      Argument schemas for register_rest_route().
     * @param RestPermission                      $permission
     * @param class-string                        $class
     * @param list<RestArgument>                  $arguments Method parameters in declaration order.
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
    ) {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * The route as regular expression for register_rest_route(), e.g. `/items/(?P<id>\d+)`.
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
}
