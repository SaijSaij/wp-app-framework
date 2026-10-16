# Pipelines and releases

The repositories check every push in GitHub Actions, and a release of the framework is only published if the test consumers and the starter pass with it. This page describes the workflows and the release procedure.

## Workflows of the framework

| Workflow | When | What |
|---|---|---|
| `ci.yml` | every push to `main`, every pull request; called by `release.yml` | PHPUnit, Vitest, PHPStan, PHP-CS-Fixer, ESLint, Stylelint in the tools image of wp-test-env; the documentation build with the generated API reference |
| `docs.yml` | every push to `main` | publishes the documentation as `next` to the `gh-pages` branch |
| `release.yml` | a tag `v<version>` | the checks, the release gates, the package ZIP and the documentation of the minor version |
| `consumer-release.yml` | called by the release workflow of a consumer, and by the gates | builds, tests and publishes the release ZIP of a theme, child theme or plugin |

All of them check out wp-test-env next to the framework and run the commands in its images, the same way as locally.

### The release gates

`release.yml` tests a tag of the framework with the consumers before anything is published. The gates run at the same time as the checks of `ci.yml`, so the slowest gate – the starter – sets the duration:

- **gate** – theme-dev-test (`main`) built with the framework of the tag: lint, manifest, release build, its browser tests against the ZIP and its WP-CLI tests;
- **gate-plugin** – plugin-dev-test next to an old release of theme-dev-test, so two framework versions run side by side;
- **gate-child-theme** – theme-dev-test-child on an old release of theme-dev-test;
- **gate-starter** – the CI of [wp-app-starter](https://github.com/saijsaij/wp-app-starter): a theme, a child theme and a plugin created from it with every option, linted, built, released and tested, with the framework of the tag.

Only if the checks and all gates pass, `publish` attaches the package ZIP (`git archive`, without the `export-ignore` files) to the GitHub release, and `docs` publishes the documentation of the minor version (`0.16/` …). A pre-release tag gets no documentation of its own.

### The release workflow of consumers

A consumer's own `release.yml` calls `consumer-release.yml` for its tags (see [Releases and CI](../build-and-release/ci.md)). It reads the kind from `composer.json` and `style.css`, installs, lints and checks the manifest, builds the release ZIP (the tag must equal the version in the header), installs it into fresh instances of wp-test-env – a child theme on its parent theme release, a plugin next to a theme release or on the default theme of WordPress – runs the browser tests and, with `cli: true`, the WP-CLI tests, and only then attaches the ZIP. With `framework-ref`, the gates build with a checkout of the framework instead of the locked version.

### Images, packages and documentation

- **wp-test-env** publishes its images to the GitHub Container Registry (`ghcr.io/saijsaij/wp-test-env-wordpress`, `-tools`), tagged with a hash of their Dockerfiles, on every change and daily, so they carry the latest stable WordPress and PHP within a day. `bin/build` pulls the image of its checkout and builds it only if it is missing.
- **Packagist** updates `saij/wp-app-framework` and `saij/wp-app-starter` through the GitHub integration on every push and tag.
- **The documentation** lives in directories of the `gh-pages` branch: one per minor version and `next` for `main`, with a version switcher (`docs/scripts/publish.mjs`). Never push to `gh-pages` by hand.

## The release procedure

A release of the framework is also a release of the test consumers, and it is followed by one of the starter. The order matters, because each step needs the one before.

1. **Release tests.** Run the release pools of the three consumers with your checkout (see [Testing](./testing.md#when-to-run-what)); all must pass. Plugin and child theme install the theme release that `with-theme` in their release workflow names (`tests/download-with-theme`), as their release workflows do, so a test that needs something only the new theme has fails here and not after the tag.
2. **wp-test-env first.** If the release needs changes of wp-test-env, push them first: the workflows check out its `main`.
3. **Framework `main`.** Push the framework, wait for its CI and for Packagist (a few minutes).
4. **Consumers.** In theme-dev-test, plugin-dev-test, theme-dev-test-child and theme-bench, move the lock file to the new `main` (`composer update saij/wp-app-framework`). Raise the version in `style.css` or the plugin's main file of the three test consumers; plugin and child theme name the previous theme release in `with-theme`, so two framework versions run side by side. Commit, push and tag them (`v<version>`).
5. **Benchmark, while the consumer releases run.** Their workflows run on GitHub and do not disturb the measurement; local tests would, so run none at the same time. Run `bin/bench` of theme-bench and compare it with the last report: no new database query on the base load, no unexplained cost. A problem it shows is fixed on the framework's `main` before the tag; the consumers then get the new lock file in a patch release. Then check that the release workflows of the consumers passed.
6. **Framework tag.** Tag the framework (`v<version>`) and wait for `release.yml`: checks, all four gates, package ZIP and documentation. GitHub Pages serves the new documentation a minute or two after the job `docs`.
7. **Release notes** for every release (see below).
8. **Starter.** After a new minor version, raise the starter's requirement (`"saij/wp-app-framework": "^0.x"`), tag the starter with release notes, and check `composer create-project saij/wp-app-starter` against Packagist (a Composer home of its own, so neither cache nor path repositories hide the published versions). There is no need to wait for the CI of the starter: `gate-starter` tested the same starter with the framework of the tag.
9. **Package links.** `composer update` replaced the links to your framework checkout in the local consumers; restore them with `bin/prepare --link` of wp-test-env.

### Release notes

`release.yml` creates the GitHub release with generated notes. Once the workflow has passed, replace them with notes written for the people who use the release:

- a short summary;
- new features, changes of behavior and breaking changes, each with the steps to upgrade and a link to [Upgrading](../reference/upgrading.md);
- fixes;
- compatibility: WordPress, PHP, the consumers the tag was gated with and the releases that went out with it;
- links to the documentation of the version;
- the compare link last.

```sh
gh release edit v0.16.0 --notes-file notes.md
```

A rerun of the workflow only uploads the ZIP and keeps the text. The test consumers and the starter get short notes: what they test anew, the framework version they are built with, and the compare link.
