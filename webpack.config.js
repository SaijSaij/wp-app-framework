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
 * - the scripts in the consumer's assets/ directory, which belong to no PHP class:
 *   document views (assets/menu.view.js → build/assets/menu.view.js, class under
 *   wpAppFramework[slug].views['assets/menu.view']), editor scripts without a block
 *   (assets/format.editor.js) and shared modules (assets/shared/counter.js, exports under
 *   wpAppFramework[slug].shared.counter, imported anywhere in the instance as
 *   `#shared/counter`, script handle `{slug}-shared-counter`),
 * - the consumer's stylesheet, if it has one: assets/styles.css → build/styles.css, compiled
 *   with Tailwind CSS (`tailwindcss` and `@tailwindcss/webpack`, installed by the consumer).
 *   Until 0.13 the stylesheet was src/styles.css; a file there fails the build. Fonts marked
 *   with the comment `/* wp-app-framework: preload *\/` in their `@font-face` are listed in
 *   build/styles.preload.php, from which the framework prints <link rel="preload">.
 *
 * Imports of `@webcodingcat/wp-app-framework` resolve to the runtime of the same instance
 * (global `wpAppFramework[slug].api`, script handle `{slug}-wp-app-framework`), imports of
 * `@webcodingcat/wp-app-framework/view` to its view runtime (global `wpAppFramework[slug].view`,
 * script handle `{slug}-wp-app-framework-view`). A view script must not import
 * `@webcodingcat/wp-app-framework`: the editor runtime is not loaded on the front end.
 *
 * Usage in the consumer's webpack.config.js:
 *
 *     module.exports = require( '@webcodingcat/wp-app-framework/webpack' )( {
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
const SETTINGS_SCRIPT_SUFFIX = '.settings.js';
const RUNTIME_ENTRY = 'vendor/wp-app-framework/editor';
const VIEW_ENTRY = 'vendor/wp-app-framework/view';
const CANVAS_ENTRY = 'vendor/wp-app-framework/canvas';
const SETTINGS_ENTRY = 'vendor/wp-app-framework/settings';
const META_BOX_ENTRY = 'vendor/wp-app-framework/meta-box';
const IMPORT_NAME = '@webcodingcat/wp-app-framework';
const VIEW_IMPORT_NAME = '@webcodingcat/wp-app-framework/view';
const SETTINGS_IMPORT_NAME = '@webcodingcat/wp-app-framework/settings';
const STYLES_ENTRY = 'styles';
const STYLES_SOURCE = 'styles.css';
/** Directory of the consumer for everything without a PHP class: stylesheet, scripts without a block, static files. */
const ASSET_DIRECTORY = 'assets';
/** Directory below assets/ with the shared modules. */
const SHARED_DIRECTORY = 'shared';
/** Prefix of imports of shared modules, mapped by `imports` in the consumer's package.json for IDEs. */
const SHARED_IMPORT_PREFIX = '#shared/';
const SHARED_IMPORTS_KEY = '#shared/*';
const SHARED_IMPORTS_TARGET = './assets/shared/*.js';

/** Comment in an `@font-face` of the stylesheet that asks for a preload of its woff2 font. */
const PRELOAD_MARKER = /\/\*\s*wp-app-framework:\s*preload\s*\*\//;
/** List of the preloaded fonts, below build/. */
const PRELOAD_FILE = 'styles.preload.php';

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
 * @param {string} [prefix]  Prefix of the entry names, e.g. "assets/".
 * @return {Object<string, Object>} Entries.
 */
function viewScripts( directory, slug, prefix = '' ) {
	return Object.fromEntries(
		Object.entries( scripts( directory, VIEW_SCRIPT_SUFFIX ) ).map(
			( [ name, file ] ) => [
				prefix + name,
				{
					import: file,
					library: {
						type: 'window',
						name: [
							'wpAppFramework',
							slug,
							'views',
							prefix + name,
						],
						export: 'default',
					},
				},
			]
		)
	);
}

/**
 * The shared modules of the consumer: every script below assets/shared/ except view and editor scripts.
 *
 * @param {string} assetDirectory
 * @return {Object<string, string>} Files by module name (path below assets/shared/ without ".js").
 */
function sharedModules( assetDirectory ) {
	return Object.fromEntries(
		Object.entries(
			scripts( path.join( assetDirectory, SHARED_DIRECTORY ), '.js' )
		).filter(
			( [ name ] ) =>
				! ( name + '.js' ).endsWith( VIEW_SCRIPT_SUFFIX ) &&
				! ( name + '.js' ).endsWith( EDITOR_SCRIPT_SUFFIX )
		)
	);
}

/**
 * A name as part of a script handle: kebab case, e.g. "ui/ModalStack" → "ui-modal-stack".
 * The framework derives the same handles in PHP (Asset\ScriptKind::kebab()).
 *
 * @param {string} name
 * @return {string} Kebab case.
 */
function kebab( name ) {
	return name
		.replace( /(?<=[a-z0-9])([A-Z])/g, '-$1' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

/**
 * The requests of the modules of an entry, among them the external ones (`@webcodingcat/wp-app-framework`, `#shared/…`).
 *
 * @param {Object} compilation
 * @param {Object} entrypoint
 * @return {Set<string>} Requests.
 */
function externalRequests( compilation, entrypoint ) {
	const requests = new Set();
	for ( const chunk of entrypoint.chunks ) {
		for ( const module of compilation.chunkGraph.getChunkModulesIterable(
			chunk
		) ) {
			if ( typeof module.userRequest === 'string' ) {
				requests.add( module.userRequest );
			}
		}
	}
	return requests;
}

/**
 * What each kind of script must not import: the APIs of the other places, which are not loaded where the script runs.
 * Entries of a kind end in its suffix without ".js".
 */
const SCRIPT_RULES = [
	{
		suffix: VIEW_SCRIPT_SUFFIX,
		label: 'view script',
		place: 'on the front end',
		own: VIEW_IMPORT_NAME,
		foreign: [
			[ IMPORT_NAME, 'the editor runtime' ],
			[ SETTINGS_IMPORT_NAME, 'the API of the settings page' ],
		],
	},
	{
		suffix: SETTINGS_SCRIPT_SUFFIX,
		label: 'settings script',
		place: 'on the settings page',
		own: SETTINGS_IMPORT_NAME,
		foreign: [
			[ IMPORT_NAME, 'the editor runtime' ],
			[ VIEW_IMPORT_NAME, 'the view runtime' ],
		],
	},
	{
		suffix: EDITOR_SCRIPT_SUFFIX,
		label: 'editor script',
		place: 'in the block editor',
		own: IMPORT_NAME,
		foreign: [ [ SETTINGS_IMPORT_NAME, 'the API of the settings page' ] ],
	},
];

/**
 * Fails the build when a script imports the API of another place (SCRIPT_RULES), also through shared modules –
 * a view script the editor runtime, a settings script the editor runtime – and when shared modules import each
 * other in a cycle.
 */
class ScriptImportCheck {
	apply( compiler ) {
		compiler.hooks.compilation.tap( 'wp-app-framework', ( compilation ) => {
			compilation.hooks.afterChunks.tap( 'wp-app-framework', () => {
				const requests = new Map();
				for ( const [ name, entrypoint ] of compilation.entrypoints ) {
					requests.set(
						name,
						externalRequests( compilation, entrypoint )
					);
				}
				const sharedOf = ( name ) =>
					[ ...( requests.get( name ) ?? [] ) ]
						.filter( ( request ) =>
							request.startsWith( SHARED_IMPORT_PREFIX )
						)
						.map(
							( request ) =>
								`${ ASSET_DIRECTORY }/${ SHARED_DIRECTORY }/${ request.slice(
									SHARED_IMPORT_PREFIX.length
								) }`
						);
				// Entries of scripts in assets/ carry the directory in their name; the others are below src/.
				const file = ( name ) =>
					name.startsWith( ASSET_DIRECTORY + '/' )
						? `${ name }.js`
						: `src/${ name }.js`;
				const error = ( message ) =>
					compilation.errors.push(
						new compiler.webpack.WebpackError(
							`wp-app-framework: ${ message }`
						)
					);

				for ( const name of requests.keys() ) {
					const rule = SCRIPT_RULES.find( ( { suffix } ) =>
						name.endsWith( suffix.slice( 0, -3 ) )
					);
					if ( ! rule ) {
						continue;
					}
					// The script and the shared modules it reaches, with the path to each.
					const paths = new Map( [ [ name, [ name ] ] ] );
					const queue = [ name ];
					while ( queue.length > 0 ) {
						const current = queue.shift();
						for ( const shared of sharedOf( current ) ) {
							if ( ! paths.has( shared ) ) {
								paths.set( shared, [
									...paths.get( current ),
									shared,
								] );
								queue.push( shared );
							}
						}
					}
					for ( const [ reached, through ] of paths ) {
						for ( const [ request, what ] of rule.foreign ) {
							if ( ! requests.get( reached )?.has( request ) ) {
								continue;
							}
							error(
								reached === name
									? `the ${ rule.label } ${ file(
											name
										) } imports ${ request }, ${ what }, which is not loaded ${
											rule.place
										}; ${ rule.label }s import ${ rule.own }.`
									: `the ${ rule.label } ${ file(
											name
										) } uses ${ file( reached ) } (${ through
											.map( file )
											.join(
												' → '
											) }), which imports ${ request }, ${ what }, which is not loaded ${
											rule.place
										}; modules used by ${ rule.label }s import ${ rule.own }.`
							);
						}
					}
				}

				// Cycles between shared modules: WordPress cannot order their scripts.
				const state = new Map();
				const visit = ( name, stack ) => {
					if ( state.get( name ) === 'done' ) {
						return;
					}
					if ( state.get( name ) === 'active' ) {
						const cycle = [
							...stack.slice( stack.indexOf( name ) ),
							name,
						];
						error(
							`the shared modules import each other in a cycle: ${ cycle
								.map( file )
								.join( ' → ' ) }.`
						);
						return;
					}
					state.set( name, 'active' );
					for ( const shared of sharedOf( name ) ) {
						visit( shared, [ ...stack, name ] );
					}
					state.set( name, 'done' );
				};
				for ( const name of requests.keys() ) {
					if (
						name.startsWith(
							`${ ASSET_DIRECTORY }/${ SHARED_DIRECTORY }/`
						)
					) {
						visit( name, [] );
					}
				}
			} );
		} );
	}
}

/**
 * The woff2 files of the `@font-face` rules of a stylesheet marked for preloading.
 *
 * @param {string} styles Path of the stylesheet.
 * @return {{fonts: string[], errors: string[]}} Absolute paths of the fonts, and errors.
 */
function markedFonts( styles ) {
	const fonts = [];
	const errors = [];
	const css = fs.readFileSync( styles, 'utf8' );
	for ( const [ , body ] of css.matchAll( /@font-face\s*\{([^}]*)\}/g ) ) {
		if ( ! PRELOAD_MARKER.test( body ) ) {
			continue;
		}
		const url = body.match(
			/url\(\s*(['"]?)([^'")]+\.woff2)(?:[?#][^'")]*)?\1\s*\)/
		);
		if ( ! url || /^[a-z]+:|^\/\//i.test( url[ 2 ] ) ) {
			errors.push(
				`wp-app-framework: an @font-face in ${ styles } is marked for preloading, but has no woff2 file of the project in its src (url("./fonts/….woff2")).`
			);
			continue;
		}
		fonts.push( path.resolve( path.dirname( styles ), url[ 2 ] ) );
	}
	return { fonts, errors };
}

/**
 * Writes build/styles.preload.php: the built files of the fonts marked for preloading, so that the
 * framework preloads the same URLs the stylesheet loads.
 */
class FontPreload {
	constructor( styles ) {
		this.styles = styles;
	}

	apply( compiler ) {
		const { Compilation, sources } = compiler.webpack;
		compiler.hooks.thisCompilation.tap(
			'wp-app-framework',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'wp-app-framework',
						stage: Compilation.PROCESS_ASSETS_STAGE_ADDITIONAL,
					},
					() => {
						const { fonts, errors } = markedFonts( this.styles );
						for ( const message of errors ) {
							compilation.errors.push(
								new compiler.webpack.WebpackError( message )
							);
						}
						if ( fonts.length === 0 ) {
							return;
						}
						const built = [];
						for ( const font of fonts ) {
							const module = [ ...compilation.modules ].find(
								( candidate ) => candidate.resource === font
							);
							const filename = module?.buildInfo?.filename;
							if ( ! filename ) {
								compilation.errors.push(
									new compiler.webpack.WebpackError(
										`wp-app-framework: the font ${ font }, marked for preloading in ${ this.styles }, was not built.`
									)
								);
								continue;
							}
							built.push( filename );
						}
						compilation.emitAsset(
							PRELOAD_FILE,
							new sources.RawSource(
								`<?php return array( ${ built
									.map(
										( file ) =>
											`'${ file.replace( /[\\']/g, '\\$&' ) }'`
									)
									.join( ', ' ) } );\n`
							)
						);
					}
				);
			}
		);
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
 * The name of the shared module an import `#shared/<name>` refers to; fails if it does not exist.
 *
 * @param {string} assetDirectory
 * @param {string} request
 * @return {string} Module name.
 */
function sharedModule( assetDirectory, request ) {
	const name = request.slice( SHARED_IMPORT_PREFIX.length );
	if ( ! Object.hasOwn( sharedModules( assetDirectory ), name ) ) {
		throw new Error(
			`wp-app-framework: ${ request } is no shared module: ${ ASSET_DIRECTORY }/${ SHARED_DIRECTORY }/${ name }.js does not exist.`
		);
	}
	return name;
}

/**
 * Checks that the consumer's package.json maps the imports of shared modules for IDEs and other tools.
 *
 * @param {string} root
 */
function checkSharedImports( root ) {
	const file = path.join( root, 'package.json' );
	const imports = JSON.parse( fs.readFileSync( file, 'utf8' ) ).imports;
	if ( imports?.[ SHARED_IMPORTS_KEY ] !== SHARED_IMPORTS_TARGET ) {
		throw new Error(
			`wp-app-framework: the project has shared modules in ${ ASSET_DIRECTORY }/${ SHARED_DIRECTORY }/; add "imports": { "${ SHARED_IMPORTS_KEY }": "${ SHARED_IMPORTS_TARGET }" } to ${ file }, so that imports of ${ SHARED_IMPORT_PREFIX }… resolve in IDEs.`
		);
	}
}

/**
 * Entries of the scripts in src/: editor and view scripts next to the classes of blocks and block supports,
 * settings scripts next to the classes of settings groups.
 *
 * @param {string} root
 * @param {string} slug
 * @return {Object<string, Object|string>} Entries.
 */
function sourceEntries( root, slug ) {
	const entries = {
		...scripts( path.join( root, 'src' ), EDITOR_SCRIPT_SUFFIX ),
		...viewScripts( path.join( root, 'src' ), slug ),
		...scripts( path.join( root, 'src' ), SETTINGS_SCRIPT_SUFFIX ),
	};
	for ( const name of Object.keys( entries ) ) {
		if ( name.startsWith( ASSET_DIRECTORY + '/' ) ) {
			throw new Error(
				`wp-app-framework: src/${ name }.js has the same entry name as a script in ${ ASSET_DIRECTORY }/; rename the directory src/${ ASSET_DIRECTORY }/.`
			);
		}
	}
	return entries;
}

/**
 * Entries of the scripts in assets/: document views, editor scripts without a block, shared modules.
 *
 * @param {string} root
 * @param {string} slug
 * @return {Object<string, Object|string>} Entries.
 */
function assetEntries( root, slug ) {
	const directory = path.join( root, ASSET_DIRECTORY );
	const prefix = ASSET_DIRECTORY + '/';
	const entries = {
		...viewScripts( directory, slug, prefix ),
		...Object.fromEntries(
			Object.entries( scripts( directory, EDITOR_SCRIPT_SUFFIX ) ).map(
				( [ name, file ] ) => [ prefix + name, file ]
			)
		),
	};
	for ( const [ name, file ] of Object.entries(
		sharedModules( directory )
	) ) {
		entries[ `${ prefix }${ SHARED_DIRECTORY }/${ name }` ] = {
			import: file,
			library: {
				type: 'window',
				name: [ 'wpAppFramework', slug, 'shared', name ],
			},
		};
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

	const formerStyles = path.join( root, 'src', STYLES_SOURCE );
	if ( fs.existsSync( formerStyles ) ) {
		throw new Error(
			`wp-app-framework: ${ formerStyles } is no longer built; move it to ${ ASSET_DIRECTORY }/${ STYLES_SOURCE } and make the paths in it relative to that directory (e.g. @source "../src").`
		);
	}

	const assetDirectory = path.join( root, ASSET_DIRECTORY );
	if ( Object.keys( sharedModules( assetDirectory ) ).length > 0 ) {
		checkSharedImports( root );
	}

	// Checked once: after adding or removing the stylesheet, restart the watcher.
	const styles = path.join( root, ASSET_DIRECTORY, STYLES_SOURCE );
	const tailwind = fs.existsSync( styles )
		? tailwindRule( defaults, styles, root, fromRoot )
		: null;

	return {
		...defaults,
		// A function, so a new editor or view script is picked up when the watcher rebuilds.
		entry: () => ( {
			...assetEntries( root, slug ),
			[ RUNTIME_ENTRY ]: path.join( __dirname, 'assets', 'editor.js' ),
			// Styles of the editor canvas (iframe), loaded separately from the runtime.
			[ CANVAS_ENTRY ]: path.join( __dirname, 'assets', 'canvas.scss' ),
			// The settings page of the instance (admin page with the forms of its settings groups).
			[ SETTINGS_ENTRY ]: path.join( __dirname, 'assets', 'settings.js' ),
			// The meta boxes of the classic edit screen (forms of the meta classes).
			[ META_BOX_ENTRY ]: path.join( __dirname, 'assets', 'meta-box.js' ),
			// The view runtime: base classes of view scripts, mounts their views.
			[ VIEW_ENTRY ]: path.join( __dirname, 'assets', 'view.js' ),
			...sourceEntries( root, slug ),
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
			new ScriptImportCheck(),
			...( tailwind ? [ new FontPreload( styles ) ] : [] ),
			new webpack.DefinePlugin( {
				WP_APP_FRAMEWORK_SLUG: JSON.stringify( slug ),
			} ),
			new DependencyExtractionWebpackPlugin( {
				requestToExternal: ( request ) => {
					if ( request === IMPORT_NAME ) {
						return [ 'wpAppFramework', slug, 'api' ];
					}
					if ( request.startsWith( SHARED_IMPORT_PREFIX ) ) {
						return [
							'wpAppFramework',
							slug,
							'shared',
							sharedModule( assetDirectory, request ),
						];
					}
					if ( request === SETTINGS_IMPORT_NAME ) {
						return [ 'wpAppFramework', slug, 'settingsApi' ];
					}
					return request === VIEW_IMPORT_NAME
						? [ 'wpAppFramework', slug, 'view' ]
						: undefined;
				},
				requestToHandle: ( request ) => {
					if ( request === IMPORT_NAME ) {
						return `${ slug }-wp-app-framework`;
					}
					if ( request.startsWith( SHARED_IMPORT_PREFIX ) ) {
						return `${ slug }-shared-${ kebab(
							sharedModule( assetDirectory, request )
						) }`;
					}
					if ( request === SETTINGS_IMPORT_NAME ) {
						return `${ slug }-wp-app-framework-settings`;
					}
					return request === VIEW_IMPORT_NAME
						? `${ slug }-wp-app-framework-view`
						: undefined;
				},
			} ),
		],
	};
};
