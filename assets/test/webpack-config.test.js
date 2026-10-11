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
 * @param {Object}        options
 * @param {boolean}       options.styles   Whether the consumer has src/styles.css.
 * @param {Array<string>} options.packages Packages installed in the consumer.
 * @return {string} Root directory of the consumer.
 */
function consumer( { styles, packages } ) {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'wpaf-webpack-' ) );
	roots.push( root );
	fs.writeFileSync( path.join( root, 'package.json' ), '{}' );
	fs.mkdirSync( path.join( root, 'src' ) );
	if ( styles ) {
		fs.writeFileSync( path.join( root, 'src', 'styles.css' ), '' );
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
} );
