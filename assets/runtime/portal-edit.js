import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
} from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { Notice, ToolbarButton } from '@wordpress/components';
import { useRegistry, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';

import { analyzeMarkup, missingFields } from './analyze';
import {
	innerContext,
	providedContext,
	renderContext,
	usedContext,
} from './block-context';
import { BlockDataContext } from './context';
import { Inspector } from './inspector';
import { innerBlocksOptions } from './inner-blocks';
import { BlockMarkup } from './markup';
import { blankInlineText, stableStringify } from './paths';
import { parsePreview } from './parse-preview';
import { PreviewMarkup, usePreview } from './preview';

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
 * typing never re-renders, every other change does, including a change of
 * the context values the block uses.
 *
 * @param {string}           name
 * @param {Object}           attributes
 * @param {Object}           fields     Inline fields of the block.
 * @param {number|undefined} postId
 * @param {Object}           context    Context values from renderContext().
 * @return {{key: string, entry: Object}} Render job.
 */
function renderJob( name, attributes, fields, postId, context ) {
	const renderAttributes = blankInlineText( attributes, fields );
	const hasContext = Object.keys( context ).length > 0;
	return {
		key: stableStringify( {
			name,
			attributes: renderAttributes,
			postId,
			...( hasContext ? { context } : {} ),
		} ),
		entry: {
			name,
			attributes: renderAttributes,
			...( postId ? { postId } : {} ),
			...( hasContext ? { context } : {} ),
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
	const select = registry.select( 'core/block-editor' );
	const available = innerContext( select, getBlockType, clientId );
	const pending = select
		.getBlocks( clientId )
		.map( ( block ) => ( { block, available } ) );
	while ( pending.length > 0 ) {
		const { block: inner, available: context } = pending.shift();
		const blockType = getBlockType( inner.name );
		const config = runtime.config.blocks[ inner.name ];
		if ( config ) {
			const { key, entry } = renderJob(
				inner.name,
				inner.attributes,
				config.fields,
				postId,
				renderContext( usedContext( context, blockType ) )
			);
			runtime.renderer.prefetch( key, entry );
		}
		const next = providedContext( context, blockType, inner.attributes );
		pending.push(
			...inner.innerBlocks.map( ( block ) => ( {
				block,
				available: next,
			} ) )
		);
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
 * The toolbar of a block: the preview switch (blocks with view scripts) and
 * the buttons registered with registerToolbarButton().
 *
 * @param {Object}                                       props
 * @param {Array<import('react').ComponentType<Object>>} props.buttons     Registered toolbar button components.
 * @param {boolean}                                      props.hasView     Whether the block or one of its supports has a view script.
 * @param {boolean}                                      props.isPreview
 * @param {(isPreview: boolean) => void}                 props.setPreview
 * @param {Object}                                       props.buttonProps Props of the registered buttons.
 */
function Toolbar( { buttons, hasView, isPreview, setPreview, buttonProps } ) {
	return (
		<>
			{ hasView && (
				<BlockControls group="other">
					<ToolbarButton
						icon="visibility"
						label="Preview"
						isPressed={ isPreview }
						onClick={ () => setPreview( ! isPreview ) }
					/>
				</BlockControls>
			) }
			{ buttons.length > 0 && (
				<BlockControls>
					{ buttons.map( ( Button, index ) => (
						<Button key={ index } { ...buttonProps } />
					) ) }
				</BlockControls>
			) }
		</>
	);
}

/**
 * Edit component of every framework block: renders the block on the server
 * and replaces the marked elements with inline editors.
 *
 * In preview mode (blocks with view scripts) it shows the block's front-end
 * markup instead, with its views mounted; inline editing pauses until the
 * preview ends.
 *
 * @param {Object}                       props
 * @param {string}                       props.name          Block name.
 * @param {string}                       props.clientId      Client ID of the block.
 * @param {Object}                       props.attributes    Block attributes.
 * @param {(attributes: Object) => void} props.setAttributes Updates attributes.
 * @param {Object}                       props.context       Block context (postId in query loops, values of ancestors).
 * @param {Object}                       props.runtime       Renderer, registry and configuration of the instance.
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
	// The context object may change identity on every render; the values are compared by content.
	const contextKey = stableStringify( renderContext( context ) );
	const values = useMemo( () => JSON.parse( contextKey ), [ contextKey ] );
	const { key, entry } = useMemo(
		() => renderJob( name, attributes, block.fields, postId, values ),
		[ name, attributes, block.fields, postId, values ]
	);
	const innerOptions = useMemo(
		() =>
			innerBlocksOptions(
				block.innerBlockOptions,
				InnerBlocks.ButtonBlockAppender
			),
		[ block.innerBlockOptions ]
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

	const [ isPreview, setPreview ] = useState( false );
	const preview = usePreview( {
		clientId,
		postId,
		context: values,
		path: runtime.config.previewPath,
		enabled: isPreview,
	} );
	const previewMarkup = useMemo(
		() =>
			preview.html === undefined
				? null
				: parsePreview( preview.html, document ),
		[ preview.html ]
	);
	const viewRuntime = runtime.getViewRuntime();

	let content;
	if ( isPreview && preview.error ) {
		content = (
			<Message status="error">{ `${ name }: ${ preview.error.message }` }</Message>
		);
	} else if ( isPreview && previewMarkup?.error ) {
		content = (
			<Message status="error">{ `${ name }: ${ previewMarkup.error }` }</Message>
		);
	} else if ( isPreview && ! viewRuntime ) {
		content = (
			<Message status="error">{ `${ name }: the view runtime of ${ runtime.config.slug } is not loaded.` }</Message>
		);
	} else if ( isPreview && previewMarkup ) {
		content = (
			<PreviewMarkup
				markup={ previewMarkup }
				extraProps={ preview.loading ? LOADING_PROPS : IDLE_PROPS }
				viewRuntime={ viewRuntime }
			/>
		);
	} else if ( state.error && state.key === key ) {
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
				innerBlocksOptions={ innerOptions }
				extraProps={
					loading || ( isPreview && preview.loading )
						? LOADING_PROPS
						: IDLE_PROPS
				}
			/>
		);
	}

	// Props of every registered component: toolbar buttons, controls and panels.
	const componentProps = { attributes, setAttributes, clientId, isPreview };

	return (
		<BlockDataContext.Provider value={ data }>
			{ content }
			<Toolbar
				buttons={ runtime.registry.getToolbarButtons( name ) }
				hasView={ block.view }
				isPreview={ isPreview }
				setPreview={ setPreview }
				buttonProps={ componentProps }
			/>
			<Inspector
				registry={ runtime.registry }
				componentProps={ componentProps }
			/>
		</BlockDataContext.Provider>
	);
}
