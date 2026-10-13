# wp-app-framework

PHP/JS framework for building standalone full site editing themes, child themes and plugins for WordPress 7 (PHP 8.5).

- Composer package: `saij/wp-app-framework`
- PHP namespace: `Saij\WpAppFramework`
- JS import name: `@saij/wp-app-framework`, runtime global: `window.wpAppFramework[slug]` (one per framework instance)

The framework is a library that themes, child themes and plugins (its *consumers*) include via Composer. It is neither a theme nor a plugin and registers no blocks itself. Each consumer runs its own framework instance; blocks, REST routes and settings are named after the consumer's namespace (`extra.wp-app-framework.namespace`), technical names – script handles, JS global, cache directory, init action – after its slug, so a theme and several plugins with different framework versions run side by side.

## Documentation

**[saijsaij.github.io/wp-app-framework](https://saijsaij.github.io/wp-app-framework/)** – getting started, guides for every part of the API, build and release. It is published per minor version; `next` documents the unreleased state of `main`.

## Status

In development (0.x): framework core, REST routes, Twig, server-side blocks, block supports, settings, editor runtime, front-end scripts, release build, plugins and child themes. Breaking changes are listed in [Upgrading](https://saijsaij.github.io/wp-app-framework/next/reference/upgrading.html).

## Source layout

`src/` is organized by feature: each directory holds everything of one area – the attributes consumers use, the build-time collectors and the runtime classes.

| Directory | Contents |
|---|---|
| `Theme.php`, `ChildTheme.php`, `Plugin.php` | Boot and paths of the active theme, child theme or of a plugin |
| `Instance.php` | Boot of one framework instance (internal) |
| `Post/` | `#[PostType]`, `#[PostMeta]`, `#[Taxonomy]`, post objects (`AbstractPost`, `Post`, `Page`), core taxonomies (`Category`, `Tag`), `PostFormat`, collector (build), post store and registry, registration, rewrite rules and meta boxes (runtime), template function `post()` |
| `Query/` | `PostQuery` (`Event::query()`), conditions (`Terms`, `Any`, `All`), field classes in `Field/`, `#[MetaFields]` with its build check and generator, sort clauses for meta fields |
| `Project/` | Kind, slug, namespace and settings of a consumer from its `composer.json` (build) |
| `Api/` | `#[PublicApi]`, `#[PluginApi]`, `#[ThemeApi]` attributes, collector and signature checks (build), hooks between instances and `has_plugin()` (runtime) |
| `Hook/` | `#[Hook]` attribute, collector (build), definition, registrar (runtime) |
| `Rest/` | `#[RestController]`, `#[RestRoute]`, `#[Param]` attributes, `HttpMethod`/`Format` enums, `Permission/` (`PublicAccess`, `Capability`, `Callback`), `RestException`, collector (build), definition, registrar (runtime) |
| `Twig/` | `#[TemplateFunction]`, `#[TemplateFilter]` attributes, collector (build), definition, `TemplateRenderer` and runtime loader (runtime), override finder (build) |
| `Block/` | `#[Block]`, `#[Supports]`, `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Content]`, `#[Label]`, `#[Hidden]` attributes, `Labeled` interface, `BlockCategory`/`Align` enums, collector (build), definition, registrar and template functions (runtime); `Support/` (`#[BlockSupport]`, `#[UseSupport]`, `SupportGroup`, collector) |
| `Editor/` | Editor template functions (`field()`, `inner_blocks()`, `is_editor()`), render context, batch render and preview routes and editor assets (runtime) |
| `View/` | Loading of the front-end scripts (view scripts) of blocks and block supports and the marker of their root elements (runtime) |
| `Setting/` | `#[SettingsGroup]` attribute, collector (build), settings store, template global and settings page (runtime) |
| `Style/` | Loading of the consumer's built stylesheet (runtime) |
| `Cache/` | `#[Cache]` attribute, tags (`Tag/`), variants (`Vary/`), collector (build), `CacheStore` with backends (`Backend/`), tag versions and their hooks (runtime) |
| `Filesystem/` | Atomic file writes (build and runtime) |
| `Asset/` | Scripts next to classes (`ScriptKind`: editor and view scripts, build) and registration of scripts and styles from the consumer's build (runtime) |
| `Value/` | `#[ListOf]`, `#[Url]` attributes, schema derivation of typed values (build), value factory (runtime) |
| `DependencyInjection/` | `#[NoService]` attribute, service candidates and resolver (build), container (runtime) |
| `Manifest/` | Manifest builder, generator and loader, checks shared by entry point collectors; `Discovery/` (PSR-4 class discovery), `DevCache/` (development mode cache) |
| `Console/` | Command line entry point of `bin/wp-app-framework` (and `bin/wp-app-framework-prefixed.php`) |
| `Release/` | Release build: project copy, Strauss settings, Twig patch, prefix check, archive (build only) |

New areas get their own directory following the same pattern. Tests in `tests/Unit/` mirror this layout.

The editor runtime and its styles live in `assets/` (`editor.js` entry, `editor.scss` styles of the inspector, `settings.js` and `settings.scss` of the settings page, `meta-box.js` and `meta-box.scss` of the meta boxes, `_controls.scss` styles of the controls they share, `canvas.scss` styles of the editor canvas, `runtime/` modules, tests in `runtime/test/`; `view.js` entry of the view runtime with its modules and tests in `view/`); `webpack.config.js` is the helper for the consumer's build, tested in `assets/test/`. The documentation website lives in `docs/` (see Development).

## Conventions

- The theme API must be fully resolvable by IDEs: enums and classes instead of magic strings, first-class callables instead of method names, required information as required attribute arguments.
- Class properties are never public; access goes through getters with a `get` prefix (`is`/`has` for booleans). Attribute classes such as `#[Hook]` and value classes (block attribute classes) are the exceptions and expose public readonly properties; test doubles of WordPress classes (`tests/Stubs/`) mirror the public properties of WordPress.
- Code, comments and documentation are in English.

## Development

Themes and plugins require this repository by version (see [Releases and CI](https://saijsaij.github.io/wp-app-framework/next/build-and-release/ci.html)). During development, [wp-dev-env](https://github.com/saijsaij/wp-dev-env) replaces the installed package by a symlink to this checkout (`.env`: `WP_PACKAGE_LINKS=saij/wp-app-framework=wp-app-framework`), so changes take effect immediately without a release build; lock file and `composer.json` of the consumer stay unchanged. The consumer checkouts and this checkout share the same parent directory; see [theme-dev-test](https://github.com/saijsaij/theme-dev-test) and [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test), the test theme and test plugin of the framework, for examples. To test a release with unreleased framework changes: `composer release -- --package-source=saij/wp-app-framework=../wp-app-framework` in the consumer.

A local WordPress for testing is provided by [wp-dev-env](https://github.com/saijsaij/wp-dev-env).

All checks run in the `tools` container of [wp-dev-env](https://github.com/saijsaij/wp-dev-env) and must pass without errors or warnings.

PHP: unit tests (PHPUnit), static analysis (PHPStan, level max, with the WordPress stubs of `szepeviktor/phpstan-wordpress`) and code style (PHP-CS-Fixer, PER Coding Style). WordPress is not loaded in unit tests; minimal test doubles of the WordPress classes and functions with the signatures of WordPress live in `tests/Stubs/` and are loaded by the PHPUnit bootstrap only, so the analysis checks the tests against the real signatures. Fixtures that are wrong on purpose (build errors under test) carry `@phpstan-ignore` with the reason.

```sh
composer install
composer test
composer lint    # PHPStan and PHP-CS-Fixer
composer fix     # applies the code style
```

JavaScript and styles of the runtime: unit tests (Vitest), ESLint and Stylelint (configurations of `@wordpress/scripts`):

```sh
npm ci
npm test
npm run lint     # lint:js and lint:style
npm run format
```

`package-lock.json` pins these development tools. Themes do not use it: npm ignores the lock files of dependencies, so the theme's own lock file decides which versions it builds with.

Everything that needs WordPress, a browser or a complete consumer build – the successful manifest build, REST routes, Twig in WordPress, blocks on the front end and in the editor – is tested in [theme-dev-test](https://github.com/saijsaij/theme-dev-test); plugins and their interplay with the theme in [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test).

### Documentation

The documentation website lives in `docs/` (Rspress; pages in `docs/content/`, sidebar in `docs/rspress.config.mjs`). It is not part of the package (`export-ignore`). Every change of the consumer API or of observable behavior updates its pages in the same change.

```sh
npm --prefix docs ci
npm --prefix docs run build      # docs/build/, fails on broken links and anchors
npm --prefix docs run dev        # local preview with reload
```

GitHub Actions publish it to the `gh-pages` branch: `main` as `next/`, each release tag as its minor version (`docs/scripts/publish.mjs`).
