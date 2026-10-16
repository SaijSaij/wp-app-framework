# Container blocks

Many blocks hold other blocks: a section holds teasers, tabs hold one panel per tab, a slider holds slides. In WordPress these are blocks with *inner blocks*. A container decides which blocks may go inside, which ones a new container starts with, and how editors may change them. The blocks inside often depend on their container, too: a tab only makes sense inside tabs, and it needs to know which tab is active.

This page shows how to build such a pair of blocks. The basics of a block – attribute class, template, `render()` – are explained in [Blocks](./blocks.md).

## A container and its children

A block becomes a container when its `render()` accepts `string $content`, the rendered inner blocks. Its template marks the element that holds them with `inner_blocks()`:

```php
#[Block('tabs', title: 'Tabs', category: BlockCategory::Design)]
final class Tabs
{
    public function __construct(private TemplateRenderer $twig) {}

    public function render(TabsAttributes $attributes, string $content): string
    {
        return $this->twig->render('Block/Tabs/Tabs.twig', ['attrs' => $attributes, 'content' => $content]);
    }
}
```

```twig
<div {{ block_wrapper_attributes() }}>
  <div{{ inner_blocks() }}>{{ content|raw }}</div>
</div>
```

Without further declarations every block may be inserted into it, and the editor behaves as for `core/group`. `#[InnerBlocks]` changes that.

### Inner blocks

`#[InnerBlocks]` on the block class describes the inner blocks:

```php
#[Block('tabs', title: 'Tabs', category: BlockCategory::Design)]
#[InnerBlocks(
    allowed: [Tab::class],
    template: [new TemplateBlock(Tab::class, ['title' => 'Tab 1']), new TemplateBlock(Tab::class, ['title' => 'Tab 2'])],
    templateLock: TemplateLock::Insert,
    orientation: Orientation::Horizontal,
)]
final class Tabs { /* … */ }
```

- **`allowed`** limits the blocks that can be inserted. Here only tabs.
- **`template`** is what a newly inserted container starts with: two tabs with their titles.
- **`templateLock`** restricts changes. `TemplateLock::Insert` lets editors reorder the tabs, but not add or remove them; `TemplateLock::All` allows no changes to the structure, `TemplateLock::ContentOnly` only changes of the content. Without it the container inherits the lock of its parent, and `TemplateLock::Unlocked` lifts a lock of the parent.
- **`orientation`** tells the editor that the tabs sit side by side. The movers then show arrows to the left and right.

Three more options control how blocks are added. **`defaultBlock`** is the block the editor suggests, and with **`directInsert: true`** the "+" at the end of the container inserts it right away. **`prioritized`** lists blocks that the quick inserter of the container – the small "+" – and the slash command offer first. **`appender`** chooses that "+" at the end: `Appender::Default` lets WordPress decide (an empty paragraph to type into, or the "+" button if paragraphs are not allowed), `Appender::Button` always shows the button, and `Appender::None` shows nothing, so blocks are added through the toolbar or the list view only.

