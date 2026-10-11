# Releases and CI

Releasing a theme or plugin should be a single step: push a tag. The framework provides a GitHub Actions workflow that does the rest. It builds the release ZIP, installs it into a plain WordPress, runs your browser tests against it, and attaches the ZIP to the GitHub release only if everything passed. A ZIP that fails its tests is never published.

Tags follow semantic versioning with a `v` prefix: `v1.2.0`.

## Themes, child themes and plugins

Your repository calls the reusable workflow `.github/workflows/consumer-release.yml` of the framework repository from a workflow of its own. That workflow runs on `push: tags: ['v*']` and needs `permissions: contents: write` to attach the ZIP. The test projects [theme-dev-test](https://github.com/saijsaij/theme-dev-test), [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child) and [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test) contain working examples to copy.

Pin the workflow to a framework tag, so a new framework version never changes your release process unannounced.

The workflow:

1. checks out the project and [wp-dev-env](https://github.com/saijsaij/wp-dev-env);
2. runs `composer lint` and `npm run lint`, if they are defined, and `wp-app-framework build`;
3. builds the release; when it publishes, with `--expect-version` set to the version of the tag, so a tag that doesn't match `Version:` fails;
4. installs the ZIP into a plain WordPress (`scripts/release-test` of wp-dev-env);
5. runs the project's browser tests (`npm run test:e2e`) with `E2E_TARGET=release`; the input `e2e: false` skips them.

### Plugins and child themes need a theme

A theme can be tested on its own, but a plugin or a child theme needs a theme to run in. The workflow decides from the `type` in `composer.json` and the `Template:` header of `style.css`:

- a **plugin** is installed next to the release ZIP of a theme;
- a **child theme** is installed on top of the release ZIP of its parent theme.

The input `with-theme: <owner>/<repository>@<tag>` names that theme release. It is required for plugins and child themes.

### Inputs

| Input | Default | Purpose |
|---|---|---|
| `repository` | the calling repository | the project to release |
| `ref` | the calling ref, i.e. the tag | the revision to build |
| `e2e` | `true` | run the browser tests (`npm run test:e2e`) against the release |
| `publish` | `true` | attach the ZIP to the GitHub release of the tag |
| `framework-ref` | the locked version | build with another revision of the framework (the framework's release gate uses it) |
| `wp-dev-env-ref` | `main` | the revision of wp-dev-env that provides the tools and the release test |
| `with-theme` | – | the theme release for plugins and child themes, `<owner>/<repository>@<tag>` |

## The framework

The framework repository tests itself in two workflows:

- **`ci.yml`** runs PHPUnit, Vitest and all linters on pushes and pull requests, and builds this documentation.
- **`release.yml`** runs on a tag. It runs the same checks and then the release gates: theme-dev-test, plugin-dev-test and theme-dev-test-child are built with the framework of the tag (`framework-ref`) and tested in the browser, the plugin and the child theme with an older release of the theme, without publishing anything. Only then does it attach `wp-app-framework-<version>.zip` to the GitHub release – made with `git archive`, without the development files marked `export-ignore` – and publish the documentation of the tag's minor version.

The gates make sure that a framework release works for a theme, a plugin and a child theme before anyone can depend on it.

## Requiring the framework

Themes and plugins require the framework from its GitHub repository by version, and the lock file pins the exact commit:

```json
"repositories": [{"type": "vcs", "url": "https://github.com/saijsaij/wp-app-framework"}]
```
