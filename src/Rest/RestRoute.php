<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest;

use Attribute;
use WebCodingCat\WpAppFramework\Rest\Permission\Permission;

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
 *
 * @api Attributes/REST
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RestRoute
{
    /**
     * @param string                      $path       Empty or starting with a slash, e.g. `/{id}`.
     * @param HttpMethod|list<HttpMethod> $methods    The HTTP method or methods the route answers, e.g.
     *                                                `HttpMethod::Get` or `[HttpMethod::Put, HttpMethod::Patch]`.
     * @param Permission                  $permission Who may call the route: PublicAccess, Capability or Callback.
     */
    public function __construct(
        public string $path,
        public HttpMethod|array $methods,
        public Permission $permission,
    ) {}
}
