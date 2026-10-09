<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest\Permission;

use Closure;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A public static method decides, passed as first-class callable: `new Callback(self::canEdit(...))`.
 *
 * The method returns bool. Its parameters follow the rules of the route method:
 * services are injected, `WP_REST_Request` receives the request, all other
 * parameters must be request arguments of the route with the same name and type.
 * It runs as permission_callback, after WordPress validated and sanitized the
 * arguments. Static, because PHP allows first-class callables in attribute
 * arguments only for static methods.
 */
#[NoService]
final readonly class Callback implements Permission
{
    public function __construct(private Closure $callback)
    {
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
