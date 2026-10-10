# Boot and modes

## Boot

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

A plugin boots from its main file, which must be named after its directory (`my-plugin/my-plugin.php`):

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

A child theme with code of its own (blocks, hooks, routes) boots its own framework instance from its `functions.php`, with the same autoloader lines and `Saij\WpAppFramework\ChildTheme::boot();` – see [Child themes](./child-themes.md). A child theme without code needs neither boot nor build.

The slug of a theme is `get_template()`, of a child theme `get_stylesheet()`, of a plugin its directory name; the asset URLs of a plugin come from `plugin_dir_url()`. Each slug boots once: a second boot with the same slug – from the same consumer, or a theme and a plugin with the same directory name – is an error. At its end, the boot fires the action `wp_app_framework/init/{slug}` (without arguments).

The `type` in the consumer's `composer.json` must be `wordpress-theme` or `wordpress-plugin`; the build reads the kind of the project from it. A theme needs a `style.css`; if it declares a parent theme (`Template:`), the project is a child theme.

## Modes

- **Release mode:** the theme or plugin contains `generated/manifest.php`, which only the [release build](../build-and-release/release.md) creates. The manifest is loaded as is: no reflection, no file checks, no writes to the project. Compiled Twig templates go to a cache directory named after the template version of the release (see [Twig templates](./twig.md#environment)).
- **Development mode:** otherwise. The manifest is built by reflection and cached in `wp-content/cache/{slug}/manifest/` (never in the project). Like Symfony's container cache in debug mode, a meta file records the resources the build depended on, using relative paths only. Each request compares modification times; only for newer files the class signature (attributes, parents, interfaces, traits and the signatures of the constructor and public methods, not method bodies) or file content is recomputed, and only a difference triggers a rebuild.

  In development mode all consumers share the unprefixed classes of the framework and its dependencies: whichever autoloader comes first provides them. The boot therefore requires that the loaded framework is the consumer's own `vendor/saij/wp-app-framework` (by real path) and that the loaded Twig version is the one in its `vendor/composer/installed.php`, and fails otherwise. Link the same framework checkout into every consumer (`WP_PACKAGE_LINKS` of [wp-dev-env](https://github.com/saijsaij/wp-dev-env)).
