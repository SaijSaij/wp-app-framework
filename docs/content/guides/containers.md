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

## Rules

`#[InnerBlocks(allowed: null, template: null, templateLock: null, orientation: null, defaultBlock: null, directInsert: false, prioritized: [], appender: Appender::Default)]`:

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
