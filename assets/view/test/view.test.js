// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';

import { mountWithin } from '../mount';
import { BlockView, MARKER, SupportView } from '../view';

function marked( slug, views, html = '' ) {
	const element = document.createElement( 'div' );
	element.setAttribute( MARKER, JSON.stringify( { slug, views } ) );
	element.innerHTML = html;
	return element;
}

afterEach( () => {
	document.body.innerHTML = '';
	vi.restoreAllMocks();
} );

describe( 'view base classes', () => {
	it( 'find elements of their own block, not of nested blocks with view scripts', () => {
		const outer = marked(
			'my-theme',
			[ { view: 'Outer.view' } ],
			'<p class="a">1</p><div class="a"><p class="a">2</p></div>'
		);
		const nested = marked(
			'other-plugin',
			[ { view: 'Nested.view' } ],
			'<p class="a">nested</p>'
		);
		nested.classList.add( 'a' );
		outer.append( nested );
		document.body.append( outer );

		let view;
		class Outer extends BlockView {
			mount() {
				view = this;
			}
		}
		mountWithin( 'my-theme', { 'Outer.view': Outer }, document.body, {
			isPreview: false,
		} );

		expect(
			view.findAll( '.a' ).map( ( element ) => element.textContent )
		).toEqual( [ '1', '2', '2' ] );
		expect( view.find( 'p' ).textContent ).toBe( '1' );
		expect( view.find( '.missing' ) ).toBeNull();
		expect( view.element ).toBe( outer );
		expect( view.document ).toBe( document );
		expect( view.window ).toBe( window );
		expect( view.isPreview ).toBe( false );
	} );

	it( 'remove listeners and run cleanups after unmount(), in reverse order', () => {
		const element = marked(
			'my-theme',
			[ { view: 'Toggle.view' } ],
			'<button>Go</button>'
		);
		document.body.append( element );
		const calls = [];
		class Toggle extends BlockView {
			mount() {
				this.listen( this.find( 'button' ), 'click', () =>
					calls.push( 'click' )
				);
				this.onUnmount( () => calls.push( 'first cleanup' ) );
				this.onUnmount( () => calls.push( 'second cleanup' ) );
			}

			unmount() {
				calls.push( 'unmount' );
			}
		}

		const unmount = mountWithin(
			'my-theme',
			{ 'Toggle.view': Toggle },
			element,
			{ isPreview: true }
		);
		element.querySelector( 'button' ).click();
		unmount();
		element.querySelector( 'button' ).click();

		expect( calls ).toEqual( [
			'click',
			'unmount',
			'second cleanup',
			'first cleanup',
		] );
	} );

	it( 'give support views the value of the support', () => {
		const element = marked( 'my-theme', [
			{ view: 'Block.view' },
			{ view: 'Reveal.view', value: 'slow' },
		] );
		const values = [];
		class Block extends BlockView {
			mount() {
				values.push( 'block' );
			}
		}
		class Reveal extends SupportView {
			mount() {
				values.push( this.value, this.element === element );
			}
		}

		mountWithin(
			'my-theme',
			{ 'Block.view': Block, 'Reveal.view': Reveal },
			element,
			{ isPreview: false }
		);

		expect( values ).toEqual( [ 'block', 'slow', true ] );
	} );

	it( 'refuse a view that overrides the constructor', () => {
		const error = vi
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		class Custom extends BlockView {
			// eslint-disable-next-line no-useless-constructor -- calls the base constructor without its arguments on purpose.
			constructor() {
				super();
			}
		}

		mountWithin(
			'my-theme',
			{ 'Custom.view': Custom },
			marked( 'my-theme', [ { view: 'Custom.view' } ] ),
			{ isPreview: false }
		);

		expect( error ).toHaveBeenCalledWith(
			'wp-app-framework: the view script Custom.view of my-theme failed to mount.',
			expect.objectContaining( {
				message:
					'wp-app-framework: Custom must not override the constructor of its base class; set up the view in mount().',
			} )
		);
	} );
} );

describe( 'mountWithin', () => {
	it( 'mounts only the blocks of its instance', () => {
		const mounted = [];
		class Mine extends BlockView {
			mount() {
				mounted.push( this.element.id );
			}
		}
		const root = document.createElement( 'div' );
		const mine = marked( 'my-theme', [ { view: 'Mine.view' } ] );
		mine.id = 'mine';
		const theirs = marked( 'other-plugin', [ { view: 'Mine.view' } ] );
		theirs.id = 'theirs';
		root.append( mine, theirs );

		mountWithin( 'my-theme', { 'Mine.view': Mine }, root, {
			isPreview: false,
		} );

		expect( mounted ).toEqual( [ 'mine' ] );
	} );

	it( 'reports scripts that are missing or do not extend the right base class', () => {
		const error = vi
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		const mounted = [];
		class Wrong extends SupportView {}
		class Right extends BlockView {
			mount() {
				mounted.push( 'right' );
			}
		}

		mountWithin(
			'my-theme',
			{ 'Wrong.view': Wrong, 'Right.view': Right, 'Plain.view': {} },
			marked( 'my-theme', [
				{ view: 'Missing.view' },
				{ view: 'Wrong.view' },
				{ view: 'Plain.view', value: true },
				{ view: 'Right.view' },
			] ),
			{ isPreview: false }
		);

		expect( error.mock.calls.map( ( [ message ] ) => message ) ).toEqual( [
			'wp-app-framework: the view script Missing.view of my-theme is not loaded.',
			'wp-app-framework: the default export of the view script Wrong.view of my-theme must be a class that extends BlockView from @webcodingcat/wp-app-framework/view.',
			'wp-app-framework: the view script Plain.view of my-theme is not loaded.',
		] );
		expect( mounted ).toEqual( [ 'right' ] );
	} );
} );
