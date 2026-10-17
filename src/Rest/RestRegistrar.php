<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest;

use Closure;
use LogicException;
use Throwable;
use WebCodingCat\WpAppFramework\Cache\CacheStore;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers the REST routes of the manifest with WordPress (on rest_api_init).
 *
 * Services are resolved only when a route is called. A route with #[Cache]
 * answers from the cache store: data as returned, a WP_REST_Response with its
 * status and headers; errors and responses with a status of 400 or more are
 * not stored. A RestException becomes a
 * WP_Error with its status; any other exception is logged and answered with
 * 500 – including details and stack trace only when debugging is enabled.
 *
 * @internal
 *
 * @phpstan-import-type RouteEntry from Manifest
 * @phpstan-import-type RestArgument from RestRouteDefinition
 */
#[NoService]
final readonly class RestRegistrar
{
    public const string INTERNAL_ERROR_CODE = 'wp_app_framework_internal_error';

    public function __construct(
        private Container $container,
        private ValueFactory $values,
        private bool $debug,
        private ?CacheStore $cache = null,
    ) {}

    /**
     * @param list<RouteEntry> $routes
     */
    public function register(array $routes): void
    {
        foreach ($routes as $route) {
            register_rest_route($route['namespace'], $route['route'], [
                'methods' => $route['methods'],
                'args' => $route['args'],
                'permission_callback' => $this->permissionCallback($route),
                'callback' => fn(WP_REST_Request $request): mixed => $this->respond($route, $request),
            ]);
        }
    }

    /**
     * @param RouteEntry $route
     */
    public function permissionCallback(array $route): callable
    {
        $permission = $route['permission'];

        if ($permission['type'] === 'public') {
            return static fn(): bool => true;
        }

        if ($permission['type'] === 'capability') {
            $capability = $permission['capability'];

            return static fn(): bool => current_user_can($capability);
        }

        return function (WP_REST_Request $request) use ($route, $permission): bool|WP_Error {
            $prepared = $this->preparePermissionArguments($route, $permission['arguments'], $request);
            if ($prepared === null) {
                return false;
            }

            return $this->call(
                $route,
                'permission callback',
                // The build checked that the callback is a public static method returning bool.
                fn(): bool => $permission['class']::{$permission['method']}(...$this->arguments($permission['arguments'], $request, $prepared)) === true,
            );
        };
    }

    /**
     * @param RouteEntry $route
     */
    public function respond(array $route, WP_REST_Request $request): mixed
    {
        $handler = fn(): mixed => $this->container->get($route['class'])->{$route['method']}(...$this->arguments($route['arguments'], $request, null));
        if ($route['cache'] !== null && $this->cache !== null) {
            $cache = $this->cache;
            $entry = $route['cache'];

            // Exceptions (also of a value that cannot be cached) become errors like without the cache.
            return $this->call($route, 'callback', static fn(): mixed => self::fromCache($cache->fetch(
                'rest',
                implode(',', $route['methods']) . ' /' . $route['namespace'] . $route['route'],
                self::cacheInputs($route['arguments'], $request),
                $entry,
                static fn(): array => self::toCache($handler()),
            )[0]));
        }

        $result = $this->call($route, 'callback', $handler);

        if ($route['void'] && !$result instanceof WP_Error) {
            return new WP_REST_Response(null, 204);
        }

        return $result;
    }

    /**
     * The cache entry of a response and whether it may be stored: data as returned, a WP_REST_Response as its data,
     * status and headers, stored only with a status below 400.
     *
     * @return array{0: array{data: mixed, status?: int, headers?: array<string, string>}, 1: bool}
     */
    private static function toCache(mixed $result): array
    {
        if (!$result instanceof WP_REST_Response) {
            return [['data' => $result], true];
        }

        /** @var array<string, string> $headers */
        $headers = $result->get_headers();

        return [['data' => $result->get_data(), 'status' => $result->get_status(), 'headers' => $headers], $result->get_status() < 400];
    }

    private static function fromCache(mixed $entry): mixed
    {
        if (!is_array($entry) || !array_key_exists('data', $entry)) {
            throw new LogicException('A cached REST response has an unexpected format.');
        }
        if (!isset($entry['status'])) {
            return $entry['data'];
        }

        /** @var array{data: mixed, status: int, headers: array<string, string>} $entry Written by toCache(). */
        return new WP_REST_Response($entry['data'], $entry['status'], $entry['headers']);
    }

    /**
     * The request arguments a cached route depends on: the URL parameters and the arguments the request passes.
     *
     * @param list<RestArgument> $arguments
     * @return array<string, mixed>
     */
    private static function cacheInputs(array $arguments, WP_REST_Request $request): array
    {
        $url = $request->get_url_params();
        $inputs = [];
        foreach ($arguments as $argument) {
            $param = $argument['param'];
            if ($argument['kind'] === 'url') {
                $inputs[$param] = $url[$param] ?? null;
            } elseif ($argument['kind'] === 'param' && $request->has_param($param)) {
                $inputs[$param] = $request->get_param($param);
            }
        }

        return $inputs;
    }

    /**
     * Maps the request to the named arguments of a route or permission method.
     *
     * Path parameters come from the URL parameters (WordPress would prefer a query
     * argument of the same name in get_param()). Other request arguments are passed
     * only if present, so parameter defaults apply. Enums and lists are converted
     * by the value factory.
     *
     * @param list<RestArgument>        $arguments
     * @param array<string, mixed>|null $prepared  Validated and sanitized request arguments of a permission callback.
     * @return array<string, mixed>
     */
    private function arguments(array $arguments, WP_REST_Request $request, ?array $prepared): array
    {
        $url = $request->get_url_params();
        $named = [];

        foreach ($arguments as $argument) {
            $param = $argument['param'];

            if ($argument['kind'] === 'service') {
                $named[$param] = $this->container->get($argument['class']);
                continue;
            }
            if ($argument['kind'] === 'request') {
                $named[$param] = $request;
                continue;
            }

            if ($prepared !== null) {
                if (!array_key_exists($param, $prepared)) {
                    continue;
                }
                $value = $prepared[$param];
            } elseif ($argument['kind'] === 'url') {
                $value = $url[$param];
            } elseif ($request->has_param($param)) {
                $value = $request->get_param($param);
            } else {
                continue;
            }

            $named[$param] = $this->values->create($argument['value'], $value, $param);
        }

        return $named;
    }

    /**
     * Validates and sanitizes the request arguments a permission callback needs,
     * against the schema of its own route.
     *
     * WordPress validates and sanitizes a request only for the handler it
     * matched (and not at all if validation failed), but rest_send_allow_header()
     * calls the permission callbacks of all handlers of the route with that
     * request to compute the Allow header. Without this step a callback could
     * receive raw strings or invalid values.
     *
     * @param RouteEntry         $route
     * @param list<RestArgument> $arguments
     * @return array<string, mixed>|null Null if an argument is invalid or a required one is missing: the permission is denied.
     */
    private function preparePermissionArguments(array $route, array $arguments, WP_REST_Request $request): ?array
    {
        $url = $request->get_url_params();
        $prepared = [];

        foreach ($arguments as $argument) {
            if ($argument['kind'] !== 'url' && $argument['kind'] !== 'param') {
                continue;
            }

            $name = $argument['param'];
            $schema = $route['args'][$name];
            $isUrl = $argument['kind'] === 'url';

            if (!($isUrl ? array_key_exists($name, $url) : $request->has_param($name))) {
                if ($schema['required'] ?? false) {
                    return null;
                }
                continue;
            }

            $value = $isUrl ? $url[$name] : $request->get_param($name);
            if (is_wp_error(rest_validate_value_from_schema($value, $schema, $name))) {
                return null;
            }
            $prepared[$name] = rest_sanitize_value_from_schema($value, $schema, $name);
        }

        return $prepared;
    }

    /**
     * @template T
     * @param RouteEntry    $route
     * @param Closure(): T $call
     * @return T|WP_Error
     */
    private function call(array $route, string $part, Closure $call): mixed
    {
        try {
            return $call();
        } catch (RestException $exception) {
            return new WP_Error(
                $exception->getErrorCode(),
                $exception->getMessage(),
                ['status' => $exception->getStatus()] + $exception->getData(),
            );
        } catch (Throwable $exception) {
            error_log(sprintf(
                'wp-app-framework: REST route %s /%s%s (%s) %s failed: %s: %s in %s:%d',
                implode(',', $route['methods']),
                $route['namespace'],
                $route['route'],
                $route['class'] . '::' . $route['method'] . '()',
                $part,
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            $data = ['status' => 500];
            if ($this->debug) {
                $data['exception'] = [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ];
            }

            return new WP_Error(self::INTERNAL_ERROR_CODE, 'Internal server error.', $data);
        }
    }
}
