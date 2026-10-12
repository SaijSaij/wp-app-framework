import apiFetch from '@wordpress/api-fetch';
import { InnerBlocks } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { RawHTML } from '@wordpress/element';

import { PortalEdit } from './portal-edit';
import { createRegistry } from './registry';
import { createRenderer } from './renderer';

/**
 * Starts the runtime of one framework instance: registers its blocks with the
 * editor and adds the API for its editor scripts to the instance global wpAppFramework[slug],
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
		// The view runtime of the instance (view.js), loaded with the editor runtime; mounts views in the preview.
		getViewRuntime: () => instance.viewRuntime,
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
			save: saveFunction( block ),
		} );
	}

	instance.registerControl = runtime.registry.registerControl;
	instance.registerSupportControl = runtime.registry.registerSupportControl;
	instance.registerPanel = runtime.registry.registerPanel;
	instance.registerToolbarButton = runtime.registry.registerToolbarButton;
}

/**
 * What a block stores besides the block comment: inner blocks without a
 * wrapper, the value of #[RichText(postContent: true)] as bare inline HTML
 * (the attribute is sourced from the whole inner HTML), otherwise nothing.
 *
 * @param {Object} block Block configuration.
 * @return {(props: {attributes: Object}) => import('react').ReactNode} save() of the block type.
 */
function saveFunction( block ) {
	if ( block.innerBlocks ) {
		return () => <InnerBlocks.Content />;
	}
	const field = block.postContentField;
	if ( field ) {
		// RawHTML without other props serializes without a wrapper element.
		return ( { attributes } ) => (
			<RawHTML>{ attributes[ field ] ?? '' }</RawHTML>
		);
	}
	return () => null;
}
