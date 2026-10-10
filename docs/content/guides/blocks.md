# Blocks

A block is a service class with `#[Block]` and a `render()` method; its attributes are a typed attribute class. There is no `block.json`: the build derives the attribute schema from the attribute class, and WordPress passes it to the editor (`get_block_editor_server_block_settings()`). The post content stores the block comment with the attributes as JSON, e.g. `<!-- wp:sections/hero {"topline":"Summer"} /-->`. The only markup in the post content is the value of a `#[RichText(postContent: true)]` field: inline HTML without wrapping element, e.g. `<!-- wp:sections/paragraph -->Summer <strong>party</strong><!-- /wp:sections/paragraph -->` (see [Text in the post content](#text-in-the-post-content)).

```php
use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Block\Supports;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

#[Block('sections/hero', title: 'Hero', category: BlockCategory::Design, icon: 'cover-image')]
#[Supports(anchor: true, align: [Align::Wide, Align::Full])]
final class Hero
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    public function render(HeroAttributes $attrs, string $content, WP_Block $block): string
    {
        return $this->renderer->render('Block/Hero/Hero.twig', ['attrs' => $attrs, 'content' => $content]);
    }
}

final readonly class HeroAttributes
{
    public function __construct(
        #[PlainText] public string $topline = '',
        #[RichText(allowedFormats: ['core/bold', 'core/italic'])] public string $headline = '',
        #[Image] #[Label('Image')] public ?int $imageId = null,
        public Gradient $gradient = Gradient::Black,
        #[ListOf(Button::class)] public array $buttons = [],
    ) {
    }
}

final readonly class Button
{
    public function __construct(
        #[PlainText] public string $label,
        #[Content] #[Url] public string $url = '',
    ) {
    }
}
```

```twig
<section {{ block_wrapper_attributes({class: 'hero hero--' ~ attrs.gradient.value}) }}>
    {% if attrs.topline or is_editor() %}
        <p{{ field('topline') }}>{{ attrs.topline }}</p>
    {% endif %}
    <h1{{ field('headline') }}>{{ attrs.headline|raw }}</h1>
    <figure{{ field('imageId') }}>{{ attachment_image(attrs.imageId, 'large') }}</figure>
    {% for button in attrs.buttons %}
        <a href="{{ button.url }}"{{ field(['buttons', loop.index0, 'label']) }}>{{ button.label }}</a>
    {% endfor %}
    <div{{ inner_blocks() }}>{{ content|raw }}</div>
</section>
```

`field()`, `inner_blocks()` and `is_editor()` only matter in the editor; on the front end the marks are empty (see [Editor](./editor.md)).

## `#[Block]`

`#[Block(name, title, category, icon: '', description: '', keywords: [], usesContext: [], allowedBlocks: null, excerptWrapper: false)]` on a service class.

- The name is `namespace/name` (lowercase letters, digits, `-`); the namespace `core` and duplicate names are build errors, and the framework itself declares no blocks.
- `category` is a `BlockCategory` case.
- `usesContext` lists the context values the block reads from `WP_Block::$context`, e.g. `postId` in a query loop.
- `allowedBlocks` limits the blocks that can be inserted as inner blocks (`allowed_blocks`); it requires `render()` to accept `string $content`.
- `excerptWrapper` adds a container to `excerpt_allowed_wrapper_blocks` (see [Excerpts](#excerpts)); it also requires `string $content`.
- Blocks are registered on `init` with `api_version` 3.

## `#[Supports]`

`#[Supports(contentRole: false, anchor: false, align: [], customClassName: true, multiple: true, reusable: true, inserter: true, lock: true, renaming: true, customCSS: true)]` with WordPress' defaults; only differing values are registered. `html` is always disabled. Container blocks set `contentRole: true`, so inner blocks can be inserted in contentOnly mode. `customCSS` (enabled by default in WordPress 7) lets users with `edit_css` store free CSS per block instance; the theme decides whether it allows it – per block with `customCSS: false`, or for all blocks with its own `register_block_type_args` hook.

## `render()`

`render()` is public, non-static and returns `string`. Its parameters are matched by type, each at most once and in any order: the attribute class, `string` (the rendered inner blocks) and `WP_Block`. Services come through the constructor; the block service is created when the block is first rendered.

## Attribute class

A value class (see [Values](./values.md)) whose parameters all have a default value, because an attribute can always be missing in the content. The names `lock`, `metadata`, `className`, `anchor`, `align` and `style` are reserved for the attributes WordPress adds itself (`style` comes with the `customCSS` support, enabled by default in WordPress 7). The attribute schema gets `type`, `enum`, `properties`/`items`, `default` and `role: content` (below).

## Fields

`#[PlainText]` (inline text, strings only), `#[RichText(allowedFormats: null)]` (inline HTML, strings only; `allowedFormats` lists the formats the editor offers, e.g. `['core/bold']`, `null` allows all, `[]` none) and `#[Image]` (an attachment ID, `?int` only) mark the fields edited in the canvas; `#[Content]` marks other content such as links.

With `inspector: true` (`#[RichText(inspector: true)]`, also on `#[PlainText]` and `#[Image]`) a field is edited in the block inspector instead of the canvas – for values the markup does not show, or that are better edited in a form.

An attribute gets `role: content` if it or a nested field is marked, so the block stays editable in contentOnly mode (patterns and template parts open in it in WordPress 7). The marks have no effect on REST arguments.

## Text in the post content

`#[RichText(postContent: true)]` stores the value as the block's inner HTML instead of in the block comment: inline HTML without wrapping element, so text blocks such as paragraphs and headings keep their text in the post content, while the markup stays in Twig (`source: html` without selector; the editor saves it with `RawHTML`). WordPress never parses sourced attributes on the server; the framework takes the value from the block content before creating the attribute object, so the template prints it like any other value (`{{ attrs.text|raw }}`).

Rules (build errors): on a parameter of the attribute class itself (not in value classes or list items), at most one per block, only in blocks without inner blocks (both would share the block content) and with the default `''` (the editor stores an empty value as empty block content). The root element of the template may be the field itself, e.g. `<p {{ block_wrapper_attributes() }}{{ field('text') }}>{{ attrs.text|raw }}</p>`.

## Excerpts

`excerpt: true` on `#[PlainText]` or `#[RichText]` (also in list items, value classes and with `inspector: true`) includes a value in automatic excerpts (`get_the_excerpt()` without a manual excerpt: archives, `core/post-excerpt`, feeds, REST). Blocks with such fields are added to `excerpt_allowed_blocks`.

WordPress renders allowed blocks for an excerpt while the `get_the_excerpt` filter runs and offers no other signal; during that filter the block renders only its excerpt fields, one paragraph each (rich text as stored, plain text escaped), without `render()` or its template – `wp_trim_excerpt()` strips the tags. If other code renders blocks inside a `get_the_excerpt` filter, framework blocks render as excerpts there, too.

WordPress never renders blocks with inner blocks for excerpts, so `excerpt: true` in such a block is a build error; `#[Block(excerptWrapper: true)]` includes a container's allowed inner blocks instead, like `core/group`.

Search is unchanged: WordPress searches the raw post content, which finds single words of values in the block comment JSON (stored as is, also non-ASCII), but no phrases across formatting and no values with `&`, `<`, `>` or `"` (stored as `&` etc.); attribute names and enum values match, too.

## Editor labels

`#[Label('…')]` labels a value in the inspector and is the placeholder of an inline field; without it the label is derived from the parameter name (`imageId` → "Image id"). A backed enum implements `Block\Labeled` (`label(): string`, evaluated in the build) to label its cases; otherwise the editor shows the case values. `#[Hidden]` keeps a value out of the generated inspector (not allowed on inline fields).

## Attributes at render time

WordPress validates the attributes against the schema before rendering (an invalid value falls back to the default) but does not sanitize them. The value factory sanitizes each attribute against its schema (`rest_sanitize_value_from_schema()`) and creates the attribute object; attributes of block supports are ignored.

## Errors

Exceptions in `render()` or in creating the attributes are rethrown if `WP_DEBUG` is enabled; otherwise they are logged with `error_log()` and the block renders nothing.

## Core blocks

The framework disables none. A theme decides itself which core blocks it allows, e.g. with a [hook](./hooks.md):

```php
final class AllowedBlocks
{
    /**
     * @param bool|list<string> $allowed
     * @return bool|list<string>
     */
    #[Hook('allowed_block_types_all')]
    public function filter(bool|array $allowed): bool|array
    {
        return ['core/post-content', 'core/template-part', 'sections/hero' /* … */];
    }
}
```

## Escaping

| Value | Output |
|---|---|
| `#[PlainText]` and other strings | `{{ attrs.topline }}` – Twig's autoescaping |
| `#[RichText]` | `{{ attrs.headline\|raw }}` – exactly as stored; WordPress filters it with `wp_kses` when a user without `unfiltered_html` saves |
| `#[Url]` strings | `{{ button.url }}` – the schema has `format: uri`, so the value is sanitized with `sanitize_url()` (disallowed protocols such as `javascript:` become empty) and then autoescaped |
| Images | `#[Image]` attachment ID with `{{ attachment_image(attrs.imageId, size = 'full', attributes = {}) }}` (`wp_get_attachment_image()`; empty for `null`) |
| Inner blocks | `{{ content\|raw }}` |
| Root element | `{{ block_wrapper_attributes(extra = {}) }}` (`get_block_wrapper_attributes()`: classes and anchor of the supports) |

No string filters such as `upper` on HTML (uppercase via CSS).
