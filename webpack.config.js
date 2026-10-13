/**
 * Webpack configuration helper for consumers of the framework (themes and plugins).
 *
 * Builds, into the consumer's `build/` directory:
 * - the editor runtime of this framework instance (build/vendor/wp-app-framework/editor.js),
 * - the script of its settings page (build/vendor/wp-app-framework/settings.js),
 * - the view runtime of this framework instance (build/vendor/wp-app-framework/view.js),
 * - the consumer's editor scripts next to its code (src/Block/Hero/Hero.editor.js →
 *   build/Block/Hero/Hero.editor.js),
 * - the consumer's view scripts next to its code (src/Block/Faq/Faq.view.js →
 *   build/Block/Faq/Faq.view.js); the class each exports by default is assigned to
 *   wpAppFramework[slug].views['Block/Faq/Faq.view'], where the view runtime finds it,
 * - the consumer's stylesheet, if it has one: src/styles.css → build/styles.css, compiled
 *   with Tailwind CSS (`tailwindcss` and `@tailwindcss/webpack`, installed by the consumer).
 *
 * Imports of `@saij/wp-app-framework` resolve to the runtime of the same instance
 * (global `wpAppFramework[slug]`, script handle `{slug}-wp-app-framework`), imports of
 * `@saij/wp-app-framework/view` to its view runtime (global `wpAppFramework[slug].view`,
 * script handle `{slug}-wp-app-framework-view`). A view script must not import
 * `@saij/wp-app-framework`: the editor runtime is not loaded on the front end.
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
const VIEW_SCRIPT_SUFFIX = '.view.js';
const RUNTIME_ENTRY = 'vendor/wp-app-framework/editor';
const VIEW_ENTRY = 'vendor/wp-app-framework/view';
const CANVAS_ENTRY = 'vendor/wp-app-framework/canvas';
const SETTINGS_ENTRY = 'vendor/wp-app-framework/settings';
const META_BOX_ENTRY = 'vendor/wp-app-framework/meta-box';
const IMPORT_NAME = '@saij/wp-app-framework';
const VIEW_IMPORT_NAME = '@saij/wp-app-framework/view';
const STYLES_ENTRY = 'styles';
const STYLES_SOURCE = 'styles.css';

/** Major version of Tailwind CSS the stylesheet is built with (peer dependency). */
const TAILWIND_MAJOR = 4;

/**
 * Scripts with a suffix below a directory, by entry name (path without extension).
 *
 * @param {string} directory
 * @param {string} suffix    E.g. ".editor.js".
 * @return {Object<string, string>} Entries.
 */
function scripts( directory, suffix ) {
	const entries = {};
	if ( ! fs.existsSync( directory ) ) {
		return entries;
	}
	for ( const file of fs.readdirSync( directory, { recursive: true } ) ) {
		const relative = String( file ).split( path.sep ).join( '/' );
		if ( relative.endsWith( suffix ) ) {
			entries[ relative.slice( 0, -'.js'.length ) ] = path.join(
				directory,
				relative
			);
		}
	}
	return entries;
}

/**
 * Entries of the view scripts below a directory: each assigns its default export (the view class)
 * to wpAppFramework[slug].views[entry name].
 *
 * @param {string} directory
 * @param {string} slug
 * @return {Object<string, Object>} Entries.
 */
function viewScripts( directory, slug ) {
	return Object.fromEntries(
		Object.entries( scripts( directory, VIEW_SCRIPT_SUFFIX ) ).map(
			( [ name, file ] ) => [
				name,
				{
					import: file,
					library: {
						type: 'window',
						name: [ 'wpAppFramework', slug, 'views', name ],
						export: 'default',
					},
				},
			]
		)
	);
}

/**
 * Fails the build when a view script (an entry ending in ".view") imports the editor runtime.
 */
class ViewImportCheck {
	apply( compiler ) {
		compiler.hooks.compilation.tap( 'wp-app-framework', ( compilation ) => {
			compilation.hooks.afterChunks.tap( 'wp-app-framework', () => {
				for ( const [ name, entrypoint ] of compilation.entrypoints ) {
					if (
						! name.endsWith( VIEW_SCRIPT_SUFFIX.slice( 0, -3 ) )
					) {
						continue;
					}
					const imports = entrypoint.chunks.some( ( chunk ) =>
						[
							...compilation.chunkGraph.getChunkModulesIterable(
								chunk
							),
						].some(
							( module ) => module.userRequest === IMPORT_NAME
						)
					);
					if ( imports ) {
						compilation.errors.push(
							new compiler.webpack.WebpackError(
								`wp-app-framework: the view script src/${ name }.js imports ${ IMPORT_NAME }, the editor runtime, which is not loaded on the front end; view scripts import ${ VIEW_IMPORT_NAME }.`
							)
						);
					}
				}
			} );
		} );
	}
}

