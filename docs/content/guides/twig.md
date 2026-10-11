# Twig templates

Markup in the framework lives in [Twig](https://twig.symfony.com) templates, not in PHP strings. Twig escapes every value by default, so you don't have to remember `esc_html()` for each one; only `|raw` and functions marked HTML print a value unescaped. It also keeps markup readable, and it lets child themes and themes override the markup of a block without touching its code.

Twig renders the markup of blocks and fragments your code produces, such as the body of a mail or an HTML response. Page layouts are not Twig; they are block templates and template parts, as in any block theme.

## Where templates live

Templates sit in the `src/` directory of the theme or plugin, next to the code that uses them (`src/Hook/Mail.twig`, `src/Block/Hero/Hero.twig`), or in shared directories (`src/Component/Button.twig`). There is no separate `views/` directory.

A template name is always the full path relative to `src/`, including the extension: `'Component/Button.twig'`. That holds in PHP and in templates alike. There are no relative names, so a name means the same thing wherever it appears.

## Rendering a template

A service renders a template through the `TemplateRenderer`, which it asks for in its constructor. `render(name, context)` returns the HTML as a string:

```php
use Saij\WpAppFramework\Hook\Hook;
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
```

The Twig environment is created when the first template renders, so a request without templates pays nothing for it. Blocks render their templates the same way (see [Blocks](./blocks.md)).

## Lookup

A template name without a namespace is searched in several layers. The first layer that has the file wins:

1. `overrides/{theme-slug}/` of the active child theme, if it has that directory,
2. the theme's `src/`,
3. the framework's `src/`.

A higher layer overrides a template by providing a file with the same path. For example, a child theme of `my-theme` changes the markup of the theme's hero block, `src/Block/Hero/Hero.twig`, with its own `overrides/my-theme/Block/Hero/Hero.twig`. The theme's PHP code stays as it is.

Sometimes you need exactly one layer, for example when an override extends the original template it replaces. `@theme/…` and `@framework/…` address exactly that layer and cannot be overridden:

```twig
{% include 'Component/Button.twig' %}            {# child overrides → theme → framework #}
{% include '@framework/Component/Button.twig' %} {# exactly this layer #}
```

`wp-app-framework build` lists the project's templates that override framework templates.

### Child themes and plugins

The `src/` of a **child theme** belongs to its own framework instance, if it boots one. Its templates are searched in its own `src/`, then in the framework's, and they never override templates of the theme. To override the theme, a child theme uses `overrides/{theme-slug}/`.

A **plugin's** templates are searched in this order: `overrides/{plugin-slug}/` of the child theme, `overrides/{plugin-slug}/` of the theme, the plugin's `src/`, the framework's `src/`. A theme overrides `src/Block/Note/Note.twig` of the plugin `my-plugin` with `overrides/my-plugin/Block/Note/Note.twig`. Because overrides live in their own directory, the theme's templates in `src/` never collide with the plugin's. `@plugin/…` addresses the plugin's own templates exactly.

The plugin's Twig environment renders the override. The override therefore sees the plugin's template functions and filters and the context the plugin passes, not those of the theme.

Compiled templates are named after the slug of their instance, so a theme and a plugin never share compiled code of templates with the same path.

## Environment

Twig runs with settings that catch mistakes early:

- **Autoescaping** (`html`) is always on.
- **`strict_variables`** is always on, so an undefined variable is an error instead of an empty string.
- In development mode, **debug**, **auto reload** and **`dump()`** are enabled; in release mode they are off.

A theme or plugin with settings groups has the global `settings` (see [Settings](./settings.md#using-settings)). The framework provides the template functions of blocks (`block_wrapper_attributes()`, see [Blocks](./blocks.md#escaping)), of the editor (`field()`, `inner_blocks()`, `is_editor()`, see [Editor](./editor.md#marking-editable-elements)), `has_plugin()` (see [Plugins](./plugins.md#public-template-functions-and-filters)) and the [WordPress functions](#wordpress-functions) below.

Twig exceptions reach the code that rendered the template, so the error handling of hooks and REST routes applies to them.

### Cache

Compiled templates are cached in `wp-content/cache/{slug}/twig/`.

Release mode compiles without auto reload: Twig never checks whether a template changed. Each release therefore uses its own directory, `wp-content/cache/{slug}/twig/{templateVersion}/`. The template version is a hash over the names and contents of all templates of the project and the framework; `build --output` writes it into the release manifest.

Overrides add one more level. If the active theme or child theme overrides templates of another instance – a plugin, or the theme overridden by its child theme – that instance's cache directory gets a further level named after the versions of theme and child theme. A changed override therefore needs a new version of the theme or child theme. Directories of older releases are not removed yet.

## Template functions and filters

Your own Twig functions and filters are methods of services with `#[TemplateFunction]` or `#[TemplateFilter]`:

```php
use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;

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
{{ price(total) }} {{ name|highlight }}
```

The service is created when a template calls one of its functions for the first time. Like other services, it gets its dependencies through its constructor.

### Escaping

By default Twig escapes the result of a function or filter. A method that returns HTML sets `html: true`; its result is printed as it is, and it must return `string`.

An HTML filter receives the filtered value already HTML-escaped, unless the value is safe already (for example after `|raw`). Twig's `nl2br` works the same way. In the example, `highlight` can wrap `$value` in `<mark>` without escaping it first. Other arguments are passed unescaped: an HTML function or filter must escape every argument it puts into its result.

### Rules

- `#[TemplateFunction(name, html: false)]` and `#[TemplateFilter(name, html: false)]` go on public, non-static methods of services.
- Names are valid identifiers and unique per kind. They must not reuse a function or filter Twig provides, because Twig would silently replace it.
- Parameters must not be services (inject them through the constructor), variadic or by reference.
- A filter receives the filtered value as its first parameter.
- Callables offered to other consumers (`public: true`) are described in [Plugins](./plugins.md#public-template-functions-and-filters).

## WordPress functions

Templates often need data from WordPress: an image for an attachment ID, the title and URL of a linked post, the site name. The framework provides these as template functions. Each is a thin wrapper around a WordPress function, and the `wp_` prefix tells you so at a glance.

```twig
<a href="{{ wp_post_url(attrs.target) }}">{{ wp_post_title(attrs.target) }}</a>
{{ wp_attachment_image(attrs.imageId, 'large', { class: 'rounded' }) }}
<time>{{ wp_post_date(attrs.target, 'j. F Y') }}</time>
```

The names follow one rule: `wp_` + object + property, without `get_` or `the_`. A WordPress function that already starts with `wp_` keeps its name (`wp_date`, not `wp_wp_date`). The prefix is not reserved for the framework, but a name the framework already uses is a build error.

The functions handle missing data for you:

- IDs may be `null`, for example an empty `#[Image]` or [reference](./values.md#references). `null` and objects that do not exist give `''`, so a template does not need to check first.
- Posts that are not publicly viewable count as missing, except when the block editor renders the block. Attachments are not checked for visibility.

Text is escaped by Twig. Some values come from WordPress already escaped: term and user names, the site name and description, and the logout URL. The framework decodes them first, so they are not escaped twice. Functions marked HTML below print their result unescaped.

| Function | WordPress | Result |
|---|---|---|
| `wp_attachment_image(id, size = 'full', attributes = {})` | `wp_get_attachment_image()` | HTML |
| `wp_attachment_image_url(id, size = 'full')` | `wp_get_attachment_image_url()` | URL |
| `wp_attachment_url(id)` | `wp_get_attachment_url()` | URL |
| `wp_attachment_caption(id)` | `wp_get_attachment_caption()` | HTML as stored (filtered with kses when saved without `unfiltered_html`) |
| `wp_attachment_alt(id)` | meta `_wp_attachment_image_alt` | text |
| `wp_post_url(id)` | `get_permalink()` | URL |
| `wp_post_title(id)` | `post_title` | text |
| `wp_post_excerpt(id)` | `get_the_excerpt()` | plain text: tags removed, entities decoded |
| `wp_post_date(id, format = '')` | `get_the_date()` | text; `''` uses the date format of the site |
| `wp_post_thumbnail(id, size = 'post-thumbnail', attributes = {})` | `get_the_post_thumbnail()` | HTML |
| `wp_term_url(id)` | `get_term_link()` | URL |
| `wp_term_name(id)` | `name` of the term | text |
| `wp_user_name(id)` | `display_name` of the user | text |
| `wp_user_url(id)` | `get_author_posts_url()` | URL of the author archive |
| `wp_avatar(id, size = 96)` | `get_avatar()` | HTML; `''` if avatars are turned off |
| `wp_home_url(path = '')` | `home_url()` | URL |
| `wp_site_name()` | `get_bloginfo('name')` | text |
| `wp_site_description()` | `get_bloginfo('description')` | text |
| `wp_login_url(redirect = '')` | `wp_login_url()` | URL |
| `wp_logout_url(redirect = '')` | `wp_logout_url()` | URL with a nonce |
| `wp_is_user_logged_in()` | `is_user_logged_in()` | `true`/`false` |
| `wp_date(format, timestamp = null)` | `wp_date()` | text in the site's time zone and language; `null` for now |
| `wp_human_time_diff(from, to = 0)` | `human_time_diff()` | text, e.g. "5 minutes"; `0` for now |
| `wp_number_format(number, decimals = 0)` | `number_format_i18n()` | text |
| `wp_size_format(bytes, decimals = 0)` | `size_format()` | text, e.g. "2 KB" |

:::warning
The output of `wp_logout_url()`, `wp_is_user_logged_in()` and `wp_avatar()` depends on the request (a nonce, the login state), not only on the attributes of a block. Keep that in mind wherever output is cached.
:::
