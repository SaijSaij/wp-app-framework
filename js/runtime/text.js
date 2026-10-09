import { escapeHTML } from '@wordpress/escape-html';
import { create } from '@wordpress/rich-text';

/**
 * #[PlainText] values are stored as plain text, but RichText edits HTML
 * strings: escape before editing, decode after (via the public rich-text API,
 * so entities are read exactly as RichText reads them).
 */

/**
 * @param {string} text
 * @return {string} HTML for RichText.
 */
export function textToHtml( text ) {
	return escapeHTML( text ?? '' );
}

/**
 * @param {string} html Value from RichText without formats.
 * @return {string} Plain text.
 */
export function htmlToText( html ) {
	return create( { html } ).text;
}
