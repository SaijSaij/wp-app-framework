/**
 * Requests of the reference controls, without React: posts and terms through
 * /wp/v2/search (published posts only), users through /wp/v2/users (without
 * the capability list_users only authors of published posts). References to a
 * post class (`restBase`) search the collection of its post type, which also
 * works for post types that are not public: /wp/v2/search covers public ones only.
 */

/** Results per search. */
export const PER_PAGE = 20;

/**
 * @param {Object} control Control description (`target`, `subtypes`).
 * @param {Object} query   Search (`search`) or IDs (`include`).
 * @return {string} REST path with query arguments.
 */
function path( control, query ) {
	const params = new URLSearchParams();
	if ( control.target === 'user' ) {
		params.set( '_fields', 'id,name' );
	} else if ( control.restBase ) {
		params.set( '_fields', 'id,title' );
	} else {
		params.set( 'type', control.target );
		params.set( 'subtype', ( control.subtypes ?? [ 'any' ] ).join( ',' ) );
		params.set( '_fields', 'id,title' );
	}
	for ( const [ key, value ] of Object.entries( query ) ) {
		params.set( key, String( value ) );
	}
	let route = '/wp/v2/search';
	if ( control.target === 'user' ) {
		route = '/wp/v2/users';
	} else if ( control.restBase ) {
		route = `/wp/v2/${ control.restBase }`;
	}
	return `${ route }?${ params.toString() }`;
}

/**
 * @param {Object} control
 * @param {string} search  Search text.
 * @return {string} Path of a search.
 */
export function searchPath( control, search ) {
	return path( control, { search, per_page: PER_PAGE } );
}

/**
 * @param {Object}        control
 * @param {Array<number>} ids     IDs whose titles are needed.
 * @return {string} Path that returns these objects.
 */
export function includePath( control, ids ) {
	return path( control, { include: ids.join( ',' ), per_page: ids.length } );
}

/**
 * Titles by ID from the results of a request.
 *
 * @param {Object}        control
 * @param {Array<Object>} items   Results of /wp/v2/search or /wp/v2/users.
 * @return {Object<number, string>} Titles.
 */
export function titles( control, items ) {
	const result = {};
	for ( const item of items ) {
		// Collections return the title as { rendered }, /wp/v2/search as a string.
		const title =
			control.target === 'user'
				? item.name
				: ( item.title?.rendered ?? item.title );
		result[ item.id ] = title || `#${ item.id }`;
	}
	return result;
}

/**
 * Moves an entry of a list.
 *
 * @param {Array<number>} ids
 * @param {number}        from
 * @param {number}        to
 * @return {Array<number>} The new list.
 */
export function move( ids, from, to ) {
	const next = [ ...ids ];
	next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
	return next;
}
