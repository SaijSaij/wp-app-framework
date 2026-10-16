# Architecture

This page describes how the framework works inside: what happens in the build and what at run time, how instances stay apart, how the container, the editor runtime and the release build work, and where each part lives in the source. [Concepts](../concepts.md) explains the same ideas from the point of view of a consumer; read it first if you haven't.

## Build and run time

The framework reads PHP attributes – `#[Block]`, `#[Hook]`, `#[RestRoute]` and all the others – only in the **build**. The build discovers the classes of a consumer, reads their attributes with reflection, checks everything it can and writes a **manifest**: a plain PHP array (`var_export`), no generated code. At **run time**, the framework loads the manifest and registers blocks, hooks, routes and the rest from it, without reflection.

This split is the most important rule of the framework: *compile only in the build, never at run time*. It makes errors surface early – every build error names the class, file and parameter at fault – and it keeps a request in release mode free of extra work. Release mode uses no reflection, no stat checks and no `composer.json`, and it never writes into the consumer's directory.

The build runs in two places:

- **Development mode** – the consumer has no `generated/manifest.php` – builds the manifest on the first request and caches it in `wp-content/cache/{slug}/manifest/` (`src/Manifest/DevCache/`). On every request it compares the modification times of the source files with the cache, and only if a file changed, a hash of the *signatures* (attributes, signatures of constructors and public methods; no method bodies). The manifest is rebuilt only when that hash changes. A new kind of input to the build must therefore be part of the signature or of the listing hash, or changes to it would not trigger a rebuild.
- **The release build** (`wp-app-framework release`) writes `generated/manifest.php` into a copy of the consumer, after prefixing (see below).

`Manifest::FORMAT` (`src/Manifest/Manifest.php`) versions the structure of the manifest. Every change of its structure raises it; a cached or released manifest of another format is rejected instead of misread.

## From attribute to WordPress

Each feature follows the same chain. Taking hooks as the simplest example (`src/Hook/`):

1. **The attribute** – `Hook.php`, a `final readonly` class with public promoted properties – is what a consumer writes.
2. **The collector** – `HookCollector.php` – runs in the build. It finds the attribute on the discovered classes, checks it (method visibility, parameters, return type, names) and produces plain data. Every mistake is a build error with class and method name.
3. **The definition** – `HookDefinition.php` – describes the shape of that data for PHPStan (array shapes), shared by the collector and the registrar.
4. **The manifest** – `src/Manifest/ManifestBuilder.php` – calls all collectors in order and puts their data into the manifest.
5. **The registrar** – `HookRegistrar.php` – runs at run time. `src/Instance.php` boots one instance: it loads the manifest, builds the container and hands each part of the manifest to its registrar, which calls `add_action()`, `register_block_type()`, `register_rest_route()` and so on. Services are resolved only when a hook fires, a route is called or a block renders.

Larger areas add more classes to the same directory – stores, codecs, template functions – but keep this order. [Adding a feature](./adding-a-feature.md) turns it into a checklist.

## Instances

Every consumer that boots creates its own instance (`Instance`), and instances share nothing. All global names are derived from the consumer:

- the **namespace** (`extra.wp-app-framework.namespace`, stored in the manifest) for everything content and data refer to: blocks, REST routes, WP-CLI commands, settings options, post types, taxonomies, meta keys and tables;
- the **slug** (the directory name) for technical names: script handles, the JavaScript global `wpAppFramework[slug]`, cache paths, the editor routes, hooks of the framework such as `wp_app_framework/init/{slug}`.

Instances talk to each other only through WordPress filters named after the slug: the public API (`#[PublicApi]`, filter `wp_app_framework/api/{slug}`) and public template functions and filters (filter `wp_app_framework/template/{slug}`). Only plain PHP and WordPress types cross that boundary, because every release prefixes the framework differently: a `Twig\Markup` of one instance is a different class in another. The build rejects framework or dependency classes in such signatures.

In development mode, all consumers share the unprefixed framework and Twig classes – whichever autoloader runs first provides them. The boot checks that the loaded framework is the consumer's own (by real path) and that the loaded Twig version is the one it installed, so a consumer never silently runs another framework checkout.

## The container

The framework has its own minimal dependency injection container (`src/DependencyInjection/`). It does what the consumers of earlier frameworks actually used: constructor autowiring of concrete classes, one shared instance per class, nothing else – no public `get()`, no aliases, no tags, no string IDs. All concrete classes of the consumer's PSR-4 directories are *candidates*; only those reachable from an entry point (a block, hook, route …) are built and checked. Unresolvable parameters and cycles are build errors.

A few instances are provided by the framework (`Theme`, `Plugin`, `ChildTheme`, `TemplateRenderer`, `CacheStore`, `CronScheduler`, `ViewData`, `Assets`, `MediaLibrary`); internal ones such as the editor's render context or the post store may only be injected into framework classes. Post and row objects (`Event::get()`, `LogEntry::query()`) are active records and no services: they reach the store of their instance through internal static registries (`PostRegistry`, `TableRegistry`), filled at boot.

