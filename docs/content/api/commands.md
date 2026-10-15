# Commands

The framework installs the command `vendor/bin/wp-app-framework` into your project. It runs in the project whose `vendor/autoload.php` it loads, which is the project you call it in.

| Command | Does |
|---|---|
| `build [--output=<file>]` | Builds and checks the manifest of the project: services, hooks, routes, blocks and their templates, parts and patterns. Without `--output` it writes nothing; with it, it writes a release manifest. Exit code 1 with a list of errors. See [Build](../build-and-release/build.md). |
| `release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...` | Builds the release ZIP: copies the project, installs the dependencies, prefixes them with Strauss, builds the manifest and the scripts and checks the result. `--expect-version` fails if the version in the header differs; `--package-source` builds with a local checkout of a package. See [Release](../build-and-release/release.md). |
| `meta-fields <meta class> [--force]` | Writes the class with `#[MetaFields]` for a meta class, next to it. See [Queries](../guides/queries.md#meta-fields-in-queries). |
| `table-fields <row class> [--force]` | Writes the class with `#[TableFields]` for a row class, next to it. See [Tables](../guides/tables.md#field-classes). |

Options take their value with `=`: `--output=dist/theme.zip`. `meta-fields` and `table-fields` replace an existing file only with `--force`; they are the only commands that write source code.

Your project usually runs them through Composer scripts, for example `"build-manifest": "wp-app-framework build"` and `"release": "wp-app-framework release"` (see [Getting started](../getting-started.md)).
