# Blocks

Blocks are the heart of a framework theme. Every block is rendered on the server from a Twig template, and content editors edit it inline in the editor canvas. This page shows how to write a block, which attributes it can have and how its values reach the markup.

## How blocks are stored

A WordPress block usually stores its HTML in the post content. Once saved, that HTML stays as it is: changing the block's markup later means either migrating old posts or leaving them with the old markup.

Framework blocks store no markup. The post content holds only the block comment with the attributes as JSON:

```html
<!-- wp:sections/hero {"topline":"Summer","gradient":"blue"} /-->
```

When the page is displayed, the framework renders the block from its Twig template. A change to the template therefore shows up in every post at once. Block supports such as alignment or an anchor are attributes as well.

There is one exception: the value of a `#[RichText(postContent: true)]` field is stored as inline HTML without a wrapping element, e.g. `<!-- wp:sections/paragraph -->Summer <strong>party</strong><!-- /wp:sections/paragraph -->`. That keeps the text of paragraphs and headings in the post content, where search and other plugins expect it (see [Text in the post content](#text-in-the-post-content)).

## Anatomy of a block

A block is made of three parts: a service class with `#[Block]` and a `render()` method, a typed attribute class, and a Twig template. There is no `block.json`. The build derives the attribute schema from the attribute class, and WordPress passes it to the editor on its own (through `get_block_editor_server_block_settings()`). The schema and the PHP object you work with therefore always match.

Here is a hero block with a topline, a headline, an image, a gradient and a list of buttons.

### The block class

```php
use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;
use Saij\WpAppFramework\Twig\TemplateRenderer;

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
```

`#[Block]` names the block and describes it for the inserter. `#[Supports]` switches on WordPress features: here an HTML anchor and the wide and full alignments.

`render()` receives what it asks for. `HeroAttributes` is the attribute object, `string $content` the rendered inner blocks, and `WP_Block $block` WordPress' block instance. A block that asks for `$content` can contain inner blocks.

### The attribute class

```php
use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

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

Each constructor parameter becomes an attribute, and its type decides how it is edited:

- `$topline` and `$headline` are edited in the canvas: plain text, and rich text with bold and italic.
- `$imageId` is an image chosen from the media library in the canvas. Only the attachment ID is stored.
- `$gradient` is a backed enum. The editor shows a select with its cases in the inspector.
- `$buttons` is a list of `Button` value objects. The inspector shows a list editor, and each button's label is edited in the canvas.

### The template

```twig
<section {{ block_wrapper_attributes({class: 'hero hero--' ~ attrs.gradient.value}) }}>
    {% if attrs.topline or is_editor() %}
        <p{{ field('topline') }}>{{ attrs.topline }}</p>
    {% endif %}
    <h1{{ field('headline') }}>{{ attrs.headline|raw }}</h1>
    <figure{{ field('imageId') }}>{{ wp_attachment_image(attrs.imageId, 'large') }}</figure>
    {% for button in attrs.buttons %}
        <a href="{{ button.url }}"{{ field(['buttons', loop.index0, 'label']) }}>{{ button.label }}</a>
    {% endfor %}
    <div{{ inner_blocks() }}>{{ content|raw }}</div>
</section>
```

The template is ordinary Twig with a few helpers:

- `block_wrapper_attributes()` goes on the root element. It adds the classes and the anchor of the block's supports, and the marker of its [front-end scripts](./frontend.md).
- `field('topline')` marks the element that shows a field, so the editor can turn it into an editor. `inner_blocks()` marks where inner blocks go.
- `is_editor()` keeps the empty topline in the editor, so content editors can fill it in, while the front end leaves it out.

On the front end `field()`, `inner_blocks()` and `is_editor()` print nothing; they only matter in the editor (see [Editor](./editor.md)). The headline is printed with `|raw` because it holds HTML; everything else is escaped by Twig (see [Escaping](#escaping)).

## `#[Block]`

`#[Block(name, title, category, icon: '', description: '', keywords: [], usesContext: [], allowedBlocks: null, excerptWrapper: false)]` goes on a service class.

The **name** is `namespace/name` in lowercase letters, digits and `-`. The namespace belongs to the theme or plugin, for example `sections/hero`. The namespace `core` and duplicate names are build errors. The framework itself declares no blocks.

The **category** is a `BlockCategory` case; an enum instead of a string lets your IDE complete it.

**`usesContext`** lists the context values the block reads from `WP_Block::$context`. The typical case is `postId` in a query loop, where a block shows data of the current post.

**`allowedBlocks`** limits which blocks can be inserted as inner blocks (`allowed_blocks` in WordPress). It requires `render()` to accept `string $content`.

**`excerptWrapper`** adds a container to WordPress' `excerpt_allowed_wrapper_blocks` (see [Excerpts](#excerpts)). It also requires `string $content`.

The framework registers blocks on `init` with `api_version` 3.

## `#[Supports]`

`#[Supports]` switches on the block supports of WordPress. Its arguments and their defaults are WordPress' own:

`#[Supports(contentRole: false, anchor: false, align: [], customClassName: true, multiple: true, reusable: true, inserter: true, lock: true, renaming: true, customCSS: true)]`

Only values that differ from WordPress' defaults are registered. `html` is always disabled, because a framework block has no stored HTML to edit.

**`contentRole: true`** is for container blocks without content of their own. WordPress 7 opens patterns and template parts in contentOnly mode, where editors may only insert blocks into containers that count as content. A block counts as content if it has a content attribute (see [Fields](#fields)) or `contentRole: true`. A section that only holds inner blocks needs `contentRole: true`, so that blocks can be inserted into it in contentOnly mode.

**`customCSS`**, enabled by default in WordPress 7, lets users with `edit_css` store free CSS for each block instance. Whether to allow that is the theme's decision: per block with `customCSS: false`, or for all blocks with a `register_block_type_args` hook of its own.

Settings of your own that several blocks share, such as a color scheme, are [block supports](./supports.md) of the theme or plugin, added with `#[UseSupport]`.

## `render()`

`render()` is public, non-static and returns `string`. Its parameters are matched by type, each at most once and in any order:

- the attribute class,
- `string` – the rendered inner blocks,
- `WP_Block` – WordPress' block instance.

Services come through the constructor. The block service is created when the block renders for the first time, not when WordPress registers it.

## Attribute class

The attribute class is a value class (see [Values](./values.md)). Every parameter needs a default value, because any attribute can be missing in the content: the block was inserted before the attribute existed, or the editor never set it.

Some names are taken. WordPress adds the attributes `lock`, `metadata`, `className`, `anchor`, `align` and `style` itself (`style` comes with the `customCSS` support, enabled by default in WordPress 7). The attributes of the block's own [block supports](./supports.md) are taken as well. None of these can be a parameter of the attribute class.

From the class, the build creates the attribute schema with `type`, `enum`, `properties`/`items`, `default` and `role: content` (see below).

## Fields

Most blocks hold text and images that content editors should change directly where they see them. Mark these parameters, and the editor makes them editable in the canvas:

- **`#[PlainText]`** – inline text, on strings only.
- **`#[RichText(allowedFormats: null)]`** – inline HTML, on strings only. `allowedFormats` lists the formats the editor offers, e.g. `['core/bold']`; `null` allows all, `[]` none.
- **`#[Image]`** – an attachment ID, on `?int` only.

Some values are not visible in the markup, or are better edited in a form. With `inspector: true` – `#[RichText(inspector: true)]`, also on `#[PlainText]` and `#[Image]` – a field is edited in the block inspector instead of the canvas.

**`#[Content]`** marks other content, such as the URL of a link. It is edited in the inspector like any other value, but it counts as content: its control appears in the inspector's "Content" group, and WordPress keeps it editable in contentOnly mode (see below).

[References](./values.md#references) to posts, terms and users (`#[PostReference]`, `#[TermReference]`, `#[UserReference]`) are edited with a search in the inspector, and they count as content too.

### Content in contentOnly mode

In WordPress 7, patterns and template parts open in contentOnly mode. Content editors can change the content of blocks there, but not their design. A block without content attributes is hidden and cannot be selected.

WordPress decides by the attribute's `role: content`. The build sets it for you: an attribute gets `role: content` if it, or a field nested in it, carries `#[PlainText]`, `#[RichText]`, `#[Image]` or `#[Content]`, or is a reference. In the example, `buttons` is content because each button has a label and a URL.

The marks have no effect on REST arguments.

## Editor labels

The editor needs a label for each value. `#[Label('…')]` sets the label in the inspector and the placeholder of an inline field. Without it, the label is derived from the parameter name: `imageId` becomes "Image id".

A backed enum labels its cases by implementing `Block\Labeled` with a method `label(): string`. The build evaluates it, so it may not depend on run-time state. Without it, the editor shows the case values.

`#[Hidden]` keeps a value out of the generated inspector. It is not allowed on inline fields.

## Text in the post content

Text blocks such as paragraphs and headings are a special case. Search, SEO plugins and exports read the raw post content, and for most blocks they find only JSON there. For a block whose whole purpose is text, it is better to keep the text in the post content.

`#[RichText(postContent: true)]` does that. The value is stored as the block's inner HTML instead of in the block comment: inline HTML without a wrapping element. The markup around it still comes from Twig.

```php
final readonly class ParagraphAttributes
{
    public function __construct(
        #[RichText(postContent: true)] public string $text = '',
    ) {}
}
```

```twig
<p {{ block_wrapper_attributes() }}{{ field('text') }}>{{ attrs.text|raw }}</p>
```

As the example shows, the root element of the template may be the field itself.

For your code nothing changes: the template prints the value like any other. Under the hood, the attribute is a sourced attribute (`source: html` without selector), and the editor saves it with `RawHTML`. WordPress never parses sourced attributes on the server, so the framework takes the value from the block content before it creates the attribute object.

The following are build errors, each for a reason:

- The field is not a parameter of the attribute class itself (but of a value class or a list item): only top-level values can map to the inner HTML.
- A block has more than one such field: there is only one inner HTML.
- The block has inner blocks: inner blocks and the field would share the block content.
- The default is not `''`: the editor stores an empty value as empty block content.

## Excerpts

WordPress creates automatic excerpts from the post content – for archives, `core/post-excerpt`, feeds and REST – when a post has no manual excerpt (`get_the_excerpt()`). To do that, it renders only blocks that it knows contain text. Framework blocks tell WordPress which of their texts belong in an excerpt.

`excerpt: true` on `#[PlainText]` or `#[RichText]` includes the value in automatic excerpts. It also works in list items, in value classes and with `inspector: true`. Blocks with such fields are added to `excerpt_allowed_blocks`.

When WordPress renders a block for an excerpt, the block renders only its excerpt fields, one paragraph each: rich text as stored, plain text escaped. Neither `render()` nor the template runs. `wp_trim_excerpt()` then strips the tags.

:::warning
WordPress renders blocks for an excerpt while the `get_the_excerpt` filter runs, and offers no other signal. If other code renders blocks inside a `get_the_excerpt` filter, framework blocks render as excerpts there, too.
:::

WordPress never renders blocks with inner blocks for excerpts, so `excerpt: true` in such a block is a build error. For a container, use `#[Block(excerptWrapper: true)]` instead. It includes the excerpts of the container's allowed inner blocks, like `core/group`.

### Search

Search is unchanged. WordPress searches the raw post content, and it finds single words of values in the block comment JSON, which stores non-ASCII characters as they are. It does not find phrases across formatting, nor values with `&`, `<`, `>` or `"` (stored as `&` etc.). Attribute names and enum values match, too.

## Attributes at render time

WordPress validates the attributes against the schema before rendering: an invalid value falls back to the default. It does not sanitize them, though. The framework's value factory therefore sanitizes each attribute against its schema (`rest_sanitize_value_from_schema()`) and only then creates the attribute object. Attributes of block supports, WordPress' and [your own](./supports.md), are not part of the attribute object.

## Errors

An exception in `render()` or while the attribute object is created is rethrown if `WP_DEBUG` is enabled. Otherwise it is logged with `error_log()`, and the block renders nothing. The rest of the page still renders.

## Core blocks

The framework disables no core blocks. Which ones a theme offers is the theme's decision. A [hook](./hooks.md) on `allowed_block_types_all` limits them:

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

Twig escapes every value unless you say otherwise. This table shows how each kind of value gets into the markup:

| Value | Output |
|---|---|
| `#[PlainText]` and other strings | `{{ attrs.topline }}` – Twig's autoescaping |
| `#[RichText]` | `{{ attrs.headline\|raw }}` – exactly as stored; WordPress filters it with `wp_kses` when a user without `unfiltered_html` saves |
| `#[Url]` strings | `{{ button.url }}` – the schema has `format: uri`, so the value is sanitized with `sanitize_url()` (disallowed protocols such as `javascript:` become empty) and then autoescaped |
| Images | `#[Image]` attachment ID with `{{ wp_attachment_image(attrs.imageId, size = 'full', attributes = {}) }}` (`wp_get_attachment_image()`; empty for `null`; more in [WordPress functions](./twig.md#wordpress-functions)) |
| References | IDs; `{{ wp_post_title(attrs.target) }}`, `{{ wp_post_url(attrs.target) }}`, `wp_term_name()`, `wp_term_url()`, `wp_user_name()` and more [WordPress functions](./twig.md#wordpress-functions) (see [References](./values.md#references)) |
| Inner blocks | `{{ content\|raw }}` |
| Root element | `{{ block_wrapper_attributes(extra = {}) }}` (`get_block_wrapper_attributes()`: classes and anchor of the supports, plus the classes of [block supports](./supports.md) and the marker of [front-end scripts](./frontend.md)) |

Don't apply string filters such as `upper` to HTML: they change tags, URLs and entities too. Use CSS for uppercase text.
