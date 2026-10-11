import { describe, it, expect } from 'vitest';

import { createValue } from '../defaults';

describe( 'createValue', () => {
	it( 'uses defaults, null for nullable types and empty values otherwise', () => {
		expect(
			createValue( {
				type: 'object',
				properties: {
					label: { type: 'string', required: true },
					url: { type: 'string', format: 'uri', default: '' },
					size: {
						type: [ 'string', 'null' ],
						enum: [ 's', 'l', null ],
					},
					tone: { type: 'string', enum: [ 'light', 'dark' ] },
					count: { type: 'integer', required: true },
					tags: {
						type: 'array',
						items: { type: 'string' },
						default: [ 'a' ],
					},
					on: { type: 'boolean', required: true },
				},
			} )
		).toEqual( {
			label: '',
			url: '',
			size: null,
			tone: 'light',
			count: 0,
			tags: [ 'a' ],
			on: false,
		} );
	} );

	it( 'copies object defaults', () => {
		const schema = { type: 'array', default: [ { a: 1 } ] };
		const value = createValue( schema );
		value[ 0 ].a = 2;
		expect( schema.default[ 0 ].a ).toBe( 1 );
	} );
} );
