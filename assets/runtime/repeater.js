import {
	BlockContextProvider,
	// The live preview of the other items, as core/post-template has it; design.md explains the exception.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBlockPreview as useBlockPreview,
	store as blockEditorStore,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { memo } from '@wordpress/element';

import { REPEATER_ITEM } from './analyze';
import { __ } from '@wordpress/i18n';

/**
 * Items of a repeater (blocks whose render() accepts a Repeater): the server
 * marks the element of each item with repeater_item(), holding the item's
 * block context as JSON. In the editor the active item – the first, or the
 * one clicked – shows the inner blocks to edit, the others a live preview of
 * the same inner blocks in their context, like core/post-template.
 */

/** Class of the preview of an item that is not edited (canvas.scss). */
const PREVIEW_CLASS = 'wp-app-framework-repeater-preview';

/**
 * Fails early when the block editor lacks the preview the repeater needs:
 * `__experimentalUseBlockPreview` is not a stable API of WordPress.
 *
 * @param {Object} blocks Block configurations of the instance.
 * @param {string} slug
 */
export function checkBlockPreview( blocks, slug ) {
	const repeaters = Object.entries( blocks )
		.filter( ( [ , block ] ) => block.repeater )
		.map( ( [ name ] ) => name );
	if ( repeaters.length > 0 && typeof useBlockPreview !== 'function' ) {
		throw new Error(
			`wp-app-framework: the blocks ${ repeaters.join(
				', '
			) } of ${ slug } repeat their inner blocks (Repeater), but this version of WordPress has no __experimentalUseBlockPreview in @wordpress/block-editor; the framework needs an update for it.`
		);
	}
}

/**
 * The block context of an item from its element.
 *
 * @param {Object} node Element marked with repeater_item().
 * @return {Object} Context, e.g. { postId: 12, postType: "post" }.
 */
export function itemContext( node ) {
	try {
		const context = JSON.parse( node.attribs[ REPEATER_ITEM ] );
		return context !== null && typeof context === 'object' ? context : {};
	} catch {
		return {};
	}
}

function ActiveElement( { tagName: Tag, props, options } ) {
	return <Tag { ...useInnerBlocksProps( props, options ) } />;
}

/**
 * The preview of the inner blocks in the context of another item; a click or
 * Enter makes it the active item.
 */
const PreviewElement = memo( function Preview( {
	tagName: Tag,
	props,
	clientId,
	onActivate,
} ) {
	const blocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);
	const preview = useBlockPreview( {
		blocks,
		props: {
			...props,
			className: [ props.className, PREVIEW_CLASS ]
				.filter( Boolean )
				.join( ' ' ),
		},
	} );
	return (
		<Tag
			{ ...preview }
			role="button"
			tabIndex={ 0 }
			aria-label={ __( 'Edit this item', 'wp-app-framework' ) }
			onClick={ onActivate }
			onKeyDown={ ( event ) => {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					onActivate();
				}
			} }
		/>
	);
} );

/**
 * The element of an item: the inner blocks to edit if it is the active one,
 * else their preview; both in the block context of the item.
 *
 * @param {Object}     props
 * @param {string}     props.tagName
 * @param {Object}     props.props      Props of the element.
 * @param {Object}     props.context    Block context of the item.
 * @param {boolean}    props.isActive
 * @param {() => void} props.onActivate Makes the item the active one.
 * @param {string}     props.clientId   Client ID of the repeating block.
 * @param {Object}     props.options    Options of useInnerBlocksProps() (#[InnerBlocks]).
 */
export function RepeaterItem( {
	tagName,
	props,
	context,
	isActive,
	onActivate,
	clientId,
	options,
} ) {
	return (
		<BlockContextProvider value={ context }>
			{ isActive ? (
				<ActiveElement
					tagName={ tagName }
					props={ props }
					options={ options }
				/>
			) : (
				<PreviewElement
					tagName={ tagName }
					props={ props }
					clientId={ clientId }
					onActivate={ onActivate }
				/>
			) }
		</BlockContextProvider>
	);
}
