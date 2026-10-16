import {
	useBlockEditingMode,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useMemo } from '@wordpress/element';
import { attributesToProps, domToReact } from 'html-react-parser';

import { FIELD, INNER_BLOCKS, isElement } from './analyze';
import { FieldBoundary, ImageField, TextField } from './fields';
import { LIST_ITEM } from './ghosts';
import { matchesPattern } from './paths';

function elementProps( node ) {
	const {
		[ FIELD ]: field,
		[ INNER_BLOCKS ]: innerBlocks,
		...attribs
	} = node.attribs;
	return attributesToProps( attribs, node.name );
}

/** Class of the elements edited in the canvas; canvas.scss outlines them on hovered and selected blocks. */
const FIELD_CLASS = 'wp-app-framework-field';
// Root element of a block of the framework in the canvas (canvas.scss).
const BLOCK_CLASS = 'wp-app-framework-block';

/**
 * Joins class names, skipping empty ones.
 *
 * @param {...(string|undefined)} names
 * @return {string|undefined} Class attribute.
 */
function classNames( ...names ) {
	const joined = names.filter( Boolean ).join( ' ' );
	return joined === '' ? undefined : joined;
}

function fieldProps( node ) {
	const props = elementProps( node );
	return { ...props, className: classNames( props.className, FIELD_CLASS ) };
}

/** Class of the new item of a list with appender (canvas.scss). */
const GHOST_CLASS = 'wp-app-framework-ghost';

/**
 * Options that render the markup of a new item without inline editors: text
 * fields show their label as placeholder, images their markup.
 *
 * @param {Object} fields Inline fields by pattern.
 * @return {Object} Options of domToReact().
 */
function ghostOptions( fields ) {
	const options = {
		replace( node ) {
			if ( ! isElement( node ) || node.attribs[ FIELD ] === undefined ) {
				return undefined;
			}
			const Tag = node.name;
			return (
				<Tag { ...elementProps( node ) }>
					{ ghostContent( node, fields, options ) }
				</Tag>
			);
		},
	};
	return options;
}

/**
 * The content of a marked element of a new item: the label of a text field
 * as placeholder, else the markup.
 *
 * @param {Object} node
 * @param {Object} fields  Inline fields by pattern.
 * @param {Object} options Options of domToReact().
 * @return {Object} Content.
 */
function ghostContent( node, fields, options ) {
	const path = node.attribs[ FIELD ];
	const pattern =
		path === undefined
			? undefined
			: Object.keys( fields ).find( ( candidate ) =>
					matchesPattern( path, candidate )
				);
	const field = fields[ pattern ];
	if ( field && field.kind !== 'image' ) {
		return (
			<span className="wp-app-framework-ghost__placeholder">
				{ field.label }
			</span>
		);
	}
	return domToReact( node.children, options );
}

/**
 * The new item of a list with appender: the markup of an empty item, faded;
 * a click or Enter adds the item.
 *
 * @param {Object}     props
 * @param {Object}     props.node   Root element of the item (list_item()).
 * @param {Object}     props.fields Inline fields by pattern.
 * @param {string}     props.label  Label of the list.
 * @param {() => void} props.onAdd  Adds the item.
 */
function GhostItem( { node, fields, label, onAdd } ) {
	const mode = useBlockEditingMode();
	if ( mode === 'disabled' ) {
		return null;
	}
	const Tag = node.name;
	const { href, ...props } = elementProps( node );
	const add = ( event ) => {
		event.preventDefault();
		event.stopPropagation();
		onAdd();
	};
	return (
		<Tag
			{ ...props }
			className={ classNames( props.className, GHOST_CLASS ) }
			role="button"
			tabIndex={ 0 }
			aria-label={ `Add item to ${ label }` }
			onClick={ add }
			onKeyDown={ ( event ) => {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					add( event );
				}
			} }
		>
			{ ghostContent( node, fields, ghostOptions( fields ) ) }
		</Tag>
	);
}

function InnerBlocksElement( { tagName: Tag, props, options } ) {
	return <Tag { ...useInnerBlocksProps( props, options ) } />;
}

/**
 * The new item a marked list item is, if it is one.
 *
 * @param {Object} node
 * @param {Object} ghosts New items by list path (ghostItems()).
 * @return {{path: string, label: string}|null} The list of the new item.
 */
function ghostOf( node, ghosts ) {
	const item = node.attribs[ LIST_ITEM ];
	if ( item === undefined ) {
		return null;
	}
	const separator = item.lastIndexOf( '.' );
	const path = item.slice( 0, separator );
	const ghost = ghosts[ path ];
	return ghost && String( ghost.index ) === item.slice( separator + 1 )
		? { path, label: ghost.label }
		: null;
}

