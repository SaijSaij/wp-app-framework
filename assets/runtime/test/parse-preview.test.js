// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';

import { parsePreview } from '../parse-preview';

describe( 'parsePreview', () => {
	it( 'splits the markup into the root element and the HTML inside it', () => {
		expect(
			parsePreview(
				'\n<div class="faq" data-wp-app-framework-views="{&quot;slug&quot;:&quot;my-theme&quot;}"><button>Why?</button> <p hidden>Because.</p></div>\n',
				document
			)
		).toEqual( {
			tagName: 'div',
			attributes: {
				class: 'faq',
				'data-wp-app-framework-views': '{"slug":"my-theme"}',
			},
			innerHTML: '<button>Why?</button> <p hidden="">Because.</p>',
		} );
	} );

	it( 'ignores comments around the root element', () => {
		expect(
			parsePreview( '<!-- a --><section></section>', document ).tagName
		).toBe( 'section' );
	} );

	it( 'needs exactly one root element', () => {
		expect( parsePreview( '<p>a</p><p>b</p>', document ) ).toEqual( {
			error: 'The block must render exactly one root element, it rendered 2 nodes.',
		} );
		expect( parsePreview( 'text', document ) ).toEqual( {
			error: 'The block must render exactly one root element, it rendered 1 nodes.',
		} );
		expect( parsePreview( '', document ) ).toEqual( {
			error: 'The block must render exactly one root element, it rendered 0 nodes.',
		} );
	} );
} );