The build never loads a class that builds on a class outside the project, its Composer dependencies and PHP – for example `extends WP_Widget` – because loading it would fail outside WordPress. `src/Manifest/Discovery/` reads such classes with the tokenizer, excludes them from the candidates and lists them.

## The editor runtime

Blocks are rendered on the server, also in the editor. The editor runtime (`assets/runtime/`, built by the consumer's webpack) sends the attributes of the blocks to a batch route of the instance, `POST /wp-app-framework/v1/{slug}/render`, and receives their HTML. Twig marks editable elements only in these renders: `field()` adds `data-field`, `inner_blocks()` adds `data-inner-blocks`. The runtime parses the HTML with `html-react-parser` and replaces the marked elements with WordPress' `RichText`, `InnerBlocks` and image controls; the markup exists only once, in Twig. Changes of inline text fields need no new render; every other attribute change re-renders, with the old HTML kept until the new one arrives.

The inspector is generated from the attribute schema in the manifest; consumers extend it with `registerControl()`, `registerPanel()`, `registerSupportControl()` and `registerToolbarButton()`. Front-end scripts (`*.view.js`) run in their own view runtime (`assets/view/`). The framework ships only JavaScript sources and the webpack helper (`webpack.config.js`); each consumer builds the runtime of its own instance into its `build/`.

## The release build

A release ZIP must run next to other themes and plugins built with other framework versions. The release build (`src/Release/`, command `wp-app-framework release`) therefore copies the consumer and prefixes the framework and every dependency into the consumer's own namespace:

1. Copy the files Git knows (`git ls-files -co --exclude-standard`).
2. `composer install --no-dev --classmap-authoritative` in the copy.
3. Prefix with **Strauss** into `vendor-prefixed/`, including the call sites in the consumer's code; then patch **Twig**, whose compiler writes class names as strings into compiled templates (a fixed list of replacements with expected counts – a Twig update that changes them fails the build on purpose).
4. Build the manifest and compile the templates in the copy.
5. `npm ci && npm run build`, then check that every file the manifest needs at run time exists (`BuildCheck`).
6. Remove the development files (everything `export-ignore`) and check with the tokenizer that no unprefixed name is left.
7. Write `dist/{slug}-{version}.zip`.

The order is fixed, because the manifest needs the prefixed class names.

## Source layout

`src/` is organized by feature: each directory holds everything of one area – the attributes consumers use, the build-time collectors and the runtime classes.

| Directory | Contents |
|---|---|
| `Theme.php`, `ChildTheme.php`, `Plugin.php` | Boot and paths of the active theme, child theme or of a plugin |
| `Instance.php` | Boot of one framework instance |
| `Api/` | Public API between instances (`#[PublicApi]`, `#[PluginApi]`, `#[ThemeApi]`) |
| `Asset/` | Scripts next to classes, static assets, image variants, media library |
| `Block/` | Blocks, their attributes and categories; `Support/` for block supports |
| `Cache/` | `#[Cache]`, tags, variants, `CacheStore` and its backends |
| `Console/` | Command line entry point `bin/wp-app-framework` |
| `Cron/` | Cron events and tasks |
| `DependencyInjection/` | Candidates and resolver (build), container (run time) |
| `Editor/` | Editor template functions, render context, render and preview routes, editor assets |
| `Filesystem/` | Atomic file writes, removal of directory trees |
| `Hook/` | `#[Hook]` |
| `Lifecycle/` | Activation, deactivation, uninstallation |
| `Manifest/` | Manifest builder, generator and loader; `Discovery/` (classes), `DevCache/` (development mode) |
| `Post/` | Post types, meta, taxonomies, post objects |
| `Project/` | Kind, slug, namespace and settings of a consumer from its `composer.json` |
| `Query/` | Post queries and the field classes shared with tables |
| `Release/` | The release build |
| `Rest/` | REST controllers and routes |
| `Setting/` | Settings groups and the settings page |
| `Style/` | Loading of the consumer's stylesheet |
| `Table/` | Tables, row objects, row queries, schema sync |
| `Twig/` | Twig environment, template functions and filters, WordPress functions |
| `Value/` | Field markers, typed values, references, links, the value factory |
| `View/` | Front-end scripts of blocks and supports |
| `WpCli/` | WP-CLI commands |

New areas get their own directory following the same pattern; `tests/Unit/` mirrors the layout.

Next to `src/`:

- `assets/` – the JavaScript and styles of the runtimes: `editor.js` (editor runtime, modules in `runtime/`), `view.js` (view runtime, `view/`), `settings.js` and `meta-box.js` (settings page and meta boxes) with their styles, and their Vitest tests in `test/` directories;
- `webpack.config.js` – the webpack helper consumers use (`@saij/wp-app-framework/webpack`);
- `types/` – TypeScript declarations of both JavaScript imports, checked by `tsc` over `types/check/`;
- `tools/` – the generators of the API reference, run by the documentation build;
- `docs/` – this website (Rspress, `docs/content/`), not part of the package;
- `tests/` – PHPUnit tests, fixtures and the test doubles of WordPress.
