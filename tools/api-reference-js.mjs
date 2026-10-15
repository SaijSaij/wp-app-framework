/**
 * Generates the JavaScript part of the API reference from the type declarations in types/: one page per export
 * of `@saij/wp-app-framework` and `@saij/wp-app-framework/view` in docs/content/api/js/, and their sidebar in
 * docs/.generated/api-js.json. Run by the documentation build (npm run build in docs/).
 *
 * Every page comes from the declarations and their JSDoc. An export, member or parameter without description fails
 * the run, so the documentation build never publishes an incomplete reference.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';

/** The two imports and their declaration files, in the order of the sidebar. */
export const MODULES = [
	{
		name: '@saij/wp-app-framework',
		file: 'index.d.ts',
		directory: 'editor',
		title: 'Editor',
	},
	{
		name: '@saij/wp-app-framework/view',
		file: 'view.d.ts',
		directory: 'view',
		title: 'View',
	},
];

/** Directory of the generated pages below docs/content/. */
export const PAGES = 'api/js';

/**
 * Reads the declarations and returns the pages and the sidebar, or throws with every error found.
 *
 * @param {string} typesDirectory Directory with index.d.ts and view.d.ts.
 * @param {string} content        The docs/content/ directory, for the targets of `@see`.
 * @return {{pages: Object<string, string>, sidebar: Object[]}} Markdown by page path below docs/content/ (without
 *                                                              `.md`), and the sidebar group for Rspress.
 */
export function generate( typesDirectory, content ) {
	const files = MODULES.map( ( module ) =>
		path.join( typesDirectory, module.file )
	);
	const program = ts.createProgram( files, {
		strict: true,
		noEmit: true,
		types: [],
		lib: [ 'lib.es2022.d.ts', 'lib.dom.d.ts' ],
	} );
	const checker = program.getTypeChecker();
	const errors = [];
	const pages = {};
	const groups = [];

	// Page of each declared name, for links between pages; a name exported by both modules links to the editor page.
	const links = {};
	for ( const module of MODULES ) {
		for ( const symbol of exportsOf( program, checker, module ) ) {
			links[ symbol.getName() ] ??=
				`${ PAGES }/${ module.directory }/${ symbol.getName() }`;
		}
	}

	for ( const module of MODULES ) {
		const items = [];
		for ( const symbol of exportsOf( program, checker, module ) ) {
			const page = `${ PAGES }/${ module.directory }/${ symbol.getName() }`;
			const context = { checker, errors, links, page, module, content };
			const markdown = render( symbol, context );
			if ( markdown !== null ) {
				pages[ page ] = markdown;
				items.push( {
					text: label( symbol ),
					link: `/${ page }`,
				} );
			}
		}
		groups.push( {
			text: `${ module.title } (${ module.name })`,
			collapsible: true,
			collapsed: true,
			items,
		} );
	}

	if ( errors.length > 0 ) {
		throw new Error(
			`The JavaScript API reference is incomplete:\n - ${ [
				...new Set( errors ),
			].join( '\n - ' ) }`
		);
	}

	return {
		pages,
		sidebar: [
			{
				text: 'JavaScript',
				collapsible: true,
				collapsed: true,
				items: groups,
			},
		],
	};
}

/**
 * The exported symbols of a module that consumers use, sorted: functions and classes first, then types.
 *
 * @param {ts.Program}     program
 * @param {ts.TypeChecker} checker
 * @param {Object}         module  One of MODULES.
 * @return {ts.Symbol[]} Symbols.
 */
function exportsOf( program, checker, module ) {
	const source = program
		.getSourceFiles()
		.find( ( file ) => file.fileName.endsWith( `/${ module.file }` ) );
	const symbol = checker.getSymbolAtLocation( source );
	const order = ( item ) => {
		const declaration = declarationOf( item );
		return ts.isFunctionDeclaration( declaration ) ||
			ts.isClassDeclaration( declaration )
			? 0
			: 1;
	};
	return checker
		.getExportsOfModule( symbol )
		.filter( ( item ) => declarationOf( item ).getSourceFile() === source )
		.sort(
			( a, b ) =>
				order( a ) - order( b ) ||
				a.getName().localeCompare( b.getName() )
		);
}

