# CLAUDE.md – wp-app-framework

PHP/JS library (Composer `saij/wp-app-framework`, namespace `Saij\WpAppFramework`, JS `@saij/wp-app-framework`) for building standalone full site editing themes, child themes and plugins for WordPress 7 (latest stable, never beta) on PHP 8.5. It is neither a theme nor a plugin and registers no blocks itself. The user documentation is the website built from `docs/` (https://saijsaij.github.io/wp-app-framework/); the README covers development of the framework; this file lists what matters when changing the framework.

<!-- Maintainers: keep this file under 200 lines; describe behavior in the documentation (docs/content/) and link to it from here. -->

**Read first:** the developer documentation in `docs/content/development/` is the source for how the framework is built and worked on; this file keeps only the short rules, commands and pitfalls. Read the page that fits the task before you start:

- `architecture.md` – build and run time, manifest, instances, container, editor runtime, release build, source layout;
- `design.md` – principles, fixed decisions, rejected alternatives (do not propose them again), lessons of earlier projects;
- `conventions.md` – code, error messages, the public API and its compatibility, how to write documentation, commits;
- `testing.md` – which test goes where, test doubles, browser test rules, what to run at which stage;
- `pipelines.md` – the workflows, release gates and the release procedure with release notes;
- `adding-a-feature.md` – the checklist for a new or changed feature.

## Principles

1. **No markup in post content.** Every block renders on the server. `post_content` holds block comments with JSON attributes; block supports are attributes too. Sole exception: at most one `#[RichText(postContent: true)]` field per block, stored as inline HTML without wrapping element between the comments (`source: html` without selector). No other attribute uses `source: html|attribute|text`; markup lives in Twig only.
2. **Inline editing in the editor canvas is mandatory.**
3. **Full site editing.** Pages are block templates (`templates/*.html`) and template parts (`parts/*.html`). Twig renders block markup and markup fragments of consumer code (mails, HTML responses), never page layouts.
4. **Twig for block markup, Tailwind (v4) for styling.** The framework provides the build (`assets/styles.css` → `build/styles.css` in the webpack helper) and loads the result; tokens and their mapping to WordPress' preset variables belong to the theme. The framework ships no CSS for the front end.
5. **Plain WordPress:** no Bedrock, no Composer on the server.
6. **Everything that can be expressed as a value** (colors, spacing, variants, order) is configurable in the backend; new block types, roles and layout structures stay code.

Fixed design decisions:

- No `block.json`: blocks are PHP classes with attributes; the build derives the attribute schema.
- Compile only in the build, never at runtime. The manifest is plain data (`var_export`). Release mode uses no reflection, no stat checks, no `composer.json` and never writes into a consumer's directory. Development mode may use reflection but writes only to `wp-content/cache/{slug}/`. The only commands that write source code are `wp-app-framework meta-fields` and `table-fields` (a class with `#[MetaFields]` or `#[TableFields]`), on explicit request of the developer.
- Queries (`Event::query()`) are immutable (`#[\NoDiscard]` on every method returning a query) and run through `WP_Query` without `suppress_filters`; sorting by meta fields writes its own SQL clauses (`Query\MetaOrderClauses`, filter `posts_clauses` only while the query runs) so that missing values sort like their default.
- Own minimal, internal DI container: constructor autowiring of concrete classes, shared instances, no public `get()`, no aliases, no tags. Post classes are active records (`Event::get()`, `save()`): they reach the store of their instance through the internal `Post\PostRegistry`, filled at boot; post, meta and taxonomy classes are no services. Row classes (`#[Table]`) work the same through `Table\TableRegistry`.
- Tables of consumers are synced by `Table\SchemaSync` (no `dbDelta()`), only when the schema hash of the manifest differs from the autoloaded option `{slug}_tables`: a request with current tables costs no database query (`TableStore::ensure()`, on `init` and before every access).
- Prefixing with Strauss is mandatory in every release; each consumer ships its own prefixed framework.
- Consumers are themes (`Theme::boot()`), child themes with code (`ChildTheme::boot()`) and plugins (`Plugin::boot(__FILE__)`). Each is an isolated instance: names of content and data (blocks `{ns}/name`, REST routes `{ns}/{version}`, settings options `{ns}_name`, WP-CLI commands `wp {ns} …`) derive from its namespace (`extra.wp-app-framework.namespace`, stored in the manifest), technical names (script handles, JS global, cache paths, editor routes, init action `wp_app_framework/init/{slug}`) from its slug. Instances talk only through the public interface (filters `wp_app_framework/api/{slug}` and `wp_app_framework/template/{slug}`).
- Twig lookup: child `overrides/{theme-slug}/` → theme `src/` → framework `src/`; plugins: child and theme `overrides/{plugin-slug}/` → plugin `src/` → framework `src/`. Template names are full paths relative to `src/`.

## Rules

- Verify WordPress, Twig and Strauss behavior in their source code or official documentation before relying on it, and cite file and line (WordPress core: `/var/www/html` in the `wordpress` container of wp-dev-env; Twig: `vendor/twig/twig`). Say clearly when something is not verified.
- Fix errors at their cause; no stacked workarounds.
- Explicit over implicit: no silent fallbacks, no silently failing `file_exists()`, no unmarked placeholders, no undocumented behavior sold as reliable. Missing prerequisites raise clear exceptions or build errors naming class, file and parameter.
- Minimal: no dependencies or abstractions without demonstrated need.
- Class properties are never `public`; access through getters (`get…`, booleans `is…`/`has…`). Exceptions: PHP attribute classes, block attribute classes and their value classes (`readonly`, public promoted properties), meta classes (`#[PostMeta]`, not readonly, promoted properties of any visibility), test doubles of WordPress classes (mirror WordPress' public properties).
- The consumer-facing API must be fully resolvable by IDEs: enums and classes instead of magic strings, first-class callables instead of method names, required information as required attribute arguments.
- Everything in this repository is English: code, identifiers, comments, README, commit messages.
- READMEs link to the other repositories by GitHub URL (`https://github.com/saijsaij/<repo>`), never by relative path. Pages in `docs/content/` link to each other by relative `.md` paths.

## Writing documentation

Guide pages are explanatory prose for people learning the framework (what for, how, example, rules collected at the end); the API reference is generated from `@api` doc comments, which every API class, public member and parameter needs (no raw `{`, `}`, `<`, `>` outside backticks). Details: `docs/content/development/conventions.md#documentation`.

## Commands

All checks run in the tools image of [wp-test-env](https://github.com/saijsaij/wp-test-env), checked out next to this repository; run the commands from this directory. Every check must pass without errors or warnings.

```sh
../wp-test-env/bin/tools -w wp-app-framework composer install
../wp-test-env/bin/tools -w wp-app-framework composer test   # PHPUnit
../wp-test-env/bin/tools -w wp-app-framework composer lint   # PHPStan (level max) + PHP-CS-Fixer (PER-CS); fix: composer fix
../wp-test-env/bin/tools -w wp-app-framework npm ci
../wp-test-env/bin/tools -w wp-app-framework npm test             # Vitest
../wp-test-env/bin/tools -w wp-app-framework npm run lint         # ESLint + Stylelint + tsc over types/; fix: npm run format
docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.12  # after changing .github/workflows/
../wp-test-env/bin/tools -w wp-app-framework/docs npm ci
../wp-test-env/bin/tools -w wp-app-framework/docs npm run build  # documentation; fails on broken links and anchors
```

Browser, WP-CLI and release tests of the consumers run in pools of wp-test-env (`bin/run` with the pool files in their `tests/`); see their CLAUDE.md.

`npm run lint` and `npm run format` include the scripts and styles in `docs/`; ESLint resolves their imports in `docs/node_modules`, so install `docs/` first.

## Tests

Units and every build error are tested here (PHPUnit with fixtures in `tests/Fixtures/`, `@phpstan-ignore` with the reason on fixtures that are wrong on purpose; Vitest next to the runtime modules); everything that needs WordPress, a browser or a complete consumer build in the test consumers. WordPress is not loaded in unit tests: the doubles in `tests/Stubs/` follow the real WordPress 7.1 signatures; the WP-CLI double is also PHPStan's signature file. Stages: while developing only the tests of the change, before a commit all linters and builds, before a push the browser suites of the affected consumers in development mode, at a release the release pools and the benchmark. Details: `docs/content/development/testing.md`.

## Pitfalls

- Changing the manifest structure requires bumping `Manifest::FORMAT` (`src/Manifest/Manifest.php`).
- Only plain PHP and WordPress types cross instance boundaries: every release prefixes the framework and its dependencies differently (e.g. never pass a `Twig\Markup` to another instance).
- In development mode all consumers share the unprefixed framework and Twig classes; the boot checks that the loaded framework is the consumer's own (real path) and the Twig version matches.
- `generated/manifest.php` in a consumer selects release mode; never write it into a source checkout.
- Twig does not recompile a template class already loaded in the same process – check template changes per request in WordPress, not within one PHPUnit process.
- OPcache in the WordPress image revalidates every 2 seconds (`revalidate_freq=2`): changes may need a moment to show up.
- The documentation is published per minor version from the tag (`release.yml`, job `docs`) and as `next` from `main` (`docs.yml`) into directories of the `gh-pages` branch (`docs/scripts/publish.mjs`); never push to `gh-pages` by hand. Each build needs its own `DOCS_BASE` (`/wp-app-framework/<version>/`); the build script disables Rspress' persistent cache, whose key ignores the base.
- `Release\TwigPatcher` expects exact match counts in Twig's compiler; a Twig update that changes them fails the release build on purpose – check the patch list against the new Twig source.
- `WpCli\CliCommandCollector::WP_CLI_COMMANDS` lists the root commands of WP-CLI; the build rejects them as namespace of a consumer with commands. The CLI test of theme-dev-test (`tests/cli/run`) compares the list with the installed WP-CLI – update it when that test fails after a WP-CLI update.
- The build never loads a class of the sources that builds on a class outside the project, its Composer dependencies and PHP (`Manifest\Discovery\ExternalParents`, read with the tokenizer): a framework class extending a WordPress class would be excluded the same way. Unit tests that build a project with `ManifestBuilder::forProject()` need `Tests\Support\ProjectLoader`.
- The development cache rebuilds only when signatures (attributes, signatures of constructor and public methods) or relevant file contents change; a new kind of input to the build must be part of the signature or the listing hash.

## Compatibility and releases

- Within a major version the consumer API only grows (what the API reference lists); before 1.0, every break gets a section in `docs/content/reference/upgrading.md`. The filters and actions between instances (`wp_app_framework/api/{slug}`, `…/template/{slug}`, `…/init/{slug}`, `…/namespace/{namespace}`) may only be extended, also across major versions.
- Every change of the consumer API or of observable behavior updates `docs/content/` in the same change; every change of conventions, tests or pipelines updates `docs/content/development/`.
- Releases follow the procedure in `docs/content/development/pipelines.md` (release pools, benchmark, wp-test-env first, framework `main`, consumer locks and tags, framework tag with the four gates, release notes, starter, package links); every GitHub release gets written release notes instead of the generated ones.
