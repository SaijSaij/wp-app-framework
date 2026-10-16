# Boot and modes

Before the framework can register anything, your theme or plugin has to start it. That takes one call in the file WordPress loads first: `functions.php` for a theme, the main plugin file for a plugin. This page shows that call for each kind of project. It then explains the two modes the framework runs in: development mode for your checkout and release mode for production.

## Boot

### Themes

A theme loads Composer's autoloader and boots the framework in its `functions.php`:

```php
<?php

declare(strict_types=1);

// Release builds ship prefixed dependencies in vendor-prefixed/; source checkouts use Composer's vendor/.
require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Theme::boot();
```

The autoloader lines choose between two directories. In your Git checkout, Composer installs everything into `vendor/`. A release ZIP contains `vendor-prefixed/` instead, with every dependency renamed into the theme's own namespace (see [Release](../build-and-release/release.md)). If neither exists, `require` fails with a fatal error that names the missing path.

### Plugins

A plugin does the same in its main file. The main file must be named after the plugin's directory, for example `my-plugin/my-plugin.php`, and it passes its own path to the boot:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Requires PHP: 8.5
 */

declare(strict_types=1);

require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Plugin::boot(__FILE__);
```

The framework needs the file to find the plugin's directory and its URL; asset URLs of a plugin come from `plugin_dir_url()`.

### Child themes

A child theme without code needs neither a boot nor a build. It overrides templates of its parent theme and adjusts `theme.json` like any child theme.

A child theme with code of its own – blocks, hooks, REST routes – boots its own framework instance in its `functions.php`. It uses the same autoloader lines and `Saij\WpAppFramework\ChildTheme::boot();`. [Child themes](./child-themes.md) explains how it works next to its parent theme.

### Slug, namespace and init action

Each instance is identified by a slug. For a theme it is `get_template()`, for a child theme `get_stylesheet()` and for a plugin its directory name. The framework derives the technical global names of the instance from it: script handles, the JavaScript global, the cache directory and the init action. Blocks, REST routes, WP-CLI commands and settings options use the namespace from `composer.json` instead (see [Concepts](../concepts.md#consumers-and-instances)).

A slug can boot only once. A second boot with the same slug is an error, whether it comes from the same project or from a theme and a plugin that happen to share a directory name. The same holds for the namespace: when an instance boots, the framework fires the action `wp_app_framework/namespace/{namespace}`, and a second instance with the same namespace stops with an error.

At the end of the boot the framework fires the action `wp_app_framework/init/{slug}`, without arguments.

### The kind of project

The framework's build reads the kind of project from the `type` in its `composer.json`, which must be `wordpress-theme` or `wordpress-plugin`. A theme needs a `style.css`. If that file names a parent theme in its `Template:` header, the project is a child theme.

## Modes

The framework reads your attributes in a build step and writes the result into a manifest (see [Concepts](../concepts.md#build-and-run-time)). Where that manifest comes from depends on the mode, and the framework chooses the mode by itself. You never set a constant or a flag.

### Release mode

A theme or plugin is in release mode when it contains `generated/manifest.php`. Only the [release build](../build-and-release/release.md) creates that file, so release mode is what runs on a live site.

In release mode the framework loads the manifest as it is: no reflection, no file checks, and no writes into the theme or plugin directory. Compiled Twig templates go to a cache directory named after the template version of the release (see [Twig templates](./twig.md#environment)).

### Development mode

Without `generated/manifest.php` the framework is in development mode. This is the mode of your checkout. You change a class, reload the page, and the change is there.

The framework builds the manifest by reflection and caches it in `wp-content/cache/{slug}/manifest/`, never in the project itself. To avoid rebuilding on every request, it works like Symfony's container cache in debug mode. A meta file records the files the build depended on, with relative paths only. On each request the framework compares modification times. Only for files that are newer does it recompute the class signature or the file content. The signature covers attributes, parents, interfaces, traits and the signatures of the constructor and public methods; it does not cover method bodies. Only a difference triggers a rebuild, so editing the body of a method costs nothing.

### Several consumers in development mode

In development mode the framework and its dependencies are not prefixed. All consumers therefore share the same classes, and whichever autoloader runs first provides them. A plugin built against one framework checkout would silently run with the framework of the theme.

To rule that out, the boot checks two things and fails if either does not hold:

- the loaded framework is the consumer's own `vendor/saij/wp-app-framework`, compared by real path;
- the loaded Twig version is the one recorded in the consumer's `vendor/composer/installed.php`.

In practice, link the same framework checkout into every consumer. The option `WP_PACKAGE_LINKS` of [wp-dev-env](https://github.com/saijsaij/wp-dev-env) does exactly that. Twig may also come from somewhere else: in a Bedrock site, the root `vendor/` of the site can load it before any consumer. The error names the directory the loaded Twig comes from, so you know which installation to align.