/**
 * The declaration of a symbol, following re-exports.
 *
 * @param {ts.Symbol} symbol
 * @return {ts.Declaration} Declaration.
 */
function declarationOf( symbol ) {
	return symbol.declarations[ 0 ];
}

/**
 * @param {ts.Symbol} symbol
 * @return {string} Sidebar text.
 */
function label( symbol ) {
	return ts.isFunctionDeclaration( declarationOf( symbol ) )
		? `${ symbol.getName() }()`
		: symbol.getName();
}

/**
 * The page of an export, or null for one that only re-exports a type of the other module.
 *
 * @param {ts.Symbol} symbol
 * @param {Object}    context
 * @return {string|null} Markdown.
 */
function render( symbol, context ) {
	const declaration = declarationOf( symbol );
	if ( ts.isExportSpecifier( declaration ) ) {
		return null;
	}
	const name = symbol.getName();
	const lines = [];
	const description = documentation( symbol, context.checker );
	if ( description === '' ) {
		context.errors.push(
			`${ context.module.name }: ${ name } has no description.`
		);
	}

	if ( ts.isFunctionDeclaration( declaration ) ) {
		lines.push(
			`# ${ name }()`,
			'',
			`Function of \`${ context.module.name }\`.`,
			''
		);
		lines.push( ...importLine( name, context.module ) );
		lines.push( '```ts', signatureText( declaration ), '```', '' );
		lines.push( description, '' );
		lines.push( ...see( symbol, context ) );
		lines.push(
			...parameters( declaration, symbol, `${ name }()`, context )
		);
		lines.push( ...returns( declaration, symbol, context ) );
	} else if ( ts.isClassDeclaration( declaration ) ) {
		const base = declaration.heritageClauses?.[ 0 ]?.types[ 0 ]?.getText();
		lines.push(
			`# ${ name }`,
			'',
			`Class of \`${ context.module.name }\`${
				base ? `; your view extends it` : ''
			}.`,
			''
		);
		lines.push( ...importLine( name, context.module ) );
		lines.push( description, '' );
		lines.push( ...see( symbol, context ) );
		lines.push( ...members( symbol, name, context ) );
	} else {
		const kind = ts.isInterfaceDeclaration( declaration )
			? 'Interface'
			: 'Type';
		lines.push(
			`# ${ name }`,
			'',
			`${ kind } of \`${ context.module.name }\`, for type checks in your IDE.`,
			''
		);
		lines.push(
			'```ts',
			declaration.getText().replace( /\/\*\*[\s\S]*?\*\/\s*/g, '' ),
			'```',
			''
		);
		lines.push( description, '' );
		lines.push( ...see( symbol, context ) );
		if ( ts.isInterfaceDeclaration( declaration ) ) {
			lines.push( ...members( symbol, name, context ) );
		}
	}

	return (
		lines
			.join( '\n' )
			.replace( /\n{3,}/g, '\n\n' )
			.trimEnd() + '\n'
	);
}

/**
 * @param {string} name
 * @param {Object} module
 * @return {string[]} Lines.
 */
function importLine( name, module ) {
	return [
		'```js',
		`import { ${ name } } from '${ module.name }';`,
		'```',
		'',
	];
}

/**
 * The members of a class or interface, inherited ones included; private and protected ones left out.
 *
 * @param {ts.Symbol} symbol
 * @param {string}    owner   Name of the class or interface.
 * @param {Object}    context
 * @return {string[]} Lines.
 */
