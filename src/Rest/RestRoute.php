<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use Attribute;
use Saij\WpAppFramework\Rest\Permission\Permission;

/**
 * Registers a public method of a `#[RestController]` service as a REST route.
 *
 * The route is the controller's base path followed by `$path`. `{name}`
 * placeholders match the method parameter of the same name. Parameters typed
 * with a service candidate are injected, a `WP_REST_Request` parameter receives
 * the request, all other parameters are request arguments of the same name;
 * their schema is derived from the parameter type, default value and `#[Param]`.
 *
 * A method returning `void` responds with 204 No Content.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RestRoute
{
    /**
     * @param string                       $path        Empty or starting with a slash, e.g. `/{id}`.
     * @param HttpMethod|list<HttpMethod>  $methods
     * @param Permission                   $permission  Who may call the route: PublicAccess, Capability or Callback.
     */
    public function __construct(
        public string $path,
        public HttpMethod|array $methods,
        public Permission $permission,
    ) {}
}