:::warning
With `TemplateLock::All` or `TemplateLock::ContentOnly`, the editor brings the inner blocks of every container in line with the template whenever it opens one. Inner blocks that differ – in a post, a template or a pattern – are replaced. The [build](../build-and-release/build.md#checking-templates-parts-and-patterns) reports templates, template parts and patterns whose inner blocks differ from a locked template.
:::

### Referring to blocks

`allowed` and the other options name blocks in three ways, so that your IDE can resolve them wherever possible:

- blocks of your theme or plugin by their class, `Tab::class`;
- core blocks by a case of `CoreBlock`, `CoreBlock::Paragraph`;
- other blocks by their full name, `'acme/slider'`.

A `TemplateBlock` takes the block in the same way, its attribute values and, for a container, its inner blocks: `new TemplateBlock(Tab::class, ['title' => 'Tab 1'], [new TemplateBlock(CoreBlock::Paragraph)])`. Attribute values of your own blocks are checked in the build against their attribute class; a backed enum stands for its value.

### Restricting where a block goes

A tab outside of tabs makes no sense. `parent` on the child limits it to the containers it may be inserted into directly; the inserter then offers it only there:

```php
#[Block('tab', title: 'Tab', category: BlockCategory::Design, parent: [Tabs::class])]
final class Tab { /* … */ }
```

`ancestor` is the looser variant: the block may be inserted anywhere inside the named blocks, also deeper down. Both take the same values as `allowed`. The build checks that each container of your own in `parent` allows the child, and it reports your blocks placed elsewhere in templates, template parts and patterns.

## Passing values to inner blocks

The tabs know which tab is active; each tab needs that value to render itself open or closed. WordPress passes such values as *block context*: a container provides values of its attributes, and the blocks inside read them, at any depth, without storing them themselves.

The container marks the attributes it passes on with `#[ProvidesContext]` in its attribute class:

```php
final readonly class TabsAttributes
{
    public function __construct(
        #[ProvidesContext]
        public int $active = 0,
        public TabStyle $style = TabStyle::Line,
    ) {}
}
```

A block inside reads the value with a parameter of `render()` marked `#[Context]`. It names the container class and its attribute:

```php
final class Tab
{
    public function render(
        TabAttributes $attributes,
        string $content,
        #[Context(Tabs::class, 'active')]
        int $active = 0,
    ): string {
        // …
    }
}
```

The parameter receives the value with its type, created like an attribute: an enum as its case, a value class as an object. Outside of tabs there is no value, and the parameter keeps its default; that's why it needs a default or a nullable type. In the editor the value reaches the block as well: when an editor changes the active tab, the tabs inside render again.

The attribute name is the only string here, and the build checks it: the container must be a block of your theme or plugin, its attribute class must have the attribute, the attribute must be marked with `#[ProvidesContext]`, and the parameter must have the attribute's type.

WordPress itself names context values; the framework derives the names, `{namespace}/{block}/{attribute}` – here `sections/tabs/active`. Values of other blocks, such as `postId` in a query loop, are read as before with `usesContext` and `WP_Block` (see [Blocks](./blocks.md#block)).

## Repeating inner blocks

A list of events, the terms of a taxonomy, the comments of a post: some containers show their inner blocks once for every item, like `core/post-template` in a query loop. Editors design one item from blocks, and the container repeats it. Such a container asks for a [`Repeater`](../api/php/Block/Repeater.md) in `render()` instead of `string $content`, decides which items there are, and renders the inner blocks for each:

```php
use Saij\WpAppFramework\Block\Repeater;

#[Block('event-list', title: 'Event list', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [EventCard::class, CoreBlock::PostTitle])]
final readonly class EventList
{
    public function __construct(private TemplateRenderer $renderer) {}

    public function render(EventListAttributes $attrs, Repeater $repeater): string
    {
        $items = array_map($repeater->post(...), Event::query()->limit($attrs->count)->get());

        return $this->renderer->render('Block/EventList/EventList.twig', ['items' => $items]);
    }
}
```

The template marks the element of each item with `repeater_item()` and prints the item's inner blocks in it:

```twig
<ul {{ block_wrapper_attributes() }}>
    {% for item in items %}
        <li{{ repeater_item(item) }}>{{ item.content|raw }}</li>
    {% endfor %}
</ul>
```

### Kinds of items

Each method of the repeater renders the inner blocks once and returns a [`RepeaterItem`](../api/php/Block/RepeaterItem.md). The inner blocks get the item as block context:

| Method | Item | Block context |
|---|---|---|
| `post($post)` | an ID, a `WP_Post` or a post object | `postId`, `postType`; the post is the global post while the item renders |
| `term($term)` | an ID or a `WP_Term` | `termId`, `taxonomy` |
| `comment($comment)` | an ID or a `WP_Comment` | `commentId` |
| `user($user)` | an ID or a `WP_User` | `userId` |
| `row($row)` | a saved row object of a [table](./tables.md) | `{namespace}/{table}`, e.g. `sections/log` |

Posts, terms and comments use the context of WordPress' own loop blocks, so core blocks such as `core/post-title`, `core/term-name` or `core/comment-content` show the item. One repeater may mix kinds: a list can show a post, then a term. The context of the ancestors stays available to the inner blocks, and the container's own `#[ProvidesContext]` values reach them as well. An item that does not exist – a deleted post, a row without primary key – throws an `InvalidArgumentException`.

### The current item

A block of your theme or plugin reads the item it is shown for with [`#[Item]`](../api/php/Block/Item.md) on a parameter of `render()`:

```php
public function render(EventCardAttributes $attrs, #[Item] ?Event $event): string
```

The type says which item: a post class of the theme or plugin or `WP_Post`, `WP_Term`, `WP_Comment`, `WP_User` or a row class. The parameter is nullable; it is null outside an item of its kind. A block can take several of them and show whichever is there. The framework adds the context names to the block's `usesContext` itself. A post comes from the `postId` context, which WordPress also sets for the post being shown, so outside a repeater a `#[Item] ?WP_Post` is that post, as for `core/post-title`; a post class gets only posts of its own type.

### In the editor

The editor shows the inner blocks of the first item to edit and the other items as live previews of the same blocks in their own context, like `core/post-template`. A click on a preview edits that item instead. Everything typed shows up in all items at once; the blocks are stored once, as inner blocks of the container. In renders for the editor, `item.content` is empty: the runtime fills the element of each item.

The previews render each framework block for its item with the item's context. For a post that the editor may not edit, such as the published post of another author, the render route allows the render if the post is public; drafts of others stay closed. Core blocks in a preview load their data themselves; `core/post-title` asks for the post with `context=edit`, which WordPress refuses an author for posts of others, as in a query loop of WordPress.

A container without items shows no inner blocks in the editor. Render something else in that case, for example `{% else %}<li>No events yet</li>`.

### Caching

[`#[Cache]`](./cache.md) works on a repeating container: its key contains the inner blocks, so a changed item design renders anew. The items themselves are data like any other – invalidate them with tags, such as `new PostTypeTag(Event::class)`. Inner blocks with a cache of their own are cached per item, because their key contains their context.

## Rules

[`#[InnerBlocks(allowed: null, template: null, templateLock: null, orientation: null, defaultBlock: null, directInsert: false, prioritized: [], appender: Appender::Default)]`](../api/php/Block/InnerBlocks.md):

| Argument | Type | WordPress |
|---|---|---|
| `allowed` | list of block references, `null` for all | `allowed_blocks` of the block type |
| `template` | list of `TemplateBlock` | `template` of `useInnerBlocksProps()` |
| `templateLock` | `TemplateLock` (`All`, `Insert`, `ContentOnly`, `Unlocked` = `false`), `null` to inherit | `templateLock` |
| `orientation` | `Orientation` (`Horizontal`, `Vertical`) | `orientation` |
| `defaultBlock` | `TemplateBlock` without inner blocks | `defaultBlock` |
| `directInsert` | `bool`; needs `defaultBlock` | `directInsert` |
| `prioritized` | list of block references, each in `allowed` | `prioritizedInserterBlocks` |
| `appender` | `Appender` (`Default`, `Button`, `None`) | `renderAppender` |

A block reference is a block class of the same theme or plugin, a `CoreBlock` case or the full name of another block. The build reports:

- `#[InnerBlocks]` on a block whose `render()` takes no `string $content`;
- a class that is no block of the theme or plugin, a name without `/`, a name in your own namespace (name those blocks by class) and a core block named as string although `CoreBlock` has a case for it – strings are only for core blocks newer than the framework;
- template blocks, a default block or prioritized blocks that the container does not allow, inner blocks in a template block of a block without inner blocks, a default block with inner blocks, and `directInsert` without `defaultBlock`;
- unknown or invalid attribute values of your own blocks in a `TemplateBlock`;
- `parent` naming a container of your own that does not allow the block.

`#[Block(parent: null, ancestor: null)]` takes lists of block references.

Context:

- `#[ProvidesContext]` marks parameters of a block attribute class only, not of value classes or settings groups. The value is passed as context `{namespace}/{block}/{attribute}`.
- `#[Context(Block::class, 'attribute')]` marks parameters of `render()` only. The block must be a block of the same theme or plugin, the attribute marked with `#[ProvidesContext]`, the parameter type that of the attribute (it may be nullable where the attribute is not), and the parameter needs a default or a nullable type.
- `usesContext` must not name context of your own blocks; use `#[Context]` instead.

Repeaters:

- `render()` takes either `string $content` or a `Repeater`, not both; `#[InnerBlocks]` works the same for both.
- A repeating block cannot be an `excerptWrapper`, nor have a `#[RichText(postContent: true)]` field, and its template marks items with `repeater_item()`, not `inner_blocks()` – in every render, `inner_blocks()` throws there, and `repeater_item()` throws in other blocks.
- `#[Item]` goes on a nullable parameter of `render()` whose type is a post class of the theme or plugin, `WP_Post`, `WP_Term`, `WP_Comment`, `WP_User` or a row class of the theme or plugin, and not together with `#[Context]`.
