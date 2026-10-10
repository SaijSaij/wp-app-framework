# REST routes

```php
use Saij\WpAppFramework\Rest\Format;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\Capability;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestException;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('sections/v1', base: '/items')]
final class ItemController
{
    #[RestRoute('/{id}', HttpMethod::Get, new PublicAccess())]
    public function show(int $id, ItemRepository $items, Variant $variant = Variant::Short): array
    {
        return $items->find($id) ?? throw new RestException('sections_item_not_found', 'Item not found.', 404);
    }

    #[RestRoute('/{id}', [HttpMethod::Put, HttpMethod::Patch], new Callback(self::canEdit(...)))]
    public function update(int $id, #[Param(maxLength: 200)] string $title, #[Param(format: Format::Email)] ?string $contact = null): WP_REST_Response
    {
        // …
        return new WP_REST_Response($data, 201);
    }

    #[RestRoute('/{id}', HttpMethod::Delete, new Capability('delete_posts'))]
    public function delete(int $id): void
    {
        // … responds with 204 No Content
    }

    public static function canEdit(int $id, ItemRepository $items): bool
    {
        return current_user_can('edit_post', $items->getPostId($id));
    }
}
```

## Controller and routes

- `#[RestController(namespace, base: '')]` on the class defines the REST namespace (no leading or trailing slash) and an optional base path prepended to every route of the class. The value may be a class constant. A `#[RestRoute]` in a class without it is a build error.
- `#[RestRoute(path, methods, permission)]` is repeatable; all three arguments are required, so a missing permission is reported by the IDE and by the build. `path` is empty or starts with `/`; `methods` is an `HttpMethod` case or a list of them.

## Permission

`new PublicAccess()` (everybody), `new Capability('…')` (`current_user_can()`) or `new Callback(self::method(...))`. A callback is a first-class callable of a public **static** method returning `bool` – PHP allows first-class callables in attribute arguments only for static methods. Its parameters follow the route rules below; request arguments must exist in the route with the same name and type. Before calling it, the framework validates and sanitizes these arguments against the route's own schema and denies access if one is invalid, because WordPress also calls permission callbacks for the `Allow` header with a request validated for another handler.

## Path placeholders

`{name}` matches the parameter of the same name, which must be required and of type `int` (`\d+`), `string` (`[^/]+`) or a backed enum (its values). Their values are taken from the URL parameters, never from a query argument of the same name. Route paths may only contain letters, digits, `.`, `_`, `~`, `-`, `/` and placeholders.

## Parameters

- Service candidates are injected, `WP_REST_Request` receives the request, all other parameters are request arguments of the same name: `int`, `float`, `string`, `bool`, a backed enum or `array` with `#[ListOf(Enum::class)]` / `#[ListOf(ValueClass::class)]` (see [Values](./values.md)), optionally nullable with a default value. A single value class without `#[ListOf]` is not supported: a class type is injected as service.
- The argument schema (`type`, `enum`, `items`, `required`, `default`) is derived from the signature; WordPress validates and sanitizes the arguments, answers 400 on errors and shows the schema on `OPTIONS`. Missing optional arguments use the parameter default; enums, value objects and lists are converted by the value factory.
- `#[Param(description:, minimum:, maximum:, minLength:, maxLength:, pattern:, format: Format::…)]` adds schema keywords; the build checks that they apply to the parameter type.

## Responses and errors

- **Responses:** a `void` method responds with 204 No Content; any other value goes through `rest_ensure_response()` (200). Return a `WP_REST_Response` for another status or headers.
- **Errors:** throw `RestException(code, message, status, data)` (status 400–599) for an error response. Any other exception is logged with `error_log()` and answered with 500 `wp_app_framework_internal_error`; class, message, file, line and stack trace are included only if `WP_DEBUG` is enabled.
- Services are created when the route is called.
