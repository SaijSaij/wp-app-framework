/**
 * Publishes one built version of the documentation to the gh-pages branch,
 * which GitHub Pages serves as https://webcodingcat.github.io/wp-app-framework/.
 *
 * The branch holds one directory per version – "next" (main) and "X.Y" (the
 * newest tag of a minor version) – plus versions.json for the version switcher,
 * index.html redirecting to the latest release and .nojekyll. Only the directory
 * of the given version is replaced; the other versions stay as they are.
 * (A Pages deployment with actions/deploy-pages would replace the whole site.)
 *
 * Usage, from a checkout with the remote "origin" and a git identity:
 *   node scripts/publish.mjs <build-dir> <next|X.Y>
 */
import { execFileSync } from 'node:child_process';
import {
	cpSync,
	existsSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const BRANCH = 'gh-pages';
const REMOTE = 'origin';

const [ buildArg, version ] = process.argv.slice( 2 );
if ( ! buildArg || ! version ) {
	fail( 'Usage: publish.mjs <build-dir> <next|X.Y>' );
}
if ( ! /^(next|\d+\.\d+)$/.test( version ) ) {
	fail(
		`Invalid version "${ version }": expected "next" or <major>.<minor>, e.g. 0.3.`
	);
}
const buildDir = resolve( buildArg );
if ( ! existsSync( join( buildDir, 'index.html' ) ) ) {
	fail(
		`${ buildDir }/index.html not found: build the documentation first.`
	);
}

const worktree = mkdtempSync( join( tmpdir(), 'wp-app-framework-docs-' ) );
const exists = git( 'ls-remote', '--heads', REMOTE, BRANCH ) !== '';
// Local branch for the first publication only (an orphan needs a branch name).
const orphan = `${ BRANCH }-publish`;
try {
	if ( exists ) {
		git( 'fetch', '--quiet', REMOTE, BRANCH );
		git( 'worktree', 'add', '--quiet', '--detach', worktree, 'FETCH_HEAD' );
	} else {
		git( 'worktree', 'add', '--quiet', '--detach', worktree );
		gitIn( worktree, 'switch', '--quiet', '--orphan', orphan );
	}

	const target = join( worktree, version );
	rmSync( target, { recursive: true, force: true } );
	cpSync( buildDir, target, { recursive: true } );

	const versions = updateVersions(
		join( worktree, 'versions.json' ),
		version
	);
	const start = versions.latest ?? 'next';
	writeFileSync(
		join( worktree, 'index.html' ),
		'<!doctype html>\n<meta charset="utf-8">\n<title>wp-app-framework</title>\n' +
			`<meta http-equiv="refresh" content="0; url=${ start }/">\n` +
			`<link rel="canonical" href="${ start }/">\n` +
			`<a href="${ start }/">Documentation of wp-app-framework ${ start }</a>\n`
	);
	writeFileSync( join( worktree, '.nojekyll' ), '' );

	gitIn( worktree, 'add', '--all' );
	if ( gitIn( worktree, 'status', '--porcelain' ) === '' ) {
		console.log(
			`Documentation ${ version } is unchanged; nothing to publish.`
		);
	} else {
		const source = git( 'rev-parse', '--short', 'HEAD' );
		gitIn(
			worktree,
			'commit',
			'--quiet',
			'-m',
			`Publish documentation ${ version } (${ source })`
		);
		gitIn(
			worktree,
			'push',
			'--quiet',
			REMOTE,
			`HEAD:refs/heads/${ BRANCH }`
		);
		console.log(
			`Published documentation ${ version } to ${ BRANCH }; versions: ${ JSON.stringify( versions ) }`
		);
	}
} finally {
	git( 'worktree', 'remove', '--force', worktree );
	if ( ! exists && git( 'branch', '--list', orphan ) !== '' ) {
		git( 'branch', '--quiet', '-D', orphan );
	}
}

/**
 * @param {string} file      versions.json in the worktree.
 * @param {string} published Version being published.
 * @return {{latest: string|null, next: boolean, versions: string[]}} New content.
 */
function updateVersions( file, published ) {
	const data = existsSync( file )
		? JSON.parse( readFileSync( file, 'utf8' ) )
		: { latest: null, next: false, versions: [] };
	if ( published === 'next' ) {
		data.next = true;
	} else if ( ! data.versions.includes( published ) ) {
		data.versions.push( published );
	}
	data.versions.sort( ( a, b ) => {
		const [ aMajor, aMinor ] = a.split( '.' ).map( Number );
		const [ bMajor, bMinor ] = b.split( '.' ).map( Number );
		return bMajor - aMajor || bMinor - aMinor;
	} );
	data.latest = data.versions[ 0 ] ?? null;
	writeFileSync( file, JSON.stringify( data, null, '\t' ) + '\n' );
	return data;
}

function git( ...args ) {
	return execFileSync( 'git', args, { encoding: 'utf8' } ).trim();
}

function gitIn( dir, ...args ) {
	return execFileSync( 'git', [ '-C', dir, ...args ], {
		encoding: 'utf8',
	} ).trim();
}

function fail( message ) {
	console.error( message );
	process.exit( 1 );
}