/**
 * Module rule that compiles the consumer's stylesheet with Tailwind CSS. It reuses the
 * extract loader and css-loader of the `.css` rule of `@wordpress/scripts` and replaces its
 * postcss-loader (a postcss.config.js in the consumer would replace the PostCSS setup of
 * `@wordpress/scripts` for every stylesheet).
 *
 * @param {Object}                      defaults Configuration of `@wordpress/scripts`.
 * @param {string}                      styles   Path of the stylesheet.
 * @param {string}                      root     Root directory of the consumer.
 * @param {(request: string) => Object} fromRoot Requires a module installed in the consumer.
 * @return {{cssRule: Object, stylesRule: Object}} The `.css` rule of `@wordpress/scripts` and the new rule.
 */
function tailwindRule( defaults, styles, root, fromRoot ) {
	let tailwindVersion;
	let loader;
	try {
		tailwindVersion = fromRoot( 'tailwindcss/package.json' ).version;
		loader = require.resolve( '@tailwindcss/webpack', { paths: [ root ] } );
	} catch {
		throw new Error(
			`wp-app-framework: ${ styles } is built with Tailwind CSS ${ TAILWIND_MAJOR }; install it in the consumer: npm install --save-dev tailwindcss@${ TAILWIND_MAJOR } @tailwindcss/webpack@${ TAILWIND_MAJOR }`
		);
	}
	if ( Number( tailwindVersion.split( '.' )[ 0 ] ) !== TAILWIND_MAJOR ) {
		throw new Error(
			`wp-app-framework: the stylesheet needs tailwindcss ${ TAILWIND_MAJOR }.x, the consumer has ${ tailwindVersion }.`
		);
	}

	const cssRule = defaults.module.rules.find(
		( rule ) => String( rule.test ) === String( /\.css$/ )
	);
	const [ extractLoader, cssLoader, postcssLoader ] = cssRule?.use ?? [];
	if (
		! cssLoader?.loader?.includes( 'css-loader' ) ||
		! postcssLoader?.loader?.includes( 'postcss-loader' )
	) {
		throw new Error(
			'wp-app-framework: the .css rule of @wordpress/scripts has changed (expected the extract loader, css-loader and postcss-loader).'
		);
	}

	return {
		cssRule,
		stylesRule: {
			test: styles,
			use: [
				extractLoader,
				cssLoader,
				// Without source(none), Tailwind detects sources below the consumer's root (default: the working directory).
				{ loader, options: { base: root } },
			],
		},
	};
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

	// Checked once: after adding or removing the stylesheet, restart the watcher.
	const styles = path.join( root, 'src', STYLES_SOURCE );
	const tailwind = fs.existsSync( styles )
		? tailwindRule( defaults, styles, root, fromRoot )
		: null;

	return {
		...defaults,
		// A function, so a new editor or view script is picked up when the watcher rebuilds.
		entry: () => ( {
			[ RUNTIME_ENTRY ]: path.join( __dirname, 'assets', 'editor.js' ),
			// Styles of the editor canvas (iframe), loaded separately from the runtime.
			[ CANVAS_ENTRY ]: path.join( __dirname, 'assets', 'canvas.scss' ),
			// The settings page of the instance (admin page with the forms of its settings groups).
			[ SETTINGS_ENTRY ]: path.join( __dirname, 'assets', 'settings.js' ),
			// The meta boxes of the classic edit screen (forms of the meta classes).
			[ META_BOX_ENTRY ]: path.join( __dirname, 'assets', 'meta-box.js' ),
			// The view runtime: base classes of view scripts, mounts their views.
			[ VIEW_ENTRY ]: path.join( __dirname, 'assets', 'view.js' ),
			...scripts( path.join( root, 'src' ), EDITOR_SCRIPT_SUFFIX ),
			...viewScripts( path.join( root, 'src' ), slug ),
			...( tailwind ? { [ STYLES_ENTRY ]: styles } : {} ),
		} ),
		module: tailwind
			? {
					...defaults.module,
					rules: [
						...defaults.module.rules.map( ( rule ) =>
							rule === tailwind.cssRule
								? { ...rule, exclude: styles }
								: rule
						),
						tailwind.stylesRule,
					],
				}
			: defaults.module,
		output: {
			...defaults.output,
			path: path.join( root, 'build' ),
			// The entry is a function: the library type of the view scripts must be enabled up front.
			enabledLibraryTypes: [ 'window' ],
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
			new ViewImportCheck(),
			new webpack.DefinePlugin( {
				WP_APP_FRAMEWORK_SLUG: JSON.stringify( slug ),
			} ),
			new DependencyExtractionWebpackPlugin( {
				requestToExternal: ( request ) => {
					if ( request === IMPORT_NAME ) {
						return [ 'wpAppFramework', slug ];
					}
					return request === VIEW_IMPORT_NAME
						? [ 'wpAppFramework', slug, 'view' ]
						: undefined;
				},
				requestToHandle: ( request ) => {
					if ( request === IMPORT_NAME ) {
						return `${ slug }-wp-app-framework`;
					}
					return request === VIEW_IMPORT_NAME
						? `${ slug }-wp-app-framework-view`
						: undefined;
				},
			} ),
		],
	};
};
