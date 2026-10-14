/**
 * Title of a list item: its first plain text field (the first line of a
 * multiline one), else the text of its first rich text field, else "Item n".
 *
 * @param {import('./paths').AttributeValue} item
 * @param {number}                           index
 * @param {string}                           path   Pattern of the list, e.g. "buttons".
 * @param {Object}                           fields Inline fields of the block by pattern.
 * @return {string} Title.
 */
export function itemTitle( item, index, path, fields ) {
	const prefix = `${ path }.*.`;
	const candidates = Object.entries( fields )
		.filter(
			( [ pattern, field ] ) =>
				pattern.startsWith( prefix ) &&
				! pattern.slice( prefix.length ).includes( '.' ) &&
				( field.kind === 'plain' || field.kind === 'rich' )
		)
		// Plain text first; the sort is stable within each kind.
		.sort(
			( [ , a ], [ , b ] ) =>
				Number( a.kind !== 'plain' ) - Number( b.kind !== 'plain' )
		);
	for ( const [ pattern, field ] of candidates ) {
		const raw = item?.[ pattern.slice( prefix.length ) ];
		if ( typeof raw !== 'string' ) {
			continue;
		}
		let text = field.kind === 'rich' ? raw.replace( /<[^>]*>/g, '' ) : raw;
		if ( field.multiline ) {
			text = text.trim().split( '\n' )[ 0 ];
		}
		text = text.trim();
		if ( text !== '' ) {
			return text;
		}
	}
	return `Item ${ index + 1 }`;
}
