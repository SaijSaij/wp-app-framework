# Values

Blocks, REST routes and settings all deal with structured values: a headline, a choice among variants, a list of buttons, a link to another page. In WordPress these arrive as untyped arrays, either from the block comment, the request or an option. The framework lets you describe them once, as typed PHP classes, and uses that description in two places:

- **In the build**, it derives a JSON schema from the class. WordPress uses the schema to validate block attributes, REST arguments and settings.
- **At run time**, the *value factory* turns the stored array into an instance of your class, with enums as enum cases and nested objects as objects. It does this without reflection.

Block attribute classes, [settings groups](./settings.md), nested value classes and `#[ListOf]` REST arguments all work this way, so what you learn here applies to all of them. The data of a one-time [cron task](./cron.md#one-time-tasks) is a value class too, without references.

## Value classes

A value class is a `readonly` class whose values are all public promoted constructor parameters:

```php
final readonly class Button
{
    public function __construct(
        #[PlainText] public string $label,
        #[Url] public string $url = '',
        public ButtonStyle $style = ButtonStyle::Primary,
    ) {}
}
```

It declares no other properties. Elsewhere in the framework, class properties are never public; value classes are the exception, because they are plain data.

## Types

A value can have one of these types, each optionally nullable:

| PHP type | Schema |
|---|---|
| `int`, `float`, `string`, `bool` | `integer`, `number`, `string`, `boolean` |
| backed enum | the type of its values, with `enum` listing the case values |
| value class | `object` with `properties` |
| `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` | `array` with `items`; see [Lists](#lists) |

A nullable type adds `null` to the schema type (`type: [..., 'null']`). PHP has no generic types, so a list says what it contains with `#[ListOf]`. Lists of scalars exist only for [references](#references).

Enums and value classes must be in the scanned PSR-4 directories. A value class that contains itself, directly or indirectly, is a build error.

The columns of [tables](./tables.md) use the same value factory with one more type: `DateTimeImmutable`, read from the database as a UTC time `Y-m-d H:i:s`. Anything else in such a column, including the zero date `0000-00-00 00:00:00`, is an error. Blocks, REST routes and settings have no time type.

## Defaults and missing values

The default value of a parameter becomes the schema's `default`: enums as their value, value objects as arrays.

A missing value uses the constructor's default. Inside a nested value class, a parameter without a default is `required` in the schema. Unknown keys in nested objects are ignored, just as WordPress ignores unknown block attributes.

## Wrong values

The value factory never coerces silently. A value that does not match its type throws an `InvalidArgumentException` that names the path to the value, for example `buttons[1].url`. Blocks, REST routes and settings each decide what happens next; see their pages.

## Lists

A list holds items of one backed enum or one value class, declared with [`#[ListOf(class, min: 0, max: null, itemLabel: null, appender: false)]`](../api/php/Value/ListOf.md). Many lists have a natural size: a hero shows at most three buttons, a comparison needs at least two columns. `min` and `max` set these limits:

```php
#[ListOf(Button::class, max: 3)] public array $buttons = [],
#[ListOf(Column::class, min: 2, max: 4)] public array $columns = [new Column(), new Column()],
```

The limits apply where people edit the list. In the block inspector, on the settings page and in meta boxes, "Add item" is disabled when the list has `max` items, and "Remove" when it has `min` items. A list of post objects (`#[ListOf(Venue::class)]`) hides its search at `max` instead.

A stored list can still be outside the limits, for example after you lowered `max` in an update of your theme. The framework keeps such a list as it is, so changing a limit never loses content:

- Your template receives only the first `max` items. It can rely on the limit.
- The editor shows all stored items, marks the ones beyond `max` as unused and says how many to remove. Nothing changes until an editor removes them.
- A list shorter than `min` reaches your template as it is; the editor asks for the missing items.

This is why the limits are not part of the JSON schema of stored values. WordPress would treat a list with too many items as invalid and replace the whole list with its default. REST arguments are different: a request is not stored content, so their schema contains `minItems` and `maxItems`, also for lists inside value classes, and WordPress answers a request outside the limits with 400.

Editors see each item as a card that shows only its title, so a list stays clear even when its items have many values. A click on the title expands the item's controls; several items can be expanded at once, and a new item starts expanded. Items are sorted by dragging them at their handle, or with "Move up" and "Move down" in their menu, which also work with the keyboard.

The title is the first text of the item, such as the text of a badge, or the label of an enum case. An item without text is titled with `itemLabel` and its number, "Badge 2". Without `itemLabel`, the framework uses the short name of the item class in words: `Badge` becomes "Badge", `SocialLink` becomes "Social link". Set `itemLabel` when the class name does not fit, for example for a German editor interface:

```php
#[ListOf(Badge::class, max: 3, itemLabel: 'Auszeichnung')] public array $badges = [],
```

With `appender: true`, editors also add items in the canvas of the block editor, through a faded new item at the end of the list; see [New list items in the canvas](./editor.md#new-list-items-in-the-canvas).

### Rules

| Rule | |
|---|---|
| `itemLabel` | not empty; `null` (default) for the short name of the class in words |
| `appender` | block attributes only, also in value classes, but not in list items; not on `#[Hidden]` lists and lists of posts |
| `min` | 0 or more; default 0 |
| `max` | 1 or more and at least `min`; `null` (default) for no limit |
| Default value | must have between `min` and `max` items, because a new block or setting starts with it; `null` for a nullable list is allowed |
| Stored values | not validated against the limits; the value factory creates the first `max` items |
| REST arguments | validated with `minItems` and `maxItems` |

A `min` below 0, a `max` below 1, a `min` greater than `max`, a default outside the limits, an empty `itemLabel` and `appender` where it is not allowed (settings groups, meta classes, REST arguments, list items, hidden lists, lists of posts) are build errors. Lists of [references](#references) without `#[ListOf]` have no limits.

## URLs

`#[Url]` on a string adds `format: uri` to the schema, so the value is sanitized with `sanitize_url()`. Disallowed protocols such as `javascript:` become an empty string.

## Colors

A background, an accent, the color of a banner: some values are colors that editors choose freely. Mark such a string with [`#[Color]`](../api/php/Value/Color.md) (namespace `Saij\WpAppFramework\Value\`):

```php
use Saij\WpAppFramework\Value\Color;

final readonly class BannerAttributes
{
    public function __construct(
        #[Color] public string $background = '',
        #[Color] public string $overlay = '#00000080',
    ) {}
}
```

The value is a hex color – `#rgb`, `#rgba`, `#rrggbb` or `#rrggbbaa`, the last two digits being the transparency – or an empty string for no color. Editors choose it with a color picker: it offers the colors of the theme's palette, a picker for any color with transparency, and "Clear" for no color. The palette is the one of the block editor: the colors changed in the site editor if there are any, else those of `theme.json`. Only its hex colors can be chosen, because a color is stored as hex value, not as the slug of the palette entry. A color picked from the palette therefore stays as it is when the palette changes later.

The schema of a color has a `pattern`, so WordPress rejects any other value, and the value factory checks it again – also values that were stored without the schema, such as an option changed with `update_option()`. A template can therefore print the color into a `style` attribute:

```twig
<section {{ block_wrapper_attributes({style: attrs.background ? 'background-color: ' ~ attrs.background : ''}) }}>
```

### Rules

| Rule | |
|---|---|
| Type | `string`, never nullable; `''` means no color |
| Allowed in | block attributes, value classes, lists, settings groups, meta classes, REST arguments |
| Schema | `string` with a `pattern` that allows the four hex formats and the empty string |
| Control | the color palette of the theme and a color picker with transparency |

The following are build errors:

- `#[Color]` on another type than `string`, or on `?string`;
- `#[Color]` together with `#[PlainText]`, `#[RichText]`, `#[Image]` or `#[Url]`;
- a default that is no hex color, such as `'red'`;
- `#[Color]` together with `#[Param(pattern:)]` on a REST argument.

A stored value that is no color is a [wrong value](#wrong-values). The build checks colors in templates and patterns against the pattern.

## Identifiers

Some list items are referred to from elsewhere: a block picks one of the color schemes listed in the settings, a tab is opened by a link. The position of the item is no good reference, because editors reorder lists, and neither is its name, because they rename items. Mark a string of the item class with [`#[Identifier]`](../api/php/Value/Identifier.md) (namespace `Saij\WpAppFramework\Value\`), and each item gets an identifier that never changes:

```php
use Saij\WpAppFramework\Value\Color;
use Saij\WpAppFramework\Value\Identifier;
use Saij\WpAppFramework\Value\PlainText;

final readonly class ColorScheme
{
    public function __construct(
        #[Identifier] public string $id = '',
        #[PlainText] public string $name = '',
        #[Color] public string $background = '#ffffff',
    ) {}
}
```

An identifier is eight lowercase hex digits, such as `3f9a1c2e`. The list editors generate it when an item is added – in the block inspector, in the canvas of the block editor, on the settings page and in meta boxes – unique within the list. Editors never see or change it; it has no control. Store it wherever you refer to the item, and look the item up by it:

```twig
{% set scheme = settings.design.schemes|filter(s => s.id == attrs.scheme)|first %}
```

An item that was stored before its class had an identifier has the empty string. When a settings group or post meta is saved, the framework fills in such missing identifiers and gives a duplicate a new one; the first item with an identifier keeps it. Block attributes are saved by the block editor without the framework, so an old item of a block keeps `''` until it is removed and added again.

Items in the default of a list have no identifier either, unless you give them one. Write a fixed identifier into an item the theme refers to itself, for example the first color scheme:

```php
#[ListOf(ColorScheme::class, min: 1)]
public array $schemes = [new ColorScheme('a1b2c3d4', 'Page')],
```

### Rules

| Rule | |
|---|---|
| Type | `string`, never nullable; default `''` |
| Allowed in | value classes that are items of `#[ListOf]`, in block attributes, settings groups, meta classes and REST arguments; one identifier per item class |
| Schema | `string` with a `pattern` that allows eight lowercase hex digits and the empty string |
| Control | none; generated when an item is added |
| Filled in | when a settings group or post meta is saved: missing, invalid and duplicate identifiers |

The following are build errors:

- `#[Identifier]` on another type than `string`, or on `?string`;
- a default other than `''`;
- `#[Identifier]` together with `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Content]`, `#[Url]`, `#[Color]`, `#[Hidden]` or a reference;
- `#[Identifier]` on a parameter of a class that is no list item – a block attribute class, a settings group, a meta class, a value class used as a single value, a REST argument;
- two identifiers in one item class;
- an item in the default of a list whose identifier is invalid or appears twice.

A stored value that is no identifier is a [wrong value](#wrong-values). The build checks identifiers in templates and patterns against the pattern.

## Links

A call to action, a link in a footer setting, a registration link of an event: a link points either to content of the site or to any address. `#[Url]` stores the address as text, so a link to a page breaks when someone changes the page's slug. A `Link` (namespace `Saij\WpAppFramework\Value\`) stores the ID of the page instead, and the framework looks up its current URL when it renders – the same idea as [references](#references), plus a URL entered freely and "Open in new tab".

```php
use Saij\WpAppFramework\Value\Label;
use Saij\WpAppFramework\Value\Link;

final readonly class TeaserAttributes
{
    public function __construct(
        #[Label('Call to action')] public Link $cta = new Link(),
    ) {}
}
```

A link is never `null`. A new `Link()` has an empty URL, and an empty URL means "no link". Print it with [`link_attributes()`](./twig.md#links), which writes `href` and, for a new tab, `target` and `rel`:

```twig
{% if attrs.cta.url %}
    <a class="teaser__cta" {{ link_attributes(attrs.cta) }}>More</a>
{% endif %}
```

### Linked content and free URLs

Editors choose the target in the block inspector with the link control of the block editor. It searches posts, terms and media files. A chosen result stays *synced*: the link stores its kind and ID, and the input field is locked until the editor chooses "Unsync and edit". An address typed into the field is a *free URL*: it is stored as it is, without ID.

A link is stored as four values:

```json
{"url":"https://example.org/about/","opensInNewTab":true,"kind":"post","id":42}
{"url":"https://example.com/","opensInNewTab":false,"kind":"url","id":0}
```

`kind` is a `LinkKind`: `Url` for a free URL, `Post`, `Term` or `Attachment` for linked content. For linked content, the stored URL is only what the editor showed when the target was chosen; templates never see it.

### Resolution

When the value is created, a linked post, term or media file gets its current URL: the permalink of a post, the archive of a term, the file of a media item. A link becomes empty – an empty `url`, while `kind` and `id` stay – if its target:

- no longer exists,
- is of a post type or taxonomy that is not viewable,
- is a post or media file that is not publicly viewable, such as a draft or the attachment of a draft.

Renders for the block editor keep posts and media files that are not public, so editors still see what they chose. Free URLs stay as they are; like `#[Url]`, they are sanitized with `sanitize_url()`.

Resolving costs about as much as a reference: one lookup of the object, usually from the object cache, and one call for its URL. A [cached block](./cache.md) keeps the resolved URL until its cache entry is invalidated. If the URLs of linked pages matter, tag the block with the types it links to, for example `PostTypeTag(Page::class)`, as you would for references.

:::tip
In templates and patterns of your theme, use free URLs only. IDs of pages, terms and media files differ from site to site, and the build does not check them.
:::

### Outside the block editor

The settings page and meta boxes on the classic edit screen do not load the block editor. There a link is edited with a form of its own: a search of published posts, which links the chosen post with its ID, a field for a free URL and the toggle "Open in new tab". A linked post shows its title and can be unlinked, which keeps its current URL as a free URL. Links to terms and media files are chosen in the block editor only.

### Rules

| Rule | |
|---|---|
| Type | `Link`, never nullable; default `new Link()` |
| Allowed in | block attributes, value classes, lists (`#[ListOf(Link::class)]`), settings groups, meta classes |
| Schema | `object` with `url` (`format: uri`), `opensInNewTab`, `kind` (enum `url`, `post`, `term`, `attachment`) and `id` |
| Blocks | counts as content (`role: content`), editable in contentOnly mode |
| Kind and ID | `kind: url` with `id: 0`; any other kind with a positive ID |

The following are build errors:

- `?Link`: a link is never null; an empty URL means no link;
- `#[Content]`, `#[Url]`, `#[PlainText]`, `#[RichText]`, `#[Image]` or a reference on a `Link`: a link is content with its own control.

A stored link whose kind and ID do not match is a [wrong value](#wrong-values). The build checks links in templates and patterns against the schema, so a wrong `kind` or a string as `opensInNewTab` is an error there; it does not check whether an ID exists.

## References

A block that links to another page, a setting that names the privacy policy page, a teaser that shows a category: these are references to WordPress objects. Storing the URL or the title would break as soon as someone renames the page. References store the ID instead, and the framework looks up the current data when it renders.

[`#[PostReference(postTypes: null)]`](../api/php/Value/PostReference.md), [`#[TermReference(taxonomies: null)]`](../api/php/Value/TermReference.md) and [`#[UserReference]`](../api/php/Value/UserReference.md) (namespace `Saij\WpAppFramework\Value\`) mark such IDs in block attributes, value classes and settings groups:

```php
#[PostReference(postTypes: ['page'])] public ?int $target = null,   // one page, null without
#[PostReference] public array $featured = [],                      // a list of post IDs, in order (list<int>)
#[TermReference(taxonomies: ['category'])] public ?int $topic = null,
#[UserReference] public ?int $author = null,
```

On `?int` the parameter holds one reference, or `null` if nothing is chosen. On `array` it holds a list of IDs in the chosen order; there is no `#[ListOf]` for it. The schema is `integer` or `["integer", "null"]`, or an array of integers.

`postTypes` and `taxonomies` limit the choice to these keys; `null` allows all viewable post types or taxonomies. The build does not check the keys against the registered types, because types are registered at run time. The search in the editor accepts only searchable post types and taxonomies.

In blocks, a reference counts as content (`role: content`), like `#[Image]`.

### Post objects

A parameter typed as a [post class](./posts.md) – a class with `#[PostType]`, or the framework's `Post` and `Page` – is a reference too, without an attribute: it stores the ID and gives you the post object.

```php
public ?Event $event = null,                         // the event, null without
#[ListOf(Venue::class)] public array $venues = [],   // a list of venues (list<Venue>)
```

The single reference must be nullable. It resolves if the post has the type of the class and is published, also for post types that are not public, such as data types. The search in the editor uses the REST collection of the post type, so it finds posts of these types too.

`postTypes` and `taxonomies` also take post and taxonomy classes instead of names: `#[PostReference(postTypes: [Event::class, 'page'])]`, `#[TermReference(taxonomies: [Genre::class, Category::class])]`. WordPress' own types and taxonomies are the framework classes `Post`, `Page`, `Category` and `Tag`.

### Editing

References are edited with a search, in the block inspector and on the settings page. Posts and terms are searched through `/wp/v2/search`, which finds published posts only. Users are searched through `/wp/v2/users`; users without the capability `list_users` see only authors of published posts. A list shows its entries as cards that can be dragged, moved up, moved down and removed.

### Resolution

Content changes after a reference was stored. A page is deleted, or put back into draft. The framework checks this when it creates the value. A reference becomes `null`, or is left out of a list, if its target:

- no longer exists,
- is of another type, or not of a viewable type,
- is a post that is not publicly viewable.

A deleted page therefore simply disappears from the output; nothing is logged. Renders for the block editor keep posts that are not public, such as drafts, so editors still see what they chose.

### Output

The [WordPress functions](./twig.md#wordpress-functions) take the IDs: `wp_post_title(id)`, `wp_post_url(id)`, `wp_term_name(id)`, `wp_term_url(id)`, `wp_user_name(id)` and more. They return `''` for `null` and missing objects. Posts that are not publicly viewable count as missing outside the editor.

```twig
{% if attrs.target %}
    <a href="{{ wp_post_url(attrs.target) }}">{{ wp_post_title(attrs.target) }}</a>
{% endif %}
```

### Rules

The following are build errors:

- a reference on a type other than `?int` or `array`, or together with `#[ListOf]`;
- a reference combined with `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Content]` or `#[Url]`;
- several references on one parameter;
- empty or invalid `postTypes` or `taxonomies`;
- a reference on a REST argument. Declare the ID as `int` there.

## Where values are used

[Blocks](./blocks.md#attribute-class), [REST routes](./rest.md#parameters) and [Settings](./settings.md) describe what each of them adds to these rules; [Tables](./tables.md#types) list the types of columns.
