# Settings

Settings are values for the whole site that a theme or plugin makes configurable in the backend: a tagline, the logo, the page with the privacy policy, the default density of lists. You describe them as typed classes, as you do with block attributes. The framework takes care of the rest:

- it stores each group of settings in a WordPress option,
- it adds a settings page with a form for all groups,
- it passes the values to your services and templates as typed objects.

Settings that several blocks share but that are stored per block, such as a color scheme for each block, are [block supports](./supports.md), not settings.

## Declaring a group

Settings come in groups. A group is a `readonly` class with `#[SettingsGroup]`, and each setting is a public promoted constructor parameter with a default:

```php
use Saij\WpAppFramework\Value\Image;
use Saij\WpAppFramework\Value\Label;
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

The settings page shows this group as a form: a text field for the tagline, a select for the density, a URL field, a list editor for the social links, and an image picker for the logo.

`#[SettingsGroup(name, title, description: '', position: 10)]` takes these arguments:

- **`name`** consists of lowercase letters, digits and `_` and starts with a letter. It is unique within the theme or plugin, and it names the option and the key in templates.
- **`title`** is the title of the group on the settings page. **`description`** is an optional text above its form.
- **`position`** orders the groups: lower values first, then by name.

Every setting needs a default, because a setting is missing until the group has been saved for the first time.

## Types of settings

Settings follow the rules of [values](./values.md): `int`, `float`, `string`, `bool`, backed enums, value classes and `#[ListOf]` lists, each optionally nullable. Enum cases are labeled with [`Labeled`](./blocks.md#editor-labels). `#[Url]` and `#[Label]` work as for block attributes, and `#[Hidden]` keeps a setting off the settings page.

Some markers from blocks have a meaning in settings too:

- **`#[Image]`** on a `?int` stores the ID of an attachment, chosen from the media library. It also works in value classes and list items. Print the image with [`wp_attachment_image()`](./twig.md#wordpress-functions). A value class used both by a block and by a settings group edits its image in the block canvas there and in the form here.
- **`#[PlainText]`** is a text field; with `multiline: true` a text area. Print multiline text with `|nl2br`.
- **[`Link`](./values.md#links)** is a link to a post or a free URL, with "Open in new tab": a search of published posts and a URL field. Print it with `link_attributes()`.
- **`#[RichText(allowedFormats: null)]`** is edited with WordPress' classic editor, because the rich text of the block editor works only inside a block. `allowedFormats` may contain `core/bold`, `core/italic`, `core/link`, `core/strikethrough`, `core/subscript` and `core/superscript`; other formats are build errors. The editor keeps only these elements and line breaks, without paragraphs. Print rich text with `|raw`. When a user without `unfiltered_html` saves (for example on multisite), it is filtered with `wp_kses_post()`.
- **[References](./values.md#references)** (`#[PostReference]`, `#[TermReference]`, `#[UserReference]`) are chosen with a search, one or as a list.

Other markers do not fit:

- `inspector` and `excerpt` have no effect in settings.
- `postContent` is a build error.
- `#[Content]` marks the content of blocks and means nothing in settings. It is a build error, also in value classes that a group uses.

A group class is not a service. It is created from the stored values, never autowired, and `#[Hook]` or other entry points on it are build errors.

## Storage

Each group is stored in its own option, named `{namespace}_{name}`, where `{namespace}` is the namespace of the theme or plugin (`extra.wp-app-framework.namespace`) with `_` instead of `-`. A theme with the namespace `my-theme` stores the group `general` in `my_theme_general`. The option holds an object with every setting of the group; settings missing from a saved object get their default.

The framework registers the option with `register_setting()` on `init`, with its schema and `show_in_rest`. That makes it readable and writable through `/wp/v2/settings` under the same name. WordPress validates and sanitizes saved values against the schema there, and requires the capability `manage_options` for reading and saving.

The option belongs to the theme's namespace, not to its directory name or the active stylesheet. A child theme without code therefore uses the settings of its parent theme, and switching to a child theme keeps them. A child theme with code and each plugin have their own groups and options. Deleting a plugin deletes the options of its groups (see [Activation, deactivation and uninstallation](./lifecycle.md)); the options of a theme stay when the site switches to another theme.

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

The group object is created from the stored values when it is first needed, and kept until the option changes. Groups can be injected into the constructors of services only, not as parameters of hooks, REST routes or template functions.

Templates read the groups of their own theme or plugin through the global `settings`:

```twig
<p class="tagline tagline--{{ settings.general.density.value }}">{{ settings.general.tagline }}</p>
{{ wp_attachment_image(settings.general.logo, 'medium') }}
```

`settings` exists only if the theme or plugin declares settings groups. An unknown group name is an error.

### Invalid stored values

A stored value can stop matching its setting, for example when a case is removed from its enum. The framework replaces such a value with the setting's default and logs it (`wp-app-framework: settings: …` in the PHP error log). The other settings of the group keep their stored values, so one outdated value never resets the whole group.

## Settings page

A theme or plugin with settings groups gets one settings page. Its title is the name of the theme or plugin: the `Theme Name:` header of `style.css`, or the `Plugin Name:` header of the plugin's main file. A missing name is a build error.

- Themes and child themes find the page under "Appearance", plugins under "Settings".
- Only users with `manage_options` can open it.
- Each group is a form built from the same controls as the block inspector: text, number, toggle, select, rich text, image from the media library, reference search, nested values and the list editor. Several groups appear as tabs; a tab with unsaved changes is marked with "•".
- One "Save" button saves all groups in a single request through `/wp/v2/settings`. If a value is invalid, WordPress rejects the whole request, the page names the group and the setting, and nothing is saved.

The page script is built by the theme's or plugin's build, like the editor runtime (see [Editor](./editor.md#build)), to `build/vendor/wp-app-framework/settings.js`. Without it, the settings page fails with an exception naming the missing file.
