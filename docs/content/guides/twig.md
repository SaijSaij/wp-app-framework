# Twig templates

Templates live in the `src/` directory of each level, next to the code that uses them (`src/Hook/Mail.twig`) or in shared directories (`src/Component/Button.twig`). A template name is always the full path relative to `src/`, including the extension – in PHP and in templates alike; there are no relative names.

```php
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;
use Saij\WpAppFramework\Twig\TemplateRenderer;

final class Mail
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    #[Hook('sections_mail_body')]
    public function body(string $body, string $name): string
    {
        return $this->renderer->render('Hook/Mail.twig', ['body' => $body, 'name' => $name]);
    }
}

final class Formatting
{
    #[TemplateFunction('price')]
    public function price(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    #[TemplateFilter('highlight', html: true)]
    public function highlight(string $value): string
    {
        return '<mark>' . $value . '</mark>';
    }
}
```

```twig
{% include 'Component/Button.twig' %}      {# child overrides → theme → framework #}
{% include '@framework/Component/Button.twig' %} {# exactly this level #}
{{ price(total) }} {{ name|highlight }}
```

## Lookup

Names without a namespace are searched in `overrides/{theme-slug}/` of the active child theme (if it has that directory), then in the theme's `src/`, then in the framework's. A higher level overrides a template by providing the same path: a child theme overrides `src/Block/Hero/Hero.twig` of the theme `my-theme` with `overrides/my-theme/Block/Hero/Hero.twig`. `@theme/…` and `@framework/…` address exactly one level and cannot be overridden – use them where the exact template matters, e.g. when an override extends the original. `wp-app-framework build` lists templates that override framework templates.

- **Child themes:** the `src/` of a child theme belongs to its own framework instance (if it boots one): its templates are searched in its `src/`, then in the framework's, and never override templates of the theme.
- **Plugins:** a plugin's templates are searched in `overrides/{plugin-slug}/` of the child theme and of the theme, then in the plugin's `src/`, then in the framework's. So a theme overrides `src/Block/Note/Note.twig` of the plugin `my-plugin` with `overrides/my-plugin/Block/Note/Note.twig`; its own templates in `src/` never collide with plugin templates. `@plugin/…` addresses the plugin's own templates exactly. Overrides are rendered by the plugin's Twig environment: the plugin's template functions and filters and the context the plugin passes apply.
- **Instances:** compiled templates are named after the slug of their instance as well, so a theme and a plugin never share the compiled code of templates with the same path.

## Environment

Autoescaping (`html`) and `strict_variables` are always on; undefined variables are errors. In development mode, debug, auto reload and `dump()` are enabled.

Compiled templates are cached in `wp-content/cache/{slug}/twig/`. Release mode compiles without auto reload, so each release uses its own directory `wp-content/cache/{slug}/twig/{templateVersion}/`; the version is a hash over the names and contents of all templates of the project and the framework, written into the release manifest by `build --output`. If the active theme or child theme overrides templates of another instance (a plugin, or the theme overridden by its child theme), that instance's directory gets a further level named after the versions of theme and child theme: a changed override needs a new theme version. Directories of older releases are not removed yet.

Twig exceptions reach the caller, so the error handling of hooks and REST routes applies.

## Template functions and filters

- **`#[TemplateFunction(name, html: false)]` / `#[TemplateFilter(name, html: false)]`** register public, non-static methods of services; the service is created when a template first calls them. Names must be valid identifiers, unique per kind and must not reuse a function or filter Twig provides (Twig would silently replace it); parameters must not be services (inject them through the constructor), variadic or by reference. A filter receives the filtered value as its first parameter.
- **Escaping:** results are escaped unless `html: true`; such a method must return `string`. An HTML filter receives the filtered value HTML-escaped unless it is already safe (e.g. `|raw`, like Twig's `nl2br`). Other arguments are passed unescaped: an HTML function or filter must escape every argument it puts into its result.
- Callables offered to other consumers (`public: true`) are described in [Plugins](./plugins.md#public-template-functions-and-filters).
