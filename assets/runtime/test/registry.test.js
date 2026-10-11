import { afterEach, describe, expect, it, vi } from 'vitest';

import { createRegistry } from '../registry';

const blocks = {
	'test/card': { supports: [ 'surface' ] },
	'test/hero': { supports: [] },
};
const types = {
	'test/card': { attributes: { title: {}, surface: {} } },
	'test/hero': { attributes: { surface: {}, topline: {} } },
};
const getBlockType = ( name ) => types[ name ];

const SupportControl = () => null;
const BlockControl = () => null;

describe( 'registerSupportControl', () => {
	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'replaces the control of the support in the blocks that use it', () => {
		const registry = createRegistry( blocks, getBlockType );
		registry.registerSupportControl( 'surface', SupportControl );

		expect( registry.getControl( 'test/card', 'surface' ) ).toBe(
			SupportControl
		);
		expect( registry.getControlledAttributes( 'test/card' ) ).toEqual( [
			'surface',
		] );
		// test/hero has its own attribute "surface", not the support.
		expect( registry.getControl( 'test/hero', 'surface' ) ).toBeUndefined();
		expect( registry.getControlledAttributes( 'test/hero' ) ).toEqual( [] );
	} );

	it( 'lets a control registered for the block win', () => {
		const registry = createRegistry( blocks, getBlockType );
		registry.registerSupportControl( 'surface', SupportControl );
		registry.registerControl( 'test/card', 'surface', BlockControl );

		expect( registry.getControl( 'test/card', 'surface' ) ).toBe(
			BlockControl
		);
		expect( registry.getControlledAttributes( 'test/card' ) ).toEqual( [
			'surface',
		] );
	} );

	it( 'warns about supports no block uses', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createRegistry( blocks, getBlockType );
		registry.registerSupportControl( 'framed', SupportControl );

		expect( warn ).toHaveBeenCalledWith(
			'wp-app-framework: registerSupportControl( "framed" ): no block of this theme or plugin uses a block support with that attribute.'
		);
		expect( registry.getControl( 'test/card', 'framed' ) ).toBeUndefined();
	} );
} );
