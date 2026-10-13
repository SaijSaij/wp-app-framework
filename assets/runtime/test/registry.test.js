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
		const registry = createRegistry( blocks, getBlockType, 'test' );
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
		const registry = createRegistry( blocks, getBlockType, 'test' );
		registry.registerSupportControl( 'surface', SupportControl );
		registry.registerControl( 'card', 'surface', BlockControl );

		expect( registry.getControl( 'test/card', 'surface' ) ).toBe(
			BlockControl
		);
		expect( registry.getControlledAttributes( 'test/card' ) ).toEqual( [
			'surface',
		] );
	} );

	it( 'warns about supports no block uses', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createRegistry( blocks, getBlockType, 'test' );
		registry.registerSupportControl( 'framed', SupportControl );

		expect( warn ).toHaveBeenCalledWith(
			'wp-app-framework: registerSupportControl( "framed" ): no block of this theme or plugin uses a block support with that attribute.'
		);
		expect( registry.getControl( 'test/card', 'framed' ) ).toBeUndefined();
	} );
} );

describe( 'registerPanel and registerControl', () => {
	it( 'add the namespace to the block name', () => {
		const registry = createRegistry( blocks, getBlockType, 'test' );
		const Panel = () => null;
		registry.registerPanel( 'hero', Panel );
		registry.registerControl( 'hero', 'topline', BlockControl );

		expect( registry.getPanels( 'test/hero' ) ).toEqual( [ Panel ] );
		expect( registry.getControl( 'test/hero', 'topline' ) ).toBe(
			BlockControl
		);
	} );
} );

describe( 'registerToolbarButton', () => {
	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'adds buttons to the toolbar of a block in order', () => {
		const registry = createRegistry( blocks, getBlockType, 'test' );
		const First = () => null;
		const Second = () => null;
		registry.registerToolbarButton( 'card', First );
		registry.registerToolbarButton( 'card', Second );

		expect( registry.getToolbarButtons( 'test/card' ) ).toEqual( [
			First,
			Second,
		] );
		expect( registry.getToolbarButtons( 'test/hero' ) ).toEqual( [] );
	} );

	it( 'warns about unknown blocks', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createRegistry( blocks, getBlockType, 'test' );
		registry.registerToolbarButton( 'missing', () => null );

		expect( warn ).toHaveBeenCalledWith(
			'wp-app-framework: registerToolbarButton( "missing" ): no block of this theme or plugin has that name.'
		);
		expect( registry.getToolbarButtons( 'test/missing' ) ).toEqual( [] );
	} );

	it( 'warns about names with a namespace', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const registry = createRegistry( blocks, getBlockType, 'test' );
		registry.registerToolbarButton( 'test/card', () => null );

		expect( warn ).toHaveBeenCalledWith(
			'wp-app-framework: registerToolbarButton( "test/card" ): use the block name without namespace, e.g. "card"; the namespace of this theme or plugin is added.'
		);
		expect( registry.getToolbarButtons( 'test/card' ) ).toEqual( [] );
	} );
} );
