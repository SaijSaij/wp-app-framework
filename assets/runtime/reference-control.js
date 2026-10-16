import apiFetch from '@wordpress/api-fetch';
import {
	BaseControl,
	Card,
	CardHeader,
	ComboboxControl,
	DropdownMenu,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

import { listLimits } from './list';
import { LimitNotice, listItemClass, UNUSED_MARK } from './list-notice';
import { includePath, move, searchPath, titles } from './references';

/** Delay before a typed search is sent. */
const SEARCH_DELAY = 300;

/**
 * Titles of known objects and the results of the current search.
 *
 * @param {Object}        control Control description.
 * @param {Array<number>} ids     IDs that need titles (the stored value).
 * @return {{known: Object<number, string>, results: Array<number>, search: (text: string) => void}} State.
 */
function useReferences( control, ids ) {
	const [ known, setKnown ] = useState( {} );
	const [ results, setResults ] = useState( [] );
	const timer = useRef( null );
	const missing = ids.filter( ( id ) => ! ( id in known ) );
	const missingKey = missing.join( ',' );

	useEffect( () => {
		if ( missing.length === 0 ) {
			return;
		}
		apiFetch( { path: includePath( control, missing ) } ).then(
			( items ) => {
				const found = titles( control, items );
				// Objects the search does not return (e.g. drafts) keep their ID as title.
				for ( const id of missing ) {
					found[ id ] ??= `#${ id }`;
				}
				setKnown( ( previous ) => ( { ...previous, ...found } ) );
			}
		);
		// The missing IDs are covered by missingKey.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ missingKey, control ] );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const search = ( text ) => {
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			apiFetch( { path: searchPath( control, text ) } ).then(
				( items ) => {
					setKnown( ( previous ) => ( {
						...previous,
						...titles( control, items ),
					} ) );
					setResults( items.map( ( item ) => item.id ) );
				}
			);
		}, SEARCH_DELAY );
	};

	return { known, results, search };
}

/**
 * Options of a combobox: the given IDs with their titles.
 *
 * @param {Array<number>}          ids
 * @param {Object<number, string>} known
 * @return {Array<{value: string, label: string}>} Options.
 */
function options( ids, known ) {
	return [ ...new Set( ids ) ].map( ( id ) => ( {
		value: String( id ),
		label: known[ id ] ?? `#${ id }`,
	} ) );
}

/**
 * A reference to one post, term or user (#[PostReference] on ?int …): search
 * and select; clearing removes the reference.
 *
 * @param {Object}                   props
 * @param {Object}                   props.control  Control description.
 * @param {?number}                  props.value    ID.
 * @param {(value: ?number) => void} props.onChange
 * @param {string}                   [props.label]
 */
export function ReferenceControl( { control, value, onChange, label } ) {
	const id = Number.isInteger( value ) ? value : null;
	const { known, results, search } = useReferences(
		control,
		id === null ? [] : [ id ]
	);
	return (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label ?? control.label }
			value={ id === null ? null : String( id ) }
			options={ options(
				id === null ? results : [ id, ...results ],
				known
			) }
			onFilterValueChange={ search }
			onChange={ ( selected ) =>
				onChange( selected ? Number( selected ) : null )
			}
		/>
	);
}

/**
 * A list of references (#[PostReference] on array …): search to add, cards to
 * move and remove.
 *
 * @param {Object}                         props
 * @param {Object}                         props.control  Control description.
 * @param {?Array<number>}                 props.value    IDs.
 * @param {(value: Array<number>) => void} props.onChange
 * @param {string}                         [props.label]
 */
export function ReferencesControl( { control, value, onChange, label } ) {
	const ids = Array.isArray( value ) ? value : [];
	const { known, results, search } = useReferences( control, ids );
	const name = label ?? control.label;
	const limits = listLimits( control, ids.length );

	return (
		<div className="wp-app-framework-list">
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			<LimitNotice limits={ limits } />
			{ ids.map( ( id, index ) => (
				<Card
					size="small"
					className={ listItemClass( limits, index ) }
					key={ id }
				>
					<CardHeader size="xSmall">
						<Flex>
							<FlexBlock className="wp-app-framework-list__title">
								{ known[ id ] ?? `#${ id }` }
								{ limits.isUnused( index ) && UNUSED_MARK }
							</FlexBlock>
							<FlexItem>
								<DropdownMenu
									icon="ellipsis"
									label={ `Actions for ${
										known[ id ] ?? `#${ id }`
									}` }
									controls={ [
										{
											title: 'Move up',
											icon: 'arrow-up-alt2',
											isDisabled: index === 0,
											onClick: () =>
												onChange(
													move(
														ids,
														index,
														index - 1
													)
												),
										},
										{
											title: 'Move down',
											icon: 'arrow-down-alt2',
											isDisabled:
												index === ids.length - 1,
											onClick: () =>
												onChange(
													move(
														ids,
														index,
														index + 1
													)
												),
										},
										{
											title: 'Remove',
											icon: 'trash',
											isDisabled: ! limits.canRemove,
											onClick: () =>
												onChange(
													ids.filter(
														( other ) =>
															other !== id
													)
												),
										},
									] }
								/>
							</FlexItem>
						</Flex>
					</CardHeader>
				</Card>
			) ) }
			{ limits.canAdd && (
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ `Add to ${ name }` }
					value={ null }
					options={ options(
						results.filter( ( id ) => ! ids.includes( id ) ),
						known
					) }
					onFilterValueChange={ search }
					onChange={ ( selected ) => {
						if ( selected ) {
							onChange( [ ...ids, Number( selected ) ] );
						}
					} }
				/>
			) }
		</div>
	);
}
