import apiFetch from '@wordpress/api-fetch';
import {
	BaseControl,
	Button,
	ComboboxControl,
	Flex,
	FlexBlock,
	FlexItem,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

import { useBufferedText } from './controls';
import {
	emptyLink,
	includePath,
	isLinked,
	linkFromSearchResult,
	resultTitle,
	resultUrl,
	searchPath,
	unlink,
} from './links';

/** Delay before a typed search is sent. */
const SEARCH_DELAY = 300;

const formatText = ( text ) => text;
const parseText = ( text ) => text;

/**
 * Title and current URL of a linked object, fetched once per object.
 *
 * @param {Object}                                       link
 * @param {Object<string, {title: string, url: string}>} known Objects by "kind:id" from search results.
 * @return {?{title: string, url: string}} The object; null while unknown.
 */
function useLinkedObject( link, known ) {
	const [ objects, setObjects ] = useState( {} );
	const key = isLinked( link ) ? `${ link.kind }:${ link.id }` : null;
	const object = key === null ? null : ( known[ key ] ?? objects[ key ] );

	useEffect( () => {
		if ( key === null || object !== undefined ) {
			return;
		}
		apiFetch( { path: includePath( link ) } ).then( ( results ) => {
			// Objects the request does not return (e.g. drafts) show their stored URL.
			setObjects( ( previous ) => ( {
				...previous,
				[ key ]: {
					title: resultTitle( results[ 0 ] ),
					url: resultUrl( results[ 0 ] ),
				},
			} ) );
		} );
		// The link is covered by the key.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key, object ] );

	return object ?? null;
}

/**
 * A link value (`Link`) outside the block editor (settings page, meta boxes
 * of the classic edit screen), where LinkControl and its search are not
 * available: a search of posts, which links the chosen post with its ID, or a
 * URL entered freely, plus "Open in new tab". A linked post shows its title
 * and can be unlinked, which keeps its current URL as a free URL.
 *
 * @param {Object}                  props
 * @param {Object}                  props.control  Control description.
 * @param {?Object}                 props.value    Stored link.
 * @param {(value: Object) => void} props.onChange
 * @param {string}                  [props.label]
 */
export function LinkFormControl( { control, value, onChange, label } ) {
	const name = label ?? control.label;
	const link = value ?? emptyLink();
	const [ results, setResults ] = useState( [] );
	const [ known, setKnown ] = useState( {} );
	const timer = useRef( null );
	const linked = useLinkedObject( link, known );
	const { text, change, flush } = useBufferedText(
		link.url,
		( url ) =>
			onChange( {
				url,
				opensInNewTab: link.opensInNewTab,
				kind: 'url',
				id: 0,
			} ),
		formatText,
		parseText
	);
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const search = ( searchText ) => {
		clearTimeout( timer.current );
		if ( searchText.trim() === '' ) {
			return;
		}
		timer.current = setTimeout( () => {
			apiFetch( { path: searchPath( searchText ) } ).then( ( items ) => {
				setResults( items );
				setKnown( ( previous ) => ( {
					...previous,
					...Object.fromEntries(
						items.map( ( item ) => [
							`post:${ item.id }`,
							{
								title: resultTitle( item ),
								url: resultUrl( item ),
							},
						] )
					),
				} ) );
			} );
		}, SEARCH_DELAY );
	};

	return (
		<div
			className="wp-app-framework-control wp-app-framework-link-form"
			role="group"
			aria-label={ name }
		>
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			{ isLinked( link ) ? (
				<Flex className="wp-app-framework-link-form__linked">
					<FlexBlock className="wp-app-framework-link-form__title">
						{ linked?.title || link.url || `#${ link.id }` }
					</FlexBlock>
					<FlexItem>
						<Button
							variant="tertiary"
							onClick={ () =>
								onChange( unlink( link, linked?.url ) )
							}
						>
							Unlink
						</Button>
					</FlexItem>
				</Flex>
			) : (
				<>
					<ComboboxControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Search content"
						value={ null }
						options={ results.map( ( item ) => ( {
							value: String( item.id ),
							label: resultTitle( item ) || item.url,
						} ) ) }
						onFilterValueChange={ search }
						onChange={ ( selected ) => {
							const result = results.find(
								( item ) => String( item.id ) === selected
							);
							if ( result ) {
								onChange(
									linkFromSearchResult(
										result,
										link.opensInNewTab
									)
								);
							}
						} }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="URL"
						type="url"
						value={ text }
						onChange={ change }
						onBlur={ flush }
					/>
				</>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label="Open in new tab"
				checked={ link.opensInNewTab }
				onChange={ ( checked ) =>
					onChange( {
						url: link.url,
						opensInNewTab: checked,
						kind: link.kind,
						id: link.id,
					} )
				}
			/>
		</div>
	);
}
