import { describe, it, expect } from 'vitest';

import {
	blankInlineText,
	getPath,
	matchesPattern,
	setPath,
	stableStringify,
} from '../paths';

const fields = {
	topline: { kind: 'plain' },
	headline: { kind: 'rich' },
	imageId: { kind: 'image' },
	'buttons.*.label': { kind: 'plain' },
	'caption.text': { kind: 'plain' },
	note: { kind: 'rich', inspector: true },
};

describe( 'paths', () => {
	it( 'reads and immutably writes nested values, keeping lists', () => {
		const attributes = {
			buttons: [ { label: 'A', url: 'x' }, { label: 'B' } ],
			caption: { text: 'c' },
		};
		expect( getPath( attributes, 'buttons.1.label' ) ).toBe( 'B' );
		expect( getPath( attributes, 'missing.deep' ) ).toBeUndefined();

		const changed = setPath( attributes, 'buttons.0.label', 'Z' );
		expect( changed.buttons ).toEqual( [
			{ label: 'Z', url: 'x' },
			{ label: 'B' },
		] );
		expect( Array.isArray( changed.buttons ) ).toBe( true );
		expect( attributes.buttons[ 0 ].label ).toBe( 'A' );
		expect( changed.caption ).toBe( attributes.caption );
	} );

	it( 'matches list indexes against "*" only', () => {
		expect( matchesPattern( 'buttons.3.label', 'buttons.*.label' ) ).toBe(
			true
		);
		expect( matchesPattern( 'buttons.x.label', 'buttons.*.label' ) ).toBe(
			false
		);
		expect( matchesPattern( 'buttons.3', 'buttons.*.label' ) ).toBe(
			false
		);
		expect( matchesPattern( 'topline', 'topline' ) ).toBe( true );
	} );

	it( 'blanks inline text, also in list items, but keeps images and other values', () => {
		const attributes = {
			topline: 'Top',
			headline: '<em>Head</em>',
			imageId: 5,
			gradient: 'black',
			buttons: [
				{ label: 'A', url: 'x' },
				{ label: 'B', url: 'y' },
			],
			caption: { text: 'c' },
		};
		expect( blankInlineText( attributes, fields ) ).toEqual( {
			topline: '',
			headline: '',
			imageId: 5,
			gradient: 'black',
			buttons: [
				{ label: '', url: 'x' },
				{ label: '', url: 'y' },
			],
			caption: { text: '' },
		} );
		expect( attributes.topline ).toBe( 'Top' );
	} );

	it( 'gives the same key for equal values regardless of key order', () => {
		expect( stableStringify( { b: 1, a: [ { y: 2, x: 1 } ] } ) ).toBe(
			stableStringify( { a: [ { x: 1, y: 2 } ], b: 1 } )
		);
		expect( stableStringify( { a: undefined, b: null } ) ).toBe(
			'{"b":null}'
		);
	} );

	it( 'changes the key for every value except inline text', () => {
		const key = ( attributes ) =>
			stableStringify( blankInlineText( attributes, fields ) );
		const base = {
			note: 'n',
			topline: 'a',
			imageId: 1,
			buttons: [ { label: 'A', url: 'x' } ],
		};
		expect( key( { ...base, topline: 'b' } ) ).toBe( key( base ) );
		expect(
			key( { ...base, buttons: [ { label: 'B', url: 'x' } ] } )
		).toBe( key( base ) );
		expect( key( { ...base, imageId: 2 } ) ).not.toBe( key( base ) );
		// Edited in the inspector: the server prints it.
		expect( key( { ...base, note: 'm' } ) ).not.toBe( key( base ) );
		expect(
			key( { ...base, buttons: [ { label: 'A', url: 'z' } ] } )
		).not.toBe( key( base ) );
		expect(
			key( {
				...base,
				buttons: [ ...base.buttons, { label: '', url: '' } ],
			} )
		).not.toBe( key( base ) );
	} );
} );
