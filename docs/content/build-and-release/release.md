# Release

```sh
vendor/bin/wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...
```

Builds the release ZIP of a theme or plugin (by the `type` in its `composer.json`) – plain WordPress needs no Composer: all dependencies, including this framework and Twig, ship prefixed in `vendor-prefixed/`, so several consumers with different framework versions coexist. The command works on a copy in the system's temporary directory and never changes the checkout:

1. Copies the files git does not ignore (`git ls-files --cached --others --exclude-standard`, so uncommitted changes are included). `generated/manifest.php` must not exist in the checkout.
2. `composer install --no-dev --classmap-authoritative` from the lock file. `--package-source=saij/wp-app-framework=../wp-app-framework` replaces a package by a copy of a local checkout (its files without `export-ignore`, like a distribution archive), e.g. to test unreleased framework changes; the output says that the build is not from the lock file. Path repositories are not supported: the copy has no sibling checkouts.
3. Runs [Strauss](https://github.com/BrianHenryIE/strauss) (`strauss` in the PATH; the tools image of [wp-dev-env](https://github.com/saijsaij/wp-dev-env) provides it) with the call sites of the project, then patches Twig: Twig's compiler writes class names such as `use Twig\Environment;` as plain strings into every compiled template, which no prefixing tool rewrites. `Release\TwigPatcher` rewrites exactly these places and requires each pattern as often as expected, so a Twig update that changes them fails the build.
4. Builds `generated/manifest.php` with the prefixed classes (`bin/wp-app-framework-prefixed.php` in the prefixed copy, written with the template version), then compiles all templates of the project and the framework as release mode would and checks the compiled code for unprefixed names.
5. `npm ci && npm run build` (if there is a `package.json`), which builds the editor runtime from `vendor/`.
6. Removes `node_modules/`, `vendor/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json` and every path the project marks `export-ignore` in `.gitattributes` (use `dir/**` for directories), then checks all PHP files for names of dependencies that were not prefixed (string literals too, except inside `vendor-prefixed/`, where dependencies mention their own class names in messages).
7. Writes `dist/<slug>-<version>.zip` (or `--output`) with the project directory as root; slug = directory name, version = `Version:` of the (child) theme's `style.css` or of the plugin's main file `<slug>.php`, which `--expect-version` must match. A child theme is built without its parent theme: calls of the parent's public template callables compile without it.

Every step fails with the file, command or setting at fault; on failure the build directory is kept and its path printed.

## Strauss settings

The project declares the Strauss settings in its `composer.json` – `extra` is part of the lock file's content hash, so the build cannot add them – and the command checks the values it relies on:

```json
"extra": {
    "strauss": {
        "target_directory": "vendor-prefixed",
        "namespace_prefix": "Vendor\\Theme\\Vendor\\",
        "classmap_prefix": "Vendor_Theme_Vendor_",
        "include_root_autoload": true,
        "update_call_sites": ["src", "functions.php"],
        "delete_vendor_packages": false
    }
}
```

`update_call_sites` must contain the file that boots the framework (`functions.php` of a theme or child theme, `<slug>.php` of a plugin). The prefix must not be the project's own namespace (Strauss' default). Leave `constant_prefix` unset: it would rename the constants the Symfony polyfills define for PHP itself (e.g. `MB_CASE_UPPER`); Twig defines none.

## Unreleased framework changes

To test a release with unreleased framework changes, run in the consumer:

```sh
composer release -- --package-source=saij/wp-app-framework=../wp-app-framework
```
