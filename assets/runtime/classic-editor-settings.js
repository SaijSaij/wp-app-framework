/**
 * Settings of the classic editor for #[RichText] on the settings page, without React.
 */

/** Formats of #[RichText] on the settings page: toolbar buttons and elements of the classic editor. */
const FORMATS = {
	'core/bold': { button: 'bold', elements: 'strong/b' },
	'core/italic': { button: 'italic', elements: 'em/i' },
	'core/link': { button: 'link', elements: 'a[href|target|rel]' },
	'core/strikethrough': { button: 'strikethrough', elements: 'del/s' },
	'core/subscript': { button: 'subscript', elements: 'sub' },
	'core/superscript': { button: 'superscript', elements: 'sup' },
};

/**
 * Settings of the classic editor for the allowed formats: their buttons and
 * only their elements (plus line breaks), inline HTML without paragraphs.
 *
 * @param {?Array<string>} allowedFormats Formats of #[RichText], null for all.
 * @return {Object} TinyMCE settings.
 */
export function editorSettings( allowedFormats ) {
	const formats = ( allowedFormats ?? Object.keys( FORMATS ) ).map(
		( format ) => FORMATS[ format ]
	);
	return {
		toolbar1: formats.map( ( format ) => format.button ).join( ',' ),
		valid_elements: [
			...formats.map( ( format ) => format.elements ),
			'br',
		].join( ',' ),
		wpautop: false,
		forced_root_block: false,
	};
}
