import {
	BlockView,
	DocumentView,
	MARKER,
	SupportView,
	destroyView,
} from './view';

/**
 * Root elements of blocks of an instance with view scripts within an element (the element included).
 *
 * @param {string}  slug
 * @param {Element} root
 * @return {Array<{element: Element, views: Array<{view: string, value?: (string|number|boolean|null)}>}>} Marked blocks in document order.
 */
function markedBlocks( slug, root ) {
	const elements = [
		...( root.matches( `[${ MARKER }]` ) ? [ root ] : [] ),
		...root.querySelectorAll( `[${ MARKER }]` ),
	];
	const blocks = [];
	for ( const element of elements ) {
		let marker;
		try {
			marker = JSON.parse( element.getAttribute( MARKER ) );
		} catch {
			marker = null;
		}
		if ( marker?.slug === slug && Array.isArray( marker.views ) ) {
			blocks.push( { element, views: marker.views } );
		}
	}
	return blocks;
}

function reportError( message, error ) {
	// eslint-disable-next-line no-console
	console.error( `wp-app-framework: ${ message }`, error ?? '' );
}

/**
 * Mounts the views of the blocks of an instance within an element: one
 * instance per view script and root element. A script whose default export
 * does not extend the right base class, or that is not loaded, is reported on
 * the console; the other views still mount.
 *
 * @param {string}                  slug              Slug of the instance.
 * @param {Object<string, unknown>} classes           View classes of the instance by script (wpAppFramework[slug].views).
 * @param {Element}                 root
 * @param {Object}                  options
 * @param {boolean}                 options.isPreview Whether the views run in the editor preview.
 * @return {() => void} Unmounts these views again, in reverse order.
 */
export function mountWithin( slug, classes, root, { isPreview } ) {
	const mounted = [];
	for ( const { element, views } of markedBlocks( slug, root ) ) {
		for ( const entry of views ) {
			const isSupport = 'value' in entry;
			const Base = isSupport ? SupportView : BlockView;
			const ViewClass = classes?.[ entry.view ];
			if ( typeof ViewClass !== 'function' ) {
				reportError(
					`the view script ${ entry.view } of ${ slug } is not loaded.`
				);
				continue;
			}
			if ( ! ( ViewClass.prototype instanceof Base ) ) {
				reportError(
					`the default export of the view script ${ entry.view } of ${ slug } must be a class that extends ${ Base.name } from @webcodingcat/wp-app-framework/view.`
				);
				continue;
			}
			try {
				const view = new ViewClass( element, {
					slug,
					isPreview,
					value: isSupport ? entry.value : undefined,
				} );
				mounted.push( view );
				view.mount();
			} catch ( error ) {
				reportError(
					`the view script ${ entry.view } of ${ slug } failed to mount.`,
					error
				);
			}
		}
	}

	return () => {
		for ( const view of mounted.splice( 0 ).reverse() ) {
			try {
				destroyView( view );
			} catch ( error ) {
				reportError(
					`a view of ${ slug } failed to unmount (${ view.constructor.name }).`,
					error
				);
			}
		}
	};
}

/**
 * Mounts the document views of an instance (assets/*.view.js): one instance per
 * script, for the document element. A script whose default export does not
 * extend DocumentView, or that is not loaded, is reported on the console; the
 * other views still mount.
 *
 * @param {string}                  slug    Slug of the instance.
 * @param {Object<string, unknown>} classes View classes of the instance by script (wpAppFramework[slug].views).
 * @param {Array<string>}           scripts The document views to mount, by script.
 * @param {Document}                doc
 */
export function mountDocumentViews( slug, classes, scripts, doc ) {
	for ( const script of scripts ) {
		const ViewClass = classes?.[ script ];
		if ( typeof ViewClass !== 'function' ) {
			reportError(
				`the document view ${ script } of ${ slug } is not loaded.`
			);
			continue;
		}
		if ( ! ( ViewClass.prototype instanceof DocumentView ) ) {
			reportError(
				`the default export of the document view ${ script } of ${ slug } must be a class that extends DocumentView from @webcodingcat/wp-app-framework/view.`
			);
			continue;
		}
		try {
			new ViewClass( doc.documentElement, {
				slug,
				isPreview: false,
			} ).mount();
		} catch ( error ) {
			reportError(
				`the document view ${ script } of ${ slug } failed to mount.`,
				error
			);
		}
	}
}
