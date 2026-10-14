/**
 * The view data of an instance: values the server sets in PHP with
 * ViewData::set() and prints before the view runtime (front end) or the
 * editor runtime (editor) as wpAppFramework[slug].data.
 */

/**
 * @param {string} slug Slug of the instance.
 * @param {string} key
 * @return {null|boolean|number|string|Array|Object} The value set for the key (JSON).
 */
export function getData( slug, key ) {
	const data = window.wpAppFramework?.[ slug ]?.data;
	if ( ! data ) {
		throw new Error(
			`wp-app-framework: the view data of ${ slug } is not loaded. It is printed with the view runtime in the footer of the front end and with the editor runtime; read it in mount() or later.`
		);
	}
	if ( ! Object.hasOwn( data, key ) ) {
		const keys = Object.keys( data );
		throw new Error(
			`wp-app-framework: ${ slug } has no view data "${ key }" (set it with ViewData::set() in PHP); ${
				keys.length > 0
					? `it has ${ keys.map( ( name ) => `"${ name }"` ).join( ', ' ) }`
					: 'it has none'
			}.`
		);
	}
	return data[ key ];
}
