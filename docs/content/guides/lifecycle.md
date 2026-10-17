# Activation, deactivation and uninstallation

A plugin or theme leaves traces in WordPress: cron events, options, tables, posts. Some work belongs to the moments when it starts and stops: a plugin creates a default page when it is activated, pauses a connection to a service when it is deactivated, and removes everything it stored when it is deleted. Without help, every plugin repeats the same code: activation and deactivation hooks, an `uninstall.php` that needs to know the names of every option and table, a loop over the sites of a network.

The framework does the part that follows from your declarations by itself. It removes the cron events of a deactivated plugin or a theme the site switched away from, and when a plugin is deleted, it removes the data the plugin declared. For everything else you mark methods with `#[OnActivation]`, `#[OnDeactivation]` and `#[OnUninstall]`.

## When WordPress activates and deactivates

Plugins and themes start and stop in different ways.

A **plugin** is activated and deactivated on the plugins screen or with `wp plugin activate` and `wp plugin deactivate`. WordPress fires an action for this in the same request. A plugin is deleted only while it is inactive; WordPress then runs its uninstallation and removes its files.

A **theme** is never activated on its own: the site switches from one theme to another. WordPress fires `switch_theme` in the request that switches, while the old theme is still loaded, and `after_switch_theme` on the next request, with the new theme. The framework treats the first as the deactivation of the old theme and the second as the activation of the new one. A theme and its own child theme count as one: switching from a theme to its child theme, or back, neither deactivates nor activates the parent theme, because it stays loaded. A child theme with code of its own is an instance of its own (see [Child themes](./child-themes.md)): switching from the parent theme to it activates it, switching back deactivates it.

Themes cannot be uninstalled. WordPress loads no code of a theme it deletes, so nothing can clean up after a theme. This is often what you want: when someone switches back to the theme, its settings and tables are still there.

## What the framework does by itself

| Moment | Plugin | Theme and child theme |
|---|---|---|
| Activation | The [rewrite rules](./posts.md#rewrite-rules) are flushed on the next request. Cron events are scheduled on the next request, tables are created on the next request or the first access. | The same. |
| Deactivation | All [cron events and tasks](./cron.md) of the plugin are removed. | All cron events and tasks of the theme are removed. |
| Uninstallation | All data of the plugin is removed (see below). | – |

## Methods of your own

A lifecycle method is a public method of a [service](./services.md) that returns `void`. Its parameters are services; WordPress passes nothing the method could use. Take a plugin that keeps a page with its overview:

```php
use WebCodingCat\WpAppFramework\Lifecycle\OnActivation;

final readonly class OverviewPage
{
    public function __construct(private PageStore $pages) {}

    #[OnActivation]
    public function create(): void
    {
        $this->pages->ensureOverview();
    }
}
```

When the plugin is activated, `create()` runs. The methods of the plugin's post types and tables work already: the framework registers the post types of the plugin first, and a table is created on its first access.

`#[OnDeactivation]` works the same way. It runs before the framework removes the cron events, so the method can still look at them:

```php
#[OnDeactivation]
public function pause(SyncClient $client): void
{
    $client->unsubscribe();
}
```

`#[OnUninstall]` runs when the plugin is deleted, before the framework removes its data. Use it for what the framework does not know about, such as an option you write with `update_option()` yourself or a file in the uploads directory:

```php
#[OnUninstall]
public function removeExports(ExportDirectory $exports): void
{
    $exports->deleteAll();
}
```

A class may have any number of lifecycle methods, and one method may have several of the attributes. The methods of one moment run in the order of their class and method names.

If a method throws, the framework rethrows the exception when `WP_DEBUG` is on. Activating a plugin then fails with the error, as WordPress shows it for any plugin. Without `WP_DEBUG`, the error is logged and the next method runs, like a failing [hook](./hooks.md).

## What the uninstallation removes

When a plugin is deleted, the framework first runs the `#[OnUninstall]` methods, then removes on each site:

- every post of the plugin's [post types](./posts.md), in any status, with its meta, comments and revisions,
- every term of the plugin's taxonomies,
- the meta keys of its meta classes, also from posts of other types, such as `Post` and `Page`,
- the options of its [settings](./settings.md) groups,
- its [cron events and tasks](./cron.md),
- its [tables](./tables.md), with the tables an earlier namespace of the plugin left, and the state of their sync,
- its technical options: the versions of [cache](./cache.md) tags, cache entries stored as transients, the rewrite hash, notices of the meta box.

Then it deletes the plugin's cache directory `wp-content/cache/{slug}` and lets WordPress rebuild its rewrite rules, which still contain the plugin's post types.

The framework removes only names it knows exactly: names from the build, or options in the exact form it writes them. It never deletes "everything that starts with the plugin's prefix", because another plugin whose namespace starts the same way (`shop` and `shop-extras`) would lose its data.

Some things stay:

- Attachments uploaded to the deleted posts. They stay in the media library; WordPress does not delete them with a post.
- Blocks of the plugin in other posts. They render nothing without the plugin.
- Entries of a persistent object cache. They are never read again, because the versions of their tags are gone, and expire with their lifetime.
- Everything the plugin stored without the framework: options, user meta, files. Remove it in an `#[OnUninstall]` method.

:::warning No uninstall.php
WordPress runs an `uninstall.php` of a plugin instead of the uninstall hook the framework registers. A plugin with this file would keep all its data, so the file is a build error. Move its code into a method with `#[OnUninstall]`.
:::

WordPress needs a static callback for the uninstall hook and stores it in the option `uninstall_plugins`. The framework registers it when the plugin is activated and again when it is deactivated, since a plugin is always deactivated before it is deleted. When WordPress uninstalls the plugin, it loads the plugin's main file first, so the plugin boots; this happens after `init`, and the framework registers the plugin's post types and taxonomies itself before the uninstallation runs.

## Multisite

Every site of a network has its own options, cron events and tables. The framework therefore runs everything site by site:

- Activating a plugin network-wide activates it on every site of the network; deactivating it network-wide deactivates it on every site. The lifecycle methods run once per site, with that site as the current one.
- A site created while the plugin is active network-wide is activated as soon as WordPress has set it up.
- Deleting a site drops the tables of the plugin on that site, together with the tables of WordPress.
- Deleting the plugin uninstalls it on every site of every network.

On a large network, activating, deactivating and deleting a plugin takes a while, since each site is handled in turn.

## Rules

| Attribute | Runs | Allowed in |
|---|---|---|
| `#[OnActivation]` | Plugin: when it is activated (`activate_{plugin}`), per site when network-wide, and for new sites of a network where it is active network-wide. Theme: on the first request after the switch to it (`after_switch_theme`), unless the previous theme was the theme itself or its child theme. | Plugins, themes, child themes |
| `#[OnDeactivation]` | Plugin: when it is deactivated (`deactivate_{plugin}`), per site when network-wide. Theme: in the request that switches away from it (`switch_theme`), unless the new theme is the theme itself or its child theme. | Plugins, themes, child themes |
| `#[OnUninstall]` | When the plugin is deleted, on every site, before the framework removes its data. | Plugins only |

Build errors:

- The method is not public, is static or does not return `void`.
- A parameter is not a service, or is variadic or passed by reference.
- The class is not a service (for example `#[NoService]`).
- `#[OnUninstall]` in a theme or child theme.
- A plugin has an `uninstall.php`. In development mode, the boot reports the file as well, since the cached manifest does not notice a new file.
