# Plugins

Plugins use the framework like themes (see [Boot and modes](./boot.md)) and may offer an interface to other consumers – the theme, other plugins. Themes may offer the same interface to their child themes. Instances never share PHP objects of the framework or of its dependencies: each release prefixes them with its own namespace. The interface therefore consists of WordPress hooks named after the provider's slug and of signatures that use only PHP, WordPress and the provider's own classes; the build of the provider rejects other types (also in the project classes such a signature uses). The provider keeps its interface compatible itself.

## Public API

`#[PublicApi]` (`Saij\WpAppFramework\Api\PublicApi`) marks a service of a plugin or theme as its API. A consumer receives the API of a plugin through a constructor parameter:

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

The parameter must be nullable with the default `null`; it is `null` if the plugin is not active. The build does not load the class, so the plugin may be missing; require it as a development dependency (`require-dev`) for the IDE and static analysis – the release build installs without development dependencies. The service is created when the consumer's service is created; doing that before `plugins_loaded` is an error, and so is an active plugin that does not provide the class.

## Public template functions and filters

`#[TemplateFunction(name, public: true)]` and `#[TemplateFilter(name, public: true)]` in a plugin or theme offer the callable to the templates of other consumers. The name must start with the provider's template prefix – its slug with underscores, e.g. `my_plugin_` for `my-plugin`. A consumer lists the plugins it uses in its `composer.json` and calls the callables by name (a child theme uses those of its parent theme without listing it):

```json
"extra": { "wp-app-framework": { "template-plugins": ["my-plugin"] } }
```

```twig
{% if has_plugin('my-plugin') %}
    {{ my_plugin_note_count() }} {{ title|my_plugin_highlight }}
{% endif %}
```

Twig resolves names while compiling templates, so a name with a declared prefix compiles without the plugin; it is resolved when called, and calling it without the plugin is an error – check with `has_plugin(slug)`. Arguments are positional. Results of `html: true` callables are printed unescaped; the input of such a filter is escaped first unless it is the result of an HTML function or filter (unlike filters of the own instance, a value marked with `|raw` is escaped as well). Names starting with a declared prefix are reserved; the consumer's own callables must not use them.

## Template overrides

A theme overrides templates of a plugin in `overrides/{plugin-slug}/`; see [Twig templates](./twig.md#lookup).
