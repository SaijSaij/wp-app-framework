import { describe, expect, it } from 'vitest';

import { editorSettings } from '../classic-editor-settings';

describe( 'editorSettings', () => {
	it( 'offers the buttons and elements of the allowed formats only', () => {
		expect( editorSettings( [ 'core/bold', 'core/link' ] ) ).toEqual( {
			toolbar1: 'bold,link',
			valid_elements: 'strong/b,a[href|target|rel],br',
			wpautop: false,
			forced_root_block: false,
		} );
	} );

	it( 'offers every format without a restriction', () => {
		expect( editorSettings( null ).toolbar1 ).toBe(
			'bold,italic,link,strikethrough,subscript,superscript'
		);
	} );
} );
