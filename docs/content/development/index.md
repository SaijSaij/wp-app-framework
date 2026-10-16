# Developing the framework

These pages are for people who work on wp-app-framework itself: who fix a bug, add a feature or prepare a release. If you build a theme or plugin *with* the framework, the [Guide](../index.md) and the [API reference](../api/index.md) are what you need.

The pages explain how the framework is built and why, the rules its code follows, how it is tested and how releases go out:

- [Architecture](./architecture.md) – build and run time, the manifest, instances, the container, the editor runtime and the release build, and where each part lives in the source.
- [Design decisions](./design.md) – the principles behind the framework, the decisions that are fixed, and the alternatives that were considered and rejected.
- [Conventions](./conventions.md) – code style, error messages, the public API and its compatibility, and how to write documentation.
- [Testing](./testing.md) – which test goes where, what to run at which stage, and how the browser tests work.
- [Pipelines and releases](./pipelines.md) – the GitHub workflows and the release procedure step by step.
- [Adding a feature](./adding-a-feature.md) – a checklist from the attribute to the documentation.

## The repositories

The framework is developed together with a few repositories that test it, measure it or make it easier to use. All of them live on GitHub under [saijsaij](https://github.com/saijsaij):

| Repository | What it is | Its part in the development |
|---|---|---|
| [wp-app-framework](https://github.com/saijsaij/wp-app-framework) | The framework: a Composer library with the JavaScript sources of its runtimes and the documentation website | Unit tests of everything, including every build error |
| [theme-dev-test](https://github.com/saijsaij/theme-dev-test) | Test theme with a block, hook, route, setting, post type … for every feature | Browser, render, WP-CLI and lifecycle tests against real WordPress; release gate |
| [plugin-dev-test](https://github.com/saijsaij/plugin-dev-test) | Test plugin | Plugin support, the public interface between instances, two framework versions side by side; release gate |
| [theme-dev-test-child](https://github.com/saijsaij/theme-dev-test-child) | Test child theme with code of its own | Child theme support and overrides; release gate |
| [theme-bench](https://github.com/saijsaij/theme-bench) | Benchmark: a theme with the framework against the same theme without it | The cost of the framework per request, now and then on request |
| [wp-test-env](https://github.com/saijsaij/wp-test-env) | Docker test environment: fresh WordPress instances, run in parallel, and a tools image with PHP, Composer and Node | Runs every check and test, locally and in CI |
| [wp-dev-env](https://github.com/saijsaij/wp-dev-env) | Docker development environment: one local WordPress site per project | Trying things out; never used for tests |
| [wp-app-starter](https://github.com/saijsaij/wp-app-starter) | `composer create-project saij/wp-app-starter` for new themes and plugins | Release gate: projects created from it must pass their own checks |

wp-test-env and wp-dev-env know nothing about the framework; they are general environments for any WordPress project. Each repository has a README with its details and a `CLAUDE.md` with the instructions for Claude Code; these pages describe what concerns the framework and the interplay of the repositories.

## Setting up

You need Git and Docker; everything else – PHP 8.5, Composer, Node 24, a browser for the tests – runs in containers. Clone the repositories next to each other. The directory that contains them is the *workspace* of wp-test-env:

```sh
mkdir wp-app && cd wp-app
for repo in wp-app-framework theme-dev-test plugin-dev-test theme-dev-test-child theme-bench wp-test-env wp-app-starter; do
    git clone "https://github.com/saijsaij/${repo}.git"
done
```

The test consumers require the framework from Packagist. To test your checkout instead, wp-test-env replaces the installed package with a symlink to it (a *package link*) while it prepares the projects; their lock files stay untouched. The first checks:

```sh
cd wp-test-env
bin/build                                                   # the images, pulled or built
bin/tools -w wp-app-framework composer install
bin/tools -w wp-app-framework composer test                 # PHPUnit
bin/tools -w wp-app-framework npm ci
bin/tools -w wp-app-framework npm test                      # Vitest
bin/run ../theme-dev-test/tests/dev.pools                   # browser tests of the test theme with your checkout
```

[Testing](./testing.md) explains these commands and when to run which.

To try something out in a browser, use [wp-dev-env](https://github.com/saijsaij/wp-dev-env): it runs a local WordPress per project, with the framework linked into the consumers the same way (`WP_PACKAGE_LINKS`). Its data persists, which is why tests never run there.