function createOptions( fields, innerBlocksOptions, ghosts, onAdd ) {
	const options = {
		replace( node ) {
			if ( ! isElement( node ) ) {
				return undefined;
			}
			const ghost = ghostOf( node, ghosts );
			if ( ghost !== null ) {
				return (
					<GhostItem
						node={ node }
						fields={ fields }
						label={ ghost.label }
						onAdd={ () => onAdd( ghost.path ) }
					/>
				);
			}
			if ( INNER_BLOCKS in node.attribs ) {
				return (
					<FieldBoundary label="Inner blocks">
						<InnerBlocksElement
							tagName={ node.name }
							props={ elementProps( node ) }
							options={ innerBlocksOptions }
						/>
					</FieldBoundary>
				);
			}
			const path = node.attribs[ FIELD ];
			if ( path === undefined ) {
				return undefined;
			}
			const pattern = Object.keys( fields ).find( ( candidate ) =>
				matchesPattern( path, candidate )
			);
			const field = fields[ pattern ];
			const FieldComponent =
				field.kind === 'image' ? ImageField : TextField;
			return (
				<FieldBoundary label={ `Field ${ path }` }>
					<FieldComponent
						tagName={ node.name }
						elementProps={ fieldProps( node ) }
						path={ path }
						field={ field }
					>
						{ field.kind === 'image'
							? domToReact( node.children, options )
							: null }
					</FieldComponent>
				</FieldBoundary>
			);
		},
	};
	return options;
}

function rootProps( node, extraProps ) {
	const props = elementProps( node );
	return {
		...props,
		...extraProps,
		className: classNames(
			props.className,
			extraProps.className,
			BLOCK_CLASS
		),
	};
}

function RootElement( {
	node,
	fields,
	extraProps,
	innerBlocksOptions,
	ghosts,
	onAdd,
} ) {
	const Tag = node.name;
	const children = useMemo(
		() =>
			domToReact(
				node.children,
				createOptions( fields, innerBlocksOptions, ghosts, onAdd )
			),
		[ node, fields, innerBlocksOptions, ghosts, onAdd ]
	);
	return (
		<Tag { ...useBlockProps( rootProps( node, extraProps ) ) }>
			{ children }
		</Tag>
	);
}

/**
 * A root element that is itself a text field: RichText with the block props, like core/paragraph.
 *
 * @param {Object} props
 * @param {Object} props.node       Root node.
 * @param {Object} props.fields     Inline fields by pattern.
 * @param {Object} props.extraProps Additional props of the root element (loading state).
 */
function RootTextElement( { node, fields, extraProps } ) {
	const path = node.attribs[ FIELD ];
	const pattern = Object.keys( fields ).find( ( candidate ) =>
		matchesPattern( path, candidate )
	);
	const blockProps = useBlockProps( rootProps( node, extraProps ) );
	return (
		<FieldBoundary label={ `Field ${ path }` }>
			<TextField
				tagName={ node.name }
				elementProps={ {
					...blockProps,
					className: classNames( blockProps.className, FIELD_CLASS ),
				} }
				path={ path }
				field={ fields[ pattern ] }
			/>
		</FieldBoundary>
	);
}

function RootInnerBlocksElement( { node, extraProps, innerBlocksOptions } ) {
	const Tag = node.name;
	return (
		<Tag
			{ ...useInnerBlocksProps(
				useBlockProps( rootProps( node, extraProps ) ),
				innerBlocksOptions
			) }
		/>
	);
}

/**
 * The block's root element (from analyzeMarkup()) with the block props of the editor; marked
 * descendants are replaced by inline editors, a marked root element by a text field.
 *
 * @param {Object}                 props
 * @param {Object}                 props.node               Root node from analyzeMarkup().
 * @param {Object}                 props.fields             Inline fields by pattern.
 * @param {Object}                 props.extraProps         Additional props of the root element (loading state).
 * @param {Object}                 props.innerBlocksOptions Options of useInnerBlocksProps() (#[InnerBlocks]).
 * @param {Object}                 props.ghosts             New items of lists with appender by list path (ghostItems()).
 * @param {(path: string) => void} props.onAdd              Adds the new item of a list.
 */
export function BlockMarkup( {
	node,
	fields,
	extraProps,
	innerBlocksOptions,
	ghosts,
	onAdd,
} ) {
	if ( FIELD in node.attribs ) {
		return (
			<RootTextElement
				node={ node }
				fields={ fields }
				extraProps={ extraProps }
			/>
		);
	}
	return INNER_BLOCKS in node.attribs ? (
		<RootInnerBlocksElement
			node={ node }
			extraProps={ extraProps }
			innerBlocksOptions={ innerBlocksOptions }
		/>
	) : (
		<RootElement
			node={ node }
			fields={ fields }
			extraProps={ extraProps }
			innerBlocksOptions={ innerBlocksOptions }
			ghosts={ ghosts }
			onAdd={ onAdd }
		/>
	);
}
