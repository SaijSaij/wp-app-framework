/**
 * Dates of #[DateTime] values: the local time of the site without time zone,
 * `2026-11-14T20:00`, or a date alone, `2026-11-14`; '' for no date. The same
 * patterns as DateTime::PATTERN and DateTime::DATE_PATTERN in PHP.
 */
export const DATE_TIME_PATTERN = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})?$/;
export const DATE_PATTERN = /^(\d{4}-\d{2}-\d{2})?$/;

/**
 * The value of a date the date picker of WordPress chose: it reports the
 * local time of the site as `Y-m-d\TH:i:s`, which a #[DateTime] value stores
 * without seconds, or without time of day.
 *
 * @param {string|null} picked The value of the picker's onChange.
 * @param {boolean}     time   With time of day.
 * @return {string|null} The value to store, or null if the picker reported none.
 */
export function dateFromPicker( picked, time ) {
	if ( typeof picked !== 'string' ) {
		return null;
	}
	const value = picked.slice( 0, time ? 16 : 10 );
	return ( time ? DATE_TIME_PATTERN : DATE_PATTERN ).test( value ) &&
		value !== ''
		? value
		: null;
}

/**
 * Whether a time format of PHP shows a 12-hour clock: `a` or `A` (am/pm) that
 * is not escaped with a backslash.
 *
 * @param {string} format E.g. the time format of the site's settings.
 * @return {boolean} True for a 12-hour clock.
 */
export function is12HourFormat( format ) {
	return /[aA]/.test( format.replace( /\\./g, '' ) );
}
