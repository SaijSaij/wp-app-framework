# wp-app-framework

PHP/JS framework for building standalone full site editing themes for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework`

The framework is a library that themes include via Composer. It is neither a theme nor a plugin and registers no blocks itself.

## Status

Framework core (step 1): boot, container, manifest build, development mode and `#[Hook]`. REST routes (step 2): `#[RestController]`, `#[RestRoute]`, `#[Param]`. Twig, blocks and the editor runtime follow.

## Usage

### Boot

The theme's `functions.php` loads the autoloader and boots the framework:

```php
<?php

declare(strict_types=1);

// Release builds ship prefixed dependencies in vendor-prefixed/; source checkouts use Composer's vendor/.
require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Theme::boot();
```

`Theme::boot()` may be called once. At its end it fires the action `wp_app_framework/init` (currently without arguments). Child themes attach to this action, because WordPress loads their `functions.php` before the parent's.

### Modes

- **Release mode:** the theme contains `generated/manifest.php`, which only the release build creates. The manifest is loaded as is: no reflection, no file checks, no writes.
- **Development mode:** otherwise. The manifest is built by reflection and cached in `wp-content/cache/{template}/manifest/` (never in the theme). Like Symfony's container cache in debug mode, a meta file records the resources the build depended on, using relative paths only. Each request compares modification times; only for newer files the class signature (attributes, parents, interfaces, traits and the signatures of the constructor and public methods, not method bodies) or file content is recomputed, and only a difference triggers a rebuild.

### Services

Every concrete class under the theme's PSR-4 directories (`autoload.psr-4` in its `composer.json`) and under the framework's `src/` is a service candidate. Interfaces, abstract classes, traits, enums, attribute classes and classes marked `#[NoService]` (`Saij\WpAppFramework\DependencyInjection\NoService`) are not. Services are shared and autowired by constructor parameter types; scalar parameters must have a default value.

Only services reachable from entry points (`#[Hook]` and `#[RestRoute]` methods, including permission callbacks) are built and checked, so value objects that are never injected need no marking. Aliases and tags are not supported. The container is internal; theme code receives services through constructor and hook parameters only. `Saij\WpAppFramework\Theme` is a predefined instance: `Theme::boot()` creates it (its constructor is private) and hands it to the container, which never builds it. Services can inject it to read the paths via `getTemplateDirectory()`, `getTemplateDirectoryUri()`, `getStylesheetDirectory()`, `getStylesheetDirectoryUri()` and `getFrameworkDirectory()`.

### Hooks

```php
use Saij\WpAppFramework\Hook\Hook;

final class TitleFilter
{
    #[Hook('the_title', priority: 20)]
    public function filter(string $title, Formatter $formatter, int $postId = 0): string
    {
        return $formatter->format($title, $postId);
    }
}
```

- The method must be public and non-static and declare a return type: `void` registers an action, any other type a filter. `#[Hook]` is repeatable.
- Parameters typed with a service candidate are injected (`Formatter`). All other parameters receive the WordPress arguments in their order (`$title`, `$postId`); `accepted_args` is derived from them. Missing optional arguments use the parameter default.
- Services are created when the hook fires. Exceptions are rethrown if `WP_DEBUG` is enabled; otherwise they are logged with `error_log()`, and filters return their original value.

### REST routes

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

