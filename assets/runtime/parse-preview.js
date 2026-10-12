/**
 * Splits the front-end markup of a block into its root element and the HTML inside it.
 *
 * @param {string}   html
 * @param {Document} document Document to parse with.
 * @return {{tagName: string, attributes: Object<string, string>, innerHTML: string}|{error: string}} Root element or error.
 */
export function parsePreview( html, document ) {
	const template = document.createElement( 'template' );
	template.innerHTML = html;
	const nodes = [ ...template.content.childNodes ].filter(
		( node ) =>
			node.nodeType === node.ELEMENT_NODE ||
			( node.nodeType === node.TEXT_NODE && node.data.trim() !== '' )
	);
	if (
		nodes.length !== 1 ||
		nodes[ 0 ].nodeType !== nodes[ 0 ].ELEMENT_NODE
	) {
		return {
			error: `The block must render exactly one root element, it rendered ${ nodes.length } nodes.`,
		};
	}
	const [ root ] = nodes;
	return {
		tagName: root.tagName.toLowerCase(),
		attributes: Object.fromEntries(
			[ ...root.attributes ].map( ( { name, value } ) => [ name, value ] )
		),
		innerHTML: root.innerHTML,
	};
}
