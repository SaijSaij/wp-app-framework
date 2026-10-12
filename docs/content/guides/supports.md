# Block supports

Some settings belong to many blocks at once. A color scheme is the typical example: a card, a section and a teaser should all offer the same choice, look the same in the inspector and produce the same class names. Copying an enum into every attribute class and a class into every template would work, but every new block would have to repeat it, and nothing would keep the copies in line.

A *block support* declares such a setting once. Every block that uses it gets an attribute, a control in the inspector and class names on its root element; the framework derives all of that in the build. WordPress uses the same idea for its own supports such as alignment, which you switch on with [`#[Supports]`](./blocks.md#supports).

Block supports apply only to the blocks of the same theme or plugin, not to core blocks or blocks of other instances. Values for the whole site rather than for a single block are [settings](./settings.md).

## Declaring a support

A support is a service class with `#[BlockSupport]` and a public method `className()`. The method receives the value of the attribute and returns the class names for it:

```php
use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('surface', label: 'Surface')]
final class Surface
{
    public function className(SurfaceTone $value = SurfaceTone::Light): ?string
    {
        return 'surface-' . $value->value;
    }
}
```

The signature of `className()` describes the whole support. Its parameter type sets the attribute's schema and the control in the inspector, and its default is the attribute's default. Here `SurfaceTone` is a backed enum, so the inspector shows a select, and blocks start with `SurfaceTone::Light`.

`#[BlockSupport(attribute, label, group: SupportGroup::Styles)]` takes three arguments:

- **`attribute`** is the name of the block attribute, in camelCase (`colorScheme`). The names WordPress reserves (`lock`, `metadata`, `className`, `anchor`, `align`, `style`) and a name that another support of the same theme or plugin uses are build errors.
- **`label`** labels the control.
- **`group`** places the control in the "Styles" tab (`SupportGroup::Styles`) or in the block's "Settings" panel (`SupportGroup::Settings`).

`className()` has exactly one parameter, with a default. Its type is one of these, each optionally nullable:

| Type | Control |
|---|---|
| backed enum | select; label the cases with [`Labeled`](./blocks.md#editor-labels) |
| `bool` | toggle |
| `int`, `float` | number |
| `string` | text |

Value classes and lists are not supported, and the parameter takes no attributes such as `#[Label]`. The method returns `string` or `?string`: the class names for the value, several separated by spaces, or `null` or `''` for none.

The class is a [service](./services.md). It can depend on other services through its constructor – for example to read the available color schemes from the settings – and it is created when a block with the support renders for the first time. A support that no block uses is not built.

## Using a support

A block adds a support with `#[UseSupport]`. It is repeatable, one per support:

```php
#[Block('sections/card', title: 'Card', category: BlockCategory::Design)]
#[UseSupport(Surface::class, default: SurfaceTone::Dark)]
final class Card
{
    public function render(CardAttributes $attrs): string { /* … */ }
}
```

The block now has the attribute `surface`. Its default comes from the parameter of `className()`; `default` overrides it for this block and must have the type of the value – a case of the enum, a `bool`, and so on.

The template doesn't need to know about the support. It prints the root element with [`block_wrapper_attributes()`](./blocks.md#escaping), as every block template does, and that adds the class names of all supports to the classes of WordPress' supports:

```twig
<article {{ block_wrapper_attributes({class: 'card'}) }}>…</article>
```

A block with supports that renders without calling `block_wrapper_attributes()` fails. That way a support never stays without effect unnoticed.

Like every attribute, the value is stored in the block comment, e.g. `<!-- wp:sections/card {"surface":"light"} /-->`. The attribute object passed to `render()` does not contain it; the support handles it.

The following are build errors: an attribute of a support with the name of a parameter of the block's attribute class, the same support used twice, and a class without `#[BlockSupport]` in `#[UseSupport]`.

## At render time

Before the block renders, the framework sanitizes the value against its schema, converts it to its PHP type (an enum case, for example) and calls `className()`.

Each returned class name must consist of letters, digits, `-` and `_`, so it must equal its `sanitize_html_class()`. Anything else is an error, because a class name built from user data could otherwise break the markup. Errors are handled like the [render errors](./blocks.md#errors) of blocks: rethrown with `WP_DEBUG`, otherwise logged, and the block renders nothing.

Blocks rendered for an [excerpt](./blocks.md#excerpts) evaluate no supports.

## In the editor

The control appears in the support's group, only in the default editing mode. In contentOnly mode – in patterns and template parts – it is hidden, as WordPress hides its own design settings there. Changing the value renders the block again, so the class names in the canvas come from the server, just like on the front end.

A script next to the support class can replace the generated control in every block that uses the support. For `src/Support/Surface.php` that is `src/Support/Surface.editor.js`; it is loaded with the runtime if a block uses the support:

```jsx
import { registerSupportControl } from '@saij/wp-app-framework';

registerSupportControl( 'surface', ( { value, onChange } ) => <SurfacePicker value={ value } onChange={ onChange } /> );
```

The component receives `{ value, onChange, attributes, setAttributes }`. A control that a block registers with [`registerControl()`](./editor.md#editor-scripts) for the same attribute wins over the support's control.

## On the front end

When a support needs JavaScript on the page – an element that fades in while it scrolls into view, for example – put a view script next to its class: `src/Support/Reveal.view.js`. It runs in every block that uses the support and receives the support's value. See [Front-end scripts](./frontend.md#block-supports).

## Styling

`className()` builds class names at run time, so Tailwind does not find them in the templates. Style them with CSS of the theme, or list them with `@source inline(…)` in `src/styles.css` (see [Styles](./styles.md)).
