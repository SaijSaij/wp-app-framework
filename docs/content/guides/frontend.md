# Front-end scripts

Some blocks need JavaScript on the page: an FAQ that opens its answers, a slider, a counter. The framework gives every such script the same shape. It is a class next to the block, which the framework loads only on pages that show the block, starts once per block and stops again when needed. In the editor, content editors can switch a block to a preview and try its behavior right there.

This page explains how a front-end script – a *view script* – is written, when it runs and how the preview in the editor works.

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

A view script outside the directory your build compiles (`src/`) is a build error. A view script that imports `@saij/wp-app-framework` – the editor runtime, which a page does not load – fails the build of the webpack helper; view scripts import `@saij/wp-app-framework/view`.

**`BlockView` and `SupportView`**

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
| `value` | `SupportView` only: the value of the support in this block. |

`find()` and `findAll()` skip the elements of nested blocks that have view scripts of their own; those blocks get their own views.

**Loading**

- A block with a view script, or with a block support that has one, must print its root element with `block_wrapper_attributes()`; otherwise rendering it fails with an error.
- The view runtime (`{slug}-wp-app-framework-view`) and the scripts (`{slug}-{namespace}-{name}-view`, `{slug}-support-{attribute}-view`) are enqueued while the block renders, deferred in the footer. They are not enqueued for automatic excerpts, and WordPress removes them again if the block renders nothing.
- The views mount on `DOMContentLoaded`, one instance per script and root element: the block's view first, then the views of its supports in the order of `#[UseSupport]`. A script that is not loaded, or whose default export does not extend the right base class, is reported on the console; the other views still mount.
- In the editor, the view runtime and the view scripts of all blocks load with the editor runtime. A block with a view script gets the **Preview** toolbar button. The preview renders the block with its inner blocks through `POST /wp-app-framework/v1/{slug}/preview` (`edit_posts`, and `edit_post` for the post), as on the front end.
