# wp-app-framework

wp-app-framework is a PHP and JavaScript framework for building standalone full site editing themes, child themes and plugins for WordPress 7 on PHP 8.5. You write blocks, hooks, REST routes and settings as plain PHP classes with attributes. The framework turns them into everything WordPress expects: registered blocks with an attribute schema, an editor with inline editing, REST routes with validation, and a settings page.

The framework is a library, not a theme or a plugin. Your theme, child theme or plugin includes it through Composer; the documentation calls these projects *consumers*. The framework registers no blocks itself; the blocks, hooks and routes you see in WordPress come from your code.

Each consumer runs its own copy of the framework, called an *instance*. Its blocks, REST routes and settings are named after the consumer's namespace from `composer.json`; technical names – script handles, the JavaScript global, the cache directory, the init action – after its slug (its directory name). That is why a theme and several plugins can run side by side, even with different framework versions. [Concepts](./concepts.md) explains this and the other ideas the guides build on.

## Principles

The framework follows a few rules that shape every part of its API. Knowing them makes many details in the guides easier to follow.

**No markup in the post content.** Every block renders on the server. The post content stores only a block comment with the attributes as JSON, for example `<!-- wp:my-theme/teaser {"title":"Summer"} /-->`; block supports are attributes too. You can change a block's markup at any time, and every post shows the new markup without being saved again. The one exception is a single `#[RichText(postContent: true)]` field per block, which keeps the text of paragraphs and headings in the post content (see [Blocks](./guides/blocks.md#text-in-the-post-content)).

**Inline editing in the editor canvas.** Content editors type directly into the block, as with core blocks. The markup still exists only once, in a Twig template: the editor renders the block on the server and replaces the marked elements with editors (see [Editor](./guides/editor.md)).

**Full site editing.** Pages are made of block templates and template parts, as in any block theme. Twig renders the markup of blocks and fragments that your code produces, such as mails or HTML responses – never whole page layouts.

**Plain WordPress.** A release ships all dependencies, including the framework and Twig, prefixed with the consumer's own namespace. The server needs neither Composer nor Bedrock, and in a release two consumers never load each other's classes (see [Release](./build-and-release/release.md)).

**Compiled in the build, not at run time.** A build step reads attributes such as `#[Hook]`, `#[RestRoute]` and `#[Block]` and writes a manifest of plain data. Mistakes such as a missing permission or a service that cannot be created show up as build errors, and a release runs without reflection (see [Boot and modes](./guides/boot.md)).

## Status

The framework is in development (0.x). Minor versions may still contain breaking changes; [Upgrading](./reference/upgrading.md) lists them and how to adapt your code.

These parts are ready to use:

- the framework core: boot, service container, manifest build, development mode and `#[Hook]`;
- REST routes with `#[RestController]`, `#[RestRoute]` and `#[Param]`;
- Twig templates with `TemplateRenderer`, `#[TemplateFunction]` and `#[TemplateFilter]`;
- server-side blocks with `#[Block]`, `#[Supports]`, typed attribute classes, `#[ListOf]` (also for REST arguments) and references to posts, terms and users;
- block supports of your own with `#[BlockSupport]`, `#[UseSupport]` and `registerSupportControl()`;
- settings with `#[SettingsGroup]`, one option per group, a settings page, injection into services and the template global `settings`;
- post types, taxonomies and typed post meta with `#[PostType]`, `#[Taxonomy]` and `#[PostMeta]`: post objects that load and save themselves, panels in the block editor and meta boxes on the classic edit screen;
- queries of post objects with `Event::query()`: conditions on meta fields and terms, groups, sorting, pages and caching, with field classes (`#[MetaFields]`) written by hand or by `wp-app-framework meta-fields`;
- the editor runtime: inline editing in the canvas, a generated inspector, toolbar buttons, a batch render route and a webpack helper;
- front-end scripts of blocks and block supports with the base classes `BlockView` and `SupportView`, loaded only with their blocks and previewed in the editor;
- the release build `wp-app-framework release` (prefixed ZIP), release mode with a versioned Twig cache, and GitHub workflows;
- plugins: `Plugin::boot()`, theme overrides of plugin templates, public APIs and public template callables;
- child themes: `ChildTheme::boot()`, overrides of theme templates in `overrides/{theme}/`, public APIs and template callables of themes, and `#[ThemeApi]`.

## Where to start

If you are new to the framework, read [Concepts](./concepts.md) first. Then follow [Getting started](./getting-started.md), which sets up a theme with a first block. The guides then explain each part of the API in turn. [Values](./guides/values.md) describes the typed values that blocks, REST routes and settings share.

The test theme [theme-dev-test](https://github.com/saijsaij/theme-dev-test) contains a working example of every feature. It comes with the test plugin [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test) and the test child theme [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child). [wp-dev-env](https://github.com/saijsaij/wp-dev-env) provides a local WordPress for development.

## Names

| What | Name |
|---|---|
| Composer package | `saij/wp-app-framework` |
| PHP namespace | `Saij\WpAppFramework` |
| JavaScript import | `@saij/wp-app-framework` (editor scripts), `@saij/wp-app-framework/view` (front-end scripts) |
| JavaScript global at run time | `window.wpAppFramework[slug]`, one per framework instance |
| Source code | [github.com/saijsaij/wp-app-framework](https://github.com/saijsaij/wp-app-framework) |
