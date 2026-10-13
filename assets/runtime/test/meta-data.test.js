import { describe, expect, it } from 'vitest';

import { changeMeta, fieldValue, formValue, valueFromMeta } from '../meta-data';

const form = {
	fields: {
		capacity: { key: 'dev_test_capacity', default: 0, id: false },
		venue: { key: 'dev_test_venue', default: 0, id: true },
		tickets: { key: 'dev_test_tickets', default: [], id: false },
	},
};

describe( 'fieldValue', () => {
	it( 'uses the default for missing values', () => {
		expect( fieldValue( form.fields.capacity, undefined ) ).toBe( 0 );
		expect( fieldValue( form.fields.capacity, null ) ).toBe( 0 );
		expect( fieldValue( form.fields.capacity, 5 ) ).toBe( 5 );
	} );

	it( 'treats 0 and missing IDs as no object', () => {
		expect( fieldValue( form.fields.venue, 0 ) ).toBeNull();
		expect( fieldValue( form.fields.venue, undefined ) ).toBeNull();
		expect( fieldValue( form.fields.venue, 12 ) ).toBe( 12 );
	} );
} );

describe( 'meta of the post entity', () => {
	it( 'reads the values by meta key', () => {
		expect(
			valueFromMeta( form, { dev_test_capacity: 3, dev_test_venue: 0 } )
		).toEqual( { capacity: 3, venue: null, tickets: [] } );
		expect( valueFromMeta( form, undefined ) ).toEqual( {
			capacity: 0,
			venue: null,
			tickets: [],
		} );
	} );

	it( 'changes one meta key and keeps the others', () => {
		const meta = { dev_test_capacity: 3, other_key: 'x' };
		expect( changeMeta( form, meta, 'venue', null ) ).toEqual( {
			dev_test_capacity: 3,
			other_key: 'x',
			dev_test_venue: null,
		} );
	} );

	it( 'reads the values of a meta box by field name', () => {
		const stored = { capacity: 9, venue: null };
		expect( formValue( form, ( field, name ) => stored[ name ] ) ).toEqual(
			{ capacity: 9, venue: null, tickets: [] }
		);
	} );
} );
