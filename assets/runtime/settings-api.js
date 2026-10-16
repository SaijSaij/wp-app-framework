/**
 * The API of settings scripts, `@saij/wp-app-framework/settings`: the webpack
 * helper maps the import to `wpAppFramework[slug].settingsApi`. Its functions
 * are declared in types/settings.d.ts.
 *
 * @param {ReturnType<import('./settings-registry').createSettingsRegistry>} registry
 * @return {Readonly<{registerSettingsControl: (group: string, path: string|Array<string>, component: import('react').ComponentType<Object>) => void}>} API.
 */
export function createSettingsApi( registry ) {
	return Object.freeze( {
		registerSettingsControl: registry.registerSettingsControl,
	} );
}
