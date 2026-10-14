// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';

import { htmlToText, textToHtml } from '../text';

describe( 'plain text fields', () => {
	it( 'round-trips text with HTML special characters', () => {
		const text = 'Tom & Jerry <script>alert(1)</script> "quoted"';
		expect( textToHtml( text ) ).toBe(
			'Tom &amp; Jerry &lt;script>alert(1)&lt;/script> "quoted"'
		);
		expect( htmlToText( textToHtml( text ) ) ).toBe( text );
	} );

	it( 'decodes entities RichText produces', () => {
		expect( htmlToText( 'a &amp; b &lt;c&gt;' ) ).toBe( 'a & b <c>' );
	} );
} );

describe( 'multiline plain text fields', () => {
	it( 'turns line breaks into <br> and back', () => {
		const text = 'Main street 1\n12345 Town & <Country>';
		const html = textToHtml( text, true );
		expect( html ).toBe( 'Main street 1<br>12345 Town &amp; &lt;Country>' );
		expect( htmlToText( html ) ).toBe( text );
	} );

	it( 'reads the line breaks RichText writes', () => {
		// RichText (rich-text's toHTMLString) writes a line break as <br>.
		expect( htmlToText( 'a<br>b<br>' ) ).toBe( 'a\nb\n' );
		expect( htmlToText( textToHtml( 'a\n\nb', true ) ) ).toBe( 'a\n\nb' );
	} );

	it( 'keeps line breaks of single-line fields as text', () => {
		expect( textToHtml( 'a\nb' ) ).toBe( 'a\nb' );
	} );
} );
