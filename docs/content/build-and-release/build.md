# Build

The framework reads your attributes in a build step and turns them into a manifest (see [Concepts](../concepts.md#build-and-run-time)). During development it runs that step itself on each request that needs it. You can also run it from the command line, which is useful in two situations:

- **To check your code.** The build reports every problem it finds: a constructor parameter the container cannot fill, a REST route without permission, an invalid attribute. Running it before a commit or in CI catches mistakes without opening WordPress.
- **To write a release manifest.** The release build uses it internally.

```sh
vendor/bin/wp-app-framework build [--output=<file>]
```

In a consumer created as in [Getting started](../getting-started.md), `composer build-manifest` runs the same command.

## Checking

Without `--output`, the command builds and checks the manifest and writes nothing. It builds the project whose `vendor/autoload.php` the Composer bin proxy loads, which is the project you run it in. Problems are listed with class and parameter names, and the exit code is 1.

The command also lists the templates of the project that override templates of the framework.

## Writing a manifest

With `--output`, the command writes the manifest to the given file. A written manifest is a release manifest and carries the template version, which names the directory of the release's Twig cache.

:::warning
Do not write `generated/manifest.php` into a source checkout of a theme or plugin. Its presence switches the project to release mode (see [Boot and modes](../guides/boot.md#modes)), and the framework stops noticing changes to your code. The [release build](./release.md) writes it into its own copy of the project.
:::
