import { useBlockProps } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { useRegistry, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';

import { analyzeMarkup, missingFields } from './analyze';
import { BlockDataContext } from './context';
import { Inspector } from './inspector';
import { BlockMarkup } from './markup';
import { blankInlineText, stableStringify } from './paths';

/** Root props while new markup loads: the old markup stays, dimmed, without layout change. */
const LOADING_PROPS = {
	'aria-busy': true,
	className: 'wp-app-framework-is-loading',
};
const IDLE_PROPS = {};

/**
 * The numeric ID of the post the block belongs to: the postId context (query
 * loops), else the edited post. Templates in the site editor have no numeric ID.
 *
 * @param {Object} context Block context.
 * @return {number|undefined} Post ID.
 */
function usePostId( context ) {
	const editedPostId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.(),
		[]
	);
	const postId = context?.postId ?? editedPostId;
	return Number.isInteger( postId ) && postId > 0 ? postId : undefined;
}

/**
 * Cache key and request entry of a block. Inline text values are blanked:
 * typing never re-renders, every other change does.
 *
 * @param {string}           name
 * @param {Object}           attributes
 * @param {Object}           fields     Inline fields of the block.
 * @param {number|undefined} postId
 * @return {{key: string, entry: Object}} Render job.
 */
function renderJob( name, attributes, fields, postId ) {
	const renderAttributes = blankInlineText( attributes, fields );
	return {
		key: stableStringify( { name, attributes: renderAttributes, postId } ),
		entry: {
			name,
			attributes: renderAttributes,
			...( postId ? { postId } : {} ),
		},
	};
}

/**
 * Prefetches the blocks of the instance among the inner blocks of a
 * container: they mount only after the container's markup arrived and would
 * otherwise need a second request.
 *
 * @param {Object}           registry Data registry.
 * @param {string}           clientId Container.
 * @param {Object}           runtime
 * @param {number|undefined} postId
 */
function prefetchInnerBlocks( registry, clientId, runtime, postId ) {
	const pending = [
		...registry.select( 'core/block-editor' ).getBlocks( clientId ),
	];
	while ( pending.length > 0 ) {
		const inner = pending.shift();
		const config = runtime.config.blocks[ inner.name ];
		if ( config ) {
			const { key, entry } = renderJob(
				inner.name,
				inner.attributes,
				config.fields,
				postId
			);
			runtime.renderer.prefetch( key, entry );
		}
		pending.push( ...inner.innerBlocks );
	}
}

/**
 * The empty element shown before the first markup arrives.
 */
function Placeholder() {
	return (
		<div
			{ ...useBlockProps( {
				'aria-busy': true,
				className: 'wp-app-framework-placeholder',
			} ) }
		/>
	);
}

function Message( { status, children } ) {
	return (
		<div { ...useBlockProps() }>
			<Notice status={ status } isDismissible={ false }>
				{ children }
			</Notice>
		</div>
	);
}

/**
 * Edit component of every framework block: renders the block on the server
 * and replaces the marked elements with inline editors.
 *
 * @param {Object}   props
 * @param {string}   props.name          Block name.
 * @param {string}   props.clientId      Client ID of the block.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Updates attributes.
 * @param {Object}   props.context       Block context (postId in query loops).
 * @param {Object}   props.runtime       Renderer, registry and configuration of the instance.
 */
export function PortalEdit( {
	name,
	clientId,
	attributes,
	setAttributes,
	context,
	runtime,
} ) {
	const block = runtime.config.blocks[ name ];
	const registry = useRegistry();
	const postId = usePostId( context );
	const { key, entry } = useMemo(
		() => renderJob( name, attributes, block.fields, postId ),
		[ name, attributes, block.fields, postId ]
	);

	const [ state, setState ] = useState( () => ( {
		key,
		html: runtime.renderer.peek( key ),
		error: null,
	} ) );

	useEffect( () => {
		if ( state.key === key && state.html !== undefined ) {
			return undefined;
		}
		const { promise, cancel } = runtime.renderer.render( key, entry );
		if ( block.innerBlocks ) {
			prefetchInnerBlocks( registry, clientId, runtime, postId );
		}
		let active = true;
		promise.then(
			( html ) => active && setState( { key, html, error: null } ),
			( error ) =>
				active &&
				setState( ( previous ) => ( {
					key,
					html: previous.html,
					error,
				} ) )
		);
		return () => {
			active = false;
			cancel();
		};
		// The key covers the entry.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	const analysis = useMemo(
		() =>
			state.html === undefined
				? null
				: analyzeMarkup( state.html, block.fields ),
		[ state.html, block.fields ]
	);
	useEffect( () => {
		if ( runtime.config.development && analysis?.root ) {
			for ( const path of missingFields(
				block.fields,
				analysis.paths
			) ) {
				// eslint-disable-next-line no-console
				console.warn(
					`wp-app-framework: ${ name } renders no element with field( "${ path }" ) in the editor, so it cannot be edited inline. Render inline fields in the editor even when they are empty: {% if … or is_editor() %}.`
				);
			}
		}
	}, [ analysis, block.fields, name, runtime.config.development ] );

	const data = useMemo(
		() => ( { name, block, attributes, setAttributes } ),
		[ name, block, attributes, setAttributes ]
	);
	const loading = state.key !== key;

	let content;
	if ( state.error && state.key === key ) {
		content = (
			<Message status="error">{ `${ name }: ${ state.error.message }` }</Message>
		);
	} else if ( analysis === null ) {
		content = <Placeholder />;
	} else if ( analysis.error ) {
		content = (
			<Message status="error">{ `${ name }: ${ analysis.error }` }</Message>
		);
	} else {
		content = (
			<BlockMarkup
				node={ analysis.root }
				fields={ block.fields }
				extraProps={ loading ? LOADING_PROPS : IDLE_PROPS }
			/>
		);
	}

	return (
		<BlockDataContext.Provider value={ data }>
			{ content }
			<Inspector registry={ runtime.registry } />
		</BlockDataContext.Provider>
	);
}
