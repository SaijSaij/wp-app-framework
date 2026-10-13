import { describe, expect, it } from 'vitest';

import { includePath, move, searchPath, titles } from '../references';

const pages = { target: 'post', subtypes: [ 'page', 'post' ] };
const anyTerm = { target: 'term', subtypes: null };
const user = { target: 'user', subtypes: null };

describe( 'reference requests', () => {
	it( 'search posts and terms of the allowed subtypes', () => {
		expect( searchPath( pages, 'about us' ) ).toBe(
			'/wp/v2/search?type=post&subtype=page%2Cpost&_fields=id%2Ctitle&search=about+us&per_page=20'
		);
		expect( searchPath( anyTerm, 'x' ) ).toBe(
			'/wp/v2/search?type=term&subtype=any&_fields=id%2Ctitle&search=x&per_page=20'
		);
	} );

	it( 'search the collection of the post type of a post class', () => {
		const venues = {
			target: 'post',
			subtypes: [ 'dev_test_venue' ],
			restBase: 'dev_test_venue',
		};
		expect( searchPath( venues, 'hall' ) ).toBe(
			'/wp/v2/dev_test_venue?_fields=id%2Ctitle&search=hall&per_page=20'
		);
		expect( includePath( venues, [ 4 ] ) ).toBe(
			'/wp/v2/dev_test_venue?_fields=id%2Ctitle&include=4&per_page=1'
		);
		expect(
			titles( venues, [ { id: 4, title: { rendered: 'Hall' } } ] )
		).toEqual( { 4: 'Hall' } );
	} );

	it( 'search users through the users route', () => {
		expect( searchPath( user, 'ann' ) ).toBe(
			'/wp/v2/users?_fields=id%2Cname&search=ann&per_page=20'
		);
	} );

	it( 'load the titles of stored IDs', () => {
		expect( includePath( pages, [ 3, 1 ] ) ).toBe(
			'/wp/v2/search?type=post&subtype=page%2Cpost&_fields=id%2Ctitle&include=3%2C1&per_page=2'
		);
	} );

	it( 'map results to titles, empty titles to the ID', () => {
		expect(
			titles( pages, [
				{ id: 1, title: 'About' },
				{ id: 2, title: '' },
			] )
		).toEqual( { 1: 'About', 2: '#2' } );
		expect( titles( user, [ { id: 5, name: 'Ann' } ] ) ).toEqual( {
			5: 'Ann',
		} );
	} );

	it( 'moves entries of a list', () => {
		expect( move( [ 1, 2, 3 ], 0, 2 ) ).toEqual( [ 2, 3, 1 ] );
	} );
} );
