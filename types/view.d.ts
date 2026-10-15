/**
 * The front-end API of `@saij/wp-app-framework/view`, for view scripts (`*.view.js` next to a block or block support
 * class, or in `assets/` for document views).
 *
 * The import is no module of its own: the webpack helper maps it to the view runtime of your theme or plugin,
 * `wpAppFramework[slug].view`. These declarations describe that object for your IDE.
 *
 * @see ../docs/content/guides/frontend.md
 */

import type { DataValue } from './index';

export type { DataValue } from './index';

/**
 * What all views share. The view runtime creates the views; your class only extends `BlockView`, `SupportView` or
 * `DocumentView` and overrides `mount()` and, if needed, `unmount()`. Do not override the constructor.
 */
declare abstract class View {
	/** Sets the view up: find elements, listen to events. Override it. */
	mount(): void;

	/** Releases what `listen()` and `onUnmount()` do not cover. Override it if needed. */
	unmount(): void;

	/** The root element of the view: the root element of the block, or the document element for a document view. */
	get element(): Element;

	/** The document of the view: in the preview of the block editor the one of the canvas iframe. */
	get document(): Document;

	/** The window of the view: in the preview of the block editor the one of the canvas iframe. */
	get window(): Window;

	/** Whether the view runs in the preview of the block editor. */
	get isPreview(): boolean;

	/**
	 * The first element below the root element that matches a selector, without elements of nested blocks with view
	 * scripts.
	 *
	 * @param selector A CSS selector.
	 * @returns The element, or null.
	 */
	find(selector: string): Element | null;

	/**
	 * The elements below the root element that match a selector, without elements of nested blocks with view
	 * scripts.
	 *
	 * @param selector A CSS selector.
	 * @returns The elements in document order.
	 */
	findAll(selector: string): Element[];

	/**
	 * A value of the view data of your theme or plugin, set in PHP with `ViewData::set()`.
	 *
	 * @param key The key of the value.
	 * @returns The value; an unknown key throws an error that names the known keys.
	 */
	data(key: string): DataValue;

	/**
	 * Loads an external script into the document of the view, once per URL and document.
	 *
	 * @param url        An `https:` URL or a protocol-relative one (`//…`).
	 * @param attributes Further attributes of the script element; `true` for an attribute without value.
	 * @returns Fulfilled once the script has loaded; rejected if it fails to load.
	 */
	loadScript(url: string, attributes?: Record<string, string | boolean>): Promise<void>;

	/**
	 * Adds an event listener that is removed when the view unmounts.
	 *
	 * @param target  The element, document or window to listen on.
	 * @param type    The event type, e.g. `click`.
	 * @param handler The listener.
	 * @param options The options of `addEventListener()`.
	 */
	listen(target: EventTarget, type: string, handler: (event: Event) => void, options?: AddEventListenerOptions | boolean): void;

	/**
	 * Runs a function when the view unmounts, after `unmount()`: for observers, timers and the like.
	 *
	 * @param cleanup The function to run.
	 */
	onUnmount(cleanup: () => void): void;
}

/**
 * The base class of the view script of a block (`Block.view.js` next to the block class). The runtime creates one
 * instance per block on the page.
 */
export abstract class BlockView extends View {}

/**
 * The base class of the view script of a block support (`Support.view.js` next to the support class). The runtime
 * creates one instance per block that uses the support.
 */
export abstract class SupportView extends View {
	/** The value of the attribute of the support in this block. */
	get value(): string | number | boolean | null;
}

/**
 * The base class of a document view (`assets/**\/*.view.js`): one instance per page, for the document element,
 * mounted before the views of blocks.
 */
export abstract class DocumentView extends View {
	/**
	 * The elements of the whole document that match a selector.
	 *
	 * @param selector A CSS selector.
	 * @returns The elements in document order.
	 */
	findAll(selector: string): Element[];
}

/**
 * Loads an external script, once per URL and document.
 *
 * @param url        An `https:` URL or a protocol-relative one (`//…`).
 * @param attributes Further attributes of the script element; `true` for an attribute without value.
 * @returns Fulfilled once the script has loaded; rejected if it fails to load.
 */
export function loadScript(url: string, attributes?: Record<string, string | boolean>): Promise<void>;

/**
 * A value of the view data of your theme or plugin, set in PHP with `ViewData::set()`.
 *
 * @param key The key of the value.
 * @returns The value; an unknown key throws an error that names the known keys.
 */
export function getData(key: string): DataValue;
