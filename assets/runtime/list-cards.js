import {
	Button,
	Card,
	CardBody,
	CardHeader,
	DropdownMenu,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';

import { dropIndex, moveItem } from './list';
import { listItemClass, UNUSED_MARK } from './list-notice';

/**
 * The items of a list as cards: a header with a drag handle, the title (a
 * toggle that expands and collapses the item's controls) and an actions menu
 * (move up, move down, remove). Used by the lists of values and of references
 * in the inspector, on the settings page and in the meta forms.
 */

let lastKey = 0;
const nextKey = () => ++lastKey;

/**
 * Stable keys of the items and the expanded ones. Items keep their key while
 * they are moved, so their state and controls move with them; a change from
 * outside (undo, an item added in the canvas) that changes the length adds
 * or removes keys at the end. The control changes the keys first, then the
 * value.
 *
 * @param {number}        length         Number of items.
 * @param {() => boolean} [takeAddition] Whether items appended from outside were added in the canvas, to expand them.
 * @return {Object} Keys and their changes: keys, isOpen(), toggle(), add(), remove(), move().
 */
export function useListCards( length, takeAddition = () => false ) {
	const keys = useRef( null );
	const [ open, setOpen ] = useState( () => new Set() );
	keys.current ??= Array.from( { length }, nextKey );
	if ( keys.current.length > length ) {
		keys.current = keys.current.slice( 0, length );
	}
	const appended = [];
	while ( keys.current.length < length ) {
		const key = nextKey();
		appended.push( key );
		keys.current = [ ...keys.current, key ];
	}
	if ( appended.length > 0 && takeAddition() ) {
		setOpen( ( previous ) => new Set( [ ...previous, ...appended ] ) );
	}

	return {
		keys: keys.current,
		isOpen: ( key ) => open.has( key ),
		toggle( key ) {
			setOpen( ( previous ) => {
				const next = new Set( previous );
				if ( ! next.delete( key ) ) {
					next.add( key );
				}
				return next;
			} );
		},
		/** A new item at the end, expanded. */
		add() {
			const key = nextKey();
			keys.current = [ ...keys.current, key ];
			setOpen( ( previous ) => new Set( previous ).add( key ) );
		},
		remove( index ) {
			keys.current = keys.current.filter(
				( _, other ) => other !== index
			);
		},
		move( from, to ) {
			keys.current = moveItem( keys.current, from, to );
		},
	};
}

/**
 * @param {Object}                                         props
 * @param {ReturnType<typeof useListCards>}                props.cards        Keys and expanded items.
 * @param {ReturnType<typeof import('./list').listLimits>} props.limits       Limits of the list.
 * @param {(index: number) => string}                      props.title        Title of an item.
 * @param {(index: number) => string}                      props.actionsLabel Label of the actions menu of an item.
 * @param {(index: number) => (Object|null)}               props.body         Controls of an item; null if it has none.
 * @param {(from: number, to: number) => void}             props.onMove       Moves an item in the value.
 * @param {(index: number) => void}                        props.onRemove     Removes an item from the value.
 */
export function ListCards( {
	cards,
	limits,
	title,
	actionsLabel,
	body,
	onMove,
	onRemove,
} ) {
	// The dragged item and where it would be dropped.
	const [ drag, setDrag ] = useState( null );
	const count = cards.keys.length;
	const move = ( from, to ) => {
		cards.move( from, to );
		onMove( from, to );
	};

	return cards.keys.map( ( key, index ) => {
		const content = body( index );
		const open = content !== null && cards.isOpen( key );
		const heading =
			title( index ) + ( limits.isUnused( index ) ? UNUSED_MARK : '' );
		let className = listItemClass( limits, index );
		if ( drag !== null && drag.over === index ) {
			className += drag.after ? ' is-drop-after' : ' is-drop-before';
		}
		if ( drag !== null && drag.from === index ) {
			className += ' is-dragged';
		}

		return (
			<Card
				size="small"
				className={ className }
				key={ key }
				onDragOver={ ( event ) => {
					if ( drag === null ) {
						return;
					}
					event.preventDefault();
					const rect = event.currentTarget.getBoundingClientRect();
					const after = event.clientY > rect.top + rect.height / 2;
					if ( drag.over !== index || drag.after !== after ) {
						setDrag( { ...drag, over: index, after } );
					}
				} }
				onDrop={ ( event ) => {
					event.preventDefault();
					if ( drag !== null && drag.over !== null ) {
						const to = dropIndex(
							drag.from,
							drag.over,
							drag.after
						);
						if ( to !== drag.from ) {
							move( drag.from, to );
						}
					}
					setDrag( null );
				} }
			>
				<CardHeader size="xSmall">
					<Flex gap={ 1 }>
						<FlexItem>
							<Button
								size="small"
								icon="menu"
								label={ `Drag ${ heading }` }
								className="wp-app-framework-list__handle"
								draggable
								onDragStart={ ( event ) => {
									event.dataTransfer.effectAllowed = 'move';
									// Firefox starts dragging only with data.
									event.dataTransfer.setData(
										'text/plain',
										heading
									);
									event.dataTransfer.setDragImage(
										event.currentTarget.closest(
											'.wp-app-framework-list__item'
										),
										0,
										0
									);
									setDrag( {
										from: index,
										over: null,
										after: false,
									} );
								} }
								onDragEnd={ () => setDrag( null ) }
							/>
						</FlexItem>
						<FlexBlock>
							{ content === null ? (
								<span className="wp-app-framework-list__title">
									{ heading }
								</span>
							) : (
								<Button
									className="wp-app-framework-list__toggle"
									// Without the glyph of the dashicon in the accessible name.
									aria-label={ heading }
									aria-expanded={ open }
									icon={
										open
											? 'arrow-up-alt2'
											: 'arrow-down-alt2'
									}
									iconPosition="right"
									onClick={ () => cards.toggle( key ) }
								>
									<span className="wp-app-framework-list__title">
										{ heading }
									</span>
								</Button>
							) }
						</FlexBlock>
						<FlexItem>
							<DropdownMenu
								icon="ellipsis"
								label={ actionsLabel( index ) }
								controls={ [
									{
										title: 'Move up',
										icon: 'arrow-up-alt2',
										isDisabled: index === 0,
										onClick: () => move( index, index - 1 ),
									},
									{
										title: 'Move down',
										icon: 'arrow-down-alt2',
										isDisabled: index === count - 1,
										onClick: () => move( index, index + 1 ),
									},
									{
										title: 'Remove',
										icon: 'trash',
										isDisabled: ! limits.canRemove,
										onClick: () => {
											cards.remove( index );
											onRemove( index );
										},
									},
								] }
							/>
						</FlexItem>
					</Flex>
				</CardHeader>
				{ open && (
					<CardBody
						size="small"
						className="wp-app-framework-list__body"
					>
						{ content }
					</CardBody>
				) }
			</Card>
		);
	} );
}
