# Block supports

A block support adds the same setting to several blocks, for example a color scheme: each block that uses it gets an attribute, a control in the inspector and class names on its root element. The theme or plugin declares the support once and adds it to its blocks; the framework derives everything else in the build.

Block supports apply to the blocks of the same theme or plugin only (not to core blocks or blocks of other instances). The supports of WordPress itself are set with [`#[Supports]`](./blocks.md#supports). Values of the whole site rather than of a block are [settings](./settings.md).

## Declaring a support

`#[BlockSupport(attribute, label, group: SupportGroup::Styles)]` on a service class with a public method `className()`:

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

- `attribute` is the name of the block attribute in camelCase (`colorScheme`); the names WordPress reserves (`lock`, `metadata`, `className`, `anchor`, `align`, `style`) and a name another support of the theme or plugin uses are build errors.
- `label` labels the control. `group` places it in the "Styles" tab (`SupportGroup::Styles`) or in the block's "Settings" panel (`SupportGroup::Settings`).
- `className()` has exactly one parameter, the value of the attribute, with a default. Its type sets the attribute schema and the control: a backed enum (select; cases labeled with [`Labeled`](./blocks.md#editor-labels)), `bool` (toggle), `int` or `float` (number), `string` (text), each optionally nullable. Value classes and lists are not supported, and the parameter takes no attributes such as `#[Label]`.
- `className()` returns the class names for the value (several separated by spaces), or `null` or `''` for none. It returns `string` or `?string`.

The class is a [service](./services.md): it may depend on other services through its constructor, and it is created when a block with the support renders for the first time. A support no block uses is not built.

## Using a support

`#[UseSupport(Support::class, default: …)]` on a `#[Block]` class, repeatable for several supports:

```php
#[Block('sections/card', title: 'Card', category: BlockCategory::Design)]
#[UseSupport(Surface::class, default: SurfaceTone::Dark)]
final class Card
{
    public function render(CardAttributes $attrs): string { /* … */ }
}
```

- The attribute is added to the block's attribute schema with the default of `className()`'s parameter; `default` overrides it for this block and must have the type of the value (a case of the enum, a `bool`, …).
- The attribute must not have the name of a parameter of the block's attribute class; using a support twice or a class without `#[BlockSupport]` is a build error.
- The template prints the root element with [`block_wrapper_attributes()`](./blocks.md#escaping), which adds the class names of the supports to the classes of WordPress' supports. A block with supports that renders without calling it fails, so a support never stays without effect unnoticed.

Like every attribute, the value is stored in the block comment, e.g. `<!-- wp:sections/card {"surface":"light"} /-->`. The attribute object of `render()` does not contain it.

## At render time

Before the block renders, the framework sanitizes the value against its schema, converts it to its PHP type (an enum case) and calls `className()`. Each class name must consist of letters, digits, `-` and `_` (it must equal its `sanitize_html_class()`); anything else is an error. Errors follow the [render errors](./blocks.md#errors) of blocks: rethrown with `WP_DEBUG`, otherwise logged, and the block renders nothing.

Blocks rendered for an [excerpt](./blocks.md#excerpts) evaluate no supports.

## In the editor

The control appears in the group of the support, only in the default editing mode (not in contentOnly mode, patterns and template parts). Changing it renders the block again, so the class names in the canvas come from the server like on the front end.

A script next to the support class replaces the generated control in every block that uses the support, e.g. `src/Support/Surface.editor.js` next to `src/Support/Surface.php`; it is loaded with the runtime if a block uses the support:

```jsx
import { registerSupportControl } from '@saij/wp-app-framework';

registerSupportControl( 'surface', ( { value, onChange } ) => <SurfacePicker value={ value } onChange={ onChange } /> );
```

The component receives `{ value, onChange, attributes, setAttributes }`. A control registered with [`registerControl()`](./editor.md#editor-scripts) for a block wins over the support's control.

## Styling

`className()` builds class names at run time, so Tailwind does not find them in the templates. Style them with CSS of the theme, or list them with `@source inline(…)` in `src/styles.css` (see [Styles](./styles.md)).
