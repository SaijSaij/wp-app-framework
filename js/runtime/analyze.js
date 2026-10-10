import { htmlToDOM } from 'html-react-parser';

import { matchesPattern } from './paths';

/** Attribute of an inline field, set by field() in editor renders. */
export const FIELD = 'data-field';

/** Attribute of the inner blocks element, set by inner_blocks() in editor renders. */
export const INNER_BLOCKS = 'data-inner-blocks';

/**
 * Whether a parsed node is an element. Checked by type, not with instanceof:
 * html-react-parser and its DOM parser may bundle different domhandler copies.
 *
 * @param {Object} node
 * @return {boolean} Element.
 */
export function isElement( node ) {
	return (
		node.type === 'tag' || node.type === 'script' || node.type === 'style'
	);
}

/**
 * Checks the server markup of a block: exactly one root element, marked
 * fields that exist. Returns the root node and the marked paths.
 *
 * @param {string} html
 * @param {Object} fields Inline fields by pattern.
 * @return {{root: Object|null, error: string|null, paths: string[]}} Analysis.
 */
export function analyzeMarkup( html, fields ) {
	const nodes = htmlToDOM( html ).filter(
		( node ) =>
			node.type !== 'comment' &&
			! ( node.type === 'text' && node.data.trim() === '' )
	);
	if ( nodes.length !== 1 || ! isElement( nodes[ 0 ] ) ) {
		return {
			root: null,
			error: `The template must render exactly one root element, it rendered ${ nodes.length } nodes.`,
			paths: [],
		};
	}

	const root = nodes[ 0 ];
	if ( FIELD in root.attribs ) {
		return {
			root: null,
			error: 'The root element cannot be an inline field; mark an element inside it.',
			paths: [],
		};
	}

	const paths = [];
	const pending = [ root ];
	while ( pending.length > 0 ) {
		const node = pending.pop();
		if ( ! isElement( node ) ) {
			continue;
		}
		const path = node.attribs[ FIELD ];
		if ( path !== undefined ) {
			if (
				! Object.entries( fields ).some(
					( [ pattern, field ] ) =>
						! field.inspector && matchesPattern( path, pattern )
				)
			) {
				return {
					root: null,
					error: `field( "${ path }" ) is not an inline field of the block.`,
					paths: [],
				};
			}
			paths.push( path );
		}
		pending.push( ...node.children );
	}

	return { root, error: null, paths };
}

/**
 * Fields without wildcard that the markup does not mark: the template hides
 * them (e.g. when empty), so they cannot be edited inline.
 *
 * @param {Object}   fields Inline fields by pattern.
 * @param {string[]} paths  Marked paths.
 * @return {string[]} Missing field paths.
 */
export function missingFields( fields, paths ) {
	return Object.entries( fields )
		.filter(
			( [ pattern, field ] ) =>
				! field.inspector &&
				! pattern.includes( '*' ) &&
				! paths.includes( pattern )
		)
		.map( ( [ pattern ] ) => pattern );
}
