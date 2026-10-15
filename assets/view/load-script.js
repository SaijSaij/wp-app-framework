/**
 * Loads external scripts (from a CDN, a payment provider and the like) for
 * views and editor scripts: loadScript() from `@saij/wp-app-framework/view`,
 * `@saij/wp-app-framework` and View#loadScript().
 *
 * Each URL loads once per document, also when several views or framework
 * instances ask for it: the script element carries its state, so runtimes
 * built separately by different consumers share it.
 */

/** Attribute with the state of a script element added by loadScript(): "loading", "loaded" or "error". */
const STATE = 'data-wp-app-framework-state';

/** Attribute with the attributes requested by the first loadScript() of a URL, as JSON. */
const REQUESTED = 'data-wp-app-framework-attributes';

/**
 * @param {string}                         url        An https: URL or a protocol-relative one (//…).
 * @param {Object<string, string|boolean>} attributes Further attributes of the script element, e.g. integrity,
 *                                                    crossorigin or data-*; true for an attribute without value.
 * @param {Document}                       [doc]      The document to load into; the canvas iframe's in the editor preview.
 * @return {Promise<void>} Fulfilled once the script has loaded; rejected if it fails to load.
 */
export function loadScript( url, attributes = {}, doc = document ) {
	if (
		typeof url !== 'string' ||
		! ( url.startsWith( 'https://' ) || url.startsWith( '//' ) )
	) {
		return Promise.reject(
			new Error(
				`wp-app-framework: loadScript() loads https: and protocol-relative (//…) URLs only, got ${ JSON.stringify(
					url
				) }.`
			)
		);
	}

	const requested = JSON.stringify( attributes );
	const existing = [ ...doc.scripts ].find(
		( script ) => script.getAttribute( 'src' ) === url
	);
	if ( existing ) {
		if (
			existing.hasAttribute( REQUESTED ) &&
			existing.getAttribute( REQUESTED ) !== requested
		) {
			// eslint-disable-next-line no-console
			console.warn(
				`wp-app-framework: ${ url } is already loaded with other attributes (${ existing.getAttribute(
					REQUESTED
				) }); the attributes ${ requested } are ignored.`
			);
		}
		return settled( existing, url );
	}

	const script = doc.createElement( 'script' );
	for ( const [ name, value ] of Object.entries( attributes ) ) {
		if ( value !== false ) {
			script.setAttribute( name, value === true ? '' : String( value ) );
		}
	}
	script.setAttribute( STATE, 'loading' );
	script.setAttribute( REQUESTED, requested );
	script.async = true;
	script.src = url;
	const promise = settled( script, url );
	doc.head.append( script );

	return promise;
}

/**
 * @param {HTMLScriptElement} script
 * @param {string}            url
 * @return {Promise<void>} Settles with the load of the script element.
 */
function settled( script, url ) {
	switch ( script.getAttribute( STATE ) ) {
		// Not added by loadScript(), e.g. printed by WordPress: it is part of the page already.
		case null:
		case 'loaded':
			return Promise.resolve();
		case 'error':
			return Promise.reject( failed( url ) );
	}

	return new Promise( ( resolve, reject ) => {
		script.addEventListener( 'load', () => {
			script.setAttribute( STATE, 'loaded' );
			resolve();
		} );
		script.addEventListener( 'error', () => {
			script.setAttribute( STATE, 'error' );
			reject( failed( url ) );
		} );
	} );
}

function failed( url ) {
	return new Error( `wp-app-framework: the script ${ url } failed to load.` );
}
