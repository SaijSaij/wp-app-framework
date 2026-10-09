import apiFetch from '@wordpress/api-fetch';
import { InnerBlocks } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';

import { PortalEdit } from './portal-edit';
import { createRegistry } from './registry';
import { createRenderer } from './renderer';

/**
 * Starts the runtime of one framework instance: registers its blocks with the
 * editor and adds the theme API to the instance global wpAppFramework[slug],
 * which the server prefilled with the configuration.
 *
 * @param {string} slug Slug of the instance.
 */
export function startRuntime( slug ) {
	const instance = window.wpAppFramework?.[ slug ];
	if ( ! instance?.config ) {
		throw new Error(
			`wp-app-framework: the configuration of "${ slug }" is missing; the runtime must be loaded through the framework (EditorAssets).`
		);
	}

	const { config } = instance;
	const runtime = {
		config,
		renderer: createRenderer( {
			fetch: apiFetch,
			path: config.renderPath,
			maxBlocks: config.maxBlocks,
		} ),
		registry: createRegistry( config.blocks, getBlockType ),
	};

	for ( const [ name, block ] of Object.entries( config.blocks ) ) {
		registerBlockType( name, {
			edit: ( props ) => <PortalEdit { ...props } runtime={ runtime } />,
			// Only block comments are stored; inner blocks are serialized without a wrapper.
			save: block.innerBlocks
				? () => <InnerBlocks.Content />
				: () => null,
		} );
	}

	instance.registerControl = runtime.registry.registerControl;
	instance.registerPanel = runtime.registry.registerPanel;
}
