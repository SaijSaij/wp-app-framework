# CLAUDE.md – wp-app-framework

PHP/JS library (Composer `saij/wp-app-framework`, namespace `Saij\WpAppFramework`, JS `@saij/wp-app-framework`) for building standalone full site editing themes, child themes and plugins for WordPress 7 (latest stable, never beta) on PHP 8.5. It is neither a theme nor a plugin and registers no blocks itself. The user documentation is the website built from `docs/` (https://saijsaij.github.io/wp-app-framework/); the README covers development of the framework; this file lists what matters when changing the framework.

<!-- Maintainers: keep this file under 200 lines; describe behavior in the documentation (docs/content/) and link to it from here. -->

## Principles

1. **No markup in post content.** Every block renders on the server. `post_content` holds block comments with JSON attributes; block supports are attributes too. Sole exception: at most one `#[RichText(postContent: true)]` field per block, stored as inline HTML without wrapping element between the comments (`source: html` without selector). No other attribute uses `source: html|attribute|text`; markup lives in Twig only.
2. **Inline editing in the editor canvas is mandatory.**
3. **Full site editing.** Pages are block templates (`templates/*.html`) and template parts (`parts/*.html`). Twig renders block markup and markup fragments of consumer code (mails, HTML responses), never page layouts.
4. **Twig for block markup, Tailwind (v4) for styling.** The framework provides the build (`src/styles.css` → `build/styles.css` in the webpack helper) and loads the result; tokens and their mapping to WordPress' preset variables belong to the theme. The framework ships no CSS for the front end.
5. **Plain WordPress:** no Bedrock, no Composer on the server.
6. **Everything that can be expressed as a value** (colors, spacing, variants, order) is configurable in the backend; new block types, roles and layout structures stay code.

Fixed design decisions:

- No `block.json`: blocks are PHP classes with attributes; the build derives the attribute schema.
- Compile only in the build, never at runtime. The manifest is plain data (`var_export`). Release mode uses no reflection, no stat checks, no `composer.json` and never writes into a consumer's directory. Development mode may use reflection but writes only to `wp-content/cache/{slug}/`.
- Own minimal, internal DI container: constructor autowiring of concrete classes, shared instances, no public `get()`, no aliases, no tags.
- Prefixing with Strauss is mandatory in every release; each consumer ships its own prefixed framework.
- Consumers are themes (`Theme::boot()`), child themes with code (`ChildTheme::boot()`) and plugins (`Plugin::boot(__FILE__)`). Each is an isolated instance: names of content and data (blocks `{ns}/name`, REST routes `{ns}/{version}`, settings options `{ns}_name`) derive from its namespace (`extra.wp-app-framework.namespace`, stored in the manifest), technical names (script handles, JS global, cache paths, editor routes, init action `wp_app_framework/init/{slug}`) from its slug. Instances talk only through the public interface (filters `wp_app_framework/api/{slug}` and `wp_app_framework/template/{slug}`).
- Twig lookup: child `overrides/{theme-slug}/` → theme `src/` → framework `src/`; plugins: child and theme `overrides/{plugin-slug}/` → plugin `src/` → framework `src/`. Template names are full paths relative to `src/`.

## Rules

- Verify WordPress, Twig and Strauss behavior in their source code or official documentation before relying on it, and cite file and line (WordPress core: `/var/www/html` in the `wordpress` container of wp-dev-env; Twig: `vendor/twig/twig`). Say clearly when something is not verified.
- Fix errors at their cause; no stacked workarounds.
- Explicit over implicit: no silent fallbacks, no silently failing `file_exists()`, no unmarked placeholders, no undocumented behavior sold as reliable. Missing prerequisites raise clear exceptions or build errors naming class, file and parameter.
- Minimal: no dependencies or abstractions without demonstrated need.
- Class properties are never `public`; access through getters (`get…`, booleans `is…`/`has…`). Exceptions: PHP attribute classes, block attribute classes and their value classes (`readonly`, public promoted properties), test doubles of WordPress classes (mirror WordPress' public properties).
- The consumer-facing API must be fully resolvable by IDEs: enums and classes instead of magic strings, first-class callables instead of method names, required information as required attribute arguments.
- Everything in this repository is English: code, identifiers, comments, README, commit messages.
- READMEs link to the other repositories by GitHub URL (`https://github.com/saijsaij/<repo>`), never by relative path. Pages in `docs/content/` link to each other by relative `.md` paths.

## Writing documentation

The pages in `docs/content/` are written for people learning the framework, not as a specification:

- Explanatory prose that addresses the reader ("you"), in active sentences with one idea each. Start a page with what the feature is for and why it exists, then how it works, then an example built up step by step with text between the code blocks.
- WordPress internals go into a sentence of their own, not into parentheses in the middle of a rule.
- Exact rules – signatures, parameters, defaults, build errors – stay complete; collect them in a "Rules" section or a table at the end of a section instead of packing them into the prose. Tables and lists are for reference, not for explanations.
- Shared ideas (instances, build and run time, modes, how a block works, template layers) are explained once in `concepts.md`; other pages link there.
- Use Rspress callouts (`:::warning`, `:::tip`) sparingly, for pitfalls.

## Commands

All checks run in the `tools` container of [wp-dev-env](https://github.com/saijsaij/wp-dev-env), checked out next to this repository; run the commands from the wp-dev-env directory. Every check must pass without errors or warnings.

```sh
docker compose run --rm tools composer --working-dir=/workspace/wp-app-framework install
docker compose run --rm tools composer --working-dir=/workspace/wp-app-framework test   # PHPUnit
docker compose run --rm tools composer --working-dir=/workspace/wp-app-framework lint   # PHPStan (level max) + PHP-CS-Fixer (PER-CS); fix: composer fix
docker compose run --rm tools npm --prefix /workspace/wp-app-framework ci
docker compose run --rm tools npm --prefix /workspace/wp-app-framework test             # Vitest
docker compose run --rm tools npm --prefix /workspace/wp-app-framework run lint         # ESLint + Stylelint; fix: npm run format
docker run --rm -v "$PWD/../wp-app-framework:/repo" -w /repo rhysd/actionlint:1.7.12   # after changing .github/workflows/
docker compose run --rm tools npm --prefix /workspace/wp-app-framework/docs ci
docker compose run --rm tools npm --prefix /workspace/wp-app-framework/docs run build  # documentation; fails on broken links and anchors
```

`npm run lint` and `npm run format` include the scripts and styles in `docs/`; ESLint resolves their imports in `docs/node_modules`, so install `docs/` first.

## Tests

- Units and **every build error** are tested here: PHPUnit with fixtures in `tests/Fixtures/`, Vitest in `assets/runtime/test/`, `assets/view/test/` (view runtime) and `assets/test/` (webpack helper). Fixtures that are wrong on purpose carry `@phpstan-ignore` with the reason. Tests in `tests/Unit/` mirror `src/`.
- Everything that needs WordPress, a browser or a complete consumer build (successful manifest build, REST, Twig in WordPress, blocks on the front end and in the editor, release ZIP) is tested in [theme-dev-test](https://github.com/saijsaij/theme-dev-test); plugin and child theme behavior in [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test) and [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child). Code written to check a feature stays there as a permanent test.
- WordPress is not loaded in unit tests. The test doubles in `tests/Stubs/` follow the real WordPress 7.1 signatures and are loaded by `tests/bootstrap.php` only.

## Pitfalls

- Changing the manifest structure requires bumping `Manifest::FORMAT` (`src/Manifest/Manifest.php`).
- Only plain PHP and WordPress types cross instance boundaries: every release prefixes the framework and its dependencies differently (e.g. never pass a `Twig\Markup` to another instance).
- In development mode all consumers share the unprefixed framework and Twig classes; the boot checks that the loaded framework is the consumer's own (real path) and the Twig version matches.
- `generated/manifest.php` in a consumer selects release mode; never write it into a source checkout.
- Twig does not recompile a template class already loaded in the same process – check template changes per request in WordPress, not within one PHPUnit process.
- OPcache in the WordPress image revalidates every 2 seconds (`revalidate_freq=2`): changes may need a moment to show up.
- The documentation is published per minor version from the tag (`release.yml`, job `docs`) and as `next` from `main` (`docs.yml`) into directories of the `gh-pages` branch (`docs/scripts/publish.mjs`); never push to `gh-pages` by hand. Each build needs its own `DOCS_BASE` (`/wp-app-framework/<version>/`); the build script disables Rspress' persistent cache, whose key ignores the base.
- `Release\TwigPatcher` expects exact match counts in Twig's compiler; a Twig update that changes them fails the release build on purpose – check the patch list against the new Twig source.
- The development cache rebuilds only when signatures (attributes, signatures of constructor and public methods) or relevant file contents change; a new kind of input to the build must be part of the signature or the listing hash.

## Compatibility and releases

- Within a major version the consumer API only grows: nothing is renamed, removed or changed in behavior. Consumer API: attributes and their parameters, boot methods, Twig functions, JS exports of `@saij/wp-app-framework`, the webpack helper and its conventions, the `release` command and its options, the inputs of `.github/workflows/consumer-release.yml`. Breaking changes before 1.0 are listed in `docs/content/reference/upgrading.md`.
- The filters `wp_app_framework/api/{slug}` and `wp_app_framework/template/{slug}`, the init action `wp_app_framework/init/{slug}`, the action `wp_app_framework/namespace/{namespace}` and the shape of their values are a contract between framework versions: they may only be extended, also across major versions. Internals such as the manifest format are excluded.
- Tags `v<semver>`. `release.yml` gates a tag with theme-dev-test, plugin-dev-test and theme-dev-test-child built with the framework of the tag. Order when releasing changes that consumers depend on: wp-dev-env → framework (`main`) → consumers update their lock file and tag their releases → framework tag. The child and plugin gates use a released theme (`with-theme`), so that theme release must exist first.
- Every change of the consumer API or of observable behavior updates the documentation pages in `docs/content/` in the same change.