function members( symbol, owner, context ) {
	const { checker } = context;
	const type = checker.getDeclaredTypeOfSymbol( symbol );
	const properties = checker
		.getPropertiesOfType( type )
		.filter( ( member ) => {
			const modifiers = declarationOf( member ).modifiers ?? [];
			return ! modifiers.some(
				( modifier ) =>
					modifier.kind === ts.SyntaxKind.PrivateKeyword ||
					modifier.kind === ts.SyntaxKind.ProtectedKeyword
			);
		} );
	if ( properties.length === 0 ) {
		return [];
	}

	const lines = [ '## Members', '' ];
	for ( const member of properties ) {
		const declaration = declarationOf( member );
		const name = member.getName();
		const where = `${ context.module.name }: ${ owner }.${ name }`;
		const description = documentation( member, checker );
		if ( description === '' ) {
			context.errors.push( `${ where } has no description.` );
		}
		const isMethod =
			ts.isMethodDeclaration( declaration ) ||
			ts.isMethodSignature( declaration );
		lines.push( `### ${ name }${ isMethod ? '()' : '' }`, '' );
		lines.push( '```ts', memberText( declaration ), '```', '' );
		lines.push( description, '' );
		if ( isMethod ) {
			lines.push(
				...parameters(
					declaration,
					member,
					`${ owner }.${ name }()`,
					context
				)
			);
			lines.push( ...returns( declaration, member, context ) );
		}
	}

	return lines;
}

/**
 * @param {ts.SignatureDeclaration} declaration
 * @param {ts.Symbol}               symbol
 * @param {string}                  where       For errors.
 * @param {Object}                  context
 * @return {string[]} Lines of the parameter table.
 */
function parameters( declaration, symbol, where, context ) {
	if ( declaration.parameters.length === 0 ) {
		return [];
	}
	const tags = paramTags( symbol );
	const lines = [ '| Parameter | Type | Description |', '|---|---|---|' ];
	for ( const parameter of declaration.parameters ) {
		const name = parameter.name.getText();
		const text = tags[ name ] ?? '';
		if ( text === '' ) {
			context.errors.push(
				`${ context.module.name }: parameter ${ name } of ${ where } has no description.`
			);
		}
		lines.push(
			`| \`${ name }${ parameter.questionToken ? '?' : '' }\` | ${ typeCell(
				parameter.type?.getText() ?? 'unknown',
				context
			) } | ${ text.replace( /\n/g, ' ' ) } |`
		);
	}
	lines.push( '' );

	return lines;
}

/**
 * @param {ts.SignatureDeclaration} declaration
 * @param {ts.Symbol}               symbol
 * @param {Object}                  context
 * @return {string[]} Lines.
 */
function returns( declaration, symbol, context ) {
	const type = declaration.type?.getText();
	if ( ! type || type === 'void' ) {
		return [];
	}
	const tag = symbol
		.getJsDocTags( context.checker )
		.find( ( item ) => item.name === 'returns' || item.name === 'return' );
	const text = tag ? ts.displayPartsToString( tag.text ) : '';

	return [
		`**Returns** ${ typeCell( type, context, false ) }${ text ? `: ${ text }` : '' }`,
		'',
	];
}

/**
 * @param {ts.Symbol} symbol
 * @param {Object}    context
 * @return {string[]} Lines with the links of `@see` tags (paths below docs/content/).
 */
function see( symbol, context ) {
	const links = [];
	for ( const tag of symbol.getJsDocTags( context.checker ) ) {
		if ( tag.name !== 'see' ) {
			continue;
		}
		// TypeScript splits a path into a name and text parts with a space between them.
		const target = ts
			.displayPartsToString( tag.text )
			.replace( /\s+/g, '' );
		const file = path.join( context.content, target.split( '#' )[ 0 ] );
		if (
			! target.split( '#' )[ 0 ].endsWith( '.md' ) ||
			! fs.existsSync( file )
		) {
			context.errors.push(
				`${ context.module.name }: @see ${ target } of ${ symbol.getName() } names no page below docs/content/.`
			);
			continue;
		}
		const title =
			/^# (.+)$/m.exec( fs.readFileSync( file, 'utf8' ) )?.[ 1 ] ??
			target;
		links.push( `[${ title }](${ relative( context.page, target ) })` );
	}

	return links.length === 0 ? [] : [ `See ${ links.join( ', ' ) }.`, '' ];
}

