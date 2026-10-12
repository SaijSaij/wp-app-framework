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
 * @param {boolean}                options.styles   Whether the consumer has src/styles.css.
 * @param {Array<string>}          options.packages Packages installed in the consumer.
 * @param {Object<string, string>} [options.files]  Further files below src/ by path, with their content.
 * @return {string} Root directory of the consumer.
 */
function consumer( { styles, packages, files = {} } ) {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'wpaf-webpack-' ) );
	roots.push( root );
	fs.writeFileSync( path.join( root, 'package.json' ), '{}' );
	fs.mkdirSync( path.join( root, 'src' ) );
	if ( styles ) {
		fs.writeFileSync( path.join( root, 'src', 'styles.css' ), '' );
	}
	for ( const [ file, content ] of Object.entries( files ) ) {
		fs.mkdirSync( path.dirname( path.join( root, 'src', file ) ), {
			recursive: true,
		} );
		fs.writeFileSync( path.join( root, 'src', file ), content );
	}
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
	it( 'builds the editor runtime, the canvas styles and the settings page', () => {
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
	} );

	it( 'builds no stylesheet and needs no Tailwind without src/styles.css', () => {
		const root = consumer( { styles: false, packages: TOOLCHAIN } );
		const config = createConfig( { slug: 'my-theme', root } );

		expect( Object.keys( config.entry() ) ).not.toContain( 'styles' );
		expect(
			config.module.rules.some( ( rule ) =>
				String( rule.test ).includes( 'styles.css' )
			)
		).toBe( false );
	} );

	it( 'compiles src/styles.css with Tailwind instead of PostCSS', () => {
		const root = consumer( {
			styles: true,
			packages: [ ...TOOLCHAIN, ...TAILWIND ],
		} );
		const styles = path.join( root, 'src', 'styles.css' );
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
							Object.entries( entry() ).filter( ( [ name ] ) =>
								name.endsWith( '.view' )
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
} );
