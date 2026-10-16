/**
 * Controls that settings scripts (src/**\/*.settings.js) register for values
 * on the settings page: registerSettingsControl( group, path, component ).
 * A path names a setting of the group and, below it, fields of value classes
 * and `*` for the items of a list, e.g. "schemes" or [ "schemes", "*" ].
 */

/**
 * A path as pattern, e.g. [ "schemes", "*" ] → "schemes.*".
 *
 * @param {string|Array<string>} path
 * @return {string} Pattern.
 */
function toPattern( path ) {
	return Array.isArray( path ) ? path.join( '.' ) : path;
}

/**
 * Why a pattern names no value of a group with a control, or null.
 *
 * @param {Object} group   Group configuration (controls by setting).
 * @param {string} pattern e.g. "schemes.*.name".
 * @return {string|null} Reason.
 */
function unknownPath( group, pattern ) {
	const segments = pattern.split( '.' );
	let control = group.controls[ segments[ 0 ] ];
	if ( control === undefined ) {
		return `the group has no setting "${ segments[ 0 ] }" with a control`;
	}
	for ( const segment of segments.slice( 1 ) ) {
		if ( segment === '*' ) {
			if ( control.type !== 'list' ) {
				return `"*" stands for the items of a list, but the value before it is no list`;
			}
			control = control.item;
		} else if ( control.type === 'object' ) {
			control = control.fields[ segment ];
			if ( control === undefined ) {
				return `the value has no field "${ segment }" with a control`;
			}
		} else {
			return `"${ segment }" names a field, but the value before it is no value class`;
		}
	}
	return null;
}

/**
 * @param {Array<Object>} groups Group configurations of the page (`wpAppFramework[slug].settings.groups`).
 * @return {{registerSettingsControl: (group: string, path: string|Array<string>, component: import('react').ComponentType<Object>) => void, getSettingsControl: (group: string, pattern: string) => (import('react').ComponentType<Object>)|undefined}} Registry.
 */
export function createSettingsRegistry( groups ) {
	const controls = new Map();
	const key = ( group, path ) => `${ group }\u0000${ path }`;

	/**
	 * Replaces the control of a value of a settings group.
	 *
	 * @param {string}                                group     Name of the group, as in #[SettingsGroup].
	 * @param {string|Array<string>}                  path      Setting, fields and `*` for list items.
	 * @param {import('react').ComponentType<Object>} component Receives { value, onChange, path, groupValue, DefaultControl }.
	 */
	function registerSettingsControl( group, path, component ) {
		const name = toPattern( path );
		const call = `wp-app-framework: registerSettingsControl( "${ group }", ${ JSON.stringify(
			path
		) } )`;
		const config = groups.find( ( candidate ) => candidate.name === group );
		if ( config === undefined ) {
			// eslint-disable-next-line no-console
			console.warn(
				`${ call }: the theme or plugin has no settings group "${ group }".`
			);
		} else {
			const reason = unknownPath( config, name );
			if ( reason !== null ) {
				// eslint-disable-next-line no-console
				console.warn( `${ call }: ${ reason }.` );
			}
		}
		controls.set( key( group, name ), component );
	}

	return {
		registerSettingsControl,
		getSettingsControl: ( group, path ) =>
			controls.get( key( group, path ) ),
	};
}
