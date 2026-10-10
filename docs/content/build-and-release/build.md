# Build

```sh
vendor/bin/wp-app-framework build [--output=<file>]
```

Builds and checks the manifest of the project whose `vendor/autoload.php` the Composer bin proxy loads; problems are listed with class and parameter names, and the exit code is 1. Without `--output` nothing is written; a written manifest is a release manifest and carries the template version. Do not write `generated/manifest.php` into a source checkout of a theme or plugin: its presence selects release mode (see [Boot and modes](../guides/boot.md#modes)). The [release build](./release.md) writes it.
