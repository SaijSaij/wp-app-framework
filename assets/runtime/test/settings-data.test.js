import { describe, expect, it } from 'vitest';

import { editedGroups, groupValue, saveErrorMessage } from '../settings-data';

const groups = [
	{
		name: 'layout',
		option: 'my_theme_layout',
		title: 'Layout',
		default: { wide: false },
	},
	{
		name: 'general',
		option: 'my_theme_general',
		title: 'General',
		default: { tagline: '', density: 'normal' },
	},
];

describe( 'groupValue', () => {
	it( 'fills settings missing in the stored value with their defaults', () => {
		expect( groupValue( groups[ 1 ], { tagline: 'Hi' } ) ).toEqual( {
			tagline: 'Hi',
			density: 'normal',
		} );
	} );

	it( 'uses the defaults for a value WordPress could not return', () => {
		expect( groupValue( groups[ 1 ], null ) ).toEqual( {
			tagline: '',
			density: 'normal',
		} );
	} );
} );

describe( 'editedGroups', () => {
	it( 'names the groups whose option has edits', () => {
		expect(
			editedGroups( groups, { my_theme_general: {}, title: 'x' } )
		).toEqual( [ 'general' ] );
		expect( editedGroups( groups, undefined ) ).toEqual( [] );
	} );
} );

describe( 'saveErrorMessage', () => {
	it( 'names the group and the invalid setting', () => {
		const error = {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): my_theme_general',
			data: {
				status: 400,
				params: {
					my_theme_general:
						'my_theme_general[density] is not one of compact and normal.',
				},
			},
		};
		expect( saveErrorMessage( error, groups ) ).toBe(
			'The settings were not saved. General: my_theme_general[density] is not one of compact and normal.'
		);
	} );

	it( 'falls back to the message of the error', () => {
		expect(
			saveErrorMessage(
				{ message: 'Sorry, you are not allowed to do that.' },
				groups
			)
		).toBe(
			'The settings were not saved. Sorry, you are not allowed to do that.'
		);
		expect( saveErrorMessage( undefined, groups ) ).toBe(
			'The settings were not saved. Unknown error.'
		);
	} );
} );
