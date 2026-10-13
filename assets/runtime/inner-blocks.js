/**
 * PHP encodes an empty array of attributes as JSON array.
 *
 * @param {Object|Array} attributes
 * @return {Object} Attributes.
 */
function attributesOf( attributes ) {
	return Array.isArray( attributes ) ? {} : attributes;
}

/**
 * Template nodes of the build ({name, attributes, innerBlocks}) as inner block
 * template ([name, attributes, innerBlocks]). A node without inner blocks has
 * no inner template: with an empty one the editor would remove the inner
 * blocks of a locked template (synchronizeBlocksWithTemplate()).
 *
 * @param {Array<Object>} nodes
 * @return {Array} Inner block template.
 */
export function toTemplate( nodes ) {
	return nodes.map( ( { name, attributes, innerBlocks } ) =>
		innerBlocks.length > 0
			? [ name, attributesOf( attributes ), toTemplate( innerBlocks ) ]
			: [ name, attributesOf( attributes ) ]
	);
}

/**
 * Options of useInnerBlocksProps() from the inner block options of the build
 * (#[InnerBlocks]); allowed blocks come from the block type.
 *
 * @param {Object|undefined}                      options        Inner block options of the block configuration.
 * @param {import('react').ComponentType<Object>} buttonAppender InnerBlocks.ButtonBlockAppender.
 * @return {Object} Options for useInnerBlocksProps().
 */
export function innerBlocksOptions( options, buttonAppender ) {
	const { template, appender, ...rest } = options ?? {};
	const result = { ...rest };
	if ( template ) {
		result.template = toTemplate( template );
	}
	if ( appender === 'button' ) {
		result.renderAppender = buttonAppender;
	} else if ( appender === 'none' ) {
		result.renderAppender = false;
	}
	return result;
}
