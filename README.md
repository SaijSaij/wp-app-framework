# wp-app-framework

PHP/JS framework for building standalone full site editing themes for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework`

The framework is a library that themes include via Composer. It is neither a theme nor a plugin and registers no blocks itself.

## Status

Empty repository. The framework core is implemented in the next step.

## Development

Themes include this repository during development through a Composer `path` repository (symlinked), so changes take effect immediately without a release build. The theme checkout and this checkout must therefore share the same parent directory; see [theme-sections](https://github.com/saijsaij/theme-sections) for an example.

A local WordPress for testing is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).
