import { getData } from '../view/data';
import { loadScript } from '../view/load-script';

/**
 * The editor API of an instance: the import `@saij/wp-app-framework` of its editor scripts, which the webpack helper
 * maps to wpAppFramework[slug].api. Holds exactly what types/index.d.ts declares; the rest of the instance global is
 * internal.
 *
 * @param {string} slug     Slug of the instance.
 * @param {Object} registry Registry of the instance (createRegistry()).
 * @return {Readonly<Object>} The API.
 */
export function createApi( slug, registry ) {
	return Object.freeze( {
		registerControl: registry.registerControl,
		registerSupportControl: registry.registerSupportControl,
		registerPanel: registry.registerPanel,
		registerToolbarButton: registry.registerToolbarButton,
		loadScript: ( url, attributes ) => loadScript( url, attributes ),
		getData: ( key ) => getData( slug, key ),
	} );
}
