# Front-end scripts

Some blocks need JavaScript on the page: an FAQ that opens its answers, a slider, a counter. The framework gives every such script the same shape. It is a class next to the block, which the framework loads only on pages that show the block, starts once per block and stops again when needed. In the editor, content editors can switch a block to a preview and try its behavior right there.

Other scripts belong to no block: a mobile menu, a cookie notice, smooth scrolling. They run on every page as *document views*, in the same shape.

This page explains how a front-end script – a *view script* – is written, when it runs and how the preview in the editor works. It then covers scripts for the whole page, data from PHP, external scripts and modules shared between scripts.

## How it works

A view script lives next to its block class, like an editor script: `src/Block/Faq/Faq.view.js` next to `src/Block/Faq/Faq.php`. Your build compiles it with the [webpack helper](./editor.md#build), together with the framework's *view runtime*, a small script that provides the base classes and starts the views.

When WordPress renders a block that has a view script, the framework does two things. It adds a marker attribute to the block's root element, through `block_wrapper_attributes()`. And it enqueues the view runtime and the block's script for this page. A page without the block loads neither of them.

Once the page has been parsed, the view runtime finds the marked root elements and creates one instance of the script's class for each, then calls its `mount()` method. Two FAQ blocks on a page are two independent instances, each working only inside its own block.

A block read from the [cache](./cache.md) enqueues its view scripts as well. The scripts load deferred, at the end of the page, so they never block rendering. They are ordinary scripts and may import WordPress' packages such as `@wordpress/dom-ready` or `@wordpress/api-fetch`.

## An FAQ, step by step

The block renders a question as a button and the answer below it. The answer starts hidden on the front end. In the editor it stays visible, so it can be edited inline:

```twig
{# src/Block/Faq/Faq.twig #}
<div {{ block_wrapper_attributes({class: 'faq'}) }}>
    <button type="button" class="faq__question" aria-expanded="false"{{ field('question') }}>{{ attrs.question }}</button>
    <div class="faq__answer"{{ field('answer') }}{% if not is_editor() %} hidden{% endif %}>{{ attrs.answer|raw }}</div>
</div>
```

The root element must be printed with `block_wrapper_attributes()`: that is where the marker goes. Without it, rendering the block fails with an error.

Next to the block class goes the view script. It exports a class that extends `BlockView`:

```js
// src/Block/Faq/Faq.view.js
import { BlockView } from '@saij/wp-app-framework/view';

export default class Faq extends BlockView {
	mount() {
		this.question = this.find( '.faq__question' );
		this.answer = this.find( '.faq__answer' );
		this.listen( this.question, 'click', () => this.toggle() );
	}

	toggle() {
		const open = this.answer.hidden;
		this.answer.hidden = ! open;
		this.question.setAttribute( 'aria-expanded', String( open ) );
	}
}
```

`mount()` sets the view up. `find()` looks for an element inside the block, so a second FAQ on the page is never touched. `listen()` adds an event listener the framework removes again when the view stops. You never call `document.querySelectorAll()` yourself and never clean up listeners by hand.

That is all. Run `npm run build`, add the block to a page, and the answer opens and closes.

## Life cycle

The view runtime creates the instance and calls `mount()`. On the front end a view lives as long as the page. In the editor preview, it stops when the preview ends, when the block renders again after a change, and when the block is removed. Stopping calls `unmount()` and then undoes everything registered with `listen()` and `onUnmount()`, last first.

Most views need only `mount()`. For resources `listen()` does not cover, such as an observer or a timer, register the cleanup right where you create the resource:

```js
mount() {
	const observer = new this.window.IntersectionObserver( ( entries ) => { … } );
	observer.observe( this.element );
	this.onUnmount( () => observer.disconnect() );
}
```

Override `unmount()` only for work that must happen before those cleanups. Do not override the constructor: it belongs to the framework, and a view that overrides it fails to mount.

## Block supports

A [block support](./supports.md) can have a view script too, next to its class: `src/Support/Reveal.view.js`. It runs in every block that uses the support, as a separate instance on the same root element as the block's own view. Its class extends `SupportView`, which adds `this.value`, the support's value in this block:

```js
// src/Support/Reveal.view.js
import { SupportView } from '@saij/wp-app-framework/view';

export default class Reveal extends SupportView {
	mount() {
		if ( this.value ) {
			this.element.dataset.revealed = 'true';
			this.onUnmount( () => delete this.element.dataset.revealed );
		}
	}
}
```

A page loads a support's script once, however many blocks use it. A block that uses a support with a view script needs `block_wrapper_attributes()` like a block with its own view script.

## Scripts for the whole page

A script that belongs to no block is a *document view*. It lives in the `assets/` directory of the theme or plugin (see [Concepts](../concepts.md#src-and-assets)), with a name ending in `.view.js`: `assets/menu.view.js`, or deeper, `assets/nav/sticky.view.js`. It needs no PHP class. The framework loads it on every page of the front end, deferred in the footer like the other view scripts, and never in the editor.

Its class extends `DocumentView`. The view runtime creates one instance per page, for the document element, and mounts it before the views of the blocks:

```js
// assets/menu.view.js
import { DocumentView } from '@saij/wp-app-framework/view';

export default class Menu extends DocumentView {
	mount() {
		const toggle = this.find( '.menu-toggle' );
		if ( ! toggle ) {
			return;
		}
		this.listen( toggle, 'click', () =>
			this.element.classList.toggle( 'menu-open' )
		);
	}
}
```

`find()` and `findAll()` search the whole document here. A document view has no condition in PHP: when a page does not need it, the script decides that itself and returns early, as above. Behavior that belongs to one block of your own is a view script of that block instead, which loads only with the block.

## Data from PHP

Scripts often need values that only the server knows: a setting, a key for a map service, a URL. Set them on the service `ViewData`, which you inject anywhere – in a hook, in a block's `render()`:

```php
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\View\ViewData;

final readonly class MapData
{
    public function __construct(private MapSettings $settings) {}

    #[Hook('wp')]
    public function provide(ViewData $viewData): void
    {
        $viewData->set('map', ['zoom' => $this->settings->zoom]);
    }
}
```

Every view reads a value with `this.data( key )`. Editor scripts and shared modules import `getData` instead:

```js
mount() {
	const { zoom } = this.data( 'map' );
}
```

The values belong to the instance: all views and editor scripts of the theme or plugin see them, other instances do not. On the front end the framework prints them with the view runtime in the footer, so values set while blocks render are included. A [cached block](./cache.md) stores the values its `render()` set and sets them again when it comes from the cache. In the editor the framework prints the values at the end of `enqueue_block_editor_assets`, so set them on that action or earlier.

Every visitor can read the values in the page source; never pass secrets. Values that differ per user, such as a nonce, do not suit pages a page cache stores for everyone. Values about a single block belong into its markup, as `data-` attributes the block's view reads.

## External scripts

Some libraries come only from their provider's server, such as a payment form or a map service. A view loads such a script itself, when it mounts:

```js
async mount() {
	await this.loadScript( 'https://js.example.com/v3/', { crossorigin: 'anonymous' } );
	this.map = new this.window.ExampleMaps.Map( this.find( '.map' ) );
}
```

The script loads only on pages where the view mounts, so a block's library loads only with the block. Each URL loads once per document, also when several views or several themes and plugins ask for it; the promise of every call settles when the script has loaded. Outside a view, import `loadScript` from `@saij/wp-app-framework/view`, or from `@saij/wp-app-framework` in editor scripts.

The download starts only when the view mounts. Whether a visitor has agreed to a third-party script is up to your code.

## Shared modules

Each view script is built as a bundle of its own. A module that two scripts import is therefore two copies, each with its own state. A module that several scripts share – a dialog manager, a store – goes into `assets/shared/` instead: `assets/shared/dialogs.js`. Scripts import it as `#shared/dialogs`:

```js
// assets/shared/dialogs.js
const open = new Set();
export function register( dialog ) {
	open.add( dialog );
}
```

```js
// src/Block/Modal/Modal.view.js
import { BlockView } from '@saij/wp-app-framework/view';
import { register } from '#shared/dialogs';
```

The build turns every module in `assets/shared/` into a script of its own. A script that imports it depends on it, so WordPress loads the module once, before the scripts that use it, and all of them share the same instance. View scripts, document views, editor scripts and other shared modules can import shared modules.

The prefix `#` makes the import a subpath import of Node. Map it in the `package.json` of the theme or plugin, so that your IDE finds the files; the build fails without it:

```json
{
	"imports": { "#shared/*": "./assets/shared/*.js" }
}
```

## Preview in the editor

In the editor, a block shows its editing view: fields are edited inline, and empty fields stay visible. View scripts do not run there, because clicks belong to editing. A block with a view script – its own or one of its supports – gets a **Preview** button in its toolbar instead.

The preview shows the block as it appears on the front end. The editor asks the server to render the block, with its inner blocks, exactly as on a page, and shows that markup in place of the editing view. Then it mounts the block's views on it, with `this.isPreview` set to `true`. Content editors can open the FAQ, step through a slider or try any other state.

Editing pauses while the preview is on; the inspector stays available. A changed attribute renders the preview again: the old views unmount, the new markup appears and new views mount. Pressing **Preview** again, or removing the block, unmounts the views and returns to editing. Buttons of your own in a block's toolbar are an editor feature, independent of view scripts; see [Toolbar buttons](./editor.md#toolbar-buttons).

Two things differ from the front end, and the view must allow for them:

- **Another document.** The editor runs view scripts in its main window, but the block lives in the canvas iframe. Use `this.document` and `this.window`, which belong to the block's element, instead of the globals `document` and `window`.
- **The root element belongs to the editor.** In the preview, the editor manages the class and style of the root element and resets them, for example when the block is selected. Keep the state of a view in the elements inside the block or in data attributes, not in the root element's classes.

## Rules

**Files**

| Script | Location | Default export |
|---|---|---|
| view script of a block | next to the block class: `Faq.php` → `Faq.view.js` | a class extending `BlockView` |
| view script of a block support | next to the support class: `Reveal.php` → `Reveal.view.js` | a class extending `SupportView` |
| document view | in `assets/`: `assets/menu.view.js` | a class extending `DocumentView` |
| shared module | in `assets/shared/`: `assets/shared/dialogs.js`, imported as `#shared/dialogs` | any exports |

A view script outside the directory your build compiles (`src/`) is a build error, and so is one in `src/` without a block or block support class next to it. A view script that imports `@saij/wp-app-framework` – the editor runtime, which a page does not load – fails the build of the webpack helper, also when it imports it through shared modules; view scripts import `@saij/wp-app-framework/view`. An import of a shared module that does not exist, shared modules that import each other in a cycle, and shared modules without the `imports` entry in `package.json` fail the build as well. Two scripts in `assets/` whose names give the same script handle are a build error.

**`BlockView`, `SupportView` and `DocumentView`**

| Member | Meaning |
|---|---|
| `mount()` | Override: set the view up. |
| `unmount()` | Override if needed: runs before the cleanups of `listen()` and `onUnmount()`. |
| `element` | The root element of the block. |
| `document`, `window` | The document and window of the element; in the editor preview those of the canvas iframe. |
| `isPreview` | `true` in the editor preview. |
| `find( selector )` | The first matching element in the block, or `null`. |
| `findAll( selector )` | All matching elements in the block, in document order. |
| `listen( target, type, handler, options )` | Adds an event listener, removed when the view unmounts. |
| `onUnmount( callback )` | Runs a function when the view unmounts. |
| `data( key )` | A value set with `ViewData::set()`; an unknown key throws an error that names the known ones. |
| `loadScript( url, attributes )` | Loads an external script into the view's document; see below. |
| `value` | `SupportView` only: the value of the support in this block. |

In a block's view, `find()` and `findAll()` skip the elements of nested blocks that have view scripts of their own; those blocks get their own views. In a `DocumentView`, `element` is the document element, `find()` and `findAll()` search the whole document and `isPreview` is always `false`.

**`ViewData`**

| Method | Meaning |
|---|---|
| `set( string $key, mixed $value )` | Sets a value; a later call with the same key replaces it. The key consists of letters, digits, `-` and `_` and starts with a letter. The value is `null`, a scalar or an array of these; objects and the floats `NAN` and `INF` throw. |
| `has( string $key )`, `get( string $key )` | Read a value in PHP; `get()` throws for an unknown key. |

A value set after the values were printed – after the footer scripts of the front end, after `enqueue_block_editor_assets` in the editor – throws with `WP_DEBUG` and is logged otherwise. The values are printed only where a runtime of the instance loads: on the front end with the view runtime, in the editor with the editor runtime. In JavaScript, `getData( key )` of `@saij/wp-app-framework/view` and `@saij/wp-app-framework` reads them like `this.data( key )`.

**`loadScript( url, attributes )`**

- `url` is an `https:` URL or a protocol-relative one (`//…`); any other URL rejects the promise.
- `attributes` are further attributes of the script element, such as `integrity`, `crossorigin` or `data-…`; `true` adds an attribute without value, `false` leaves it out.
- The promise is fulfilled when the script has loaded and rejected when it fails to load, also for later calls with the same URL.
- A URL loads once per document. A later call with other attributes warns on the console and keeps the first ones. A script element with the URL that the page already has, for example one WordPress printed, counts as loaded.

**Loading**

- A block with a view script, or with a block support that has one, must print its root element with `block_wrapper_attributes()`; otherwise rendering it fails with an error.
- The view runtime (`{slug}-wp-app-framework-view`) and the scripts (`{slug}-{namespace}-{name}-view`, `{slug}-support-{attribute}-view`) are enqueued while the block renders, deferred in the footer. They are not enqueued for automatic excerpts, and WordPress removes them again if the block renders nothing.
- Document views load on every page of the front end (`wp_enqueue_scripts`) with the view runtime, deferred in the footer, with the handle `{slug}-document-{path}-view` (`assets/nav/sticky.view.js` → `my-theme-document-nav-sticky-view`). They do not load in the admin, in the editor or in its preview.
- The views mount on `DOMContentLoaded`: first the document views in the order of their paths, then one instance per script and root element of the blocks – the block's view first, then the views of its supports in the order of `#[UseSupport]`. A script that is not loaded, or whose default export does not extend the right base class, is reported on the console; the other views still mount.
- A shared module has the handle `{slug}-shared-{name}` (`#shared/ui/Dialog` → `my-theme-shared-ui-dialog`) and is registered when a script that depends on it is registered; one that no script on the page uses does not load.
- In the editor, the view runtime and the view scripts of all blocks load with the editor runtime. A block with a view script gets the **Preview** toolbar button. The preview renders the block with its inner blocks through `POST /wp-app-framework/v1/{slug}/preview` (`edit_posts`, and `edit_post` for the post), as on the front end.