- `#[RestController(namespace, base: '')]` on the class defines the REST namespace (no leading or trailing slash) and an optional base path prepended to every route of the class. The value may be a class constant. A `#[RestRoute]` in a class without it is a build error.
- `#[RestRoute(path, methods, permission)]` is repeatable; all three arguments are required, so a missing permission is reported by the IDE and by the build. `path` is empty or starts with `/`; `methods` is an `HttpMethod` case or a list of them.
- **Permission:** `new PublicAccess()` (everybody), `new Capability('…')` (`current_user_can()`) or `new Callback(self::method(...))`. A callback is a first-class callable of a public **static** method returning `bool` – PHP allows first-class callables in attribute arguments only for static methods. Its parameters follow the route rules below; request arguments must exist in the route with the same name and type. Before calling it, the framework validates and sanitizes these arguments against the route's own schema and denies access if one is invalid, because WordPress also calls permission callbacks for the `Allow` header with a request validated for another handler.
- **Path placeholders** `{name}` match the parameter of the same name, which must be required and of type `int` (`\d+`), `string` (`[^/]+`) or a backed enum (its values). Their values are taken from the URL parameters, never from a query argument of the same name. Route paths may only contain letters, digits, `.`, `_`, `~`, `-`, `/` and placeholders.
- **Parameters:** service candidates are injected, `WP_REST_Request` receives the request, all other parameters are request arguments of the same name: `int`, `float`, `string`, `bool` or a backed enum, optionally nullable with a default value. The argument schema (`type`, `enum`, `required`, `default`) is derived from the signature; WordPress validates and sanitizes the arguments, answers 400 on errors and shows the schema on `OPTIONS`. Missing optional arguments use the parameter default; enum values are converted with `::from()`.
- `#[Param(description:, minimum:, maximum:, minLength:, maxLength:, pattern:, format: Format::…)]` adds schema keywords; the build checks that they apply to the parameter type.
- **Responses:** a `void` method responds with 204 No Content; any other value goes through `rest_ensure_response()` (200). Return a `WP_REST_Response` for another status or headers.
- **Errors:** throw `RestException(code, message, status, data)` (status 400–599) for an error response. Any other exception is logged with `error_log()` and answered with 500 `wp_app_framework_internal_error`; class, message, file, line and stack trace are included only if `WP_DEBUG` is enabled.
- Services are created when the route is called.

### Build

```sh
vendor/bin/wp-app-framework build [--output=<file>]
```

Builds and checks the manifest of the project whose `vendor/autoload.php` the Composer bin proxy loads; problems are listed with class and parameter names, and the exit code is 1. Without `--output` nothing is written. Do not write `generated/manifest.php` into a theme source checkout: its presence selects release mode. The release build writes it.

## Source layout

`src/` is organized by feature: each directory holds everything of one area – the attributes themes use, the build-time collectors and the runtime classes.

| Directory | Contents |
|---|---|
| `Theme.php` | Boot and paths of the active theme |
| `Hook/` | `#[Hook]` attribute, collector (build), definition, registrar (runtime) |
| `Rest/` | `#[RestController]`, `#[RestRoute]`, `#[Param]` attributes, `HttpMethod`/`Format` enums, `Permission/` (`PublicAccess`, `Capability`, `Callback`), `RestException`, collector (build), definition, registrar (runtime) |
| `DependencyInjection/` | `#[NoService]` attribute, service candidates and resolver (build), container (runtime) |
| `Manifest/` | Manifest builder, generator and loader, checks shared by entry point collectors; `Discovery/` (PSR-4 class discovery), `DevCache/` (development mode cache) |
| `Console/` | Command line entry point of `bin/wp-app-framework` |

New areas (Twig, blocks, settings, block cache) get their own directory following the same pattern. Tests in `tests/Unit/` mirror this layout.

## Conventions

- The theme API must be fully resolvable by IDEs: enums and classes instead of magic strings, first-class callables instead of method names, required information as required attribute arguments.
- Class properties are never public; access goes through getters with a `get` prefix (`is`/`has` for booleans). Attribute classes such as `#[Hook]` are the exception and expose public readonly properties.
- Code, comments and documentation are in English.

## Development

Themes include this repository during development through a Composer `path` repository (symlinked), so changes take effect immediately without a release build. The theme checkout and this checkout must therefore share the same parent directory; see [theme-sections](https://github.com/saijsaij/theme-sections) for an example.

A local WordPress for testing is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).

Tests (PHPUnit) run in the `tools` container of [wp-dev-env](https://github.com/saijsaij/wp-dev-env); WordPress is not loaded, minimal test doubles of the REST classes live in `tests/Stubs/`:

```sh
composer install
composer test
```
