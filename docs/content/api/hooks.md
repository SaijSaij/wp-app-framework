# Hooks

The framework fires these WordPress hooks. The `{slug}` is the directory name of the theme or plugin, `{namespace}` its namespace (see [Concepts](../concepts.md#consumers-and-instances)).

| Hook | Kind | Arguments | Use |
|---|---|---|---|
| `wp_app_framework/init/{slug}` | action | none | Fired at the end of the boot of an instance. Use it to run code once the framework of a theme or plugin is ready. See [Boot](../guides/boot.md#slug-namespace-and-init-action). |
| `wp_app_framework/namespace/{namespace}` | action | none | Fired when an instance claims its namespace; a second instance with the same namespace stops with an error. Not meant for your code. |
| `wp_app_framework/api/{slug}` | filter | `null`, the class name | The public API of a plugin or theme (`#[PublicApi]`). The framework uses it to resolve `#[PluginApi]` and `#[ThemeApi]` parameters; do not call it yourself. See [Plugins](../guides/plugins.md#public-api). |
| `wp_app_framework/template/{slug}` | filter | `null`, `'function'` or `'filter'`, the name | The public Twig functions and filters of a plugin or theme (`public: true`). The framework uses it in templates of other instances; do not call it yourself. See [Plugins](../guides/plugins.md#public-template-functions-and-filters). |
| `wp_app_framework/cache/backends/{slug}` | filter | the list of backends | The cache backends of an instance, in order of preference: `'object'`, `'file'`, `'transient'`; `[]` turns the cache off. See [Cache](../guides/cache.md#where-entries-are-stored). |
| `wp_app_framework/cron/{slug}/{name}` | action (WP-Cron) | none | A recurring event (`#[CronEvent]`). See [Cron events and tasks](../guides/cron.md#hook-names). |
| `wp_app_framework/cron/{slug}/task/{name}` | action (WP-Cron) | the data of the task | A one-time task (`#[CronTask]`); the framework turns the data back into the task object. |
| `wp_app_framework/cache/clean_up/{slug}` | action (WP-Cron) | none | The daily clean-up of the file cache and of Twig caches of older releases. |

The names and arguments of `init`, `namespace`, `api` and `template` are a contract between framework versions: instances built with different versions of the framework meet on them, so they only ever grow, also across major versions.

The run-time hooks of WordPress the framework uses itself (`init`, `enqueue_block_assets`, `rest_api_init` …) are not part of its API.
