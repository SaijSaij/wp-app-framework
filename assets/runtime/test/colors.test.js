import { describe, expect, it } from 'vitest';

import { COLOR_PATTERN, hexColors } from '../colors';

describe( 'COLOR_PATTERN', () => {
	it( 'accepts hex colors with alpha and the empty string', () => {
		for ( const color of [ '', '#fff', '#FFF8', '#1e4d8c', '#1E4D8C80' ] ) {
			expect( COLOR_PATTERN.test( color ) ).toBe( true );
		}
		for ( const color of [
			'red',
			'1e4d8c',
			'#1e4d8',
			'#1e4d8c800',
			'var(--x)',
			'#fff\n',
		] ) {
			expect( COLOR_PATTERN.test( color ) ).toBe( false );
		}
	} );
} );

describe( 'hexColors', () => {
	it( 'keeps the hex colors of a palette, named by name or value', () => {
		expect(
			hexColors( [
				{ name: 'Primary', slug: 'primary', color: '#1e4d8c' },
				{ name: 'Variable', slug: 'variable', color: 'var(--x)' },
				{ name: '', slug: 'clear', color: '#00000080' },
				{ name: 'Empty', slug: 'empty', color: '' },
				{ name: 'Missing' },
			] )
		).toEqual( [
			{ name: 'Primary', color: '#1e4d8c' },
			{ name: '#00000080', color: '#00000080' },
		] );
	} );

	it( 'accepts a missing palette', () => {
		expect( hexColors( undefined ) ).toEqual( [] );
	} );
} );
