# wp-app-framework

PHP/JS framework for building standalone full site editing themes for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework`

The framework is a library that themes include via Composer. It is neither a theme nor a plugin and registers no blocks itself.

## Status

Framework core (step 1): boot, container, manifest build, development mode and `#[Hook]`. REST routes, Twig, blocks and the editor runtime follow.

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

Only services reachable from entry points (currently `#[Hook]` methods) are built and checked, so value objects that are never injected need no marking. Aliases and tags are not supported. The container is internal; theme code receives services through constructor and hook parameters only. `Saij\WpAppFramework\Theme` is injectable and provides the template, stylesheet and framework directories.

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
| `DependencyInjection/` | `#[NoService]` attribute, service candidates and resolver (build), container (runtime) |
| `Manifest/` | Manifest builder, generator and loader; `Discovery/` (PSR-4 class discovery), `DevCache/` (development mode cache) |
| `Console/` | Command line entry point of `bin/wp-app-framework` |

New areas (REST routes, Twig, blocks, settings, block cache) get their own directory following the same pattern. Tests in `tests/Unit/` mirror this layout.

## Development

Themes include this repository during development through a Composer `path` repository (symlinked), so changes take effect immediately without a release build. The theme checkout and this checkout must therefore share the same parent directory; see [theme-sections](https://github.com/saijsaij/theme-sections) for an example.

A local WordPress for testing is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).

Tests (PHPUnit) run in the `tools` container of [wp-dev-env](https://github.com/saijsaij/wp-dev-env):

```sh
composer install
composer test
```
