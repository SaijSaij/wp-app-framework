import { afterEach, describe, expect, it } from 'vitest';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';

// The helper is a CommonJS module run by webpack in Node; load it the same way.
const require = createRequire( import.meta.url );
const framework = path.resolve( __dirname, '../..' );
const createConfig = require( path.join( framework, 'webpack.config.js' ) );

const roots = [];

/**
 * A consumer directory whose node_modules links to packages of the framework's node_modules.
 *
 * @param {Object}                 options
 * @param {boolean}                options.styles   Whether the consumer has assets/styles.css.
 * @param {Array<string>}          options.packages Packages installed in the consumer.
 * @param {Object<string, string>} [options.files]  Further files below src/ by path, with their content.
 * @param {Object<string, string>} [options.assets] Further files below assets/ by path, with their content.
 * @return {string} Root directory of the consumer.
 */
function consumer( { styles, packages, files = {}, assets = {} } ) {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'wpaf-webpack-' ) );
	roots.push( root );
	fs.writeFileSync( path.join( root, 'package.json' ), '{}' );
	fs.mkdirSync( path.join( root, 'src' ) );
	const write = ( directory, entries ) => {
		for ( const [ file, content ] of Object.entries( entries ) ) {
			const target = path.join( root, directory, file );
			fs.mkdirSync( path.dirname( target ), { recursive: true } );
			fs.writeFileSync( target, content );
		}
	};
	write( 'src', files );
	write( 'assets', styles ? { ...assets, 'styles.css': '' } : assets );
	for ( const name of packages ) {
		const link = path.join( root, 'node_modules', name );
		fs.mkdirSync( path.dirname( link ), { recursive: true } );
		fs.symlinkSync( path.join( framework, 'node_modules', name ), link );
	}
	return root;
}

const TOOLCHAIN = [
	'@wordpress/scripts',
	'@wordpress/dependency-extraction-webpack-plugin',
	'webpack',
];
const TAILWIND = [ 'tailwindcss', '@tailwindcss/webpack' ];

afterEach( () => {
	for ( const root of roots.splice( 0 ) ) {
		fs.rmSync( root, { recursive: true, force: true } );
	}
} );

