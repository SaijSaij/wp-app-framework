# Settings

Settings are values of the whole site that the theme or plugin makes configurable in the backend, for example a tagline or the default density of lists. They are grouped: a settings group is a typed class like a block attribute class. The framework stores each group in an option, adds a settings page with a form for all groups, and passes the values to services and templates.

Settings shared by several blocks and stored per block (a color scheme of a block) are [block supports](./supports.md), not settings.

## Declaring a group

`#[SettingsGroup(name, title, description: '', position: 10)]` on a `readonly` class whose settings are public promoted constructor parameters, each with a default:

```php
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Setting\SettingsGroup;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

#[SettingsGroup('general', title: 'General', description: 'Shown on every page.')]
final readonly class GeneralSettings
{
    /**
     * @param list<SocialLink> $links
     */
    public function __construct(
        #[Label('Tagline')]
        public string $tagline = '',
        public Density $density = Density::Normal,
        #[Url]
        public string $contactUrl = '',
        #[ListOf(SocialLink::class)]
        public array $links = [],
        #[Image]
        public ?int $logo = null,
    ) {}
}
```

- `name` consists of lowercase letters, digits and `_` and starts with a letter; it is unique within the theme or plugin. It names the option and the key in templates.
- `title` is the title of the group on the settings page, `description` an optional text above its form. `position` orders the groups (lower first, then by name).
- The settings follow the rules of [values](./values.md): `int`, `float`, `string`, `bool`, backed enums (labeled with [`Labeled`](./blocks.md#editor-labels)), value classes and `#[ListOf]` lists, each optionally nullable; `#[Url]`, `#[Label]` and `#[Hidden]` (no control on the settings page) apply as for block attributes. Every setting needs a default: it is missing until the group is saved.
- `#[Image]` on a `?int` stores the ID of an attachment, selected from the media library on the settings page (also in value classes and list items); print it with [`wp_attachment_image()`](./twig.md#wordpress-functions). A value class used by a block and by a settings group keeps its image in the block canvas there and in the form here.
- `#[PlainText]` is a text field. `#[RichText(allowedFormats: null)]` is edited with WordPress' classic editor (the rich text of the block editor works only inside a block); `allowedFormats` may contain `core/bold`, `core/italic`, `core/link`, `core/strikethrough`, `core/subscript` and `core/superscript` (others are build errors), and the editor keeps only their elements and line breaks, without paragraphs. Print rich text with `|raw`; when a user without `unfiltered_html` (e.g. on multisite) saves, it is filtered with `wp_kses_post()`. `inspector` and `excerpt` have no effect in settings; `postContent` is a build error.
- [References](./values.md#references) (`#[PostReference]`, `#[TermReference]`, `#[UserReference]`) are chosen with a search, one or as a list.
- `#[Content]` marks the content of blocks and means nothing in settings; it is a build error, also in value classes a group uses.
- The class is no service: it is never autowired itself, and `#[Hook]` or other entry points on it are build errors.

## Storage

Each group is stored in the option `{slug}_{name}`, where `{slug}` is the slug of the theme or plugin with `_` instead of `-` (the theme `my-theme` stores the group `general` in `my_theme_general`). The option holds an object with every setting; settings missing in a saved object get their default.

The framework registers the option with `register_setting()` on `init`, with its schema and `show_in_rest`, so it can be read and saved through `/wp/v2/settings` under the same name. WordPress validates and sanitizes saved values against the schema there and requires the capability `manage_options` for reading and saving.

A child theme without code uses the settings of its parent theme: they belong to the theme's slug, not to the active stylesheet. A child theme with code and each plugin have their own groups and options.

## Using settings

A service receives a group through its constructor:

```php
final class Footer
{
    public function __construct(private readonly GeneralSettings $settings) {}

    #[Hook('wp_footer')]
    public function print(): void
    {
        printf('<p>%s</p>', esc_html($this->settings->tagline));
    }
}
```

The group object is created from the stored values when it is first needed and kept until the option changes. Groups can be injected into constructors of services only, not as parameters of hooks, REST routes or template functions.

Templates read the groups of their own theme or plugin through the global `settings`:

```twig
<p class="tagline tagline--{{ settings.general.density.value }}">{{ settings.general.tagline }}</p>
{{ wp_attachment_image(settings.general.logo, 'medium') }}
```

`settings` exists only if the theme or plugin declares settings groups; an unknown group name is an error.

**Invalid stored values:** a stored value that no longer matches its setting (for example a case removed from its enum) is replaced by the setting's default and logged (`wp-app-framework: settings: …` in the PHP error log); the other settings of the group keep their stored values.

## Settings page

A theme or plugin with settings groups gets one settings page, titled with its name (the `Theme Name:` header of `style.css`, the `Plugin Name:` header of a plugin's main file; a missing name is a build error):

- themes and child themes under "Appearance", plugins under "Settings",
- for users with `manage_options`,
- the form of each group with the controls of the block inspector (text, number, toggle, select, rich text, image from the media library, reference search, nested values, list editor), several groups as tabs; a tab with unsaved changes is marked with "•",
- one "Save" button for all groups, saved in one request through `/wp/v2/settings`. If a value is invalid, WordPress rejects the whole request and the page names the group and the setting; nothing is saved.

The page script is built by the theme's or plugin's build like the editor runtime (see [Editor](./editor.md#build)) to `build/vendor/wp-app-framework/settings.js`; without it the settings page fails with an exception naming the missing file.
