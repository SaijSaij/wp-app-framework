# Concepts

This page explains the ideas that the rest of the documentation relies on: who uses the framework, what happens in the build and what happens at run time, and how a block travels from a PHP class to the page and to the editor. You don't need it to follow [Getting started](./getting-started.md), but the guides make more sense once you know it.

## Consumers and instances

The framework is a Composer library. A project that includes it is a *consumer*. There are three kinds:

- a **theme**, which boots the framework with `Theme::boot()`;
- a **plugin**, which boots it with `Plugin::boot(__FILE__)`;
- a **child theme with code of its own** (blocks, hooks, REST routes), which boots it with `ChildTheme::boot()`. A child theme that only overrides templates and adjusts `theme.json` needs no framework code at all.

Every consumer that boots creates its own *instance* of the framework. Instances are strictly separated, so everything with a global name is derived from one of two names of the consumer.

The **namespace** names what your content and data refer to. You choose it once in `composer.json`, as `extra.wp-app-framework.namespace`, for example `sections`. Your blocks are registered as `sections/hero`, your REST routes live under `sections/v1`, your WP-CLI commands under `wp sections`, your settings are stored in options such as `sections_general`, and your post types, taxonomies and meta keys are named `sections_event` and the like. These names end up in posts and in the database, so they must stay the same for as long as the content exists.

The names you give in attributes follow two patterns. Names that become part of a database name – post types, taxonomies, settings groups, meta classes, tables – consist of lowercase letters, digits and `_`, because they are joined to the namespace with `_` (`sections_event`). Names that become part of a block name, a hook or a command – blocks, variations, cron events and tasks, WP-CLI commands – consist of lowercase letters, digits and `-` (`sections/hero`, `wp sections import-events`).

The **slug** is the directory name of the theme or plugin. It names the technical parts: the REST route of the editor, the script handles, the JavaScript global `window.wpAppFramework[slug]`, the cache directory `wp-content/cache/{slug}/` and the init action `wp_app_framework/init/{slug}`. The directory name can change when a theme or plugin is installed under another name; that's why no content depends on it.

Two consumers that boot with the same slug or the same namespace are an error.

The separation goes further in a release. Each consumer ships its own copy of the framework and its dependencies, prefixed with its own namespace. A theme built with framework 0.6 and a plugin built with 0.7 load different classes and don't interfere with each other. The flip side is that instances cannot pass framework objects to each other. When a plugin wants to offer something to the theme, it does so through a *public interface* made of WordPress filters named after its slug (see [Plugins](./guides/plugins.md)).

## Build and run time

You declare almost everything with PHP attributes: `#[Hook]` on a method registers a hook, `#[RestRoute]` a REST route, `#[Block]` a block, `#[TemplateFunction]` a Twig function, `#[SettingsGroup]` a group of settings, `#[PostType]` a post type, `#[Table]` a database table. Nothing reads these attributes while WordPress handles a request in production. Instead, a *build* step reads them once and writes a **manifest**: plain PHP data listing the hooks, routes, blocks with their attribute schemas, template functions, settings and the services with their constructor dependencies.

This has two benefits. First, mistakes surface early. A REST route without a permission, a constructor parameter the container cannot fill, a template function with the name of a Twig built-in – each is a build error naming the class at fault, not a surprise in production. Second, a release does no extra work at run time: it loads the manifest and calls your classes, without reflection, without checking files, and without ever writing into the theme or plugin directory.

You can run the checks yourself at any time with `composer build-manifest` (see [Build](./build-and-release/build.md)).

## Development mode and release mode

You don't run the build by hand while you develop. The framework picks one of two modes:

- **Release mode** applies when the consumer contains `generated/manifest.php`. Only the [release build](./build-and-release/release.md) writes that file, so this is what runs on a live site.
- **Development mode** applies otherwise, for example in your Git checkout. The framework builds the manifest itself on the first request and caches it in `wp-content/cache/{slug}/manifest/`. On each request it checks whether your classes changed in a way that matters – a new attribute, a changed signature – and rebuilds only then. You edit a file and reload the page.

[Boot and modes](./guides/boot.md) describes both modes in detail.

## Services

Your classes don't create each other. Concrete classes in your PSR-4 directories are *service candidates* (with a few exceptions such as enums and attribute classes), and the framework's container creates them when needed, passing other services into their constructors. A class that has a hook, a REST route, a template function or filter, or a block is an entry point; everything it needs is created for it, and only when the hook fires, the route is called or the block renders. See [Services](./guides/services.md).

## How a block works

