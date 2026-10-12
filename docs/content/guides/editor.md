# Editor

Content editors should see a block in the editor exactly as it appears on the page, and type straight into it. Core blocks achieve that with an `edit` component in JavaScript, which duplicates the block's markup. A framework block has no such duplicate. Its markup exists only once, in the Twig template.

This page explains how the editor runtime makes that work, how you mark editable elements in a template, what the generated inspector offers, and how to extend and build the editor scripts.

## How it works

The framework ships an *editor runtime*, a script that your theme or plugin builds and the block editor loads. For each framework block in the editor it does three things:

1. It asks the server to render the block, from the same Twig template the front end uses.
2. It parses the returned HTML and finds the elements the template marked as editable.
3. It replaces those elements with editors – rich text, an image picker, inner blocks – and keeps the rest of the markup as it is.

The result looks like the front end because it is the front end's markup. When an editor changes a value in the inspector, the runtime renders the block again; when they type into a text field in the canvas, it doesn't, because the text is edited in place.

## Marking editable elements

When the editor renders a block, the template functions `field()` and `inner_blocks()` mark the editable elements. On the front end they print nothing.

### Fields

`field(path)` goes on the element that shows a field:

```twig
<p{{ field('topline') }}>{{ attrs.topline }}</p>
<figcaption{{ field('caption.text') }}>{{ attrs.caption.text }}</figcaption>
{% for button in attrs.buttons %}
    <a href="{{ button.url }}"{{ field(['buttons', loop.index0, 'label']) }}>{{ button.label }}</a>
{% endfor %}
```

The path names a `#[PlainText]`, `#[RichText]` or `#[Image]` field of the block: a name, a dotted path into a value class, or a list with indexes for list items. Anything else throws an exception, in the editor and on the front end, so a typo never goes unnoticed.

Text fields become WordPress' `RichText` with the element's tag. An image field keeps the server markup and adds buttons to select, replace and reset the image. Put `field()` on an element around the image, such as a `<figure>`, not on the `<img>` itself.

Fields with `inspector: true` are edited in the inspector, not in the canvas. Print them without `field()`; marking them throws an exception. The template shows their value like any other attribute.

### Inner blocks

`inner_blocks()` goes on the element that contains the inner blocks, in blocks whose `render()` accepts `string $content`. It may be the root element.

```twig
<div{{ inner_blocks() }}>{{ content|raw }}</div>
```

### The root element

The template renders exactly one root element with `block_wrapper_attributes()`. The runtime adds the editor's block props to it, without an extra wrapper, so the canvas has the same element structure as the front end.

The root element may itself be a text field, as in a paragraph block: `<p {{ block_wrapper_attributes() }}{{ field('text') }}>`. An image field cannot be the root.

### Empty fields and is_editor()

On the front end, a template usually leaves out an empty field. In the editor it must render it, or content editors could never fill it in. `is_editor()` covers both:

```twig
{% if attrs.topline or is_editor() %}
    <p{{ field('topline') }}>{{ attrs.topline }}</p>
{% endif %}
```

Use it for structure that does not depend on inline values. In development mode the runtime warns about fields that the markup does not mark.

### Outlines

In the canvas, the fields edited there are outlined while their block is hovered or selected: dashed, in the admin color, and solid while a field has the focus. Fields of inner blocks are outlined only for their own block. The outlines do not change the layout.

## Rendering

All blocks of an instance render through its batch route, `POST /wp-app-framework/v1/{slug}/render`. It requires `edit_posts`, and `edit_post` for a block's `postId`. The route renders like WordPress' own block renderer: with `render_block()`, with empty inner blocks and with the value of a `postContent` field as inner HTML, and the block supports apply. Each block gets its own result, so one failing block shows an error while the others render.

The runtime keeps requests few and the editor calm:

- **Batching.** Requests of the same moment are sent together, including the framework blocks inside a container. Opening a page with many blocks takes one request per 100 blocks.
- **No re-render while typing.** Text values edited in the canvas are blanked in the request, so typing in the canvas never re-renders the block. Every other change does: images, enums, list items and fields with `inspector: true`, applied after a short pause.
- **Cache.** Results are cached for the editor session by block name, attributes and post. Undo, redo and duplicated blocks need no request.
- **No jumps.** While new markup loads, the old markup stays in place, dimmed.

## Inspector

The runtime generates the block inspector from the attribute class. Each type gets a matching control:

