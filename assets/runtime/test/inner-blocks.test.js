import { describe, expect, it } from 'vitest';

import { innerBlocksOptions, toTemplate } from '../inner-blocks';

const Button = () => null;

describe( 'inner block options', () => {
	it( 'converts template nodes and leaves out empty inner templates', () => {
		expect(
			toTemplate( [
				{
					name: 'test/tabs',
					attributes: { active: 1 },
					innerBlocks: [
						{ name: 'test/tab', attributes: [], innerBlocks: [] },
					],
				},
				{ name: 'core/paragraph', attributes: [], innerBlocks: [] },
			] )
		).toEqual( [
			[ 'test/tabs', { active: 1 }, [ [ 'test/tab', {} ] ] ],
			[ 'core/paragraph', {} ],
		] );
	} );

	it( 'maps the appender and passes the other options through', () => {
		expect(
			innerBlocksOptions(
				{
					templateLock: false,
					orientation: 'horizontal',
					appender: 'button',
					prioritizedInserterBlocks: [ 'test/tab' ],
				},
				Button
			)
		).toEqual( {
			templateLock: false,
			orientation: 'horizontal',
			renderAppender: Button,
			prioritizedInserterBlocks: [ 'test/tab' ],
		} );
		expect(
			innerBlocksOptions( { appender: 'none' }, Button ).renderAppender
		).toBe( false );
		expect( innerBlocksOptions( undefined, Button ) ).toEqual( {} );
	} );
} );
