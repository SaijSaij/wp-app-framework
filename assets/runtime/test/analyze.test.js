import { describe, it, expect } from 'vitest';

import { analyzeMarkup, missingFields } from '../analyze';

const fields = {
	topline: { kind: 'plain' },
	'buttons.*.label': { kind: 'plain' },
	note: { kind: 'rich', inspector: true },
	imageId: { kind: 'image' },
};

describe( 'analyzeMarkup', () => {
	it( 'finds the single root element and the marked paths', () => {
		const { root, error, paths } = analyzeMarkup(
			'\n<section class="hero"><p data-field="topline"></p><a data-field="buttons.0.label">A</a><figure data-field="imageId"></figure><!-- c --></section>\n',
			fields
		);
		expect( error ).toBeNull();
		expect( root.name ).toBe( 'section' );
		expect( paths.sort() ).toEqual( [
			'buttons.0.label',
			'imageId',
			'topline',
		] );
		expect( missingFields( fields, paths ) ).toEqual( [] );
	} );

	it( 'counts the items of a repeater', () => {
		const { error, repeaterItems } = analyzeMarkup(
			'<ul><li data-repeater-item="{&quot;postId&quot;:1}"></li><li data-repeater-item="{&quot;termId&quot;:2}"></li></ul>',
			fields
		);
		expect( error ).toBeNull();
		expect( repeaterItems ).toBe( 2 );
		expect( analyzeMarkup( '<p></p>', fields ).repeaterItems ).toBe( 0 );
	} );

	it( 'reports templates the runtime cannot handle', () => {
		expect( analyzeMarkup( '<p></p><p></p>', fields ).error ).toBe(
			'The template must render exactly one root element, it rendered 2 nodes.'
		);
		expect( analyzeMarkup( '', fields ).error ).toBe(
			'The template must render exactly one root element, it rendered 0 nodes.'
		);
		expect(
			analyzeMarkup( '<div data-field="imageId"></div>', fields ).error
		).toBe(
			'The root element cannot be an image field; mark an element inside it.'
		);
		expect(
			analyzeMarkup( '<p data-field="note"></p>', fields ).error
		).toBe( 'field( "note" ) is not an inline field of the block.' );
		expect(
			analyzeMarkup( '<div><p data-field="other"></p></div>', fields )
				.error
		).toBe( 'field( "other" ) is not an inline field of the block.' );
		// Edited in the inspector, not in the canvas.
		expect(
			analyzeMarkup( '<div><p data-field="note"></p></div>', fields )
				.error
		).toBe( 'field( "note" ) is not an inline field of the block.' );
	} );

	it( 'accepts a root element that is a text field', () => {
		const { root, error, paths } = analyzeMarkup(
			'<p class="lead" data-field="topline">Hello</p>',
			fields
		);
		expect( error ).toBeNull();
		expect( root.name ).toBe( 'p' );
		expect( paths ).toEqual( [ 'topline' ] );
	} );

	it( 'lists fields without wildcard that the markup hides', () => {
		const { paths } = analyzeMarkup( '<div></div>', fields );
		expect( missingFields( fields, paths ) ).toEqual( [
			'topline',
			'imageId',
		] );
	} );
} );
