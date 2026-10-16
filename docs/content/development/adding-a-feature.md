# Adding a feature

A new feature of the framework touches the same places every time: the attribute or class consumers use, the build, the manifest, the run time, the tests in two repositories, the benchmark and the documentation. This checklist goes through them in order. [Architecture](./architecture.md#from-attribute-to-wordpress) explains the chain behind it; `src/Hook/` is the smallest complete example.

## Before you start

- Check the [design decisions](./design.md): is the idea among the rejected alternatives, and does it fit the principles?
- Verify the WordPress behavior you build on in the source of WordPress (the `wordpress` image of wp-test-env has it in `/usr/src/wordpress`) and note file and line. The same for Twig, Composer, Strauss or a browser API.
- Decide the API from the consumer's side first: names, required and optional arguments, enums instead of strings, what is a build error.

## The checklist

1. **Attribute or API class** in the directory of its area (`src/<Area>/`): `final readonly`, public promoted properties for an attribute, a doc comment for the class and every parameter, `@api <group>` if consumers use it (the groups are in `tools/ApiReference/Generator.php`), `@internal` on members only the framework uses.
2. **Collector** (`<Area>Collector`): reads the attribute in the build, checks everything that can be checked and returns plain data. Every mistake is an error message with class, method and parameter. Shared checks of entry points are in `src/Manifest/EntryPointInspector.php`.
3. **Definition** (`<Area>Definition`): the PHPStan array shapes of that data.
4. **Manifest:** call the collector in `ManifestBuilder`, add the data to the manifest and raise `Manifest::FORMAT`. If the build reads a new kind of input (a file type, a directory), make it part of the signature or listing hash of the development cache, or changes will not rebuild the manifest.
5. **Registrar** (`<Area>Registrar`): registers the feature from the manifest at run time, called from `Instance`. No reflection, no file checks, no writes into the consumer; services resolved only when they are needed; errors thrown with `WP_DEBUG` and logged otherwise.
6. **Unit tests** in `tests/Unit/<Area>/`: the collector with valid fixtures (`tests/Fixtures/<Area>Valid/`) and a fixture for **every build error** (`<Area>Broken/`, with `@phpstan-ignore` and the reason), the registrar against the WordPress doubles. Add missing doubles to `tests/Stubs/` with the real signatures.
7. **JavaScript**, if the editor or the front end is involved: the runtime modules in `assets/`, Vitest tests next to them, the declarations in `types/` if consumers import something new.
8. **Test consumer:** use the feature in theme-dev-test (and in plugin-dev-test or theme-dev-test-child if plugins or child themes behave differently), with a browser spec, a render case, a WP-CLI or a lifecycle check – whatever proves it in WordPress. List new specs in its README; update the expected output of `composer build-manifest` there.
9. **Benchmark:** if the feature runs on requests, add a scenario to theme-bench, so the next benchmark (run on request) measures it; `bin/bench --coverage` shows that the scenario reaches the code.
10. **Documentation:** the guide page of the area (or a new one, linked in `docs/rspress.config.mjs`), with what it is for, how it works, an example and the rules; [Upgrading](../reference/upgrading.md) if anything a consumer relies on changes; these development pages if conventions, tests or pipelines change.
11. **Checks before the commit:** `composer lint`, `npm run lint`, the documentation build (it fails on missing doc comments of the API), and lint and build of the changed consumers. Before the push, the development build with the browser suites (see [Testing](./testing.md#when-to-run-what)).

## Changing an existing feature

The same list applies to the parts you touch. In addition:

- A change of the consumer API before 1.0 is allowed only with a section in Upgrading: what changed, why, and what consumers do. From 1.0 on, the API only grows within a major version.
- The filters and actions between instances (`wp_app_framework/api/{slug}`, `wp_app_framework/template/{slug}`, `wp_app_framework/init/{slug}`, `wp_app_framework/namespace/{namespace}`) may only be extended, never changed, because instances of different framework versions use them together.
- Changing behavior also changes the doc comments, the guide and the tests in the consumers in the same commit.
