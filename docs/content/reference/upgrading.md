# Upgrading

From 1.0 on, the consumer API only grows within a major version: nothing is renamed, removed or changed in behavior, and a theme built against 1.0 keeps working with 1.9. Before 1.0, a minor version may still break the API. This page lists every such change, why it was made, and what you need to change in your theme or plugin.

The documentation is published for each minor version. The version switcher in the navigation bar shows the documentation of other versions, so you can compare the old and the new state.

## 0.15

0.15 is the last review of the API before 1.0. It renames and tightens what would otherwise stay inconsistent for the whole 1.x series.

**The field markers moved to `Saij\WpAppFramework\Value`.** `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Label]`, `#[Hidden]`, `#[Content]` and the interface `Labeled` are not only for blocks: settings groups, meta classes and value classes use them too. They now live next to `#[ListOf]`, `#[Url]` and the references. Replace `Saij\WpAppFramework\Block\` with `Saij\WpAppFramework\Value\` in the `use` lines of these seven names; the old names no longer exist, so PHP or the build reports every place you missed.

**`#[CronTask]` takes a name.** The name of a task used to come from the short name of its class, so renaming the class silently dropped the planned tasks. Now you name it, like a recurring event: `#[CronTask('send-reminder')]` (see [Cron events and tasks](../guides/cron.md)). To keep tasks that are already planned, use the name the framework derived before: the short class name in kebab case (`SendReminder` → `send-reminder`, `HTMLExport` → `html-export`). A `#[CronTask]` without a name is a build error.

**The arguments of `#[Variation]` follow `#[Block]`.** The order is now `name, title, icon, description, keywords, …`, as in `#[Block]`; `icon` and `description` swapped places. Only a variation that passes `description` or `icon` positionally is affected; name these arguments (`icon: 'columns'`), because the build cannot tell the two strings apart.

