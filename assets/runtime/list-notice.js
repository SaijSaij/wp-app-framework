import { Notice } from '@wordpress/components';

/**
 * Parts of the list controls (lists of value classes and enums, lists of
 * references) that show the limits of a list.
 */

/** Appended to the title of an item beyond `max`. */
export const UNUSED_MARK = ' (unused)';

/**
 * Class of a list item card; items beyond `max` are marked.
 *
 * @param {ReturnType<typeof import('./list').listLimits>} limits
 * @param {number}                                         index
 * @return {string} Class names.
 */
export function listItemClass( limits, index ) {
	return limits.isUnused( index )
		? 'wp-app-framework-list__item is-unused'
		: 'wp-app-framework-list__item';
}

/**
 * The notice of a list outside its limits.
 *
 * @param {Object}                                         props
 * @param {ReturnType<typeof import('./list').listLimits>} props.limits
 */
export function LimitNotice( { limits } ) {
	if ( limits.notice === null ) {
		return null;
	}
	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="wp-app-framework-list__notice"
		>
			{ limits.notice }
		</Notice>
	);
}
