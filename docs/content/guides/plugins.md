# Plugins

A plugin uses the framework just like a theme. It boots its own instance with `Plugin::boot(__FILE__)` (see [Boot and modes](./boot.md)) and has its own blocks, hooks, REST routes, templates and settings. Everything in the guides applies to plugins as well.

What is special about plugins is that they often work together with a theme. A plugin may provide data the theme displays, or helpers the theme's templates call. This page explains how a plugin – or a theme, towards its child theme – offers such an interface, and how the theme changes the plugin's markup.

## Why a public interface

In a release, every instance ships its own copy of the framework and its dependencies, prefixed with its own namespace. The theme's `Twig\Markup` and the plugin's `Twig\Markup` are different classes with different names. Instances therefore never share PHP objects of the framework or its dependencies, and passing one across would fail.

The interface between instances is built from two things that every instance understands:

- **WordPress hooks named after the provider's slug.** The framework uses them under the hood to hand over services and template callables.
- **Signatures with plain types.** Methods offered to others may use only PHP types, WordPress types and the provider's own classes. The provider's build rejects anything else, including in the project classes such a signature uses.

The provider is responsible for keeping its interface compatible across its own versions.

## Public API

`#[PublicApi]` (`Saij\WpAppFramework\Api\PublicApi`) marks a service of a plugin, or of a theme, as its API:

```php
namespace My\Plugin\Api;

use Saij\WpAppFramework\Api\PublicApi;

#[PublicApi]
final class NoteApi
{
    public function countNotes(int $postId): int { /* … */ }
}
```

Another consumer receives the API of a plugin through a constructor parameter with `#[PluginApi]`:

```php
use My\Plugin\Api\NoteApi;
use Saij\WpAppFramework\Api\PluginApi;

final class Notes
{
    public function __construct(
        #[PluginApi('my-plugin')]
        private readonly ?NoteApi $notes = null,
    ) {}
}
```

The plugin may be deactivated, so the parameter must be nullable with the default `null`. (What happens when a plugin is activated, deactivated and deleted is described in [Activation, deactivation and uninstallation](./lifecycle.md).) It is `null` whenever the plugin is not active, and your code checks for that.

The consumer's build does not load the class, so it works without the plugin. For your IDE and static analysis, require the plugin as a development dependency (`require-dev`); the release build installs without development dependencies.

The API service is created together with the consumer's service. Creating it before `plugins_loaded` is an error, because plugins may not be loaded yet. An active plugin that does not provide the requested class is an error as well.

## Public template functions and filters

A plugin can also offer Twig functions and filters to the templates of other consumers. `#[TemplateFunction(name, public: true)]` and `#[TemplateFilter(name, public: true)]` mark them. Themes can do the same for their child themes.

The name must start with the provider's template prefix: its slug with underscores, for example `my_plugin_` for `my-plugin`. That keeps public names from colliding with the consumer's own.

A consumer lists the plugins whose callables it uses in its `composer.json`:

```json
"extra": { "wp-app-framework": { "namespace": "my-theme", "template-plugins": ["my-plugin"] } }
```

Its templates then call them by name:

```twig
{% if has_plugin('my-plugin') %}
    {{ my_plugin_note_count() }} {{ title|my_plugin_highlight }}
{% endif %}
```

A child theme uses the callables of its parent theme without listing it.

Twig resolves names while it compiles a template, which may happen while the plugin is inactive. A name with a declared prefix therefore compiles without the plugin and is resolved only when it is called. Calling it without the plugin is an error, so check with `has_plugin(slug)` first.

A few more rules:

- Arguments are positional.
- Results of `html: true` callables are printed unescaped.
- The input of such an HTML filter is escaped first, unless it is the result of an HTML function or filter. Unlike filters of your own instance, a value marked with `|raw` is escaped as well.
- Names starting with a declared prefix are reserved; the consumer's own callables must not use them.

## Template overrides

A theme changes the markup of a plugin's blocks by overriding its templates in `overrides/{plugin-slug}/`. For example, `overrides/my-plugin/Block/Note/Note.twig` in the theme replaces `src/Block/Note/Note.twig` of the plugin. [Twig templates](./twig.md#lookup) describes the full lookup order.

## Styles

Class names are global, so the stylesheet of a plugin uses a Tailwind prefix. Its classes never meet those of the theme (see [Styles](./styles.md#plugins-and-child-themes)).
