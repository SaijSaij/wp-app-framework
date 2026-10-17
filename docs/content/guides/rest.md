# REST routes

A REST route in WordPress needs more code than it seems. You call `register_rest_route()`, write an `args` schema for validation, add a permission callback, read and convert the parameters, and turn errors into `WP_Error` objects with the right status code. Forgetting the permission callback makes the route public.

With the framework, a route is a method with `#[RestRoute]`. The framework derives the argument schema from the method signature, so WordPress validates and sanitizes the arguments before your code runs. The permission is a required argument of the attribute, so your IDE and the build report a route without one.

## Controller and routes

Routes live in a controller class. `#[RestController]` on the class sets the REST namespace and an optional base path for all its routes:

```php
use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestException;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

#[RestController('v1', base: '/items')]
final class ItemController
{
    #[RestRoute('/{id}', HttpMethod::Get, new PublicAccess())]
    public function show(int $id, ItemRepository $items, Variant $variant = Variant::Short): array
    {
        return $items->find($id) ?? throw new RestException('sections_item_not_found', 'Item not found.', 404);
    }
}
```

With the namespace `sections` (`extra.wp-app-framework.namespace` in `composer.json`), this registers `GET /wp-json/sections/v1/items/{id}`. Read the method signature from left to right:

- `int $id` matches the placeholder `{id}` in the path. Because it is an `int`, the route only matches digits.
- `ItemRepository $items` is a [service](./services.md), so the framework injects it.
- `Variant $variant = Variant::Short` is a backed enum with a default, so it becomes an optional request argument (`?variant=long`). WordPress accepts only the enum's values, and the method receives the enum case.

The method returns an array, which becomes a JSON response with status 200. If the item does not exist, it throws a `RestException` with status 404.

[`#[RestController(version, base: '')]`](../api/php/Rest/RestController.md) takes the version without leading or trailing slash, for example `v1`; the value may be a class constant. The routes are registered under `{namespace}/{version}`, where the namespace is the one of your theme or plugin (see [Concepts](../concepts.md#consumers-and-instances)). A [`#[RestRoute]`](../api/php/Rest/RestRoute.md) in a class without [`#[RestController]`](../api/php/Rest/RestController.md) is a build error.

[`#[RestRoute(path, methods, permission)]`](../api/php/Rest/RestRoute.md) is repeatable, and all three arguments are required, so the IDE and the build both report a missing permission. `path` is empty or starts with `/`. `methods` is an `HttpMethod` case or a list of them.

## Permission

Every route says who may call it. There are three kinds of permission, all in the namespace `WebCodingCat\WpAppFramework\Rest\Permission`:

```php
new PublicAccess()                      // everybody
new Capability('delete_posts')          // current_user_can('delete_posts')
new Callback(self::canEdit(...))        // your own check
```

A callback decides with your own logic. It is a first-class callable of a public **static** method that returns `bool`. It must be static because PHP allows first-class callables in attribute arguments only for static methods.

```php
#[RestRoute('/{id}', [HttpMethod::Put, HttpMethod::Patch], new Callback(self::canEdit(...)))]
public function update(int $id, string $title): WP_REST_Response { /* … */ }

public static function canEdit(int $id, ItemRepository $items): bool
{
    return current_user_can('edit_post', $items->getPostId($id));
}
```

The callback's parameters follow the same rules as those of the route: services are injected, and request arguments must exist in the route with the same name and type. Before calling it, the framework validates and sanitizes these arguments against the route's own schema and denies access if one is invalid. That matters because WordPress also calls permission callbacks to build the `Allow` header, with a request validated for a different route handler.

## Path placeholders

`{name}` in the path matches the parameter with the same name. That parameter must be required and of type `int` (matches `\d+`), `string` (`[^/]+`) or a backed enum (its values). Its value always comes from the URL, never from a query argument with the same name.

Route paths may only contain letters, digits, `.`, `_`, `~`, `-`, `/` and placeholders.

## Parameters

The framework looks at each parameter of the method and decides where its value comes from:

- **A service candidate** is injected.
- **`WP_REST_Request`** receives the request itself.
- **Everything else is a request argument** with the parameter's name: `int`, `float`, `string`, `bool`, a backed enum, or an `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` (see [Values](./values.md)). Each may be nullable and have a default value.

A single value class without `#[ListOf]` is not supported as a request argument, because a parameter with a class type is treated as a service.

From the request arguments the framework builds the route's argument schema (`type`, `enum`, `items`, `required`, `default`, and `minItems` and `maxItems` from the [limits of lists](./values.md#lists)). WordPress then does the work it always does with a schema: it validates and sanitizes the arguments, answers with 400 if one is invalid, and shows the schema on an `OPTIONS` request. Missing optional arguments get the parameter's default. Enums, value objects and lists are converted by the value factory, so your method receives typed values.

`#[Param]` (`WebCodingCat\WpAppFramework\Rest\Param`) adds further constraints to the schema:

```php
public function update(
    int $id,
    #[Param(maxLength: 200)] string $title,
    #[Param(format: Format::Email)] ?string $contact = null,
): WP_REST_Response
```

Its arguments are `description`, `minimum`, `maximum`, `minLength`, `maxLength`, `pattern` and `format` (a case of `WebCodingCat\WpAppFramework\Rest\Format`). The build checks that each one applies to the parameter's type.

## Responses and errors

What the method returns decides the response:

- A `void` method responds with **204 No Content**.
- Any other value goes through `rest_ensure_response()` and responds with **200**.
- For another status code or extra headers, return a `WP_REST_Response`, e.g. `new WP_REST_Response($data, 201)`.

```php
#[RestRoute('/{id}', HttpMethod::Delete, new Capability('delete_posts'))]
public function delete(int $id): void
{
    // … responds with 204 No Content
}
```

For an error response, throw `RestException(code, message, status, data)` with a status between 400 and 599. The response adds the status to the data, so `data` must not have a key `status` of its own; that is an `InvalidArgumentException`. Any other exception is an unexpected error. It is logged with `error_log()` and answered with status 500 and the code `wp_app_framework_internal_error`. Class, message, file, line and stack trace of the exception are included in the response only if `WP_DEBUG` is enabled, so a live site never shows its internals.

Like hooks, the controller and its services are created only when the route is called.

## Caching

A `GET` route can keep its response with `#[Cache]`, plain data or a `WP_REST_Response` with a status below 400: the arguments of the method are part of the key, the permission check still runs on every request, and errors are never stored. See [Cache](./cache.md#a-rest-route).
