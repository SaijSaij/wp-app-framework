/**
 * Block context in the editor: the values ancestors provide (providesContext)
 * and the ones a block uses (usesContext), computed like the block editor's
 * BlockContextProvider, so prefetched render jobs match the jobs of the
 * blocks once they mount.
 */

/** Context the server derives from the post itself (render_block()). */
const SERVER_CONTEXT = [ 'postId', 'postType' ];

/**
 * The context values sent to the render route: those the block uses, without
 * the ones the server derives from the post and without missing values.
 *
 * @param {Object|undefined} context Block context of the edit component.
 * @return {Object} Context values.
 */
export function renderContext( context ) {
	const values = {};
	for ( const [ key, value ] of Object.entries( context ?? {} ) ) {
		if ( ! SERVER_CONTEXT.includes( key ) && value !== undefined ) {
			values[ key ] = value;
		}
	}
	return values;
}

/**
 * The context available to the inner blocks of a block.
 *
 * @param {Object}           available  Context available to the block.
 * @param {Object|undefined} blockType  Registered block type.
 * @param {Object}           attributes Attributes of the block.
 * @return {Object} Context available to its inner blocks.
 */
export function providedContext( available, blockType, attributes ) {
	const provides = blockType?.providesContext;
	if ( ! provides || Object.keys( provides ).length === 0 ) {
		return available;
	}
	const next = { ...available };
	for ( const [ key, attribute ] of Object.entries( provides ) ) {
		next[ key ] = attributes?.[ attribute ];
	}
	return next;
}

/**
 * The context values a block uses.
 *
 * @param {Object}           available Context available to the block.
 * @param {Object|undefined} blockType Registered block type.
 * @return {Object} Context of the block.
 */
export function usedContext( available, blockType ) {
	const used = {};
	for ( const key of blockType?.usesContext ?? [] ) {
		if ( key in available ) {
			used[ key ] = available[ key ];
		}
	}
	return used;
}

/**
 * The context available to the inner blocks of a block in the editor: what
 * the block and its ancestors provide.
 *
 * @param {Object}                   select       Selectors of core/block-editor.
 * @param {(name: string) => Object} getBlockType
 * @param {string}                   clientId
 * @return {Object} Context available to the inner blocks.
 */
export function innerContext( select, getBlockType, clientId ) {
	let available = {};
	for ( const id of [ ...select.getBlockParents( clientId ), clientId ] ) {
		available = providedContext(
			available,
			getBlockType( select.getBlockName( id ) ),
			select.getBlockAttributes( id )
		);
	}
	return available;
}
