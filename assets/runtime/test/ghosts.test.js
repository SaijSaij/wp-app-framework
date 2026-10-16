import { describe, expect, it } from 'vitest';

import {
	addItem,
	controlAt,
	ghostItems,
	schemaAt,
	unmarkedGhosts,
	withGhostItems,
} from '../ghosts';

const schemas = {
	buttons: {
		type: 'array',
		items: {
			type: 'object',
			properties: {
				label: { type: 'string', required: true },
				url: { type: 'string', default: '' },
			},
		},
		default: [],
	},
	card: {
		type: 'object',
		properties: {
			links: { type: 'array', items: { type: 'string', enum: [ 'a' ] } },
		},
	},
};
const controls = {
	buttons: { type: 'list', label: 'Buttons', max: 2 },
	card: {
		type: 'object',
		fields: { links: { type: 'list', label: 'Links' } },
	},
};

describe( 'ghost items', () => {
	it( 'finds schemas and controls at a path', () => {
		expect( schemaAt( schemas, 'card.links' ).items.enum ).toEqual( [
			'a',
		] );
		expect( controlAt( controls, 'card.links' ).label ).toBe( 'Links' );
	} );

	it( 'adds a new item below max, also to missing and nested lists', () => {
		const attributes = { buttons: [ { label: 'A', url: '' } ] };
		const ghosts = ghostItems(
			attributes,
			[ 'buttons', 'card.links' ],
			controls
		);
		expect( ghosts ).toEqual( {
			buttons: { index: 1, label: 'Buttons' },
			'card.links': { index: 0, label: 'Links' },
		} );
		expect( withGhostItems( attributes, ghosts, schemas ) ).toEqual( {
			buttons: [
				{ label: 'A', url: '' },
				{ label: '', url: '' },
			],
			card: { links: [ 'a' ] },
		} );
		// The stored attributes stay as they are.
		expect( attributes ).toEqual( {
			buttons: [ { label: 'A', url: '' } ],
		} );
	} );

	it( 'adds no new item at max', () => {
		expect(
			ghostItems(
				{ buttons: [ { label: 'A' }, { label: 'B' } ] },
				[ 'buttons' ],
				controls
			)
		).toEqual( {} );
	} );

	it( 'adds an item to the attributes', () => {
		expect(
			addItem(
				{ buttons: [ { label: 'A', url: '' } ] },
				'buttons',
				schemas
			)
		).toEqual( {
			buttons: [
				{ label: 'A', url: '' },
				{ label: '', url: '' },
			],
		} );
		expect( addItem( {}, 'card.links', schemas ) ).toEqual( {
			card: { links: [ 'a' ] },
		} );
	} );

	it( 'reports new items the markup does not mark', () => {
		const ghosts = {
			buttons: { index: 1, label: 'Buttons' },
			'card.links': { index: 0, label: 'Links' },
		};
		expect(
			unmarkedGhosts( ghosts, [ 'buttons.0', 'buttons.1' ] )
		).toEqual( [ 'card.links' ] );
	} );
} );
