import { createValue } from './defaults';

/**
 * Identifiers of list items (#[Identifier]): eight lowercase hex digits,
 * generated when an item is added and never edited, unique within the list;
 * '' for an item without identifier yet. The same pattern as
 * Identifier::PATTERN in PHP.
 */
export const IDENTIFIER_PATTERN = /^([0-9a-f]{8})?$/;

/**
 * A new identifier that is not among the given ones.
 *
 * @param {Array<unknown>} taken Identifiers of the other items.
 * @return {string} Identifier.
 */
export function createIdentifier( taken ) {
	let identifier;
	do {
		identifier = Array.from(
			globalThis.crypto.getRandomValues( new Uint8Array( 4 ) ),
			( byte ) => byte.toString( 16 ).padStart( 2, '0' )
		).join( '' );
	} while ( taken.includes( identifier ) );
	return identifier;
}

/**
 * A new item of a list: the defaults of its schema, and a new identifier if
 * the items have one.
 *
 * @param {Object}         schema       JSON schema of an item.
 * @param {string}         [identifier] Name of the identifier of the items (`identifier` of the list control).
 * @param {Array<unknown>} items        Items of the list so far.
 * @return {import('./paths').AttributeValue} New item.
 */
export function createItem( schema, identifier, items ) {
	const item = createValue( schema );
	if (
		identifier === undefined ||
		item === null ||
		typeof item !== 'object'
	) {
		return item;
	}
	return {
		...item,
		[ identifier ]: createIdentifier(
			items.map( ( other ) => other?.[ identifier ] )
		),
	};
}
