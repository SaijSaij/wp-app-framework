// @vitest-environment jsdom
/* global WP_APP_FRAMEWORK_SLUG */
import fs from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

import { createApi } from '../runtime/api';

/**
 * The names a declaration file exports: functions and classes.
 *
 * @param {string} file Path relative to types/.
 * @return {string[]} Sorted names.
 */
function declared( file ) {
	const source = fs.readFileSync(
		path.join( __dirname, '..', '..', 'types', file ),
		'utf8'
	);
	return [
		...source.matchAll(
			/^export (?:abstract )?(?:function|class) (\w+)/gm
		),
	]
		.map( ( match ) => match[ 1 ] )
		.sort();
}

describe( 'public API', () => {
	it( 'of the editor runtime holds exactly what types/index.d.ts declares', () => {
		const registry = {
			registerControl() {},
			registerSupportControl() {},
			registerPanel() {},
			registerToolbarButton() {},
		};
		const api = createApi( 'my-theme', registry );

		expect( Object.keys( api ).sort() ).toEqual( declared( 'index.d.ts' ) );
		expect( Object.isFrozen( api ) ).toBe( true );
	} );

	it( 'of the view runtime holds exactly what types/view.d.ts declares', async () => {
		globalThis.WP_APP_FRAMEWORK_SLUG = 'my-theme';
		await import( '../view.js' );
		const view = window.wpAppFramework[ WP_APP_FRAMEWORK_SLUG ].view;

		expect( Object.keys( view ).sort() ).toEqual( declared( 'view.d.ts' ) );
		expect( Object.isFrozen( view ) ).toBe( true );
	} );
} );
