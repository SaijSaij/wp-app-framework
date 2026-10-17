import apiFetch from '@wordpress/api-fetch';
import { InnerBlocks } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { RawHTML } from '@wordpress/element';

import { createApi } from './api';
import { PortalEdit } from './portal-edit';
import { registerMetaPanels } from './meta-panels';
import { createRegistry } from './registry';
import { createRenderer } from './renderer';
import { checkBlockPreview } from './repeater';

/**
 * Starts the runtime of one framework instance: registers its blocks and the
 * panels of its meta classes with the editor and provides the API for its
 * editor scripts as wpAppFramework[slug].api (the import `@webcodingcat/wp-app-framework`).
 * The server prefilled wpAppFramework[slug].config with the configuration.
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
	checkBlockPreview( config.blocks, slug );
	const runtime = {
		config,
		// The view runtime of the instance (view.js), loaded with the editor runtime; mounts views in the preview.
		getViewRuntime: () => instance.viewRuntime,
		renderer: createRenderer( {
			fetch: apiFetch,
			path: config.renderPath,
			maxBlocks: config.maxBlocks,
		} ),
		registry: createRegistry(
			config.blocks,
			getBlockType,
			config.namespace
		),
	};

	for ( const [ name, block ] of Object.entries( config.blocks ) ) {
		registerBlockType( name, {
			edit: ( props ) => <PortalEdit { ...props } runtime={ runtime } />,
			save: saveFunction( block ),
		} );
	}

	registerMetaPanels( slug, config.meta );

	instance.api = createApi( slug, runtime.registry );
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
