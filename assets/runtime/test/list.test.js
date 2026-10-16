import { describe, expect, it } from 'vitest';

import {
	dropIndex,
	formFields,
	itemTitle,
	listItemTitle,
	listLimits,
	moveItem,
} from '../list';

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

describe( 'listItemTitle', () => {
	const options = [
		{ value: 'star', label: 'Star' },
		{ value: 'check', label: 'Check' },
	];
	const enumList = {
		item: { type: 'enum', options },
		itemLabel: 'Icon',
	};
	const objectList = {
		item: { type: 'object', fields: {} },
		itemLabel: 'Badge',
	};
	const badgeFields = { 'badges.*.text': { kind: 'plain' } };

	it( 'uses the label of an enum case', () => {
		expect( listItemTitle( enumList, 'check', 0, 'icons', {} ) ).toBe(
			'Check'
		);
		expect( listItemTitle( enumList, 'gone', 1, 'icons', {} ) ).toBe(
			'Icon 2'
		);
	} );

	it( 'uses the text of an item, else the item label with its number', () => {
		expect(
			listItemTitle(
				objectList,
				{ text: 'Fast' },
				0,
				'badges',
				badgeFields
			)
		).toBe( 'Fast' );
		expect(
			listItemTitle( objectList, { text: '' }, 2, 'badges', badgeFields )
		).toBe( 'Badge 3' );
	} );
} );

describe( 'moveItem and dropIndex', () => {
	it( 'moves an item', () => {
		expect( moveItem( [ 1, 2, 3 ], 0, 2 ) ).toEqual( [ 2, 3, 1 ] );
		expect( moveItem( [ 1, 2, 3 ], 2, 0 ) ).toEqual( [ 3, 1, 2 ] );
	} );

	it( 'drops before or after another item', () => {
		// Down: after the last item, before the last item.
		expect( dropIndex( 0, 2, true ) ).toBe( 2 );
		expect( dropIndex( 0, 2, false ) ).toBe( 1 );
		// Up: before the first item, after the first item.
		expect( dropIndex( 2, 0, false ) ).toBe( 0 );
		expect( dropIndex( 2, 0, true ) ).toBe( 1 );
		// On itself or its neighbours' near edges: no move.
		expect( dropIndex( 1, 1, true ) ).toBe( 1 );
		expect( dropIndex( 1, 0, true ) ).toBe( 1 );
		expect( dropIndex( 1, 2, false ) ).toBe( 1 );
	} );
} );

describe( 'formFields', () => {
	it( 'lists the plain and rich text values of items as fields for their titles', () => {
		const item = {
			type: 'object',
			fields: {
				id: { type: 'string' },
				name: { type: 'plain' },
				address: { type: 'plain', multiline: true },
				note: { type: 'rich' },
				color: { type: 'color' },
			},
		};

		expect( formFields( 'regions', item ) ).toEqual( {
			'regions.*.name': { kind: 'plain', multiline: false },
			'regions.*.address': { kind: 'plain', multiline: true },
			'regions.*.note': { kind: 'rich', multiline: false },
		} );
		expect(
			itemTitle(
				{ name: '', address: 'Main street\n1', note: '' },
				0,
				'regions',
				formFields( 'regions', item ),
				'Region'
			)
		).toBe( 'Main street' );
		expect( formFields( 'tags', { type: 'enum' } ) ).toEqual( {} );
		expect( formFields( undefined, item ) ).toEqual( {} );
	} );
} );
