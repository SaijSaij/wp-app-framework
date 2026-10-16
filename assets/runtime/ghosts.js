import { createValue } from './defaults';
import { createItem } from './identifiers';
import { getPath, setPath, splitPath } from './paths';

/**
 * New items in the canvas for lists with appender (#[ListOf(appender: true)]):
 * the editor renders such a list with one more item, empty, as long as it is
 * below its max. The template marks the root element of each item with
 * list_item(); the runtime shows the extra item faded and adds it on a click.
 */

/** Attribute of the root element of a list item, set by list_item() in editor renders. */
export const LIST_ITEM = 'data-list-item';

/**
 * The JSON schema at a path of the attributes, e.g. "card.links".
 *
 * @param {Object} schemas Attribute schemas of the block type.
 * @param {string} path
 * @return {Object|undefined} Schema.
 */
export function schemaAt( schemas, path ) {
	const [ head, ...rest ] = splitPath( path );
	let schema = schemas[ head ];
	for ( const segment of rest ) {
		schema = schema?.properties?.[ segment ];
	}
	return schema;
}

/**
 * The control at a path of the attributes.
 *
 * @param {Object} controls Inspector controls of the block by attribute.
 * @param {string} path
 * @return {Object|undefined} Control.
 */
export function controlAt( controls, path ) {
	const [ head, ...rest ] = splitPath( path );
	let control = controls[ head ];
	for ( const segment of rest ) {
		control = control?.fields?.[ segment ];
	}
	return control;
}

/**
 * The new items to render: for each list with appender below its max, the
 * index of the new item and the label of the list.
 *
 * @param {Object}        attributes
 * @param {Array<string>} appenders  Paths of the lists with appender.
 * @param {Object}        controls   Inspector controls of the block by attribute.
 * @return {Object<string, {index: number, label: string}>} New items by list path.
 */
export function ghostItems( attributes, appenders, controls ) {
	const ghosts = {};
	for ( const path of appenders ) {
		const control = controlAt( controls, path );
		const list = getPath( attributes, path );
		const length = Array.isArray( list ) ? list.length : 0;
		if ( control?.max === undefined || length < control.max ) {
			ghosts[ path ] = { index: length, label: control?.label ?? path };
		}
	}
	return ghosts;
}

/**
 * The attributes with a new item appended to each list that gets one.
 *
 * @param {Object} attributes
 * @param {Object} ghosts     From ghostItems().
 * @param {Object} schemas    Attribute schemas of the block type.
 * @return {Object} Attributes to render.
 */
export function withGhostItems( attributes, ghosts, schemas ) {
	let result = attributes;
	for ( const path of Object.keys( ghosts ) ) {
		const list = getPath( result, path );
		result = setPath( result, path, [
			...( Array.isArray( list ) ? list : [] ),
			createValue( schemaAt( schemas, path ).items ),
		] );
	}
	return result;
}

/**
 * The attributes with one new item added to a list. Unlike the new item
 * rendered before the click, it gets its identifier (#[Identifier]) here:
 * a random one in every render would change the render key each time.
 *
 * @param {Object} attributes
 * @param {string} path       Path of the list.
 * @param {Object} schemas    Attribute schemas of the block type.
 * @param {Object} controls   Inspector controls of the block by attribute.
 * @return {Object} Changed top-level attribute, for setAttributes().
 */
export function addItem( attributes, path, schemas, controls ) {
	const head = splitPath( path )[ 0 ];
	const list = getPath( attributes, path );
	const items = Array.isArray( list ) ? list : [];
	return {
		[ head ]: setPath( attributes, path, [
			...items,
			createItem(
				schemaAt( schemas, path ).items,
				controlAt( controls, path )?.identifier,
				items
			),
		] )[ head ],
	};
}

/**
 * The lists whose new item the markup does not mark with list_item().
 *
 * @param {Object}        ghosts    From ghostItems().
 * @param {Array<string>} listItems Paths marked with list_item() in the markup, e.g. "buttons.2".
 * @return {Array<string>} Paths of the lists.
 */
export function unmarkedGhosts( ghosts, listItems ) {
	return Object.entries( ghosts )
		.filter(
			( [ path, { index } ] ) =>
				! listItems.includes( `${ path }.${ index }` )
		)
		.map( ( [ path ] ) => path );
}
