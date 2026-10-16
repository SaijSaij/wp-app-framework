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

The path names a `#[PlainText]`, `#[RichText]` or `#[Image]` field of the block: a name, a dotted path into a value class, or a list with indexes for list items. Anything else throws an exception when the block renders in the editor, so a typo shows up as soon as you insert the block. On the front end `field()` returns an empty string at once and checks nothing, so it costs nothing there.

Text fields become WordPress' `RichText` with the element's tag. An image field keeps the server markup and adds buttons to select, replace and reset the image. Put `field()` on an element around the image, such as a `<figure>`, not on the `<img>` itself.

Fields with `inspector: true` are edited in the inspector, not in the canvas. Print them without `field()`; marking them throws an exception. The template shows their value like any other attribute.

### New list items in the canvas

A list of buttons or badges is usually extended in the inspector, with "Add item". With `appender: true` in [`#[ListOf]`](./values.md#lists), editors can add items right where they see them: the canvas shows one more item at the end of the list, empty and faded, with the labels of its text fields as placeholders. It looks like the item it would become. A click on it – or Enter – adds the item and puts the cursor into its first text field; the inspector shows the new item expanded. As long as nobody clicks, nothing is stored. When the list has reached its `max`, the new item disappears.

To know which element is an item, the template marks the root element of each item with `list_item(path)`, written like `field()`, with the names to the list and the index of the item:

```twig
{% for button in attrs.buttons %}
    <a{{ list_item(['buttons', loop.index0]) }} href="{{ button.url }}"{{ field(['buttons', loop.index0, 'label']) }}>{{ button.label }}</a>
{% endfor %}
```

The new item is an item of the list for your template: in renders for the editor, `attrs.buttons` has one more entry, created like an item added in the inspector (the defaults of the item class, empty values otherwise), except for an [identifier](./values.md#identifiers): it is `''` until the click adds the item. So a template that hides an empty list shows it in the editor, and a template that computes something from the number of items sees one more there. On the front end, the list holds only the stored items, and `list_item()` returns an empty string.

If the template renders the new item without `list_item()`, the block shows an error in the editor instead of an item that looks real but is not stored. A path that names no list with appender throws an exception, like a wrong path of `field()`.

### Inner blocks

`inner_blocks()` goes on the element that contains the inner blocks, in blocks whose `render()` accepts `string $content`. It may be the root element.

```twig
<div{{ inner_blocks() }}>{{ content|raw }}</div>
```

Unlike `field()`, `inner_blocks()` checks the block in every render, also on the front end: in a block without `string $content` in its `render()`, or in a template that is not rendered for a block, it throws an exception. The check is a lookup in the manifest, so it costs almost nothing, and a wrong template fails wherever it renders.

A block that repeats its inner blocks per item marks the element of each item with `repeater_item( item )` instead. The runtime puts the inner blocks into the element of the item being edited and a live preview into the others; a click on a preview edits that item. See [Repeating inner blocks](./containers.md#repeating-inner-blocks).

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

All blocks of an instance render through its batch route, `POST /wp-app-framework/v1/{slug}/render`. It requires `edit_posts`, and for a block's `postId` either `edit_post` or a post that is public anyway – the previews of a [repeater](./containers.md#in-the-editor) show the published posts of others. The route renders like WordPress' own block renderer: with `render_block()`, with empty inner blocks and with the value of a `postContent` field as inner HTML, and the block supports apply. Each block gets its own result, so one failing block shows an error while the others render.

Unlike WordPress' block renderer, the route also renders with the block context of the editor: the values the block reads from enclosing blocks, such as the active tab of [tabs](./containers.md#passing-values-to-inner-blocks). The runtime sends the values the block uses, except `postId` and `postType`, which the route derives from the post. The route accepts only names the block uses. It validates values of your own blocks against the attribute that provides them; values of other blocks, such as `queryId` of a query loop, are passed as they are.

The runtime keeps requests few and the editor calm:

- **Batching.** Requests of the same moment are sent together, including the framework blocks inside a container. Opening a page with many blocks takes one request per 100 blocks.
- **No re-render while typing.** Text values edited in the canvas are blanked in the request, so typing in the canvas never re-renders the block. Every other change does: images, enums, list items and fields with `inspector: true`, applied after a short pause.
- **Cache.** Results are cached for the editor session by block name, attributes, post and context. Undo, redo and duplicated blocks need no request.
- **No jumps.** While new markup loads, the old markup stays in place, dimmed.

## Inspector

The runtime generates the block inspector from the attribute class. Each type gets a matching control:

| Value | Control |
|---|---|
| backed enum | select |
| `bool` | toggle |
| `int`, `float` | number |
| `string` | text; with `#[Url]` a URL field |
| [`#[Color]`](./values.md#colors) | the colors of the theme's palette and a color picker with transparency |
| value class | the controls of its values |
| field with `inspector: true` | text, rich text or image selection |
| `#[PlainText(inspector: true, multiline: true)]` | text area |
| [`Link`](./values.md#links) | the label and the shortened URL; a click opens the link control of the block editor (search of posts, terms and media files, "Open in new tab") |
| [reference](./values.md#references) | search; a list shows cards with move and remove |
| list | one collapsed card per item (and, with `appender: true`, a [new item in the canvas](#new-list-items-in-the-canvas)): a drag handle, the title (its first text value, the label of an enum case, else the [item label](./values.md#lists) with its number, e.g. "Badge 2"), which expands and collapses the item's controls, and an actions menu (move up, move down, remove); "Add item" adds an expanded item, with a new [identifier](./values.md#identifiers) if the items have one; the [limits](./values.md#lists) of the list disable adding and removing, and items beyond `max` are marked as unused |

Fields of list items that are edited in the canvas stay in the canvas. Typed text is applied after a short pause.

The controls use only stable components of `@wordpress/components`, plus `LinkControl` of `@wordpress/block-editor` for links and its `useSettings()` for the color palette, so they keep working across WordPress updates. The settings page and classic meta boxes do not load the block editor; there a link has a form of its own, whose search finds posts only (see [Links](./values.md#links)). Their styles (`assets/editor.scss`) are part of the runtime build.

Content attributes (`role: content`) appear in the "Content" group, which stays available in contentOnly mode. All other attributes appear in "Settings". Controls of [block supports](./supports.md) appear in the "Styles" tab (panel "Appearance") or in "Settings", depending on the support's group; like "Settings", they show only in the default editing mode.

## Editor scripts

When the generated controls are not enough, a block can extend the editor with a script next to its class, for example `src/Block/Hero/Hero.editor.js`. It is loaded together with the runtime:

```jsx
import { registerControl, registerPanel } from '@saij/wp-app-framework';
import { PanelBody, RangeControl } from '@wordpress/components';

// Replaces the generated control of an attribute.
registerControl( 'hero', 'count', ( { value, onChange } ) => (
	<RangeControl label="Count" value={ value } onChange={ onChange } min={ 1 } max={ 6 } />
) );

// Adds a panel to the default group of the inspector, after the "Settings" panel.
registerPanel( 'hero', ( { attributes, setAttributes } ) => <PanelBody title="Hero">…</PanelBody> );
```

`registerControl()` replaces the generated control of one attribute. `registerPanel()` adds a panel of your own. Like `registerToolbarButton()` below, they take the block name without namespace, as in `#[Block]`. All of them warn in the console about unknown blocks and attributes, and about names with a namespace.

Every component you register receives the same props: the block's `attributes`, `setAttributes`, its `clientId` and `isPreview` (whether the block shows its [preview](#preview)). A control receives the `value` of its attribute and `onChange` as well.

The import `@saij/wp-app-framework` is not a module that webpack bundles. The webpack helper maps it to the runtime of your theme or plugin in the editor, `window.wpAppFramework[slug].api`, which holds exactly these functions: `registerControl()`, `registerSupportControl()`, `registerPanel()`, `registerToolbarButton()`, `loadScript()` and `getData()`. The package ships TypeScript declarations for this import and for `@saij/wp-app-framework/view`, so your IDE knows the functions and their props; you write plain JavaScript all the same.

### Editor scripts without a block

Some extensions of the editor belong to no block of your own: a format for rich text, a control added to a core block with a filter, a block variation, a sidebar of `registerPlugin()`. Such a script lives in the `assets/` directory, with a name ending in `.editor.js`, and needs no PHP class: `assets/highlight.editor.js`. The framework loads it in the post and site editor after the runtime, also when the theme or plugin has no blocks:

```jsx
// assets/highlight.editor.js
import { getData } from '@saij/wp-app-framework';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { registerFormatType, toggleFormat } from '@wordpress/rich-text';

registerFormatType( 'my-theme/highlight', {
	title: getData( 'highlight' ).title,
	tagName: 'mark',
	className: 'highlight',
	edit: ( { isActive, value, onChange } ) => (
		<RichTextToolbarButton
			icon="admin-appearance"
			title="Highlight"
			isActive={ isActive }
			onClick={ () => onChange( toggleFormat( value, { type: 'my-theme/highlight' } ) ) }
		/>
	),
} );
```

`getData()` reads [data from PHP](./frontend.md#data-from-php), which the framework prints with the runtime. Editor scripts can import [shared modules](./frontend.md#shared-modules) and load [external scripts](./frontend.md#external-scripts) with `loadScript()` of `@saij/wp-app-framework`. Their handle is `{slug}-document-{path}-editor` (`assets/highlight.editor.js` → `my-theme-document-highlight-editor`).

### Toolbar buttons

`registerToolbarButton()` adds buttons of your own to a block's toolbar, for actions that belong right at the block rather than in the inspector:

```jsx
import { registerToolbarButton } from '@saij/wp-app-framework';
import { ToolbarButton } from '@wordpress/components';

registerToolbarButton( 'faq', ( { attributes, setAttributes } ) => (
	<ToolbarButton
		icon="arrow-down-alt2"
		label="Open by default"
		isPressed={ attributes.open }
		onClick={ () => setAttributes( { open: ! attributes.open } ) }
	/>
) );
```

The component receives the block's `attributes`, `setAttributes`, its `clientId` and `isPreview` (whether the block shows its [preview](#preview)), and renders one or more toolbar buttons. Several registrations for a block appear in the order they were registered. An unknown block name is reported in the console.

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
module.exports = require( '@saij/wp-app-framework/webpack' )( {
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
| `build/vendor/wp-app-framework/meta-box.js` | the forms of [meta classes](./posts.md#editing) on the classic edit screen, with their styles (`meta-box.css`, `meta-box-rtl.css`); in the block editor the runtime shows them as panels |
| `build/vendor/wp-app-framework/view.js` | the view runtime: base classes of [front-end scripts](./frontend.md), starts their views; loaded with them on the front end and with the runtime in the editor |
| `build/Block/Hero/Hero.editor.js` | editor scripts, mirrored from `src/` |
| `build/Block/Faq/Faq.view.js` | front-end scripts (view scripts), mirrored from `src/` |
| `build/Setting/Design.settings.js` | [settings scripts](./settings.md#controls-of-your-own), mirrored from `src/` |
| `build/assets/menu.view.js`, `build/assets/highlight.editor.js` | [document views](./frontend.md#scripts-for-the-whole-page) and [editor scripts without a block](#editor-scripts-without-a-block), mirrored from `assets/` |
| `build/assets/shared/dialogs.js` | [shared modules](./frontend.md#shared-modules), mirrored from `assets/shared/` |
| `build/styles.css` | the consumer's `assets/styles.css`, if it has one, compiled with Tailwind (see [Styles](./styles.md)) |

Without a build, the editor fails with an exception that names the missing file. A consumer without blocks, settings, post meta and scripts in `assets/` loads no script and needs no build.

## Instances

Everything global is derived from the slug of the instance: the render and preview routes, the script and style handles and the JavaScript global `window.wpAppFramework[slug]`. Several framework instances, even in different versions, therefore run side by side in the same editor.

| Handle | Script or style |
|---|---|
| `{slug}-wp-app-framework` | the editor runtime |
| `{slug}-wp-app-framework-view` | the view runtime |
| `{slug}-{namespace}-{block}-editor`, `{slug}-{namespace}-{block}-view` | the editor and view script of a block (`my-theme-sections-hero-view`) |
| `{slug}-support-{attribute}-editor`, `{slug}-support-{attribute}-view` | the editor and view script of a block support, the attribute in kebab case (`my-theme-support-color-scheme-view`) |
| `{slug}-document-{path}-editor`, `{slug}-document-{path}-view` | editor scripts and document views in `assets/` (`my-theme-document-nav-menu-view`) |
| `{slug}-shared-{name}` | a shared module (`my-theme-shared-ui-dialog`) |
| `{slug}-wp-app-framework-settings` | the settings page |
| `{slug}-settings-{group}` | the settings script of a settings group, the name in kebab case (`my-theme-settings-color-schemes`) |
| `{slug}-wp-app-framework-meta-box` | the forms of meta classes on the classic edit screen |
| `{slug}-styles` | the compiled `assets/styles.css` |

Use a handle as a dependency of a script of your own, for example. The other parts of `window.wpAppFramework[slug]` besides `api`, `view` and `settingsApi` are internal.
