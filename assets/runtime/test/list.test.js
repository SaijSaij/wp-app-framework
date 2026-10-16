import { describe, expect, it } from 'vitest';

import { itemTitle, listLimits } from '../list';

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

describe( 'listLimits', () => {
	it( 'allows everything without limits', () => {
		const limits = listLimits( {}, 5 );
		expect( limits.canAdd ).toBe( true );
		expect( limits.canRemove ).toBe( true );
		expect( limits.isUnused( 4 ) ).toBe( false );
		expect( limits.notice ).toBeNull();
	} );

	it( 'stops adding at max and removing at min', () => {
		expect( listLimits( { min: 1, max: 3 }, 2 ) ).toMatchObject( {
			canAdd: true,
			canRemove: true,
			notice: null,
		} );
		expect( listLimits( { min: 1, max: 3 }, 3 ) ).toMatchObject( {
			canAdd: false,
			canRemove: true,
		} );
		expect( listLimits( { min: 1, max: 3 }, 1 ) ).toMatchObject( {
			canAdd: true,
			canRemove: false,
		} );
	} );

	it( 'marks the items beyond max of a longer stored list', () => {
		const limits = listLimits( { max: 3 }, 5 );
		expect( limits.canAdd ).toBe( false );
		expect( limits.canRemove ).toBe( true );
		expect( [ 0, 1, 2, 3, 4 ].map( limits.isUnused ) ).toEqual( [
			false,
			false,
			false,
			true,
			true,
		] );
		expect( limits.notice ).toBe(
			'5 items, but at most 3 allowed: only the first 3 are used. Remove 2.'
		);
	} );

	it( 'asks for the missing items of a shorter stored list', () => {
		const limits = listLimits( { min: 2 }, 0 );
		expect( limits.canRemove ).toBe( false );
		expect( limits.notice ).toBe( '0 of at least 2 items. Add 2.' );
	} );
} );
