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
