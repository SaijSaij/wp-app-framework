/**
 * Base classes of view scripts (front-end scripts of blocks, block supports and
 * the whole document), imported from `@webcodingcat/wp-app-framework/view`.
 *
 * The view runtime creates one instance per root element of a block and calls
 * mount(); when the block goes away (editor preview) it calls unmount() and
 * then undoes everything registered with listen() and onUnmount(). A document
 * view is created once per page, for the document element.
 */
import { getData } from './data';
import { loadScript } from './load-script';

/** Attribute of the root element of a block with view scripts, set by block_wrapper_attributes(). */
export const MARKER = 'data-wp-app-framework-views';

const MARKED = `[${ MARKER }]`;

/** Private state of each view: context and cleanups. */
const states = new WeakMap();

function state( view ) {
	const viewState = states.get( view );
	if ( ! viewState ) {
		throw new Error(
			'wp-app-framework: this view was not created by the view runtime.'
		);
	}
	return viewState;
}

/**
 * Common base of BlockView and SupportView; not exported to consumers.
 */
export class View {
	/**
	 * Created by the view runtime only; subclasses do not override the constructor, they set up in mount().
	 *
	 * @param {Element}                      element           Root element of the block.
	 * @param {Object}                       context
	 * @param {string}                       context.slug      Slug of the instance.
	 * @param {boolean}                      context.isPreview Whether the view runs in the preview of the block editor.
	 * @param {(string|number|boolean|null)} context.value     Value of the block support (SupportView).
	 */
	constructor( element, context ) {
		if ( ! element?.nodeType || ! context ) {
			throw new Error(
				`wp-app-framework: ${ new.target.name } must not override the constructor of its base class; set up the view in mount().`
			);
		}
		states.set( this, { element, context, cleanups: [] } );
	}

	/**
	 * Sets the view up: find elements, listen to events. Override it.
	 */
	mount() {}

	/**
	 * Releases what listen() and onUnmount() do not cover. Override it if needed.
	 */
	unmount() {}

	/** @return {Element} The root element of the block. */
	get element() {
		return state( this ).element;
	}

	/** @return {Document} The document of the block: in the editor preview the one of the canvas iframe. */
	get document() {
		return this.element.ownerDocument;
	}

	/** @return {Window} The window of the block: in the editor preview the one of the canvas iframe. */
	get window() {
		return this.document.defaultView;
	}

	/** @return {boolean} Whether the view runs in the preview of the block editor. */
	get isPreview() {
		return state( this ).context.isPreview;
	}

	/**
	 * The first element of the block matching a selector, or null; elements of nested blocks with view scripts excluded.
	 *
	 * @param {string} selector
	 * @return {Element|null} Element.
	 */
	find( selector ) {
		return this.findAll( selector )[ 0 ] ?? null;
	}

	/**
	 * The elements of the block matching a selector; elements of nested blocks with view scripts excluded.
	 *
	 * @param {string} selector
	 * @return {Element[]} Elements in document order.
	 */
	findAll( selector ) {
		const root = this.element;
		return [ ...root.querySelectorAll( selector ) ].filter(
			( element ) => element.closest( MARKED ) === root
		);
	}

	/**
	 * A value of the view data of the instance, set in PHP with ViewData::set().
	 *
	 * @param {string} key
	 * @return {null|boolean|number|string|Array|Object} The value (JSON); an unknown key throws.
	 */
	data( key ) {
		return getData( state( this ).context.slug, key );
	}

	/**
	 * Loads an external script into the document of the view, once per URL and document.
	 *
	 * @param {string}                         url          An https: URL or a protocol-relative one (//…).
	 * @param {Object<string, string|boolean>} [attributes] Further attributes of the script element.
	 * @return {Promise<void>} Fulfilled once the script has loaded.
	 */
	loadScript( url, attributes = {} ) {
		return loadScript( url, attributes, this.document );
	}

	/**
	 * Adds an event listener that is removed when the view unmounts.
	 *
	 * @param {EventTarget}            target
	 * @param {string}                 type
	 * @param {(event: Event) => void} handler
	 * @param {Object|boolean}         [options] Options of addEventListener().
	 */
	listen( target, type, handler, options ) {
		target.addEventListener( type, handler, options );
		this.onUnmount( () =>
			target.removeEventListener( type, handler, options )
		);
	}

	/**
	 * Runs a function when the view unmounts, after unmount(); for observers, timers and the like.
	 *
	 * @param {() => void} cleanup
	 */
	onUnmount( cleanup ) {
		state( this ).cleanups.push( cleanup );
	}
}

/**
 * Base class of the view script of a block (Block.view.js next to the block class).
 */
export class BlockView extends View {}

/**
 * Base class of the view script of a block support (Support.view.js next to the support class).
 */
export class SupportView extends View {
	/** @return {(string|number|boolean|null)} The value of the support's attribute in this block. */
	get value() {
		return state( this ).context.value;
	}
}

/**
 * Base class of a document view (assets/*.view.js): one instance per page, for the document element,
 * mounted before the views of blocks. It never unmounts on the front end.
 */
export class DocumentView extends View {
	/**
	 * The elements of the whole document matching a selector.
	 *
	 * @param {string} selector
	 * @return {Element[]} Elements in document order.
	 */
	findAll( selector ) {
		return [ ...this.element.querySelectorAll( selector ) ];
	}
}

/**
 * Unmounts a view: unmount(), then the cleanups in reverse order.
 *
 * @param {View} view
 */
export function destroyView( view ) {
	const { cleanups } = state( view );
	try {
		view.unmount();
	} finally {
		for ( const cleanup of cleanups.splice( 0 ).reverse() ) {
			cleanup();
		}
	}
}
