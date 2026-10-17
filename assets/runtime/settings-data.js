import { __, sprintf } from '@wordpress/i18n';

/**
 * Data of the settings page without React: values, edits and error messages.
 */

/**
 * The value of a group in the form: its defaults, overridden by the stored
 * settings. WordPress returns null for a stored value that no longer matches
 * the schema (e.g. after a removed enum case); the form then shows the defaults.
 *
 * @param {Object}  group  Group configuration.
 * @param {?Object} stored Value of the option from /wp/v2/settings.
 * @return {Object} Value of every setting of the group.
 */
export function groupValue( group, stored ) {
	return {
		...group.default,
		...( stored !== null && typeof stored === 'object' ? stored : {} ),
	};
}

/**
 * Names of the groups with unsaved changes.
 *
 * @param {Array<Object>} groups Group configurations.
 * @param {?Object}       edits  Edits of the site entity in core-data.
 * @return {Array<string>} Group names.
 */
export function editedGroups( groups, edits ) {
	return groups
		.filter( ( group ) => edits && group.option in edits )
		.map( ( group ) => group.name );
}

/**
 * Message for a failed save: /wp/v2/settings rejects the whole request and
 * names the invalid option with the path of the setting, e.g.
 * "my_theme_general[density] is not one of compact and normal.".
 *
 * @param {?Object}       error  Error of the REST request.
 * @param {Array<Object>} groups Group configurations.
 * @return {string} Message.
 */
export function saveErrorMessage( error, groups ) {
	const params = error?.data?.params;
	if ( params && typeof params === 'object' ) {
		const details = Object.entries( params ).map( ( [ option, text ] ) => {
			const group = groups.find( ( entry ) => entry.option === option );
			return group ? `${ group.title }: ${ text }` : String( text );
		} );
		if ( details.length > 0 ) {
			return sprintf(
				/* translators: %s: the errors */
				__( 'The settings were not saved. %s', 'wp-app-framework' ),
				details.join( ' ' )
			);
		}
	}
	return sprintf(
		/* translators: %s: the error */
		__( 'The settings were not saved. %s', 'wp-app-framework' ),
		error?.message ?? __( 'Unknown error.', 'wp-app-framework' )
	);
}
