import apiFetch from '@wordpress/api-fetch';
import { useBlockProps } from '@wordpress/block-editor';
import { serialize } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { attributesToProps } from 'html-react-parser';

/** Class of the root element of a framework block in the canvas (canvas.scss). */
const BLOCK_CLASS = 'wp-app-framework-block';

/**
 * Front-end markup of a block for the preview, loaded while the preview is on:
 * the block with its inner blocks, serialized and rendered by the preview
 * route of the instance. Reloads when the block changes.
 *
 * @param {Object}           options
 * @param {string}           options.clientId
 * @param {number|undefined} options.postId
 * @param {string}           options.path     Preview route of the instance.
 * @param {boolean}          options.enabled  Whether the preview is on.
 * @return {{html: string|undefined, error: Error|null, loading: boolean}} Preview state.
 */
export function usePreview( { clientId, postId, path, enabled } ) {
	const block = useSelect(
		( select ) =>
			enabled ? select( 'core/block-editor' ).getBlock( clientId ) : null,
		[ clientId, enabled ]
	);
	const content = useMemo(
		() => ( block ? serialize( block ) : null ),
		[ block ]
	);
	const [ state, setState ] = useState( {
		content: null,
		html: undefined,
		error: null,
	} );

	useEffect( () => {
		if ( content === null ) {
			return undefined;
		}
		const controller = new AbortController();
		apiFetch( {
			path,
			method: 'POST',
			data: { content, ...( postId ? { postId } : {} ) },
			signal: controller.signal,
		} ).then(
			( response ) =>
				setState( { content, html: response.html, error: null } ),
			( error ) => {
				if ( error?.name !== 'AbortError' ) {
					setState( ( previous ) => ( {
						content,
						html: previous.html,
						error,
					} ) );
				}
			}
		);
		return () => controller.abort();
	}, [ content, path, postId ] );

	return {
		html: enabled ? state.html : undefined,
		error: enabled && state.content === content ? state.error : null,
		loading: enabled && state.content !== content,
	};
}

/**
 * The block's front-end markup in the canvas with its views mounted. The root
 * element carries the block props of the editor; React does not manage the
 * markup inside it, so view scripts may change it. The views unmount before the
 * markup changes and when the preview ends.
 *
 * @param {Object} props
 * @param {Object} props.markup      Result of parsePreview().
 * @param {Object} props.extraProps  Additional props of the root element (loading state).
 * @param {Object} props.viewRuntime The view runtime of the instance (wpAppFramework[slug].viewRuntime).
 */
export function PreviewMarkup( { markup, extraProps, viewRuntime } ) {
	const ref = useRef();
	const props = attributesToProps( markup.attributes, markup.tagName );
	const blockProps = useBlockProps( {
		...props,
		...extraProps,
		ref,
		className: [ props.className, extraProps.className, BLOCK_CLASS ]
			.filter( Boolean )
			.join( ' ' ),
	} );

	useEffect(
		() => viewRuntime.mountWithin( ref.current, { isPreview: true } ),
		[ markup, viewRuntime ]
	);

	const Tag = markup.tagName;
	return (
		<Tag
			{ ...blockProps }
			dangerouslySetInnerHTML={ { __html: markup.innerHTML } }
		/>
	);
}
