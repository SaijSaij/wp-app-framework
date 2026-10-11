import { describe, expect, it } from 'vitest';

import { groupControls } from '../groups';

const entries = [
	{ id: 'title', content: true },
	{ id: 'gradient', content: false },
	{ id: 'surface', content: false, group: 'styles' },
	{ id: 'framed', content: false, group: 'settings' },
];

const ids = ( list ) => list.map( ( entry ) => entry.id );

describe( 'groupControls', () => {
	it( 'puts content, styles of supports and other controls into their groups', () => {
		const groups = groupControls( entries, 'default' );
		expect( ids( groups.content ) ).toEqual( [ 'title' ] );
		expect( ids( groups.styles ) ).toEqual( [ 'surface' ] );
		expect( ids( groups.settings ) ).toEqual( [ 'gradient', 'framed' ] );
	} );

	it( 'shows only content controls outside the default editing mode', () => {
		const groups = groupControls( entries, 'contentOnly' );
		expect( ids( groups.content ) ).toEqual( [ 'title' ] );
		expect( groups.styles ).toEqual( [] );
		expect( groups.settings ).toEqual( [] );
	} );
} );
