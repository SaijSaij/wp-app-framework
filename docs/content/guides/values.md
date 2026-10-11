# Values

Block attribute classes, [settings groups](./settings.md), nested value classes and `#[ListOf]` REST arguments share one schema derivation (build) and one value factory (runtime, without reflection).

- **Value class:** a `readonly` class whose values are all public promoted constructor parameters and that declares no other properties. Value classes are the exception to the rule that properties are never public.
- **Types:** `int`, `float`, `string`, `bool`, backed enums (schema `enum` with the case values), value classes (schema `object` with `properties`) and `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` (schema `array` with `items`), each optionally nullable (`type: [..., 'null']`). Lists of scalars exist only for [references](#references). Enums and value classes must be in the scanned PSR-4 directories; recursive value classes are build errors.
- **Defaults** become the schema `default` (enums as their value, value objects as arrays). Inside a nested value class a parameter without default is `required`. Unknown keys in nested objects are ignored, like WordPress ignores unknown block attributes.
- **`#[Url]`** on a string adds `format: uri` (sanitized with `sanitize_url()`).
- **Conversion:** missing values use the constructor default; a value that does not match its type throws an `InvalidArgumentException` naming the path, e.g. `buttons[1].url` – values are never coerced silently.

## References

`#[PostReference(postTypes: null)]`, `#[TermReference(taxonomies: null)]` and `#[UserReference]` (namespace `Saij\WpAppFramework\Value\`) store IDs of posts, terms or users in block attributes, value classes and settings groups:

```php
#[PostReference(postTypes: ['page'])] public ?int $target = null,   // one page, null without
#[PostReference] public array $featured = [],                      // a list of post IDs, in order (list<int>)
#[TermReference(taxonomies: ['category'])] public ?int $topic = null,
#[UserReference] public ?int $author = null,
```

- On `?int` one reference (null without), on `array` a list of IDs (without `#[ListOf]`). Other types, `#[ListOf]`, combinations with `#[PlainText]`, `#[RichText]`, `#[Image]`, `#[Content]` or `#[Url]`, several references on one parameter and empty or invalid `postTypes`/`taxonomies` are build errors; so are references on REST arguments (declare the ID as `int` there).
- `postTypes` and `taxonomies` limit the choice to these keys; `null` allows all viewable post types or taxonomies. The keys are not checked against the registered types in the build (they are registered at run time); the search of the editor accepts only searchable post types and taxonomies.
- The schema is `integer`/`["integer", "null"]` or an array of integers. In blocks a reference counts as content (`role: content`), like `#[Image]`.
- **Resolution:** when the value is created, a reference to an object that no longer exists, is of another type (or not viewable) or – for posts – is not publicly viewable becomes `null` and is left out of a list. Renders for the block editor keep posts that are not public (drafts), so editors see their choice. A deleted page therefore simply disappears from the output; nothing is logged.
- **Editing:** a search in the block inspector and on the settings page – posts and terms through `/wp/v2/search` (published posts only), users through `/wp/v2/users` (users without `list_users` see only authors of published posts). A list shows its entries as cards with move up, move down and remove.
- **Output:** the template functions `post_title(id)`, `post_url(id)`, `term_name(id)`, `term_url(id)` and `user_name(id)` return text (escaped by Twig), `''` for `null` and missing objects; posts that are not publicly viewable count as missing outside the editor.

See [Blocks](./blocks.md#attribute-class), [REST routes](./rest.md#parameters) and [Settings](./settings.md) for how blocks, routes and settings use them.
