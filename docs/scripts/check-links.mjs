/**
 * Checks every internal link of the built site: the target file must exist and,
 * for links with a fragment, contain an element with that id.
 *
 * Rspress checks links while compiling, but in 2.0.23 an anchor that points to
 * a page compiled later is not reported (src: node/mdx/remarkPlugins/link.js).
 *
 * Usage: node scripts/check-links.mjs <build-dir>
 * The base path is taken from <build-dir>/index.html (links to the same version).
 */
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';

const buildDir = process.argv[ 2 ];
if ( ! buildDir || ! existsSync( join( buildDir, 'index.html' ) ) ) {
	fail(
		`Usage: check-links.mjs <build-dir>; ${ buildDir }/index.html not found.`
	);
}

const pages = listHtml( buildDir );
const base = detectBase(
	readFileSync( join( buildDir, 'index.html' ), 'utf8' )
);
const ids = new Map(
	pages.map( ( file ) => [
		file,
		collect( read( file ), /\sid="([^"]+)"/g ),
	] )
);

const errors = [];
let checked = 0;
for ( const file of pages ) {
	for ( const href of collect( read( file ), /\shref="([^"]+)"/g ) ) {
		const target = resolve( file, decodeEntities( href ) );
		if ( target === null ) {
			continue;
		}
		checked++;
		const page = relative( buildDir, file );
		if ( ! existsSync( target.file ) ) {
			errors.push(
				`${ page }: ${ href } – ${ relative( buildDir, target.file ) } does not exist`
			);
		} else if (
			target.hash &&
			! ids.get( target.file )?.has( target.hash )
		) {
			errors.push(
				`${ page }: ${ href } – no element with id "${ target.hash }"`
			);
		}
	}
}

if ( errors.length > 0 ) {
	fail( `${ errors.length } broken links:\n${ errors.join( '\n' ) }` );
}
console.log(
	`Links OK: ${ checked } internal links in ${ pages.length } pages (base ${ base }).`
);

/**
 * @param {string} from Page containing the link.
 * @param {string} href Link target as written in the page.
 * @return {{file: string, hash: string}|null} Target, or null for external links.
 */
function resolve( from, href ) {
	if ( /^[a-z][a-z0-9+.-]*:/i.test( href ) || href.startsWith( '//' ) ) {
		return null;
	}
	const [ path, hash = '' ] = href.split( '#', 2 );
	let file;
	if ( path === '' ) {
		file = from;
	} else if ( path.startsWith( '/' ) ) {
		if ( ! path.startsWith( base ) ) {
			// Another version or the root of the site: not part of this build.
			return null;
		}
		file = join( buildDir, path.slice( base.length ) );
	} else {
		file = join( from, '..', path );
	}
	if ( path.endsWith( '/' ) ) {
		file = join( file, 'index.html' );
	}
	return { file, hash: decodeURIComponent( hash ) };
}

function detectBase( html ) {
	const match = html.match( /\shref="([^"]*?)static\/css\// );
	if ( ! match ) {
		fail(
			'Cannot detect the base path from the stylesheet link in index.html.'
		);
	}
	return match[ 1 ];
}

function listHtml( dir ) {
	return readdirSync( dir, { recursive: true } )
		.filter( ( name ) => name.endsWith( '.html' ) )
		.map( ( name ) => join( dir, name ) );
}

function read( file ) {
	return readFileSync( file, 'utf8' );
}

function collect( html, pattern ) {
	return new Set(
		[ ...html.matchAll( pattern ) ].map( ( match ) => match[ 1 ] )
	);
}

function decodeEntities( value ) {
	return value
		.replaceAll( '&amp;', '&' )
		.replaceAll( '&#x27;', "'" )
		.replaceAll( '&quot;', '"' );
}

function fail( message ) {
	console.error( message );
	process.exit( 1 );
}