**`@saij/wp-app-framework` is the editor API only.** The import used to resolve to the whole global `wpAppFramework[slug]`, so internal parts such as `config` were importable. It now resolves to `wpAppFramework[slug].api`, which holds `registerControl()`, `registerSupportControl()`, `registerPanel()`, `registerToolbarButton()`, `loadScript()` and `getData()` (see [Editor](../guides/editor.md#editor-scripts)). Code that imports only these needs no change. Code that called them on the global, `window.wpAppFramework[slug].registerPanel(…)`, calls them on `window.wpAppFramework[slug].api` now. `loadScript()` of both imports takes two arguments, the URL and the attributes.

**The webpack helper is `@saij/wp-app-framework/webpack`.** The package declares its entry points now, with TypeScript declarations for both imports that your IDE picks up. Replace `require( './vendor/saij/wp-app-framework/webpack.config.js' )` in your `webpack.config.js` with `require( '@saij/wp-app-framework/webpack' )`; the old path still works.

**The release workflow input `cli-tests` is now `cli`.** It goes with `e2e` (see [Releases and CI](../build-and-release/ci.md)). Rename it in the workflow of your project that calls `consumer-release.yml`.

**Internal services cannot be injected.** The render context of the editor (`Editor\RenderContext`) and the post store (`Post\PostStore`) are internal to the framework. A class of your theme or plugin that injects one of them is now a build error. Use the template functions `is_editor()`, `field()` and `inner_blocks()` in templates, and `Event::get()`, `find()` and `query()` for posts.

**The framework is on Packagist.** Composer finds `saij/wp-app-framework` without a `repositories` entry now. Remove the `vcs` entry for the framework from your `composer.json` and run `composer update saij/wp-app-framework` (see [Releases and CI](../build-and-release/ci.md#requiring-the-framework)); a project that keeps the entry still works.

**The framework is licensed under the GPL-2.0-or-later.** Releases before 0.15 declared a proprietary license. A theme or plugin that ships the framework must be under a GPL-compatible license; this is also what the plugin directory of WordPress.org requires (see [Distribution](../build-and-release/release.md#distribution)).

## 0.14

**The release workflow tests in wp-test-env.** `consumer-release.yml` used to install the release into the release site of wp-dev-env. Since 0.14 it uses [wp-test-env](https://github.com/saijsaij/wp-test-env), which starts a fresh WordPress for every test run and runs the browser tests with one Playwright worker per instance (see [Releases and CI](../build-and-release/ci.md)). Three things change in your project:

- The input `wp-dev-env-ref` is now `wp-test-env-ref`. Rename it if your workflow sets it.
- Your Playwright configuration receives the instance URLs in `WP_BASE_URLS` instead of one URL in `WP_BASE_URL`, and no `E2E_BROWSER_HOST_RULES` any more. Set `workers` to the number of URLs, pick the URL of each worker by `TEST_PARALLEL_INDEX`, and log in per worker instead of in a global setup. The configuration and `fixtures.js` of [theme-dev-test](https://github.com/saijsaij/theme-dev-test) show how.
- `tests/cli/run` gets the name of a wp-test-env instance instead of a Compose service of wp-dev-env, and runs WP-CLI with `../wp-test-env/bin/wp <instance> …` instead of `docker compose exec`.

## 0.13

**The stylesheet moved to `assets/styles.css`.** Each project now has an `assets/` directory for everything that belongs to no PHP class (see [Concepts](../concepts.md#src-and-assets)), and the Tailwind stylesheet is the first file there. Move `src/styles.css` to `assets/styles.css` and make the paths in it relative to the new place: `@source "./**/*.twig"` becomes `@source "../src/**/*.twig"`, and `url(…)` to files in `src/` gets a `../src/` in front. A file left at `src/styles.css` is a build error in the manifest build and in the webpack helper, so it cannot be forgotten.

**`asset_url`, `asset_image` and `asset_svg` are framework functions.** 0.13 adds them for [static assets](../guides/assets.md). A template function of your own with one of these names is now a build error; rename it.

**Scripts in `src/` need a block or block support.** An `*.editor.js` or `*.view.js` in `src/` without a block or block support class of the same name next to it is now a build error. Before 0.13 the webpack helper built such a file, but the framework never loaded it.

## 0.12

**Deleting a plugin deletes its data.** Before 0.12, the framework left everything behind when a plugin was deleted. Since 0.12 it removes the posts of the plugin's post types, its terms, meta keys, settings, tables, cron events, technical options and caches (see [Activation, deactivation and uninstallation](../guides/lifecycle.md)). Back up the data before you delete a plugin whose data you want to keep.

**A plugin must not have an `uninstall.php`.** WordPress runs this file instead of the uninstall hook of the framework, so it is now a build error. Move its code into a method with `#[OnUninstall]`.

## 0.11

**`link_attributes` is a framework function.** 0.11 adds the template function `link_attributes()` for [links](../guides/values.md#links). A template function of your own with this name is now a build error; rename it.

**Field classes name the class they belong to `class`.** Since 0.11 fields describe the columns of [tables](../guides/tables.md) as well as meta fields, so the first parameter of their constructors is called `$class` instead of `$metaClass`, and the getter `getMetaClass()` is now `getClass()`. Field classes written by hand or generated by `wp-app-framework meta-fields` pass the class positionally and keep working. Change the code only where it names the argument (`new IntField(metaClass: EventDetails::class, …)` becomes `new IntField(class: EventDetails::class, …)`) or calls `getMetaClass()`.

## 0.10

**Inner blocks are declared with `#[InnerBlocks]`.** Before 0.10, `#[Block]` had one argument for containers, `allowedBlocks`, with block names as strings. 0.10 adds templates, locks and more for containers (see [Container blocks](../guides/containers.md)); they live in an attribute of their own, `#[InnerBlocks]`, and `allowedBlocks` moved there as `allowed`. Blocks are named so that your IDE can resolve them: blocks of your own by class, core blocks by `CoreBlock`.

| Before | Since 0.10 |
|---|---|
| `#[Block('section', …, allowedBlocks: ['hero', 'core/paragraph'])]` | `#[Block('section', …)]` and `#[InnerBlocks(allowed: [Hero::class, CoreBlock::Paragraph])]` |
| `usesContext: ['sections/…']` for context of your own blocks | `#[ProvidesContext]` on the attribute and a `render()` parameter with `#[Context(Block::class, 'attribute')]` |

`allowedBlocks` is removed without alias, so PHP reports the unknown argument. The build reports a block name in your own namespace (name the block by class) and a core block named as string. Other blocks stay strings with their full name.

**`field()` checks its path only in the editor.** Before 0.10, a wrong path also threw on the front end. Since 0.10 `field()` returns an empty string outside the editor without looking at the path, so it costs nothing on the front end. A wrong path still fails when the block renders in the editor.

**Every block has an inserter preview.** Without `#[Example]` the inserter now previews a block with its default attributes. Add `#[Example(false)]` to a block that should show none.

## 0.9

**Each theme and plugin declares a namespace.** Before 0.9, block names carried their namespace in `#[Block]` (`sections/hero`), REST controllers named their full REST namespace (`sections/v1`), and settings options were named after the slug, the directory name of the theme or plugin. The directory name changes when a theme is installed under another name, and its settings would be lost with it. Since 0.9 the namespace in `composer.json` names everything content and data refer to (see [Concepts](../concepts.md#consumers-and-instances)).

Add the namespace to `composer.json`. Use the namespace your blocks already have, so posts, templates and patterns keep working unchanged:

```json
"extra": { "wp-app-framework": { "namespace": "sections" } }
```

Then change your code:

| Before | Since 0.9 |
|---|---|
| `#[Block('sections/hero', …)]` | `#[Block('hero', …)]` |
| `allowedBlocks: ['sections/hero', 'core/paragraph']` | `allowedBlocks: ['hero', 'core/paragraph']` |
| `registerControl( 'sections/hero', … )`, also `registerPanel()` and `registerToolbarButton()` | `registerControl( 'hero', … )` |
| `#[RestController('sections/v1', …)]` | `#[RestController('v1', …)]` |

The build reports a missing namespace, a block name with `/` and an unknown own block in `allowedBlocks`; the register functions warn in the console about names with a namespace. Block markup in templates, parts and patterns keeps the full names.

**Settings options are named after the namespace.** The option of a settings group is now `{namespace}_{name}` instead of `{slug}_{name}`, with `_` instead of `-`. If the two differ – the theme `my-theme` with the namespace `sections` stores the group `general` in `sections_general` instead of `my_theme_general` – the saved settings are not moved. Copy them once, for example with WP-CLI: `wp option get my_theme_general --format=json | wp option update sections_general --format=json`.

## 0.8

**The build checks block templates, template parts and patterns.** Before 0.8 the build looked at your PHP classes only. Mistakes in the block markup of your theme – a misspelled attribute, a value outside an enum, HTML in a block without a `postContent` field – went unnoticed, and WordPress dropped what it could not use. Since 0.8 the build checks these files against your blocks (see [Checking templates, parts and patterns](../build-and-release/build.md#checking-templates-parts-and-patterns)).

A theme or child theme that built fine with 0.7 may now fail with errors in `templates/`, `parts/` or `patterns/`. Each error names the file, the line and the block; fix the markup as the message says. Two errors need more than a fix in place: PHP code inside the block comment of one of your blocks in a pattern (write the attributes as plain JSON), and a container of your theme without content that holds content blocks in a template part or a pattern with a single top-level block (give it `#[Supports(contentRole: true)]`).

## 0.7

**Template functions that wrap WordPress functions start with `wp_`.** Before 0.7 a template could call `post_title()` or `attachment_image()`, and nothing showed that these were WordPress functions rather than framework functions or functions of the theme. 0.7 gives all of them the prefix `wp_` and adds many more (see [WordPress functions](../guides/twig.md#wordpress-functions)).

The old names are removed without aliases. A template that still uses one fails to compile with an unknown function, so you won't miss any. Rename them in your templates:

| Before | Since 0.7 |
|---|---|
| `attachment_image()` | `wp_attachment_image()` |
| `post_url()` | `wp_post_url()` |
| `post_title()` | `wp_post_title()` |
| `term_url()` | `wp_term_url()` |
| `term_name()` | `wp_term_name()` |
| `user_name()` | `wp_user_name()` |

If your own template functions use one of the new `wp_` names, rename them as well: a name the framework already uses is a build error.

## 0.3

**Child themes override theme templates in `overrides/{theme}/` instead of `src/`.** Since 0.3 a child theme can have code of its own and boot its own framework instance with `ChildTheme::boot()` (see [Child themes](../guides/child-themes.md)). Its `src/` directory now belongs to that instance and can no longer hold overrides of the parent theme.

Move each override from the child theme's `src/` to `overrides/{theme}/`, keeping the path. For the parent theme `my-theme`, `src/Block/Hero/Hero.twig` becomes `overrides/my-theme/Block/Hero/Hero.twig` (see [Twig templates](../guides/twig.md#lookup)).

## 0.2

**The init action is named after the consumer.** Since 0.2 plugins can use the framework next to a theme, and each runs its own instance. The single action `wp_app_framework/init` could not tell them apart, so it became `wp_app_framework/init/{slug}` (see [Boot and modes](../guides/boot.md#slug-namespace-and-init-action)).

Replace `add_action('wp_app_framework/init', …)` with the action of the instance you mean, for example `wp_app_framework/init/my-theme`.
