import { describe, expect, it } from 'vitest';

import {
	displayUrl,
	emptyLink,
	fromLinkControlValue,
	includePath,
	isLinked,
	linkFromSearchResult,
	resultTitle,
	resultUrl,
	searchPath,
	toLinkControlValue,
	unlink,
} from '../links';

describe( 'links', () => {
	it( 'maps stored links to LinkControl values', () => {
		expect( toLinkControlValue( emptyLink() ) ).toEqual( {
			url: '',
			opensInNewTab: false,
		} );
		expect( toLinkControlValue( undefined ) ).toEqual( {
			url: '',
			opensInNewTab: false,
		} );
		expect(
			toLinkControlValue( {
				url: 'https://example.org/',
				opensInNewTab: true,
				kind: 'url',
				id: 0,
			} )
		).toEqual( { url: 'https://example.org/', opensInNewTab: true } );
		const kinds = {
			post: 'post-type',
			term: 'taxonomy',
			attachment: 'media',
		};
		for ( const [ kind, controlKind ] of Object.entries( kinds ) ) {
			expect(
				toLinkControlValue( {
					url: 'https://example.org/a/',
					opensInNewTab: false,
					kind,
					id: 7,
				} )
			).toEqual( {
				url: 'https://example.org/a/',
				opensInNewTab: false,
				id: 7,
				kind: controlKind,
			} );
		}
	} );

	it( 'keeps exactly the four stored values of LinkControl values', () => {
		expect(
			fromLinkControlValue( {
				url: 'https://example.org/about/',
				opensInNewTab: true,
				id: 42,
				kind: 'post-type',
				type: 'page',
				title: 'About',
			} )
		).toStrictEqual( {
			url: 'https://example.org/about/',
			opensInNewTab: true,
			kind: 'post',
			id: 42,
		} );
		expect(
			fromLinkControlValue( {
				url: 'https://example.org/c/',
				id: 3,
				kind: 'taxonomy',
				type: 'category',
			} )
		).toStrictEqual( {
			url: 'https://example.org/c/',
			opensInNewTab: false,
			kind: 'term',
			id: 3,
		} );
		expect(
			fromLinkControlValue( {
				url: 'https://example.org/a.jpg',
				id: 9,
				kind: 'media',
				type: 'attachment',
			} ).kind
		).toBe( 'attachment' );
	} );

	it( 'makes everything without ID and known kind a free URL', () => {
		const free = {
			url: 'https://example.com/',
			opensInNewTab: false,
			kind: 'url',
			id: 0,
		};
		// Entered directly: LinkControl removes id, kind and type.
		expect(
			fromLinkControlValue( { url: 'https://example.com/' } )
		).toStrictEqual( free );
		expect(
			fromLinkControlValue( {
				url: 'https://example.com/',
				id: 5,
				kind: 'custom',
			} )
		).toStrictEqual( free );
		// "Unsync and edit" leaves no URL until a new one is applied.
		expect(
			fromLinkControlValue( {
				url: undefined,
				id: undefined,
				kind: undefined,
			} )
		).toStrictEqual( { ...free, url: '' } );
		expect( fromLinkControlValue( undefined ) ).toStrictEqual(
			emptyLink()
		);
	} );

	it( 'links search results and unlinks to free URLs', () => {
		const linked = linkFromSearchResult(
			{ id: 4, url: 'https://example.org/news/', title: 'News' },
			true
		);
		expect( linked ).toStrictEqual( {
			url: 'https://example.org/news/',
			opensInNewTab: true,
			kind: 'post',
			id: 4,
		} );
		expect( isLinked( linked ) ).toBe( true );
		expect( unlink( linked ) ).toStrictEqual( {
			url: 'https://example.org/news/',
			opensInNewTab: true,
			kind: 'url',
			id: 0,
		} );
		expect( isLinked( unlink( linked ) ) ).toBe( false );
		// The current URL of the object wins over the URL stored when it was chosen.
		expect( unlink( linked, 'https://example.org/moved/' ).url ).toBe(
			'https://example.org/moved/'
		);
		expect( isLinked( null ) ).toBe( false );
	} );

	it( 'builds the requests of the form', () => {
		expect( searchPath( 'a&b' ) ).toBe(
			'/wp/v2/search?type=post&subtype=any&search=a%26b&per_page=20&_fields=id%2Ctitle%2Curl'
		);
		expect( includePath( { kind: 'post', id: 4 } ) ).toBe(
			'/wp/v2/search?type=post&subtype=any&include=4&_fields=id%2Ctitle%2Curl'
		);
		expect( includePath( { kind: 'term', id: 3 } ) ).toBe(
			'/wp/v2/search?type=term&subtype=any&include=3&_fields=id%2Ctitle%2Curl'
		);
		expect( includePath( { kind: 'attachment', id: 9 } ) ).toBe(
			'/wp/v2/media?include=9&_fields=id%2Ctitle%2Csource_url'
		);
		expect( resultTitle( { title: 'News' } ) ).toBe( 'News' );
		expect( resultTitle( { title: { rendered: 'Photo' } } ) ).toBe(
			'Photo'
		);
		expect( resultTitle( undefined ) ).toBe( '' );
		expect( resultUrl( { url: 'https://example.org/a/' } ) ).toBe(
			'https://example.org/a/'
		);
		expect( resultUrl( { source_url: 'https://example.org/a.jpg' } ) ).toBe(
			'https://example.org/a.jpg'
		);
		expect( resultUrl( undefined ) ).toBe( '' );
	} );

	it( 'shortens URLs for display', () => {
		expect( displayUrl( 'https://www.example.org/about/' ) ).toBe(
			'example.org/about'
		);
		expect( displayUrl( 'mailto:a@example.org' ) ).toBe(
			'mailto:a@example.org'
		);
		expect(
			displayUrl( 'https://example.org/a/very/long/path/to/a/page/', 20 )
		).toBe( 'example.o…to/a/page' );
	} );
} );
