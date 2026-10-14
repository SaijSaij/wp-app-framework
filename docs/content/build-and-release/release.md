# Release

A framework theme or plugin cannot simply be zipped from its Git checkout. The checkout has no `vendor/`, the editor scripts are not built, and the dependencies are not prefixed. A site with two framework consumers would load one framework for both of them.

`wp-app-framework release` turns a checkout into a ZIP that runs in any plain WordPress installation, without Composer on the server. It ships all dependencies, including the framework and Twig, prefixed with the project's own namespace in `vendor-prefixed/`. Several consumers with different framework versions can therefore run side by side.

```sh
vendor/bin/wp-app-framework release [--output=<zip>] [--expect-version=<version>] [--package-source=<package>=<directory>]...
```

In a consumer created as in [Getting started](../getting-started.md), `composer release` runs the same command. It recognizes a theme or a plugin by the `type` in its `composer.json`.

## What the release build does

The command works on a copy in the system's temporary directory and never changes your checkout. It runs these steps:

1. **Copy the project.** It copies the files Git does not ignore (`git ls-files --cached --others --exclude-standard`), so uncommitted changes are included. `generated/manifest.php` must not exist in the checkout.

2. **Install the dependencies.** It runs `composer install --no-dev --classmap-authoritative` from the lock file, so the release contains exactly the versions you tested.

3. **Prefix the dependencies.** [Strauss](https://github.com/BrianHenryIE/strauss) renames every dependency into the project's namespace and rewrites the project's own code to use the new names. Strauss must be in the `PATH` (the tools image of [wp-dev-env](https://github.com/saijsaij/wp-dev-env) provides it); the release command runs it with PHP and a memory limit of 512 MB, since it parses the large class map of the dependencies.

   Then the command patches Twig. Twig's compiler writes class names such as `use Twig\Environment;` as plain strings into every compiled template, and no prefixing tool rewrites strings. `Release\TwigPatcher` rewrites exactly these places. It expects each pattern a fixed number of times, so a Twig update that changes them fails the build instead of producing a broken release.

4. **Build the manifest.** It builds `generated/manifest.php` with the prefixed classes (using `bin/wp-app-framework-prefixed.php` in the prefixed copy) and writes the template version into it. Then it compiles all templates of the project and the framework, as release mode would, and checks the compiled code for names that were not prefixed.

5. **Build the assets.** If there is a `package.json`, it runs `npm ci && npm run build`, which also builds the editor runtime from `vendor/`. Then it checks that every built file the framework loads at run time exists, because a missing one would only fail on the page that needs it. The manifest decides which files these are:

   | The project has | It needs in `build/` |
   |---|---|
   | blocks, post meta or editor scripts in `assets/` | `vendor/wp-app-framework/editor.asset.php` (editor runtime) |
   | blocks | `vendor/wp-app-framework/canvas.asset.php` (canvas styles) |
   | an editor or view script of a block or block support | that script's `.asset.php` |
   | a document view, an editor script or a shared module in `assets/` | that script's `.asset.php` below `assets/` |
   | view scripts or document views | `vendor/wp-app-framework/view.asset.php` (view runtime) |
   | settings groups | `vendor/wp-app-framework/settings.asset.php` (settings page) |
   | post meta | `vendor/wp-app-framework/meta-box.asset.php` (meta boxes) |
   | `assets/styles.css` | `styles.css` |

   A missing file stops the release with a list of the missing files and what needs them. A project without blocks, post meta, settings and stylesheet needs no `package.json`; one with them needs a `package.json` with a `build` script and a `webpack.config.js` with the framework's webpack helper.

6. **Remove development files.** It removes `node_modules/`, `vendor/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json` and every path the project marks `export-ignore` in `.gitattributes` (use `dir/**` for directories). Then it checks all PHP files for names of dependencies that were not prefixed, in string literals too. Inside `vendor-prefixed/` string literals are not checked, because dependencies mention their own class names in messages.

7. **Write the ZIP.** It writes `dist/<slug>-<version>.zip`, or the file given with `--output`, with the project directory as root. The slug is the directory name. The version is the `Version:` header of the theme's or child theme's `style.css`, or of the plugin's main file `<slug>.php`. `--expect-version` makes the build fail if the version differs, which CI uses to compare it with the tag.

A child theme is built without its parent theme. Calls of the parent's public template callables compile without it.

Every step fails with the file, command or setting at fault. On failure the build directory is kept, and its path is printed, so you can look at what went wrong.

## Strauss settings

The project declares the Strauss settings in its `composer.json`. The command cannot add them itself, because `extra` is part of the lock file's content hash. It checks the values it relies on instead:

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

- **`namespace_prefix`** and **`classmap_prefix`** name the prefix for the dependencies. The prefix must not be the project's own namespace, which is Strauss' default.
- **`update_call_sites`** lists where Strauss rewrites your code to the prefixed names. It must contain the file that boots the framework: `functions.php` of a theme or child theme, `<slug>.php` of a plugin.
- Leave **`constant_prefix`** unset. It would rename the constants that the Symfony polyfills define for PHP itself, such as `MB_CASE_UPPER`. Twig defines no constants.

## Unreleased framework changes

Normally the release uses the framework version from the lock file. To test a release with framework changes that are not released yet, replace the package with a local checkout:

```sh
composer release -- --package-source=saij/wp-app-framework=../wp-app-framework
```

`--package-source=<package>=<directory>` copies the checkout's files without those marked `export-ignore`, like a distribution archive. The output says that the build is not from the lock file. Composer path repositories are not supported, because the temporary copy has no sibling checkouts.