describe( 'webpack helper', () => {
	it( 'builds the editor runtime, the canvas styles, the settings page and the meta boxes', () => {
		const root = consumer( { styles: false, packages: TOOLCHAIN } );
		const entry = createConfig( { slug: 'my-theme', root } ).entry();

		expect( entry[ 'vendor/wp-app-framework/editor' ] ).toBe(
			path.join( framework, 'assets', 'editor.js' )
		);
		expect( entry[ 'vendor/wp-app-framework/canvas' ] ).toBe(
			path.join( framework, 'assets', 'canvas.scss' )
		);
		expect( entry[ 'vendor/wp-app-framework/settings' ] ).toBe(
			path.join( framework, 'assets', 'settings.js' )
		);
		expect( entry[ 'vendor/wp-app-framework/meta-box' ] ).toBe(
			path.join( framework, 'assets', 'meta-box.js' )
		);
	} );

	it( 'builds no stylesheet and needs no Tailwind without assets/styles.css', () => {
		const root = consumer( { styles: false, packages: TOOLCHAIN } );
		const config = createConfig( { slug: 'my-theme', root } );

		expect( Object.keys( config.entry() ) ).not.toContain( 'styles' );
		expect(
			config.module.rules.some( ( rule ) =>
				String( rule.test ).includes( 'styles.css' )
			)
		).toBe( false );
	} );

	it( 'compiles assets/styles.css with Tailwind instead of PostCSS', () => {
		const root = consumer( {
			styles: true,
			packages: [ ...TOOLCHAIN, ...TAILWIND ],
		} );
		const styles = path.join( root, 'assets', 'styles.css' );
		const config = createConfig( { slug: 'my-theme', root } );

		expect( config.entry().styles ).toBe( styles );

		const cssRule = config.module.rules.find(
			( rule ) => String( rule.test ) === String( /\.css$/ )
		);
		expect( cssRule.exclude ).toBe( styles );

		const stylesRule = config.module.rules.find(
			( rule ) => rule.test === styles
		);
		const loaders = stylesRule.use.map( ( use ) => use.loader ?? use );
		expect( loaders[ 0 ] ).toBe(
			cssRule.use[ 0 ].loader ?? cssRule.use[ 0 ]
		);
		expect( loaders[ 1 ] ).toContain( 'css-loader' );
		expect( loaders[ 2 ] ).toContain( '@tailwindcss/webpack' );
		expect( loaders ).toHaveLength( 3 );
		expect( stylesRule.use[ 2 ].options ).toEqual( { base: root } );
	} );

	it( 'fails for a stylesheet in its former place, src/styles.css', () => {
		const root = consumer( {
			styles: false,
			packages: [ ...TOOLCHAIN, ...TAILWIND ],
			files: { 'styles.css': '' },
		} );

		expect( () => createConfig( { slug: 'my-theme', root } ) ).toThrow(
			'src/styles.css is no longer built; move it to assets/styles.css'
		);
	} );

	it( 'names the packages to install if Tailwind is missing', () => {
		const root = consumer( { styles: true, packages: TOOLCHAIN } );

		expect( () => createConfig( { slug: 'my-theme', root } ) ).toThrow(
			'install it in the consumer: npm install --save-dev tailwindcss@4 @tailwindcss/webpack@4'
		);
	} );

	it( 'builds the view runtime and the editor and view scripts next to the code', () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files: {
				'Block/Hero/Hero.editor.js': '',
				'Block/Faq/Faq.view.js': '',
				'Block/Faq/Faq.php': '',
			},
		} );
		const config = createConfig( { slug: 'my-theme', root } );
		const entry = config.entry();

		expect( entry[ 'vendor/wp-app-framework/view' ] ).toBe(
			path.join( framework, 'assets', 'view.js' )
		);
		expect( entry[ 'Block/Hero/Hero.editor' ] ).toBe(
			path.join( root, 'src', 'Block/Hero/Hero.editor.js' )
		);
		expect( entry[ 'Block/Faq/Faq.view' ] ).toEqual( {
			import: path.join( root, 'src', 'Block/Faq/Faq.view.js' ),
			library: {
				type: 'window',
				name: [
					'wpAppFramework',
					'my-theme',
					'views',
					'Block/Faq/Faq.view',
				],
				export: 'default',
			},
		} );
		expect( config.output.enabledLibraryTypes ).toEqual( [ 'window' ] );
	} );

	it( 'builds the document views, editor scripts and shared modules in assets/', () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			assets: {
				'menu.view.js': '',
				'editor/highlight.editor.js': '',
				'shared/ui/modal.js': '',
				'shared/ui/panel.view.js': '',
				'helpers/format.js': '',
			},
		} );
		fs.writeFileSync(
			path.join( root, 'package.json' ),
			JSON.stringify( {
				imports: { '#shared/*': './assets/shared/*.js' },
			} )
		);
		const entry = createConfig( { slug: 'my-theme', root } ).entry();

		expect( entry[ 'assets/menu.view' ] ).toEqual( {
			import: path.join( root, 'assets', 'menu.view.js' ),
			library: {
				type: 'window',
				name: [
					'wpAppFramework',
					'my-theme',
					'views',
					'assets/menu.view',
				],
				export: 'default',
			},
		} );
		expect( entry[ 'assets/shared/ui/panel.view' ].library.name ).toEqual( [
			'wpAppFramework',
			'my-theme',
			'views',
			'assets/shared/ui/panel.view',
		] );
		expect( entry[ 'assets/editor/highlight.editor' ] ).toBe(
			path.join( root, 'assets', 'editor/highlight.editor.js' )
		);
		expect( entry[ 'assets/shared/ui/modal' ] ).toEqual( {
			import: path.join( root, 'assets', 'shared/ui/modal.js' ),
			library: {
				type: 'window',
				name: [ 'wpAppFramework', 'my-theme', 'shared', 'ui/modal' ],
			},
		} );
		expect( entry ).not.toHaveProperty( 'assets/helpers/format' );
	} );

	it( 'fails for a script in src/assets/, whose entry would collide with assets/', () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files: { 'assets/menu.view.js': '' },
		} );

		expect( () =>
			createConfig( { slug: 'my-theme', root } ).entry()
		).toThrow(
			'src/assets/menu.view.js has the same entry name as a script in assets/'
		);
	} );

	it( 'needs the imports of shared modules in package.json', () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			assets: { 'shared/counter.js': '' },
		} );

		expect( () => createConfig( { slug: 'my-theme', root } ) ).toThrow(
			'add "imports": { "#shared/*": "./assets/shared/*.js" } to'
		);
	} );

	/**
	 * A consumer with shared modules and the imports for them in its package.json.
	 *
	 * @param {Object<string, string>} assets  Files below assets/.
	 * @param {Object<string, string>} [files] Files below src/.
	 * @return {string} Root directory.
	 */
	function consumerWithShared( assets, files = {} ) {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files,
			assets,
		} );
		fs.writeFileSync(
			path.join( root, 'package.json' ),
			JSON.stringify( {
				imports: { '#shared/*': './assets/shared/*.js' },
			} )
		);
		return root;
	}

	/**
	 * Builds only the view scripts of a consumer with webpack.
	 *
	 * @param {string} root
	 * @return {Promise<import('webpack').Stats>} Result.
	 */
	function buildViews( root ) {
		const config = createConfig( { slug: 'my-theme', root } );
		const entry = config.entry;
		const webpack = require(
			path.join( framework, 'node_modules', 'webpack' )
		);
		return new Promise( ( resolve, reject ) => {
			webpack(
				{
					...config,
					mode: 'development',
					devtool: false,
					entry: () =>
						Object.fromEntries(
							Object.entries( entry() ).filter(
								( [ name ] ) =>
									name.endsWith( '.view' ) ||
									name.endsWith( '.editor' ) ||
									name.startsWith( 'assets/shared/' )
							)
						),
				},
				( error, stats ) =>
					error ? reject( error ) : resolve( stats )
			);
		} );
	}

	it( 'assigns the view class and maps the view runtime to the instance', async () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files: {
				'Block/Faq/Faq.view.js': [
					"import { BlockView } from '@saij/wp-app-framework/view';",
					"import domReady from '@wordpress/dom-ready';",
					'export default class Faq extends BlockView { mount() { domReady( () => {} ); } }',
				].join( '\n' ),
			},
		} );

		const stats = await buildViews( root );

		expect( stats.hasErrors() ).toBe( false );
		const script = fs.readFileSync(
			path.join( root, 'build', 'Block/Faq/Faq.view.js' ),
			'utf8'
		);
		expect( script ).toContain( 'window.wpAppFramework["my-theme"].view' );
		expect( script ).toContain( '["Block/Faq/Faq.view"]' );
		const asset = fs.readFileSync(
			path.join( root, 'build', 'Block/Faq/Faq.view.asset.php' ),
			'utf8'
		);
		expect( asset ).toContain( "'my-theme-wp-app-framework-view'" );
		expect( asset ).toContain( "'wp-dom-ready'" );
	}, 30000 );

	it( 'maps the editor import to the public API of the instance', async () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files: {
				'Block/Faq/Faq.php': '<?php',
				'Block/Faq/Faq.editor.js': [
					"import { registerPanel } from '@saij/wp-app-framework';",
					"registerPanel( 'faq', () => null );",
				].join( '\n' ),
			},
		} );

		const stats = await buildViews( root );

		expect( stats.hasErrors() ).toBe( false );
		const script = fs.readFileSync(
			path.join( root, 'build', 'Block/Faq/Faq.editor.js' ),
			'utf8'
		);
		expect( script ).toContain(
			'module.exports = window["wpAppFramework"]["my-theme"]["api"];'
		);
	}, 30000 );

	it( 'fails when a view script imports the editor runtime', async () => {
		const root = consumer( {
			styles: false,
			packages: TOOLCHAIN,
			files: {
				'Block/Faq/Faq.view.js': [
					"import { registerPanel } from '@saij/wp-app-framework';",
					'export default registerPanel;',
				].join( '\n' ),
			},
		} );

		const stats = await buildViews( root );

		expect(
			stats.toJson().errors.map( ( error ) => error.message )
		).toEqual( [
			'wp-app-framework: the view script src/Block/Faq/Faq.view.js imports @saij/wp-app-framework, the editor runtime, which is not loaded on the front end; view scripts import @saij/wp-app-framework/view.',
		] );
	}, 30000 );

	it( 'maps shared modules to the global and the script handle of the instance', async () => {
		const root = consumerWithShared( {
			'menu.view.js': [
				"import { DocumentView } from '@saij/wp-app-framework/view';",
				"import { count } from '#shared/ui/Counter';",
				'export default class Menu extends DocumentView { mount() { count(); } }',
			].join( '\n' ),
			'shared/ui/Counter.js': 'export function count() {}',
		} );

		const stats = await buildViews( root );

		expect( stats.hasErrors() ).toBe( false );
		const script = fs.readFileSync(
			path.join( root, 'build', 'assets/menu.view.js' ),
			'utf8'
		);
		expect( script ).toContain(
			'window["wpAppFramework"]["my-theme"]["shared"]["ui/Counter"]'
		);
		const asset = fs.readFileSync(
			path.join( root, 'build', 'assets/menu.view.asset.php' ),
			'utf8'
		);
		expect( asset ).toContain( "'my-theme-shared-ui-counter'" );
		const shared = fs.readFileSync(
			path.join( root, 'build', 'assets/shared/ui/Counter.js' ),
			'utf8'
		);
		expect( shared ).toContain( '["ui/Counter"]' );
	}, 30000 );

	it( 'fails for an import of an unknown shared module', async () => {
		const root = consumerWithShared( {
			'menu.view.js': "import '#shared/missing';",
			'shared/counter.js': '',
		} );

		const stats = await buildViews( root );

		expect(
			stats
				.toJson()
				.errors.some( ( error ) =>
					error.message.includes(
						'#shared/missing is no shared module: assets/shared/missing.js does not exist.'
					)
				)
		).toBe( true );
	}, 30000 );

	it( 'fails when a view script reaches the editor runtime through a shared module', async () => {
		const root = consumerWithShared(
			{
				'shared/format.js': [
					"import { registerPanel } from '@saij/wp-app-framework';",
					'export default registerPanel;',
				].join( '\n' ),
			},
			{
				'Block/Faq/Faq.view.js': "import '#shared/format';",
			}
		);

		const stats = await buildViews( root );

		expect(
			stats.toJson().errors.map( ( error ) => error.message )
		).toEqual( [
			'wp-app-framework: the view script src/Block/Faq/Faq.view.js uses assets/shared/format.js (src/Block/Faq/Faq.view.js → assets/shared/format.js), which imports @saij/wp-app-framework, the editor runtime, which is not loaded on the front end; modules used by view scripts import @saij/wp-app-framework/view.',
		] );
	}, 30000 );

	it( 'fails for shared modules that import each other in a cycle', async () => {
		const root = consumerWithShared( {
			'shared/a.js': "import '#shared/b';",
			'shared/b.js': "import '#shared/a';",
		} );

		const stats = await buildViews( root );

		expect(
			stats.toJson().errors.map( ( error ) => error.message )
		).toEqual( [
			'wp-app-framework: the shared modules import each other in a cycle: assets/shared/a.js → assets/shared/b.js → assets/shared/a.js.',
		] );
	}, 30000 );

	it( 'lists the built files of the fonts marked for preloading', async () => {
		const root = consumer( {
			styles: false,
			packages: [ ...TOOLCHAIN, ...TAILWIND ],
			assets: {
				'fonts/marked.woff2': 'woff2',
				'fonts/plain.woff2': 'woff2',
				'styles.css': [
					'@font-face { /* wp-app-framework: preload */ font-family: Marked; src: url("./fonts/marked.woff2") format("woff2"); }',
					'@font-face { font-family: Plain; src: url("./fonts/plain.woff2") format("woff2"); }',
					'.a { font-family: Marked, Plain; }',
				].join( '\n' ),
			},
		} );
		const config = createConfig( { slug: 'my-theme', root } );
		const webpack = require(
			path.join( framework, 'node_modules', 'webpack' )
		);

		const stats = await new Promise( ( resolve, reject ) =>
			webpack(
				{
					...config,
					mode: 'development',
					devtool: false,
					entry: () => ( { styles: config.entry().styles } ),
				},
				( error, result ) =>
					error ? reject( error ) : resolve( result )
			)
		);

		expect( stats.hasErrors() ).toBe( false );
		const list = fs.readFileSync(
			path.join( root, 'build', 'styles.preload.php' ),
			'utf8'
		);
		expect( list ).toMatch(
			/^<\?php return array\( 'fonts\/marked\.[0-9a-f]+\.woff2' \);\n$/
		);
	}, 30000 );

	it( 'fails for a font marked for preloading without a woff2 file of the project', async () => {
		const root = consumer( {
			styles: false,
			packages: [ ...TOOLCHAIN, ...TAILWIND ],
			assets: {
				'styles.css':
					'@font-face { /* wp-app-framework: preload */ font-family: Remote; src: url("https://fonts.example.com/a.woff2"); }',
			},
		} );
		const config = createConfig( { slug: 'my-theme', root } );
		const webpack = require(
			path.join( framework, 'node_modules', 'webpack' )
		);

		const stats = await new Promise( ( resolve, reject ) =>
			webpack(
				{
					...config,
					mode: 'development',
					devtool: false,
					entry: () => ( { styles: config.entry().styles } ),
				},
				( error, result ) =>
					error ? reject( error ) : resolve( result )
			)
		);

		expect(
			stats.toJson().errors.map( ( error ) => error.message )
		).toEqual( [
			`wp-app-framework: an @font-face in ${ path.join(
				root,
				'assets',
				'styles.css'
			) } is marked for preloading, but has no woff2 file of the project in its src (url("./fonts/….woff2")).`,
		] );
	}, 30000 );
} );