/**
 * A type as Markdown code, with each name that has a page of its own as a link.
 *
 * @param {string}  type
 * @param {Object}  context
 * @param {boolean} [table] Whether the type is shown in a table cell (`|` is escaped).
 * @return {string} Markdown.
 */
function typeCell( type, context, table = true ) {
	const parts = type
		.replace( /\s+/g, ' ' )
		.split( /([A-Za-z_][A-Za-z0-9_]*)/ );
	let markdown = '';
	let code = '';
	const flush = () => {
		if ( code !== '' ) {
			markdown +=
				'`' + ( table ? code.replace( /\|/g, '\\|' ) : code ) + '`';
			code = '';
		}
	};
	for ( const part of parts ) {
		const page = context.links[ part ];
		if ( page && page !== context.page ) {
			flush();
			markdown += `[\`${ part }\`](${ relative( context.page, `${ page }.md` ) })`;
		} else {
			code += part;
		}
	}
	flush();

	return markdown;
}

/**
 * A relative link between pages below docs/content/.
 *
 * @param {string} from Page without `.md`.
 * @param {string} to   File with `.md`, optionally with an anchor.
 * @return {string} Link.
 */
function relative( from, to ) {
	const link = path.posix.relative( path.posix.dirname( from ), to );
	return link.startsWith( '.' ) ? link : `./${ link }`;
}

/**
 * @param {ts.Symbol}      symbol
 * @param {ts.TypeChecker} checker
 * @return {string} The JSDoc description.
 */
function documentation( symbol, checker ) {
	return ts
		.displayPartsToString( symbol.getDocumentationComment( checker ) )
		.trim();
}

/**
 * @param {ts.Symbol} symbol
 * @return {Object<string, string>} Texts of the `@param` tags by parameter name.
 */
function paramTags( symbol ) {
	const tags = {};
	for ( const declaration of symbol.declarations ?? [] ) {
		for ( const tag of ts.getJSDocTags( declaration ) ) {
			if ( ts.isJSDocParameterTag( tag ) ) {
				tags[ tag.name.getText() ] =
					ts.getTextOfJSDocComment( tag.comment )?.trim() ?? '';
			}
		}
	}

	return tags;
}

/**
 * @param {ts.FunctionDeclaration} declaration
 * @return {string} The signature without JSDoc and `export`.
 */
function signatureText( declaration ) {
	return declaration
		.getText()
		.replace( /^export\s+/, '' )
		.replace( /;$/, '' );
}

/**
 * @param {ts.Declaration} declaration
 * @return {string} The declaration of a member without JSDoc.
 */
function memberText( declaration ) {
	return declaration.getText().replace( /;$/, '' );
}

/**
 * Writes the pages and the sidebar.
 *
 * @param {string} root The framework directory.
 */
export function main( root ) {
	const content = path.join( root, 'docs', 'content' );
	const { pages, sidebar } = generate( path.join( root, 'types' ), content );
	fs.rmSync( path.join( content, PAGES ), { recursive: true, force: true } );
	for ( const [ page, markdown ] of Object.entries( pages ) ) {
		const file = path.join( content, `${ page }.md` );
		fs.mkdirSync( path.dirname( file ), { recursive: true } );
		fs.writeFileSync( file, markdown );
	}
	const output = path.join( root, 'docs', '.generated' );
	fs.mkdirSync( output, { recursive: true } );
	fs.writeFileSync(
		path.join( output, 'api-js.json' ),
		JSON.stringify( sidebar, null, '\t' ) + '\n'
	);
	process.stdout.write(
		`JavaScript API reference: ${ Object.keys( pages ).length } pages written.\n`
	);
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	try {
		main(
			path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) )
		);
	} catch ( error ) {
		process.stderr.write( `${ error.message }\n` );
		process.exitCode = 1;
	}
}
