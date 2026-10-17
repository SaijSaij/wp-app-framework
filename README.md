# wp-app-framework

PHP/JS framework for building standalone full site editing themes, child themes and plugins for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework[slug]` (one per framework instance)

The framework is a library that themes, child themes and plugins (its *consumers*) include via Composer. It is neither a theme nor a plugin and registers no blocks itself. Each consumer runs its own framework instance; blocks, REST routes and settings are named after the consumer's namespace (`extra.wp-app-framework.namespace`), technical names – script handles, JS global, cache directory, init action – after its slug, so a theme and several plugins with different framework versions run side by side.

## Documentation

**[webcodingcat.github.io/wp-app-framework](https://webcodingcat.github.io/wp-app-framework/)** – the guide (getting started, every part of the API, build and release), the API reference generated from the code, and the developer documentation for working on the framework. It is published per minor version; `next` documents the unreleased state of `main`.

## Status

In development (0.x): framework core, REST routes, WP-CLI commands, Twig, server-side blocks, block supports, settings, editor runtime, front-end scripts, release build, activation and uninstallation, plugins and child themes. Breaking changes are listed in [Upgrading](https://webcodingcat.github.io/wp-app-framework/next/reference/upgrading.html).

## Development

**[Developing the framework](https://webcodingcat.github.io/wp-app-framework/next/development/)** on the website explains the architecture and source layout, the design decisions, the conventions, testing and the release pipelines; [CONTRIBUTING.md](CONTRIBUTING.md) sums it up. Every check runs in the tools image of [wp-test-env](https://github.com/webcodingcat/wp-test-env), checked out next to this repository:

```sh
../wp-test-env/bin/tools -w wp-app-framework composer install
../wp-test-env/bin/tools -w wp-app-framework composer test   # PHPUnit
../wp-test-env/bin/tools -w wp-app-framework composer lint   # PHPStan, PHP-CS-Fixer
../wp-test-env/bin/tools -w wp-app-framework npm ci
../wp-test-env/bin/tools -w wp-app-framework npm test        # Vitest
../wp-test-env/bin/tools -w wp-app-framework npm run lint    # ESLint, Stylelint, tsc
```

## License

[GPL-2.0-or-later](LICENSE), like WordPress.
