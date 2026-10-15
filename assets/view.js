/**
 * Entry of the view runtime. Built by the consumer's build (webpack.config.js
 * of the framework), which defines WP_APP_FRAMEWORK_SLUG; loaded on pages that
 * render a block with view scripts and in the block editor.
 *
 * Provides the base classes as wpAppFramework[slug].view (the import
 * `@saij/wp-app-framework/view` of view scripts), and, for the editor runtime,
 * wpAppFramework[slug].viewRuntime. On the front end it mounts the views of the
 * instance on DOMContentLoaded, after the deferred view scripts have run: first
 * its document views (wpAppFramework[slug].documentViews, printed by the
 * server), then the views of its blocks.
 */
/* global WP_APP_FRAMEWORK_SLUG */
import { getData } from './view/data';
import { loadScript } from './view/load-script';
import { mountDocumentViews, mountWithin } from './view/mount';
import { BlockView, DocumentView, SupportView } from './view/view';

const slug = WP_APP_FRAMEWORK_SLUG;
window.wpAppFramework = window.wpAppFramework || {};
const instance = ( window.wpAppFramework[ slug ] =
	window.wpAppFramework[ slug ] || {} );

// Only what types/view.d.ts declares.
instance.view = Object.freeze( {
	BlockView,
	SupportView,
	DocumentView,
	loadScript: ( url, attributes ) => loadScript( url, attributes ),
	getData: ( key ) => getData( slug, key ),
} );
instance.viewRuntime = {
	mountWithin: ( root, options ) =>
		mountWithin( slug, instance.views, root, options ),
};

let mounted = false;
function mountDocument() {
	if ( mounted ) {
		return;
	}
	mounted = true;
	mountDocumentViews(
		slug,
		instance.views,
		instance.documentViews ?? [],
		document
	);
	mountWithin( slug, instance.views, document.documentElement, {
		isPreview: false,
	} );
}

// Deferred scripts run before DOMContentLoaded, but already with readyState "interactive"; the view
// scripts run after this one. A runtime loaded after DOMContentLoaded still sees the load event.
if ( document.readyState === 'complete' ) {
	mountDocument();
} else {
	document.addEventListener( 'DOMContentLoaded', mountDocument );
	window.addEventListener( 'load', mountDocument );
}
