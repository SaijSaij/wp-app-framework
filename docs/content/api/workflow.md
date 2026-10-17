# Release workflow

`.github/workflows/consumer-release.yml` of the framework releases a theme, child theme or plugin from a tag: it builds the ZIP, tests it in fresh WordPress instances and attaches it to the GitHub release. Call it from a workflow of your project:

```yaml
on:
  push:
    tags: ['v*']

permissions:
  contents: write

jobs:
  release:
    uses: webcodingcat/wp-app-framework/.github/workflows/consumer-release.yml@v0.15.0
    with:
      cli: true
```

| Input | Type | Default | Meaning |
|---|---|---|---|
| `repository` | string | the calling repository | the project to release |
| `ref` | string | the calling ref, i.e. the tag | the revision to build |
| `e2e` | boolean | `true` | run the browser tests (`npm run test:e2e`) against the release |
| `e2e-instances` | number | `2` | number of WordPress instances and Playwright workers for the browser tests |
| `cli` | boolean | `false` | run the WP-CLI tests `tests/cli/run` against the release |
| `publish` | boolean | `true` | attach the ZIP to the GitHub release of the tag; the tag must equal `v` and the version in the header |
| `framework-ref` | string | the locked version | build with another revision of the framework |
| `wp-test-env-ref` | string | `main` | the revision of wp-test-env that provides the tools and the WordPress instances |
| `with-theme` | string | – | required for plugins and child themes: the theme release as `<owner>/<repository>@<tag>` |

See [Releases and CI](../build-and-release/ci.md) for what the workflow does step by step and what your tests receive.
