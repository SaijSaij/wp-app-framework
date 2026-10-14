// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';

import { getData } from '../data';
import { loadScript } from '../load-script';
import { mountDocumentViews } from '../mount';
import { BlockView, DocumentView } from '../view';

afterEach( () => {
	document.head.innerHTML = '';
	document.body.innerHTML = '';
	delete window.wpAppFramework;
	vi.restoreAllMocks();
} );

describe( 'document views', () => {
	it( 'mount once for the document element and find elements of the whole document', () => {
		document.body.innerHTML =
			'<nav><button class="toggle">Menu</button></nav><div data-wp-app-framework-views="{}"><p class="toggle">nested</p></div>';
		const views = [];
		class Menu extends DocumentView {
			mount() {
				views.push( this );
			}
		}

		mountDocumentViews(
			'my-theme',
			{ 'assets/menu.view': Menu },
			[ 'assets/menu.view' ],
			document
		);

		expect( views ).toHaveLength( 1 );
		const [ view ] = views;
		expect( view.element ).toBe( document.documentElement );
		expect( view.isPreview ).toBe( false );
		expect(
			view.findAll( '.toggle' ).map( ( element ) => element.textContent )
		).toEqual( [ 'Menu', 'nested' ] );
	} );

	it( 'report missing scripts, wrong base classes and failing mounts; the others still mount', () => {
		const error = vi
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		const mounted = [];
		class Wrong extends BlockView {}
		class Failing extends DocumentView {
			mount() {
				throw new Error( 'boom' );
			}
		}
		class Fine extends DocumentView {
			mount() {
				mounted.push( 'fine' );
			}
		}

		mountDocumentViews(
			'my-theme',
			{
				'assets/wrong.view': Wrong,
				'assets/failing.view': Failing,
				'assets/fine.view': Fine,
			},
			[
				'assets/missing.view',
				'assets/wrong.view',
				'assets/failing.view',
				'assets/fine.view',
			],
			document
		);

		expect( mounted ).toEqual( [ 'fine' ] );
		expect( error.mock.calls.map( ( [ message ] ) => message ) ).toEqual( [
			'wp-app-framework: the document view assets/missing.view of my-theme is not loaded.',
			'wp-app-framework: the default export of the document view assets/wrong.view of my-theme must be a class that extends DocumentView from @saij/wp-app-framework/view.',
			'wp-app-framework: the document view assets/failing.view of my-theme failed to mount.',
		] );
	} );

	it( 'read the view data of their instance', () => {
		window.wpAppFramework = {
			'my-theme': { data: { menu: { open: true } } },
		};
		let value;
		class Menu extends DocumentView {
			mount() {
				value = this.data( 'menu' );
			}
		}

		mountDocumentViews(
			'my-theme',
			{ 'assets/menu.view': Menu },
			[ 'assets/menu.view' ],
			document
		);

		expect( value ).toEqual( { open: true } );
	} );
} );

describe( 'view data', () => {
	it( 'is an error for an unknown key, naming the known ones', () => {
		window.wpAppFramework = { 'my-theme': { data: { menu: 1 } } };

		expect( () => getData( 'my-theme', 'map' ) ).toThrow(
			'wp-app-framework: my-theme has no view data "map" (set it with ViewData::set() in PHP); it has "menu".'
		);
	} );

	it( 'is an error before it is printed', () => {
		expect( () => getData( 'my-theme', 'map' ) ).toThrow(
			'the view data of my-theme is not loaded'
		);
	} );
} );

describe( 'loadScript()', () => {
	const URL = 'https://cdn.example.test/lib.js';

	it( 'adds the script once per URL with its attributes and settles with its load', async () => {
		const first = loadScript( URL, {
			crossorigin: 'anonymous',
			'data-key': 'k',
			defer: true,
			nomodule: false,
		} );
		const second = loadScript( URL, {
			crossorigin: 'anonymous',
			'data-key': 'k',
			defer: true,
			nomodule: false,
		} );
		const scripts = [ ...document.scripts ];

		expect( scripts ).toHaveLength( 1 );
		expect( scripts[ 0 ].getAttribute( 'src' ) ).toBe( URL );
		expect( scripts[ 0 ].getAttribute( 'crossorigin' ) ).toBe(
			'anonymous'
		);
		expect( scripts[ 0 ].getAttribute( 'data-key' ) ).toBe( 'k' );
		expect( scripts[ 0 ].getAttribute( 'defer' ) ).toBe( '' );
		expect( scripts[ 0 ].hasAttribute( 'nomodule' ) ).toBe( false );

		scripts[ 0 ].dispatchEvent( new Event( 'load' ) );
		await expect( first ).resolves.toBeUndefined();
		await expect( second ).resolves.toBeUndefined();
		await expect(
			loadScript( URL, {
				crossorigin: 'anonymous',
				'data-key': 'k',
				defer: true,
				nomodule: false,
			} )
		).resolves.toBeUndefined();
	} );

	it( 'rejects when the script fails to load, also for later calls', async () => {
		const promise = loadScript( URL );
		document.scripts[ 0 ].dispatchEvent( new Event( 'error' ) );

		await expect( promise ).rejects.toThrow(
			`wp-app-framework: the script ${ URL } failed to load.`
		);
		await expect( loadScript( URL ) ).rejects.toThrow( 'failed to load' );
	} );

	it( 'warns about other attributes for a URL already loading', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		loadScript( URL, { integrity: 'sha384-a' } );
		loadScript( URL, { integrity: 'sha384-b' } );

		expect( warn ).toHaveBeenCalledWith(
			`wp-app-framework: ${ URL } is already loaded with other attributes ({"integrity":"sha384-a"}); the attributes {"integrity":"sha384-b"} are ignored.`
		);
	} );

	it( 'treats a script of the page with the same URL as loaded', async () => {
		const script = document.createElement( 'script' );
		script.setAttribute( 'src', URL );
		document.body.append( script );

		await expect( loadScript( URL ) ).resolves.toBeUndefined();
		expect( document.scripts ).toHaveLength( 1 );
	} );

	it( 'loads https: URLs only', async () => {
		await expect(
			loadScript( 'http://cdn.example.test/lib.js' )
		).rejects.toThrow(
			'wp-app-framework: loadScript() loads https: URLs only, got "http://cdn.example.test/lib.js".'
		);
		expect( document.scripts ).toHaveLength( 0 );
	} );
} );