| Value | Control |
|---|---|
| backed enum | select |
| `bool` | toggle |
| `int`, `float` | number |
| `string` | text; with `#[Url]` a URL field |
| value class | the controls of its values |
| field with `inspector: true` | text, rich text or image selection |
| [reference](./values.md#references) | search; a list shows cards with move and remove |
| list | one card per item with its title (its first text value, else "Item n"), an actions menu (move up, move down, remove) and the item's controls, plus "Add item" |

Fields of list items that are edited in the canvas stay in the canvas. Typed text is applied after a short pause.

The controls use only stable components of `@wordpress/components`, so they keep working across WordPress updates. Their styles (`assets/editor.scss`) are part of the runtime build.

Content attributes (`role: content`) appear in the "Content" group, which stays available in contentOnly mode. All other attributes appear in "Settings". Controls of [block supports](./supports.md) appear in the "Styles" tab (panel "Appearance") or in "Settings", depending on the support's group; like "Settings", they show only in the default editing mode.

## Editor scripts

When the generated controls are not enough, a block can extend the editor with a script next to its class, for example `src/Block/Hero/Hero.editor.js`. It is loaded together with the runtime:

```jsx
import { registerControl, registerPanel } from '@saij/wp-app-framework';
import { PanelBody, RangeControl } from '@wordpress/components';

// Replaces the generated control of an attribute.
registerControl( 'sections/hero', 'count', ( { value, onChange } ) => (
	<RangeControl label="Count" value={ value } onChange={ onChange } min={ 1 } max={ 6 } />
) );

// Adds a panel to the default group of the inspector, after the "Settings" panel.
registerPanel( 'sections/hero', ( { attributes, setAttributes } ) => <PanelBody title="Hero">…</PanelBody> );
```

`registerControl()` replaces the generated control of one attribute. `registerPanel()` adds a panel of your own. `registerToolbarButton()` adds buttons to the block's toolbar (see [Front-end scripts](./frontend.md#toolbar-buttons)). All of them warn in the console about unknown blocks and attributes.

Controls of block supports are replaced with `registerSupportControl()`, in a script next to the support class (see [Block supports](./supports.md#in-the-editor)).

`@saij/wp-app-framework` always maps to the runtime of your own framework instance, never to that of another theme or plugin.

## Preview

A block with a [front-end script](./frontend.md) gets a **Preview** button in its toolbar. It shows the block's front-end markup instead of the editing view and runs its view scripts on it, so content editors can try the block's behavior; editing pauses until the preview ends. See [Preview in the editor](./frontend.md#preview-in-the-editor).

## Build

The framework ships only the sources of the runtime, in `assets/`. The theme or plugin builds them, together with its own editor scripts, with `@wordpress/scripts` and the framework's webpack helper. In development, its watcher also picks up changes of a symlinked framework checkout.

```json
{
	"scripts": { "build": "wp-scripts build", "start": "wp-scripts start" },
	"devDependencies": {
		"@saij/wp-app-framework": "file:vendor/saij/wp-app-framework",
		"@wordpress/scripts": "^36.1.0"
	}
}
```

```js
// webpack.config.js of the theme or plugin
module.exports = require( './vendor/saij/wp-app-framework/webpack.config.js' )( {
	slug: 'theme-dev-test', // get_template() of a theme, the directory name of a plugin
	root: __dirname,
} );
```

Run `composer install` before `npm install`: the `file:` dependency points into `vendor/`.

The build writes to `build/`, each file with its `.asset.php`:

| File | Content |
|---|---|
| `build/vendor/wp-app-framework/editor.js` | the runtime, with the styles of its inspector controls (`editor.css`, `editor-rtl.css`) |
| `build/vendor/wp-app-framework/canvas.css` | the styles of the editor canvas, loaded on `enqueue_block_assets` in the editor only: they reach the canvas iframe, never the front end |
| `build/vendor/wp-app-framework/settings.js` | the script of the [settings page](./settings.md#settings-page) with its styles (`settings.css`, `settings-rtl.css`), loaded on that page only |
| `build/vendor/wp-app-framework/view.js` | the view runtime: base classes of [front-end scripts](./frontend.md), starts their views; loaded with them on the front end and with the runtime in the editor |
| `build/Block/Hero/Hero.editor.js` | editor scripts, mirrored from `src/` |
| `build/Block/Faq/Faq.view.js` | front-end scripts (view scripts), mirrored from `src/` |
| `build/styles.css` | the consumer's `src/styles.css`, if it has one, compiled with Tailwind (see [Styles](./styles.md)) |

Without a build, the editor fails with an exception that names the missing file. A consumer without blocks and settings loads no script and needs no build.

## Instances

Everything global is derived from the slug of the instance: the render and preview routes, the script handles (`{slug}-wp-app-framework`, `{slug}-{block}-editor`, `{slug}-wp-app-framework-view`, `{slug}-{block}-view`, `{slug}-wp-app-framework-settings`) and the JavaScript global `window.wpAppFramework[slug]`. Several framework instances, even in different versions, therefore run side by side in the same editor.
