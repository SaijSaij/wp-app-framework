import { describe, expect, it } from 'vitest';

import {
	DATE_PATTERN,
	DATE_TIME_PATTERN,
	dateFromPicker,
	is12HourFormat,
} from '../dates';

describe( 'DATE_TIME_PATTERN and DATE_PATTERN', () => {
	it( 'accept their format and the empty string', () => {
		for ( const value of [ '', '2026-11-14T20:00' ] ) {
			expect( DATE_TIME_PATTERN.test( value ) ).toBe( true );
		}
		for ( const value of [ '', '2026-11-14' ] ) {
			expect( DATE_PATTERN.test( value ) ).toBe( true );
		}
		for ( const value of [
			'2026-11-14',
			'2026-11-14 20:00',
			'2026-11-14T20:00:00',
			'2026-11-14T20:00\n',
		] ) {
			expect( DATE_TIME_PATTERN.test( value ) ).toBe( false );
		}
		for ( const value of [ '2026-11-14T20:00', '14.11.2026' ] ) {
			expect( DATE_PATTERN.test( value ) ).toBe( false );
		}
	} );
} );

describe( 'dateFromPicker', () => {
	it( 'drops the seconds, or the time of day for a date alone', () => {
		expect( dateFromPicker( '2026-11-14T20:00:00', true ) ).toBe(
			'2026-11-14T20:00'
		);
		expect( dateFromPicker( '2026-11-14T20:00:00', false ) ).toBe(
			'2026-11-14'
		);
	} );

	it( 'reports nothing the value cannot store', () => {
		expect( dateFromPicker( null, true ) ).toBeNull();
		expect( dateFromPicker( '', true ) ).toBeNull();
		expect( dateFromPicker( 'Invalid date', true ) ).toBeNull();
	} );
} );

describe( 'is12HourFormat', () => {
	it( 'finds am/pm that is not escaped', () => {
		expect( is12HourFormat( 'g:i a' ) ).toBe( true );
		expect( is12HourFormat( 'g:i A' ) ).toBe( true );
		expect( is12HourFormat( 'H:i' ) ).toBe( false );
		expect( is12HourFormat( 'G \\U\\h\\r' ) ).toBe( false );
		expect( is12HourFormat( 'H:i \\a' ) ).toBe( false );
	} );
} );
