# Testing

The framework is tested in two places. Its own repository tests every unit and every build error without WordPress. The test consumers test everything that needs WordPress, a browser or a complete build of a theme or plugin. All tests run in [wp-test-env](https://github.com/webcodingcat/wp-test-env), locally and in CI, never against a development site of wp-dev-env.

## Which test goes where

| What | Where | How |
|---|---|---|
| A unit of PHP code, a collector, a **build error** | wp-app-framework, `tests/Unit/` (mirrors `src/`), fixtures in `tests/Fixtures/` | PHPUnit |
| A module of the editor runtime, the view runtime or the webpack helper | wp-app-framework, `assets/runtime/test/`, `assets/view/test/`, `assets/test/` | Vitest |
| The JavaScript API against its declarations | `assets/test/public-api.test.js`, `types/check/` | Vitest, `tsc` |
| A successful build of a consumer, blocks on the front end and in the editor, REST, Twig in WordPress, settings, post types, tables, cron … | [theme-dev-test](https://github.com/webcodingcat/theme-dev-test), `tests/e2e/specs/` | Playwright |
| Rendering and escaping with fixed attribute sets, without a browser | theme-dev-test, `tests/render/cases.php` | `tests/render/run` |
| WP-CLI commands | theme-dev-test and plugin-dev-test, `tests/cli/run` | shell script against an instance |
| Activation, deactivation, uninstallation | theme-dev-test and plugin-dev-test, `tests/lifecycle/run` | shell script against an instance |
| Plugin support, the public interface between instances, two framework versions side by side | [plugin-dev-test](https://github.com/webcodingcat/plugin-dev-test) and the theme's `plugin.spec.js` | Playwright |
| Child theme support and overrides | [theme-dev-test-child](https://github.com/webcodingcat/theme-dev-test-child) | Playwright |
| The cost per request | [theme-bench](https://github.com/webcodingcat/theme-bench) | `bin/bench`, `--coverage` |
| Projects created by the starter | [wp-app-starter](https://github.com/webcodingcat/wp-app-starter), `tests/projects` | all of the above for a theme, a child theme and a plugin |

Build errors are always tested in the framework: a class that is wrong on purpose cannot live in a consumer, because it would break the consumer's build. Such fixtures carry `@phpstan-ignore` with the reason. A successful build, and everything a consumer does at run time, is tested in theme-dev-test. Code written to check a feature stays there as a permanent test.

What a consumer does *with* the plugin – calling its public API, its public template functions, overriding its templates – is tested by the theme's suite; it branches explicitly on the plugin status it reads from the REST API, so the suite passes with and without the plugin.

## Test doubles

WordPress is not loaded in unit tests. `tests/Stubs/` holds minimal doubles of the WordPress classes and functions the framework uses, with the real signatures of WordPress 7.1, loaded only by the PHPUnit bootstrap; PHPStan checks the tests against these signatures. A double behaves like WordPress where the framework depends on it – for example, `wp_delete_post()` moves only posts and pages to the trash, as WordPress does – and a comment says which behavior it models. The WP-CLI double (`tests/Stubs/wp-cli.php`, WP-CLI 2.12.0) is also PHPStan's signature file: add a symbol there with its real signature before the framework uses it.

## Browser tests

The browser tests of the consumers follow a few rules, so that they stay reliable when they run in parallel:

- **One WordPress instance per Playwright worker.** `bin/e2e` of wp-test-env passes the URLs of the instances (`WP_BASE_URLS`); the Playwright configuration picks one per worker by `TEST_PARALLEL_INDEX`. Tests of different workers never share a site, and a spec never depends on another spec file.
- **Every test cleans up exactly what it created** – posts, media, users – never "delete all".
- **No console noise.** The fixtures in `tests/e2e/fixtures.js` fail a test on every console warning, console error and page error. A test that expects a message declares it with `expectConsole`; a warning WordPress causes with every theme goes into `ALLOWED`, with the reason and where WordPress causes it.
- **30 seconds per test.** A test that needs longer sets its own timeout and says why.
- **The same suite runs against the release ZIP** (`E2E_TARGET=release`); only expectations that differ between the modes branch on it.
- **Every spec is listed** in the spec table of the consumer's README.

An unstable test is a bug: find the cause and fix it, instead of retrying. The login test of the settings page, for example, typed into the wrong field because `wp-login.php` moves the focus 200 ms after loading; it now waits for the focus.

## When to run what

The checks are staged, so that development stays fast:

1. **While you develop:** only the tests of the change – PHPUnit with `--filter`, one Vitest file, one spec.
2. **Before a commit:** all linters and builds of the repositories you changed – `composer lint`, `npm run lint`, `composer build-manifest` and `npm run build` of a changed consumer, the documentation build when pages changed.
3. **Before a push:** the development build – the browser suites of the affected consumers in development mode, with your framework checkout linked into them:

   ```sh
   cd wp-test-env
   bin/run ../theme-dev-test/tests/dev.pools ../plugin-dev-test/tests/dev.pools ../theme-dev-test-child/tests/dev.pools
   ```

4. **At a release:** nothing locally. The release workflows build the consumers' ZIPs, run their suites against them and gate the framework (see [Pipelines and releases](./pipelines.md#the-release-procedure)). To reproduce a failed release workflow, run the release pools of the consumer:

   ```sh
   bin/run ../theme-dev-test/tests/release.pools ../plugin-dev-test/tests/release.pools ../theme-dev-test-child/tests/release.pools
   ```

Every check passes without errors and without warnings.

## Commands

All commands run in the tools image of wp-test-env, from its directory, with the repositories next to it:

```sh
bin/tools -w wp-app-framework composer test        # PHPUnit
bin/tools -w wp-app-framework composer lint        # PHPStan and PHP-CS-Fixer; fix: composer fix
bin/tools -w wp-app-framework npm test             # Vitest
bin/tools -w wp-app-framework npm run lint         # ESLint, Stylelint, tsc over types/; fix: npm run format
bin/tools -w wp-app-framework/docs npm run build   # documentation; fails on broken links and anchors
docker run --rm -v "$PWD/../wp-app-framework:/repo" -w /repo rhysd/actionlint:1.7.12   # after changing workflows
```

`npm run lint` includes the scripts and styles in `docs/`, so install `docs/` first (`npm ci` there).

For the browser tests, wp-test-env starts instances, runs the suites and removes the instances again:

```sh
bin/run ../theme-dev-test/tests/dev.pools                      # all pools of one consumer
bin/up tdt --theme theme-dev-test --activate theme-dev-test     # one instance, kept running
bin/e2e --instances tdt --project theme-dev-test --results tdt -- specs/blocks.spec.js
bin/down tdt
```

A **pool file** (`tests/dev.pools`, `tests/release.pools` of each consumer) describes the instances of a run: which projects are prepared and linked, which themes and plugins (or ZIPs) each pool installs, and whether it runs the browser tests or a script. The package links (`link webcodingcat/wp-app-framework=wp-app-framework`) replace the framework the consumer installed with your checkout, so the consumers test it without a release. Plugins can run without a theme; then the default theme of WordPress is active. The [README of wp-test-env](https://github.com/webcodingcat/wp-test-env) describes the format.

## The benchmark

[theme-bench](https://github.com/webcodingcat/theme-bench) measures the cost of the framework per request: a theme built with the framework against the same theme without it, in release mode, in an instance of wp-test-env of its own. It is no part of a release: run it now and then, on request, to make sure the framework does not cost too much, and compare it with the last report – a new database query on the base load or a cost nobody can explain is fixed in the next release. Never run other tests at the same time. Every area of the framework that runs on requests has a scenario there; a new feature gets one (`bin/bench --coverage` checks it), and existing scenarios stay so reports remain comparable.
