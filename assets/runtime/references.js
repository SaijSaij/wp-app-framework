/**
 * Requests of the reference controls, without React: posts and terms through
 * /wp/v2/search (published posts only), users through /wp/v2/users (without
 * the capability list_users only authors of published posts).
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
	} else {
		params.set( 'type', control.target );
		params.set( 'subtype', ( control.subtypes ?? [ 'any' ] ).join( ',' ) );
		params.set( '_fields', 'id,title' );
	}
	for ( const [ key, value ] of Object.entries( query ) ) {
		params.set( key, String( value ) );
	}
	const route = control.target === 'user' ? '/wp/v2/users' : '/wp/v2/search';
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
		result[ item.id ] =
			( control.target === 'user' ? item.name : item.title ) ||
			`#${ item.id }`;
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
