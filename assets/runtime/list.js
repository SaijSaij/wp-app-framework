import { __, sprintf } from '@wordpress/i18n';

/**
 * Title of a list item: its first plain text field (the first line of a
 * multiline one), else the text of its first rich text field, else the label
 * of an item (`itemLabel` of #[ListOf]) with its number.
 *
 * @param {import('./paths').AttributeValue} item
 * @param {number}                           index
 * @param {string}                           path        Pattern of the list, e.g. "buttons".
 * @param {Object}                           fields      Inline fields of the block by pattern.
 * @param {string}                           [itemLabel] What one item is, e.g. "Badge".
 * @return {string} Title.
 */
export function itemTitle(
	item,
	index,
	path,
	fields,
	itemLabel = __( 'Item', 'wp-app-framework' )
) {
	const prefix = `${ path }.*.`;
	const candidates = Object.entries( fields )
		.filter(
			( [ pattern, field ] ) =>
				pattern.startsWith( prefix ) &&
				! pattern.slice( prefix.length ).includes( '.' ) &&
				( field.kind === 'plain' || field.kind === 'rich' )
		)
		// Plain text first; the sort is stable within each kind.
		.sort(
			( [ , a ], [ , b ] ) =>
				Number( a.kind !== 'plain' ) - Number( b.kind !== 'plain' )
		);
	for ( const [ pattern, field ] of candidates ) {
		const raw = item?.[ pattern.slice( prefix.length ) ];
		if ( typeof raw !== 'string' ) {
			continue;
		}
		let text = field.kind === 'rich' ? raw.replace( /<[^>]*>/g, '' ) : raw;
		if ( field.multiline ) {
			text = text.trim().split( '\n' )[ 0 ];
		}
		text = text.trim();
		if ( text !== '' ) {
			return text;
		}
	}
	return `${ itemLabel } ${ index + 1 }`;
}

/**
 * The text fields of list items outside the block editor, where they have
 * controls instead of fields in the canvas: the plain and rich text values of
 * the item class by pattern, for itemTitle().
 *
 * @param {string|undefined} path Pattern of the list, e.g. "links".
 * @param {Object}           item Control of an item.
 * @return {Object} Fields by pattern.
 */
export function formFields( path, item ) {
	if ( path === undefined || item?.type !== 'object' ) {
		return {};
	}
	return Object.fromEntries(
		Object.entries( item.fields )
			.filter(
				( [ , field ] ) =>
					field.type === 'plain' || field.type === 'rich'
			)
			.map( ( [ name, field ] ) => [
				`${ path }.*.${ name }`,
				{ kind: field.type, multiline: field.multiline ?? false },
			] )
	);
}

/**
 * State of a list against the limits of its control (`min` and `max` of
 * #[ListOf]). A stored list may be outside them, for example after a limit
 * changed: the value factory creates only its first `max` items, so the
 * others are marked, and a notice says how to get back within the limits.
 *
 * @param {{min?: number, max?: number}} control Control of the list.
 * @param {number}                       count   Number of items.
 * @return {{canAdd: boolean, canRemove: boolean, isUnused: (index: number) => boolean, notice: string|null}} State.
 */
export function listLimits( control, count ) {
	const min = control.min ?? 0;
	const max = control.max ?? null;
	const excess = max === null ? 0 : count - max;
	const missing = min - count;
	let notice = null;
	if ( excess > 0 ) {
		notice = sprintf(
			/* translators: 1: number of items, 2: largest number allowed, 3: items too many */
			__(
				'%1$d items, but at most %2$d allowed: only the first %2$d are used. Remove %3$d.',
				'wp-app-framework'
			),
			count,
			max,
			excess
		);
	} else if ( missing > 0 ) {
		notice = sprintf(
			/* translators: 1: number of items, 2: smallest number allowed, 3: items missing */
			__( '%1$d of at least %2$d items. Add %3$d.', 'wp-app-framework' ),
			count,
			min,
			missing
		);
	}
	return {
		canAdd: max === null || count < max,
		canRemove: count > min,
		isUnused: ( index ) => max !== null && index >= max,
		notice,
	};
}

/**
 * Title of an item of a list control: the label of an enum case, else the
 * title of a value (itemTitle()).
 *
 * @param {Object}                           control Control of the list.
 * @param {import('./paths').AttributeValue} item
 * @param {number}                           index
 * @param {string}                           path    Pattern of the list.
 * @param {Object}                           fields  Inline fields of the block by pattern.
 * @return {string} Title.
 */
export function listItemTitle( control, item, index, path, fields ) {
	if ( control.item.type === 'enum' ) {
		const option = control.item.options.find(
			( candidate ) => candidate.value === item
		);
		if ( option ) {
			return option.label;
		}
	}
	return itemTitle( item, index, path, fields, control.itemLabel );
}

/**
 * The list with one item moved.
 *
 * @template T
 * @param {Array<T>} list
 * @param {number}   from Index of the item.
 * @param {number}   to   Its new index.
 * @return {Array<T>} New list.
 */
export function moveItem( list, from, to ) {
	const next = [ ...list ];
	next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
	return next;
}

/**
 * The new index of a dragged item dropped before or after another one.
 *
 * @param {number}  from  Index of the dragged item.
 * @param {number}  over  Index of the item it is dropped on.
 * @param {boolean} after Dropped on the lower half of that item.
 * @return {number} New index of the dragged item.
 */
export function dropIndex( from, over, after ) {
	const to = over + ( after ? 1 : 0 );
	return from < to ? to - 1 : to;
}
