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

Autoescaping (`html`) and `strict_variables` are always on; undefined variables are errors. In development mode, debug, auto reload and `dump()` are enabled. A theme or plugin with settings groups has the global `settings` (see [Settings](./settings.md#using-settings)). The framework provides the template functions of blocks (`block_wrapper_attributes()`, see [Blocks](./blocks.md#escaping)), of the editor (`field()`, `inner_blocks()`, `is_editor()`), `has_plugin()` (see [Plugins](./plugins.md#public-template-functions-and-filters)) and [WordPress functions](#wordpress-functions).

Compiled templates are cached in `wp-content/cache/{slug}/twig/`. Release mode compiles without auto reload, so each release uses its own directory `wp-content/cache/{slug}/twig/{templateVersion}/`; the version is a hash over the names and contents of all templates of the project and the framework, written into the release manifest by `build --output`. If the active theme or child theme overrides templates of another instance (a plugin, or the theme overridden by its child theme), that instance's directory gets a further level named after the versions of theme and child theme: a changed override needs a new theme version. Directories of older releases are not removed yet.

Twig exceptions reach the caller, so the error handling of hooks and REST routes applies.

## Template functions and filters

- **`#[TemplateFunction(name, html: false)]` / `#[TemplateFilter(name, html: false)]`** register public, non-static methods of services; the service is created when a template first calls them. Names must be valid identifiers, unique per kind and must not reuse a function or filter Twig provides (Twig would silently replace it); parameters must not be services (inject them through the constructor), variadic or by reference. A filter receives the filtered value as its first parameter.
- **Escaping:** results are escaped unless `html: true`; such a method must return `string`. An HTML filter receives the filtered value HTML-escaped unless it is already safe (e.g. `|raw`, like Twig's `nl2br`). Other arguments are passed unescaped: an HTML function or filter must escape every argument it puts into its result.
- Callables offered to other consumers (`public: true`) are described in [Plugins](./plugins.md#public-template-functions-and-filters).

## WordPress functions

The framework wraps WordPress functions as template functions named `wp_` + object + property, without `get_`/`the_`; a WordPress function that already starts with `wp_` keeps its name (`wp_date`, not `wp_wp_date`). The prefix is not reserved, but a name the framework already uses is a build error.

- IDs may be `null` (e.g. an empty `#[Image]` or [reference](./values.md#references)); `null` and objects that do not exist give `''`. Posts that are not publicly viewable count as missing, except when the block editor renders the block. Attachments are not checked for visibility.
- Text is escaped by Twig. Values WordPress stores or returns escaped (term and user names, site name and description, the logout URL) are decoded first, so they are not escaped twice. Functions marked HTML print their result unescaped.

```twig
<a href="{{ wp_post_url(attrs.target) }}">{{ wp_post_title(attrs.target) }}</a>
{{ wp_attachment_image(attrs.imageId, 'large', { class: 'rounded' }) }}
<time>{{ wp_post_date(attrs.target, 'j. F Y') }}</time>
```

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

The output of `wp_logout_url()`, `wp_is_user_logged_in()` and `wp_avatar()` depends on the request (nonce, login), not only on the block's attributes.
