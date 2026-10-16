import { afterEach, describe, expect, it, vi } from 'vitest';

import { createSettingsRegistry } from '../settings-registry';

const groups = [
	{
		name: 'design',
		controls: {
			title: { type: 'plain' },
			schemes: {
				type: 'list',
				item: {
					type: 'object',
					fields: { name: { type: 'plain' } },
				},
			},
			contact: {
				type: 'object',
				fields: { email: { type: 'string' } },
			},
		},
	},
];

describe( 'registerSettingsControl', () => {
	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'registers controls for settings, fields and list items', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createSettingsRegistry( groups );
		const Title = () => null;
		const Scheme = () => null;
		const Email = () => null;

		registry.registerSettingsControl( 'design', 'title', Title );
		registry.registerSettingsControl(
			'design',
			[ 'schemes', '*' ],
			Scheme
		);
		registry.registerSettingsControl(
			'design',
			[ 'contact', 'email' ],
			Email
		);

		expect( registry.getSettingsControl( 'design', 'title' ) ).toBe(
			Title
		);
		expect( registry.getSettingsControl( 'design', 'schemes.*' ) ).toBe(
			Scheme
		);
		expect( registry.getSettingsControl( 'design', 'contact.email' ) ).toBe(
			Email
		);
		expect( registry.getSettingsControl( 'design', 'schemes' ) ).toBe(
			undefined
		);
		expect( warn ).not.toHaveBeenCalled();
	} );

	it( 'warns about unknown groups and paths', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createSettingsRegistry( groups );
		const Control = () => null;

		registry.registerSettingsControl( 'layout', 'wide', Control );
		registry.registerSettingsControl( 'design', 'logo', Control );
		registry.registerSettingsControl( 'design', [ 'title', '*' ], Control );
		registry.registerSettingsControl(
			'design',
			[ 'schemes', 'name' ],
			Control
		);
		registry.registerSettingsControl(
			'design',
			[ 'schemes', '*', 'color' ],
			Control
		);

		expect( warn.mock.calls.map( ( call ) => call[ 0 ] ) ).toEqual( [
			'wp-app-framework: registerSettingsControl( "layout", "wide" ): the theme or plugin has no settings group "layout".',
			'wp-app-framework: registerSettingsControl( "design", "logo" ): the group has no setting "logo" with a control.',
			'wp-app-framework: registerSettingsControl( "design", ["title","*"] ): "*" stands for the items of a list, but the value before it is no list.',
			'wp-app-framework: registerSettingsControl( "design", ["schemes","name"] ): "name" names a field, but the value before it is no value class.',
			'wp-app-framework: registerSettingsControl( "design", ["schemes","*","color"] ): the value has no field "color" with a control.',
		] );
	} );
} );