Blocks show how these pieces fit together. A block consists of three files next to each other:

```
src/Block/Teaser/
├── Teaser.php             the block: #[Block] and a render() method
├── TeaserAttributes.php   the attributes as a typed class
└── Teaser.twig            the markup
```

Two optional scripts can sit next to them: `Teaser.editor.js` extends the block in the editor (see [Editor](./guides/editor.md#editor-scripts)), `Teaser.view.js` gives it behavior on the page (see [Front-end scripts](./guides/frontend.md)).

**In the build**, the framework reads the attribute class and derives the attribute schema from its constructor: types, defaults, the allowed values of enums, and which attributes are content. There is no `block.json`. On `init` the framework registers the block with `register_block_type()`, and WordPress passes the definition to the block editor by itself.

**In the post content**, the block is stored as a comment with its attributes as JSON:

```html
<!-- wp:my-theme/teaser {"title":"Summer party","highlighted":true} /-->
```

**On the front end**, WordPress calls the framework when it renders the block. The framework cleans the attributes against their schema, turns them into an instance of your attribute class, and calls your block's `render()` method, which renders the Twig template.

**In the editor**, the framework's editor runtime asks the server to render the block from the same template. The template marks editable elements with `field('title')`. In the editor these marks become attributes on the element, and the runtime replaces the marked elements with text editors, image pickers and inner blocks. Content editors type into the real markup; there is no second template for the editor. Typing does not re-render the block, but every other change, such as a different option in the inspector, does.

**With `#[Cache]`**, the front end renders the block once and reads its HTML from the [cache](./guides/cache.md) until a tag of the block is invalidated or the entry expires. The editor always renders it fresh.

**With a front-end script**, the block also gets behavior on the page. The framework loads the script only on pages that render the block and starts one instance of its class per block. In the editor, a **Preview** button in the block's toolbar shows the front-end markup and runs the script on it.

```
TeaserAttributes.php ──build──▶ attribute schema ──▶ register_block_type()
                                                         │
             post content: <!-- wp:my-theme/teaser {…} /-->
                                                         │
          ┌──────────────────────────────────────────────┴───────────────┐
     front end: render() → Teaser.twig → HTML         editor: render route → same HTML
                                                        → marked fields become editors
```

[Blocks](./guides/blocks.md) and [Editor](./guides/editor.md) go into the details.

## src and assets

Each project has two directories for its own files. `src/` holds everything that belongs to a PHP class: the classes themselves, their Twig templates and the scripts of blocks, block supports and settings groups, each next to its class (`Hero.php`, `Hero.twig`, `Hero.editor.js`, `Hero.view.js`, `Design.settings.js`).

`assets/` holds everything that belongs to no PHP class: the stylesheet `assets/styles.css` (see [Styles](./guides/styles.md)), scripts for the whole page (`*.view.js`, see [Scripts for the whole page](./guides/frontend.md#scripts-for-the-whole-page)), editor scripts without a block (`*.editor.js`, see [Editor](./guides/editor.md#editor-scripts-without-a-block)) modules shared between scripts (`assets/shared/`, see [Shared modules](./guides/frontend.md#shared-modules)) and static files such as images and fonts (see [Static assets](./guides/assets.md)). The directory is optional and ships with the release like `src/`.

A script in `src/` that sits next to no block or block support class – or a settings script next to no settings group – is a build error. The webpack helper would build it, but the framework would never load it.

## Templates in layers

Twig templates live in the `src/` directory of each project, next to the code that uses them. A template name is always the full path relative to `src/`, such as `Block/Teaser/Teaser.twig`.

When the framework looks for a template, it searches several layers in order. For a theme this is the active child theme's `overrides/{theme-slug}/`, then the theme's `src/`, then the framework's `src/`. A file with the same path on a higher layer wins. That is how a child theme changes the markup of a theme's block without touching its code, and how a theme changes the markup of a plugin's block. See [Twig templates](./guides/twig.md#lookup).

## Errors

The framework never hides a missing prerequisite. Missing prerequisites – a missing build output, an unknown template, a wrong boot method – raise exceptions that name the file, class or parameter at fault.

Errors in your code at run time depend on `WP_DEBUG`. With `WP_DEBUG` enabled, exceptions from hooks and blocks are rethrown, so you see them immediately. Without it, they are written to the PHP error log, and the site keeps working: a filter returns its original value, a block renders nothing. REST routes always answer with an error response instead (see [REST routes](./guides/rest.md#responses-and-errors)), and WP-CLI commands end with an error message (see [WP-CLI commands](./guides/cli.md#output-and-errors)).
