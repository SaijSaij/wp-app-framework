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
| `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` | `array` with `items` |

A nullable type adds `null` to the schema type (`type: [..., 'null']`). PHP has no generic types, so a list says what it contains with `#[ListOf]`. Lists of scalars exist only for [references](#references).

Enums and value classes must be in the scanned PSR-4 directories. A value class that contains itself, directly or indirectly, is a build error.

The columns of [tables](./tables.md) use the same value factory with one more type: `DateTimeImmutable`, read from the database as a UTC time `Y-m-d H:i:s`. Anything else in such a column, including the zero date `0000-00-00 00:00:00`, is an error. Blocks, REST routes and settings have no time type.

## Defaults and missing values

The default value of a parameter becomes the schema's `default`: enums as their value, value objects as arrays.

A missing value uses the constructor's default. Inside a nested value class, a parameter without a default is `required` in the schema. Unknown keys in nested objects are ignored, just as WordPress ignores unknown block attributes.

## Wrong values

The value factory never coerces silently. A value that does not match its type throws an `InvalidArgumentException` that names the path to the value, for example `buttons[1].url`. Blocks, REST routes and settings each decide what happens next; see their pages.

## URLs

`#[Url]` on a string adds `format: uri` to the schema, so the value is sanitized with `sanitize_url()`. Disallowed protocols such as `javascript:` become an empty string.

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

References are edited with a search, in the block inspector and on the settings page. Posts and terms are searched through `/wp/v2/search`, which finds published posts only. Users are searched through `/wp/v2/users`; users without the capability `list_users` see only authors of published posts. A list shows its entries as cards that can be moved up, moved down and removed.

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
