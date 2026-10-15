import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

import { generate } from '../../tools/api-reference-js.mjs';

const framework = path.join( __dirname, '..', '..' );
const content = path.join( framework, 'docs', 'content' );

/**
 * A directory with declaration files for the two modules.
 *
 * @param {string} index Content of index.d.ts.
 * @param {string} view  Content of view.d.ts.
 * @return {string} Directory.
 */
function declarations( index, view ) {
	const directory = fs.mkdtempSync(
		path.join( os.tmpdir(), 'api-reference-' )
	);
	fs.writeFileSync( path.join( directory, 'index.d.ts' ), index );
	fs.writeFileSync( path.join( directory, 'view.d.ts' ), view );
	return directory;
}

describe( 'JavaScript API reference', () => {
	it( 'has a page for every export of both modules', () => {
		const { pages, sidebar } = generate(
			path.join( framework, 'types' ),
			content
		);

		expect( Object.keys( pages ) ).toEqual(
			expect.arrayContaining( [
				'api/js/editor/registerControl',
				'api/js/editor/ControlProps',
				'api/js/view/BlockView',
				'api/js/view/getData',
			] )
		);
		// A type re-exported by the view module links to the editor page instead of a page of its own.
		expect( pages ).not.toHaveProperty( 'api/js/view/DataValue' );
		expect( pages[ 'api/js/view/SupportView' ] ).toContain( '### value' );
		// Inherited members are listed on the page of the subclass.
		expect( pages[ 'api/js/view/SupportView' ] ).toContain(
			'### listen()'
		);
		expect( pages[ 'api/js/editor/registerControl' ] ).toContain(
			'[`ControlProps`](./ControlProps.md)'
		);
		expect( sidebar[ 0 ].items.map( ( group ) => group.text ) ).toEqual( [
			'Editor (@saij/wp-app-framework)',
			'View (@saij/wp-app-framework/view)',
		] );
	} );

	it( 'fails for exports, members and parameters without description', () => {
		const directory = declarations(
			[
				'export function undocumented(value: string): void;',
				'/** Documented. */',
				'export function missingParameter(value: string): void;',
				'/**',
				' * Documented.',
				' *',
				' * @see guides/missing.md',
				' */',
				'export interface Props { open: boolean; }',
			].join( '\n' ),
			'/** A class. */\nexport class View { mount(): void; }\n'
		);

		expect( () => generate( directory, content ) ).toThrow(
			[
				'The JavaScript API reference is incomplete:',
				' - @saij/wp-app-framework: parameter value of missingParameter() has no description.',
				' - @saij/wp-app-framework: undocumented has no description.',
				' - @saij/wp-app-framework: parameter value of undocumented() has no description.',
				' - @saij/wp-app-framework: @see guides/missing.md of Props names no page below docs/content/.',
				' - @saij/wp-app-framework: Props.open has no description.',
				' - @saij/wp-app-framework/view: View.mount has no description.',
			].join( '\n' )
		);
	} );
} );
