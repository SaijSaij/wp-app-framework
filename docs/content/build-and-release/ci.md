# Releases and CI

Repositories are released by tags `v<semantic version>`; GitHub Actions run the release pipeline and attach the artifact to the GitHub release of the tag, only after it passed its tests.

## Themes, child themes and plugins

They call the reusable workflow `.github/workflows/consumer-release.yml` of the framework repository from a workflow on `push: tags: ['v*']` with `permissions: contents: write` (see [theme-dev-test](https://github.com/saijsaij/theme-dev-test), [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child) and [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test)).

The workflow:

1. checks out the project and [wp-dev-env](https://github.com/saijsaij/wp-dev-env),
2. runs `composer lint` and `npm run lint` (if defined) and `wp-app-framework build`,
3. builds the release with `--expect-version` of the tag,
4. installs the ZIP into a plain WordPress (`scripts/release-test`),
5. runs the project's browser tests (`npm run test:e2e`, with `E2E_TARGET=release`; input `e2e: false` skips them).

The `type` in `composer.json` and the `Template:` header of `style.css` decide how the ZIP is installed; a plugin is installed next to the release ZIP of a theme, a child theme on top of the release ZIP of its parent theme, given as input `with-theme: <owner>/<repository>@<tag>` (required for plugins and child themes).

Inputs: `repository`, `ref`, `e2e`, `publish`, `framework-ref`, `wp-dev-env-ref`, `with-theme`. Consumers pin the workflow to a framework tag.

## The framework

- `ci.yml` runs PHPUnit, Vitest and all linters on pushes and pull requests, and builds this documentation.
- `release.yml` runs them for a tag, then the release gates – theme-dev-test, plugin-dev-test and theme-dev-test-child built with the framework of the tag (`framework-ref`) and tested in the browser, the plugin and the child theme with an older release of the theme, without publishing – and finally attaches `wp-app-framework-<version>.zip` (`git archive`, without the `export-ignore` development files) to the GitHub release and publishes the documentation of the tag's minor version.

## Requiring the framework

Themes require the framework from its repository by version; the lock file pins the commit:

```json
"repositories": [{"type": "vcs", "url": "https://github.com/saijsaij/wp-app-framework"}]
```
