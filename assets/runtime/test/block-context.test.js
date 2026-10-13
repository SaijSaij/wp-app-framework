import { describe, expect, it } from 'vitest';

import {
	innerContext,
	providedContext,
	renderContext,
	usedContext,
} from '../block-context';

const types = {
	'test/tabs': { providesContext: { 'test/tabs/active': 'active' } },
	'test/tab': { usesContext: [ 'test/tabs/active', 'postId' ] },
	'test/group': {},
};

describe( 'block context', () => {
	it( 'sends the used values without postId, postType and missing values', () => {
		expect(
			renderContext( {
				postId: 1,
				postType: 'post',
				'test/tabs/active': 0,
				missing: undefined,
			} )
		).toEqual( { 'test/tabs/active': 0 } );
		expect( renderContext( undefined ) ).toEqual( {} );
	} );

	it( 'passes provided values to nested blocks like the block editor', () => {
		const available = providedContext(
			{ postId: 1 },
			types[ 'test/tabs' ],
			{
				active: 2,
			}
		);
		expect( available ).toEqual( { postId: 1, 'test/tabs/active': 2 } );
		expect( providedContext( available, types[ 'test/group' ], {} ) ).toBe(
			available
		);
		expect( usedContext( available, types[ 'test/tab' ] ) ).toEqual( {
			'test/tabs/active': 2,
			postId: 1,
		} );
	} );

	it( 'collects the context of the ancestors of a block', () => {
		const blocks = {
			a: { name: 'test/tabs', attributes: { active: 3 } },
			b: { name: 'test/group', attributes: {} },
		};
		const select = {
			getBlockParents: () => [ 'a' ],
			getBlockName: ( id ) => blocks[ id ].name,
			getBlockAttributes: ( id ) => blocks[ id ].attributes,
		};
		expect(
			innerContext( select, ( name ) => types[ name ], 'b' )
		).toEqual( { 'test/tabs/active': 3 } );
	} );
} );
