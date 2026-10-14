import { escapeHTML } from '@wordpress/escape-html';
import { create } from '@wordpress/rich-text';

/**
 * #[PlainText] values are stored as plain text, but RichText edits HTML
 * strings: escape before editing, decode after (via the public rich-text API,
 * so entities are read exactly as RichText reads them). Line breaks of
 * multiline fields are "\n" in the text and <br> in the HTML; rich-text reads
 * <br> as "\n".
 */

/**
 * @param {string}  text
 * @param {boolean} [multiline] Turn line breaks into <br>.
 * @return {string} HTML for RichText.
 */
export function textToHtml( text, multiline = false ) {
	const html = escapeHTML( text ?? '' );
	return multiline ? html.replaceAll( '\n', '<br>' ) : html;
}

/**
 * @param {string} html Value from RichText without formats.
 * @return {string} Plain text, with "\n" for line breaks.
 */
export function htmlToText( html ) {
	return create( { html } ).text;
}
