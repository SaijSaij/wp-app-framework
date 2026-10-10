/**
 * Webpack configuration helper for consumers of the framework (themes and plugins).
 *
 * Builds, into the consumer's `build/` directory:
 * - the editor runtime of this framework instance (build/vendor/wp-app-framework/editor.js),
 * - the consumer's editor scripts next to its code (src/Block/Hero/Hero.editor.js →
 *   build/Block/Hero/Hero.editor.js).
 *
 * Imports of `@saij/wp-app-framework` resolve to the runtime of the same instance
 * (global `wpAppFramework[slug]`, script handle `{slug}-wp-app-framework`).
 *
 * Usage in the consumer's webpack.config.js:
 *
 *     module.exports = require( './vendor/saij/wp-app-framework/webpack.config.js' )( {
 *         slug: 'theme-dev-test',
 *         root: __dirname,
 *     } );
 */
const fs = require( 'fs' );
const path = require( 'path' );

/** Major version of `@wordpress/scripts` the runtime is written for (peer dependency). */
const SCRIPTS_MAJOR = 36;

const EDITOR_SCRIPT_SUFFIX = '.editor.js';
const RUNTIME_ENTRY = 'vendor/wp-app-framework/editor';
const CANVAS_ENTRY = 'vendor/wp-app-framework/canvas';
const IMPORT_NAME = '@saij/wp-app-framework';

/**
 * Editor scripts below a directory, by entry name (path without extension).
 *
 * @param {string} directory
 * @return {Object<string, string>} Entries.
 */
function editorScripts( directory ) {
	const entries = {};
	if ( ! fs.existsSync( directory ) ) {
		return entries;
	}
	for ( const file of fs.readdirSync( directory, { recursive: true } ) ) {
		const relative = String( file ).split( path.sep ).join( '/' );
		if ( relative.endsWith( EDITOR_SCRIPT_SUFFIX ) ) {
			entries[ relative.slice( 0, -'.js'.length ) ] = path.join(
				directory,
				relative
			);
		}
	}
	return entries;
}

/**
 * @param {Object} options
 * @param {string} options.slug Slug of the framework instance: the directory name of the theme (get_template()) or plugin.
 * @param {string} options.root Root directory of the consumer.
 * @return {Object} Webpack configuration.
 */
module.exports = function createConfig( { slug, root } = {} ) {
	if ( typeof slug !== 'string' || ! /^[a-z0-9_-]+$/.test( slug ) ) {
		throw new Error(
			`wp-app-framework: "slug" must be the directory name of the theme (get_template()) or plugin, got ${ JSON.stringify( slug ) }.`
		);
	}
	if (
		typeof root !== 'string' ||
		! fs.existsSync( path.join( root, 'package.json' ) )
	) {
		throw new Error(
			`wp-app-framework: "root" must be the consumer's directory with its package.json, got ${ JSON.stringify( root ) }.`
		);
	}

	// Resolve the toolchain from the consumer: this file lives in its vendor directory (or its real path).
	const fromRoot = ( request ) =>
		require( require.resolve( request, { paths: [ root ] } ) );
	const scriptsVersion = fromRoot(
		'@wordpress/scripts/package.json'
	).version;
	if ( Number( scriptsVersion.split( '.' )[ 0 ] ) !== SCRIPTS_MAJOR ) {
		throw new Error(
			`wp-app-framework: the editor runtime needs @wordpress/scripts ${ SCRIPTS_MAJOR }.x, the consumer has ${ scriptsVersion }.`
		);
	}
	const defaults = fromRoot( '@wordpress/scripts/config/webpack.config' );
	if ( Array.isArray( defaults ) ) {
		throw new Error(
			'wp-app-framework: the @wordpress/scripts configuration for script modules (--experimental-modules) is not supported.'
		);
	}
	const webpack = fromRoot( 'webpack' );
	const DependencyExtractionWebpackPlugin = fromRoot(
		'@wordpress/dependency-extraction-webpack-plugin'
	);

	return {
		...defaults,
		// A function, so a new editor script is picked up when the watcher rebuilds.
		entry: () => ( {
			[ RUNTIME_ENTRY ]: path.join( __dirname, 'js', 'editor.js' ),
			// Styles of the editor canvas (iframe), loaded separately from the runtime.
			[ CANVAS_ENTRY ]: path.join( __dirname, 'js', 'canvas.scss' ),
			...editorScripts( path.join( root, 'src' ) ),
		} ),
		output: {
			...defaults.output,
			path: path.join( root, 'build' ),
		},
		resolve: {
			...defaults.resolve,
			// The runtime sources are reached through a symlink in development; webpack resolves it to the
			// real path outside the consumer, where the runtime's npm dependencies are not installed.
			modules: [ path.join( root, 'node_modules' ), 'node_modules' ],
		},
		plugins: [
			...defaults.plugins.filter(
				( plugin ) =>
					plugin.constructor.name !==
					'DependencyExtractionWebpackPlugin'
			),
			new webpack.DefinePlugin( {
				WP_APP_FRAMEWORK_SLUG: JSON.stringify( slug ),
			} ),
			new DependencyExtractionWebpackPlugin( {
				requestToExternal: ( request ) =>
					request === IMPORT_NAME
						? [ 'wpAppFramework', slug ]
						: undefined,
				requestToHandle: ( request ) =>
					request === IMPORT_NAME
						? `${ slug }-wp-app-framework`
						: undefined,
			} ),
		],
	};
};
