import { describe, expect, it } from 'vitest';

import { itemTitle } from '../list';

const fields = {
	'buttons.*.note': { kind: 'rich' },
	'buttons.*.label': { kind: 'plain' },
	'buttons.*.link.text': { kind: 'plain' },
	topline: { kind: 'plain' },
};

describe( 'itemTitle', () => {
	it( 'uses the first plain field, then rich text without tags, then the position', () => {
		expect(
			itemTitle(
				{ label: ' Go ', note: '<b>Note</b>' },
				0,
				'buttons',
				fields
			)
		).toBe( 'Go' );
		expect(
			itemTitle(
				{ label: '', note: '<b>Bold</b> note' },
				0,
				'buttons',
				fields
			)
		).toBe( 'Bold note' );
		expect( itemTitle( { label: '' }, 2, 'buttons', fields ) ).toBe(
			'Item 3'
		);
		expect( itemTitle( 'value', 0, 'tags', fields ) ).toBe( 'Item 1' );
	} );
} );

describe( 'itemTitle of multiline text', () => {
	it( 'uses the first line', () => {
		const multiline = {
			'addresses.*.text': { kind: 'plain', multiline: true },
		};
		expect(
			itemTitle(
				{ text: '\n Main street 1 \n12345 Town' },
				0,
				'addresses',
				multiline
			)
		).toBe( 'Main street 1' );
	} );
} );
