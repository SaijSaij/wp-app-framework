/**
 * Paths into attribute values: "headline", "caption.text", "buttons.0.label".
 * Field patterns of the manifest use "*" for list items ("buttons.*.label").
 */

/**
 * @param {string} path
 * @return {string[]} Segments.
 */
export function splitPath( path ) {
	return path.split( '.' );
}

/**
 * @param {Object} attributes
 * @param {string} path
 * @return {*} The value at the path, undefined if missing.
 */
export function getPath( attributes, path ) {
	let value = attributes;
	for ( const segment of splitPath( path ) ) {
		if ( value === null || typeof value !== 'object' ) {
			return undefined;
		}
		value = value[ segment ];
	}
	return value;
}

/**
 * Immutably sets the value at a path; lists stay lists.
 *
 * @param {Object} target
 * @param {string} path
 * @param {*}      value
 * @return {Object} A copy of the target with the value set.
 */
export function setPath( target, path, value ) {
	const [ head, ...rest ] = splitPath( path );
	const copy = Array.isArray( target )
		? [ ...target ]
		: { ...( target ?? {} ) };
	copy[ head ] =
		rest.length === 0
			? value
			: setPath( target?.[ head ], rest.join( '.' ), value );
	return copy;
}

/**
 * The top-level attribute of a path or pattern.
 *
 * @param {string} path
 * @return {string} Attribute name.
 */
export function attributeOf( path ) {
	return splitPath( path )[ 0 ];
}

/**
 * Whether a concrete path ("buttons.0.label") matches a field pattern ("buttons.*.label").
 *
 * @param {string} path
 * @param {string} pattern
 * @return {boolean} Match.
 */
export function matchesPattern( path, pattern ) {
	const segments = splitPath( path );
	const patternSegments = splitPath( pattern );
	return (
		segments.length === patternSegments.length &&
		patternSegments.every(
			( segment, index ) =>
				segment === segments[ index ] ||
				( segment === '*' && /^\d+$/.test( segments[ index ] ) )
		)
	);
}

/**
 * Replaces the values of text fields (plain and rich) with empty strings, also
 * in list items. These values are edited inline and never need new server
 * markup; blanking keeps required properties present.
 *
 * @param {Object}                         attributes
 * @param {Object<string, {kind: string}>} fields     Inline fields by pattern.
 * @return {Object} Attributes without inline text.
 */
export function blankInlineText( attributes, fields ) {
	let result = attributes;
	for ( const [ pattern, field ] of Object.entries( fields ) ) {
		if ( field.kind === 'plain' || field.kind === 'rich' ) {
			result = blank( result, splitPath( pattern ) );
		}
	}
	return result;
}

function blank( value, segments ) {
	if ( value === null || typeof value !== 'object' ) {
		return value;
	}
	const [ head, ...rest ] = segments;
	if ( head === '*' ) {
		return Array.isArray( value )
			? value.map( ( item ) => blank( item, rest ) )
			: value;
	}
	if ( ! ( head in value ) ) {
		return value;
	}
	if ( rest.length === 0 ) {
		return typeof value[ head ] === 'string'
			? { ...value, [ head ]: '' }
			: value;
	}
	return { ...value, [ head ]: blank( value[ head ], rest ) };
}

/**
 * JSON with sorted object keys, so equal values give equal strings.
 *
 * @param {*} value
 * @return {string} Stable JSON.
 */
export function stableStringify( value ) {
	if ( Array.isArray( value ) ) {
		return `[${ value.map( stableStringify ).join( ',' ) }]`;
	}
	if ( value !== null && typeof value === 'object' ) {
		return `{${ Object.keys( value )
			.filter( ( key ) => value[ key ] !== undefined )
			.sort()
			.map(
				( key ) =>
					`${ JSON.stringify( key ) }:${ stableStringify( value[ key ] ) }`
			)
			.join( ',' ) }}`;
	}
	return JSON.stringify( value ) ?? 'null';
}
