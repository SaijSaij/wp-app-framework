import { htmlToDOM } from 'html-react-parser';

import { LIST_ITEM } from './ghosts';
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
 * fields that exist. The root element may itself be a text field (e.g. the
 * <p> of a paragraph block), but not an image field. Returns the root node and
 * the marked paths, and the items marked with list_item().
 *
 * @param {string} html
 * @param {Object} fields Inline fields by pattern.
 * @return {{root: Object|null, error: string|null, paths: string[], listItems: string[]}} Analysis.
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
			listItems: [],
		};
	}

	const root = nodes[ 0 ];
	const rootPath = root.attribs[ FIELD ];
	if (
		rootPath !== undefined &&
		Object.entries( fields ).some(
			( [ pattern, field ] ) =>
				field.kind === 'image' && matchesPattern( rootPath, pattern )
		)
	) {
		return {
			root: null,
			error: 'The root element cannot be an image field; mark an element inside it.',
			paths: [],
			listItems: [],
		};
	}

	const paths = [];
	const listItems = [];
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
					listItems: [],
				};
			}
			paths.push( path );
		}
		if ( node.attribs[ LIST_ITEM ] !== undefined ) {
			listItems.push( node.attribs[ LIST_ITEM ] );
		}
		pending.push( ...node.children );
	}

	return { root, error: null, paths, listItems };
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
