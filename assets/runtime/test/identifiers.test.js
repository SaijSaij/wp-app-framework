import { describe, expect, it, vi } from 'vitest';

import {
	IDENTIFIER_PATTERN,
	createIdentifier,
	createItem,
} from '../identifiers';

const schema = {
	type: 'object',
	properties: {
		id: { type: 'string', default: '' },
		name: { type: 'string', default: 'New' },
	},
};

describe( 'IDENTIFIER_PATTERN', () => {
	it( 'accepts eight lowercase hex digits and the empty string', () => {
		for ( const identifier of [ '', '0a1b2c3d', 'ffffffff' ] ) {
			expect( IDENTIFIER_PATTERN.test( identifier ) ).toBe( true );
		}
		for ( const identifier of [
			'0A1B2C3D',
			'0a1b2c3',
			'0a1b2c3d4',
			'scheme-1',
			'0a1b2c3d\n',
		] ) {
			expect( IDENTIFIER_PATTERN.test( identifier ) ).toBe( false );
		}
	} );
} );

describe( 'createIdentifier', () => {
	it( 'creates eight hex digits', () => {
		expect( createIdentifier( [] ) ).toMatch( /^[0-9a-f]{8}$/ );
	} );

	it( 'never repeats an identifier of the list', () => {
		const random = vi
			.spyOn( globalThis.crypto, 'getRandomValues' )
			.mockImplementationOnce( ( bytes ) => bytes.fill( 0 ) )
			.mockImplementationOnce( ( bytes ) => bytes.fill( 1 ) );
		try {
			expect( createIdentifier( [ '00000000' ] ) ).toBe( '01010101' );
			expect( random ).toHaveBeenCalledTimes( 2 );
		} finally {
			random.mockRestore();
		}
	} );
} );

describe( 'createItem', () => {
	it( 'creates the defaults without identifier', () => {
		expect( createItem( schema, undefined, [] ) ).toEqual( {
			id: '',
			name: 'New',
		} );
	} );

	it( 'gives the item an identifier none of the others has', () => {
		const random = vi
			.spyOn( globalThis.crypto, 'getRandomValues' )
			.mockImplementationOnce( ( bytes ) => bytes.fill( 0xab ) )
			.mockImplementationOnce( ( bytes ) => bytes.fill( 0xcd ) );
		try {
			expect(
				createItem( schema, 'id', [ { id: 'abababab', name: 'A' } ] )
			).toEqual( { id: 'cdcdcdcd', name: 'New' } );
		} finally {
			random.mockRestore();
		}
	} );
} );
