# Editor

The editor runtime renders every block of the theme or plugin in the block editor on the server, from the same Twig template as the front end, and replaces the marked elements with editors. Markup exists only once, in Twig.

## Templates

In editor renders the template functions mark the editable elements:

- `{{ field('topline') }}`, `{{ field('caption.text') }}`, `{{ field(['buttons', loop.index0, 'label']) }}` on the element of an inline field. The path must name a `#[PlainText]`, `#[RichText]` or `#[Image]` field of the block (list items by index); anything else throws an exception, in the editor and on the front end. Text fields become `RichText` with the element's tag; an image field keeps the server markup and offers select, replace and reset – mark an element around the image (e.g. `<figure>`), not the `<img>`.
- Fields with `inspector: true` are printed without `field()` (marking them throws an exception); the template shows their value like any other attribute.
- `{{ inner_blocks() }}` on the element that contains the inner blocks (blocks whose `render()` accepts `string $content`). It may be the root element.
- `is_editor()` for a structure that does not depend on inline values: render inline fields in the editor even when they are empty, `{% if attrs.topline or is_editor() %}`. In development mode the runtime warns about fields the markup does not mark.
- The template renders exactly one root element with `block_wrapper_attributes()`; the runtime adds the editor's block props to it (no extra wrapper). The root element may itself be a text field (`field()`), as in a paragraph block; an image field cannot be the root.
- In the canvas, the fields edited there are outlined while their block is hovered or selected (dashed, in the admin color; solid while focused). Fields of inner blocks are outlined only for their own block. Outlines do not change the layout.

## Rendering

All blocks render through the batch route of the instance, `POST /wp-app-framework/v1/{slug}/render` (`edit_posts`, and `edit_post` for a block's `postId`), like WordPress' block renderer (`render_block()` with empty inner blocks and the value of a `postContent` field as inner HTML, block supports apply). Each block gets its own result, so one failing block shows an error and the others render.

Requests of the same moment are sent together, including the framework blocks inside a container. Text values edited in the canvas are blanked in the request: typing in the canvas never re-renders, every other change does – images, enums, list items and fields with `inspector: true` (applied after a short pause). Results are cached for the editor session by block name, attributes and post, so undo/redo and duplicates need no request. While new markup loads, the old markup stays (dimmed).

## Inspector

The runtime generates controls from the attribute classes: enum → select, `bool` → toggle, `int`/`float` → number, `string` → text (`#[Url]`: URL), value classes → their values, fields with `inspector: true` → text, rich text or image selection, lists → one card per item with its title (its first text value, else "Item n"), an actions menu (move up, move down, remove) and the item's controls, plus "Add item". Fields of items edited in the canvas stay in the canvas. Typed text is applied after a short pause.

The controls use stable components of `@wordpress/components` only; their styles (`assets/editor.scss`) are part of the runtime build. Content attributes (`role: content`) appear in the "Content" group, which stays available in contentOnly mode; all others in "Settings".

## Editor scripts

A block can extend the editor with a script next to its class, `src/Block/Hero/Hero.editor.js`, loaded with the runtime:

```jsx
import { registerControl, registerPanel } from '@saij/wp-app-framework';
import { PanelBody, RangeControl } from '@wordpress/components';

// Replaces the generated control of an attribute.
registerControl( 'sections/hero', 'count', ( { value, onChange } ) => (
	<RangeControl label="Count" value={ value } onChange={ onChange } min={ 1 } max={ 6 } />
) );

// Adds a panel to the "Settings" group.
registerPanel( 'sections/hero', ( { attributes, setAttributes } ) => <PanelBody title="Hero">…</PanelBody> );
```

Both warn in the console about unknown blocks and attributes. `@saij/wp-app-framework` maps to the runtime of the consumer's own framework instance.

## Build

The theme or plugin builds the runtime and its editor scripts with `@wordpress/scripts` and the framework's webpack helper; the framework ships only sources (`assets/`). In development its watcher also picks up changes of a symlinked framework checkout.

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

The output goes to `build/`:

- the runtime to `build/vendor/wp-app-framework/editor.js` with the styles of its inspector controls (`editor.css`, `editor-rtl.css`),
- the styles of the editor canvas to `build/vendor/wp-app-framework/canvas.css` (loaded on `enqueue_block_assets` in the editor only, so they reach the canvas iframe but never the front end),
- editor scripts mirrored from `src/` (`build/Block/Hero/Hero.editor.js`),
- the consumer's stylesheet `src/styles.css`, if it has one, compiled with Tailwind to `build/styles.css` (see [Styles](./styles.md)),

each with its `.asset.php`. Without a build the editor fails with an exception naming the missing file; a consumer without blocks loads no runtime and needs no build. Run `composer install` before `npm install`: the `file:` dependency points into `vendor/`.

## Instances

Everything global is derived from the slug of the instance (`get_template()`): the render route, the script handles (`{slug}-wp-app-framework`, `{slug}-{block}-editor`) and the JS global `window.wpAppFramework[slug]`, so several framework instances in different versions can run side by side.
