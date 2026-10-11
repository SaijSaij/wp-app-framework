# wp-app-framework

PHP/JS framework for building standalone full site editing themes, child themes and plugins for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework[slug]` (one per framework instance)
- Source: [github.com/saijsaij/wp-app-framework](https://github.com/saijsaij/wp-app-framework)

The framework is a library that themes, child themes and plugins (its *consumers*) include via Composer. It is neither a theme nor a plugin and registers no blocks itself. Each consumer runs its own framework instance; everything global – REST route, script handles, JS global, cache directory, init action – is named after the consumer's slug (its directory name), so a theme and several plugins with different framework versions run side by side.

## Principles

1. **No markup in post content.** Every block renders on the server. The post content holds block comments with JSON attributes; block supports are attributes too. The only exception is one `#[RichText(postContent: true)]` field per block (see [Blocks](./guides/blocks.md#text-in-the-post-content)).
2. **Inline editing in the editor canvas.** Markup exists once, in Twig; the editor replaces the marked elements with editors (see [Editor](./guides/editor.md)).
3. **Full site editing.** Pages are block templates and template parts. Twig renders block markup and markup fragments of your code (mails, HTML responses), never page layouts.
4. **Plain WordPress.** Releases ship all dependencies prefixed; the server needs neither Composer nor Bedrock (see [Release](./build-and-release/release.md)).
5. **Compiled in the build, not at runtime.** Attributes such as `#[Hook]`, `#[RestRoute]` and `#[Block]` are read by a build step that writes a manifest of plain data; a release loads it without reflection (see [Boot and modes](./guides/boot.md)).

## Status

The framework is in development (0.x); breaking changes are listed in [Upgrading](./reference/upgrading.md).

- Framework core: boot, container, manifest build, development mode and `#[Hook]`.
- REST routes: `#[RestController]`, `#[RestRoute]`, `#[Param]`.
- Twig: `TemplateRenderer`, `#[TemplateFunction]`, `#[TemplateFilter]`.
- Server-side blocks: `#[Block]`, `#[Supports]`, typed attribute classes, `#[ListOf]` also for REST arguments, references to posts, terms and users.
- Block supports of the theme or plugin: `#[BlockSupport]`, `#[UseSupport]`, `registerSupportControl()`.
- Settings: `#[SettingsGroup]`, one option per group, settings page, injection into services and the template global `settings`.
- Editor runtime: inline editing in the canvas, generated inspector, batch render route, webpack helper.
- Release: `wp-app-framework release` (prefixed ZIP), release mode with a versioned Twig cache, GitHub workflows.
- Plugins: `Plugin::boot()`, theme overrides of plugin templates, public APIs and public template callables of plugins.
- Child themes: `ChildTheme::boot()`, overrides of theme templates in `overrides/{theme}/`, public APIs and template callables of themes, `#[ThemeApi]`.

## Where to start

- [Getting started](./getting-started.md) sets up a theme with a first block.
- The guides describe each part of the API; [Values](./guides/values.md) is shared by blocks, REST routes and settings.
- A complete example of every feature is the test theme [theme-dev-test](https://github.com/saijsaij/theme-dev-test), with the test plugin [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test) and the test child theme [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child).
- A local WordPress for development is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).
