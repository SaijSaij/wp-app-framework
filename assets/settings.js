/**
 * Entry of the settings page of a framework instance. Built by the consumer's
 * build (webpack.config.js of the framework), which defines
 * WP_APP_FRAMEWORK_SLUG; the page configuration comes from
 * `wpAppFramework[slug].settings` (SettingsPage in PHP).
 *
 * The API of the settings scripts (`@saij/wp-app-framework/settings`, mapped
 * to `wpAppFramework[slug].settingsApi`) exists as soon as this script ran;
 * the settings scripts load after it and register their controls before the
 * page renders on DOMContentLoaded.
 */
/* global WP_APP_FRAMEWORK_SLUG */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { SettingsPage } from './runtime/settings-page';
import { createSettingsApi } from './runtime/settings-api';
import { createSettingsRegistry } from './runtime/settings-registry';

import './settings.scss';

const instance = window.wpAppFramework?.[ WP_APP_FRAMEWORK_SLUG ];
const config = instance?.settings;
const registry = createSettingsRegistry( config?.groups ?? [] );
if ( instance ) {
	instance.settingsApi = createSettingsApi( registry );
}

domReady( () => {
	const root = config && document.getElementById( config.root );
	if ( ! root ) {
		throw new Error(
			`wp-app-framework: the settings page of ${ WP_APP_FRAMEWORK_SLUG } has no configuration or root element.`
		);
	}
	createRoot( root ).render(
		<SettingsPage config={ config } registry={ registry } />
	);
} );
