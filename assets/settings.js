/**
 * Entry of the settings page of a framework instance. Built by the consumer's
 * build (webpack.config.js of the framework), which defines
 * WP_APP_FRAMEWORK_SLUG; the page configuration comes from
 * `wpAppFramework[slug].settings` (SettingsPage in PHP).
 */
/* global WP_APP_FRAMEWORK_SLUG */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { SettingsPage } from './runtime/settings-page';

import './settings.scss';

domReady( () => {
	const config = window.wpAppFramework?.[ WP_APP_FRAMEWORK_SLUG ]?.settings;
	const root = config && document.getElementById( config.root );
	if ( ! root ) {
		throw new Error(
			`wp-app-framework: the settings page of ${ WP_APP_FRAMEWORK_SLUG } has no configuration or root element.`
		);
	}
	createRoot( root ).render( <SettingsPage config={ config } /> );
} );
