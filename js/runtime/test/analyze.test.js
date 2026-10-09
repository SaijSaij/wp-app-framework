import { describe, it, expect } from 'vitest';

import { analyzeMarkup, missingFields } from '../analyze';

const fields = {
	topline: { kind: 'plain' },
	'buttons.*.label': { kind: 'plain' },
};

describe( 'analyzeMarkup', () => {
	it( 'finds the single root element and the marked paths', () => {
		const { root, error, paths } = analyzeMarkup(
			'\n<section class="hero"><p data-field="topline"></p><a data-field="buttons.0.label">A</a><!-- c --></section>\n',
			fields
		);
		expect( error ).toBeNull();
		expect( root.name ).toBe( 'section' );
		expect( paths.sort() ).toEqual( [ 'buttons.0.label', 'topline' ] );
		expect( missingFields( fields, paths ) ).toEqual( [] );
	} );

	it( 'reports templates the runtime cannot handle', () => {
		expect( analyzeMarkup( '<p></p><p></p>', fields ).error ).toBe(
			'The template must render exactly one root element, it rendered 2 nodes.'
		);
		expect( analyzeMarkup( '', fields ).error ).toBe(
			'The template must render exactly one root element, it rendered 0 nodes.'
		);
		expect(
			analyzeMarkup( '<p data-field="topline"></p>', fields ).error
		).toBe(
			'The root element cannot be an inline field; mark an element inside it.'
		);
		expect(
			analyzeMarkup( '<div><p data-field="other"></p></div>', fields )
				.error
		).toBe( 'field( "other" ) is not an inline field of the block.' );
	} );

	it( 'lists fields without wildcard that the markup hides', () => {
		const { paths } = analyzeMarkup( '<div></div>', fields );
		expect( missingFields( fields, paths ) ).toEqual( [ 'topline' ] );
	} );
} );
