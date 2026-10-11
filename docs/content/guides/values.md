# Values

Blocks, REST routes and settings all deal with structured values: a headline, a choice among variants, a list of buttons, a link to another page. In WordPress these arrive as untyped arrays, either from the block comment, the request or an option. The framework lets you describe them once, as typed PHP classes, and uses that description in two places:

- **In the build**, it derives a JSON schema from the class. WordPress uses the schema to validate block attributes, REST arguments and settings.
- **At run time**, the *value factory* turns the stored array into an instance of your class, with enums as enum cases and nested objects as objects. It does this without reflection.

Block attribute classes, [settings groups](./settings.md), nested value classes and `#[ListOf]` REST arguments all work this way, so what you learn here applies to all of them.

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

## Defaults and missing values

The default value of a parameter becomes the schema's `default`: enums as their value, value objects as arrays.

A missing value uses the constructor's default. Inside a nested value class, a parameter without a default is `required` in the schema. Unknown keys in nested objects are ignored, just as WordPress ignores unknown block attributes.

## Wrong values

The value factory never coerces silently. A value that does not match its type throws an `InvalidArgumentException` that names the path to the value, for example `buttons[1].url`. Blocks, REST routes and settings each decide what happens next; see their pages.

## URLs

`#[Url]` on a string adds `format: uri` to the schema, so the value is sanitized with `sanitize_url()`. Disallowed protocols such as `javascript:` become an empty string.

## References

A block that links to another page, a setting that names the privacy policy page, a teaser that shows a category: these are references to WordPress objects. Storing the URL or the title would break as soon as someone renames the page. References store the ID instead, and the framework looks up the current data when it renders.

`#[PostReference(postTypes: null)]`, `#[TermReference(taxonomies: null)]` and `#[UserReference]` (namespace `Saij\WpAppFramework\Value\`) mark such IDs in block attributes, value classes and settings groups:

```php
#[PostReference(postTypes: ['page'])] public ?int $target = null,   // one page, null without
#[PostReference] public array $featured = [],                      // a list of post IDs, in order (list<int>)
#[TermReference(taxonomies: ['category'])] public ?int $topic = null,
#[UserReference] public ?int $author = null,
```

On `?int` the parameter holds one reference, or `null` if nothing is chosen. On `array` it holds a list of IDs in the chosen order; there is no `#[ListOf]` for it. The schema is `integer` or `["integer", "null"]`, or an array of integers.

`postTypes` and `taxonomies` limit the choice to these keys; `null` allows all viewable post types or taxonomies. The build does not check the keys against the registered types, because types are registered at run time. The search in the editor accepts only searchable post types and taxonomies.

In blocks, a reference counts as content (`role: content`), like `#[Image]`.

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

[Blocks](./blocks.md#attribute-class), [REST routes](./rest.md#parameters) and [Settings](./settings.md) describe what each of them adds to these rules.
