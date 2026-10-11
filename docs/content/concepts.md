# Concepts

This page explains the ideas that the rest of the documentation relies on: who uses the framework, what happens in the build and what happens at run time, and how a block travels from a PHP class to the page and to the editor. You don't need it to follow [Getting started](./getting-started.md), but the guides make more sense once you know it.

## Consumers and instances

The framework is a Composer library. A project that includes it is a *consumer*. There are three kinds:

- a **theme**, which boots the framework with `Theme::boot()`;
- a **plugin**, which boots it with `Plugin::boot(__FILE__)`;
- a **child theme with code of its own** (blocks, hooks, REST routes), which boots it with `ChildTheme::boot()`. A child theme that only overrides templates and adjusts `theme.json` needs no framework code at all.

Every consumer that boots creates its own *instance* of the framework. Instances are strictly separated. Everything with a global name is derived from the consumer's slug, its directory name: the REST route of the editor, the script handles, the JavaScript global `window.wpAppFramework[slug]`, the cache directory `wp-content/cache/{slug}/`, the init action `wp_app_framework/init/{slug}` and the option names of settings.

The separation goes further in a release. Each consumer ships its own copy of the framework and its dependencies, prefixed with its own namespace. A theme built with framework 0.6 and a plugin built with 0.7 load different classes and don't interfere with each other. The flip side is that instances cannot pass framework objects to each other. When a plugin wants to offer something to the theme, it does so through a *public interface* made of WordPress filters named after its slug (see [Plugins](./guides/plugins.md)).

## Build and run time

You declare almost everything with PHP attributes: `#[Hook]` on a method registers a hook, `#[RestRoute]` a REST route, `#[Block]` a block, `#[TemplateFunction]` a Twig function, `#[SettingsGroup]` a group of settings. Nothing reads these attributes while WordPress handles a request in production. Instead, a *build* step reads them once and writes a **manifest**: plain PHP data listing the hooks, routes, blocks with their attribute schemas, template functions, settings and the services with their constructor dependencies.

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

**In the build**, the framework reads the attribute class and derives the attribute schema from its constructor: types, defaults, the allowed values of enums, and which attributes are content. There is no `block.json`. On `init` the framework registers the block with `register_block_type()`, and WordPress passes the definition to the block editor by itself.

**In the post content**, the block is stored as a comment with its attributes as JSON:

```html
<!-- wp:my-theme/teaser {"title":"Summer party","highlighted":true} /-->
```

**On the front end**, WordPress calls the framework when it renders the block. The framework cleans the attributes against their schema, turns them into an instance of your attribute class, and calls your block's `render()` method, which renders the Twig template.

**In the editor**, the framework's editor runtime asks the server to render the block from the same template. The template marks editable elements with `field('title')`. In the editor these marks become attributes on the element, and the runtime replaces the marked elements with text editors, image pickers and inner blocks. Content editors type into the real markup; there is no second template for the editor. Typing does not re-render the block, but every other change, such as a different option in the inspector, does.

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

## Templates in layers

Twig templates live in the `src/` directory of each project, next to the code that uses them. A template name is always the full path relative to `src/`, such as `Block/Teaser/Teaser.twig`.

When the framework looks for a template, it searches several layers in order. For a theme this is the active child theme's `overrides/{theme-slug}/`, then the theme's `src/`, then the framework's `src/`. A file with the same path on a higher layer wins. That is how a child theme changes the markup of a theme's block without touching its code, and how a theme changes the markup of a plugin's block. See [Twig templates](./guides/twig.md#lookup).

## Errors

The framework never hides a missing prerequisite. Missing prerequisites – a missing build output, an unknown template, a wrong boot method – raise exceptions that name the file, class or parameter at fault.

Errors in your code at run time depend on `WP_DEBUG`. With `WP_DEBUG` enabled, exceptions from hooks and blocks are rethrown, so you see them immediately. Without it, they are written to the PHP error log, and the site keeps working: a filter returns its original value, a block renders nothing. REST routes always answer with an error response instead (see [REST routes](./guides/rest.md#responses-and-errors)).
