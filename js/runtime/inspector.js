import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import {
	BaseControl,
	Button,
	Card,
	CardBody,
	CardHeader,
	DropdownMenu,
	Flex,
	FlexBlock,
	FlexItem,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useContext, useEffect, useRef, useState } from '@wordpress/element';

import { BlockDataContext, useBlockData } from './context';
import { createValue } from './defaults';
import { itemTitle } from './list';

/** Delay before a typed inspector value is applied (and the block re-rendered). */
const TYPING_DELAY = 300;

/**
 * Keeps the typed text locally and applies it after a pause or on blur, so
 * typing does not re-render the block on every key. External changes (undo)
 * replace the local text.
 *
 * @param {*}        value    Current attribute value.
 * @param {Function} onChange Applies a value.
 * @param {Function} format   Value → text.
 * @param {Function} parse    Text → value, undefined if invalid.
 * @return {{text: string, change: Function, flush: Function}} Buffered text.
 */
function useBufferedText( value, onChange, format, parse ) {
	const [ text, setText ] = useState( () => format( value ) );
	const applied = useRef( value );
	const timer = useRef( null );
	const latest = useRef( { text, onChange, parse } );
	latest.current = { text, onChange, parse };

	useEffect( () => {
		if ( value !== applied.current ) {
			applied.current = value;
			setText( format( value ) );
		}
	}, [ value, format ] );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	function apply() {
		clearTimeout( timer.current );
		timer.current = null;
		const parsed = latest.current.parse( latest.current.text );
		if ( parsed !== undefined && parsed !== applied.current ) {
			applied.current = parsed;
			latest.current.onChange( parsed );
		}
	}

	return {
		text,
		change( next ) {
			latest.current.text = next;
			setText( next );
			clearTimeout( timer.current );
			timer.current = setTimeout( apply, TYPING_DELAY );
		},
		flush() {
			if ( timer.current !== null ) {
				apply();
			}
		},
	};
}

const formatText = ( value ) => value ?? '';
const formatNumber = ( value ) =>
	value === null || value === undefined ? '' : String( value );

function TextValueControl( { control, value, onChange, label } ) {
	const parse = ( text ) => ( text === '' && control.nullable ? null : text );
	const { text, change, flush } = useBufferedText(
		value,
		onChange,
		formatText,
		parse
	);
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label ?? control.label }
			type={ control.url ? 'url' : 'text' }
			value={ text }
			onChange={ change }
			onBlur={ flush }
		/>
	);
}

function NumberValueControl( { control, value, onChange, label } ) {
	const parse = ( text ) => {
		if ( text.trim() === '' ) {
			return control.nullable ? null : undefined;
		}
		const number = Number( text );
		if (
			! Number.isFinite( number ) ||
			( control.type === 'int' && ! Number.isInteger( number ) )
		) {
			return undefined;
		}
		return number;
	};
	const { text, change, flush } = useBufferedText(
		value,
		onChange,
		formatNumber,
		parse
	);
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label ?? control.label }
			type="number"
			step={ control.type === 'int' ? 1 : 'any' }
			value={ text }
			onChange={ change }
			onBlur={ flush }
		/>
	);
}

/**
 * A #[RichText(inspector: true)] value: a rich text input with the allowed
 * formats of the field.
 *
 * @param {Object}   props
 * @param {Object}   props.control
 * @param {string}   props.value
 * @param {Function} props.onChange
 * @param {string}   [props.label]
 */
function RichTextValueControl( { control, value, onChange, label } ) {
	const { text, change } = useBufferedText(
		value,
		onChange,
		formatText,
		( html ) => html
	);
	return (
		<div className="wp-app-framework-control">
			<BaseControl.VisualLabel>
				{ label ?? control.label }
			</BaseControl.VisualLabel>
			<RichText
				tagName="div"
				className="wp-app-framework-rich-input"
				aria-label={ label ?? control.label }
				value={ text }
				onChange={ change }
				allowedFormats={ control.allowedFormats }
			/>
		</div>
	);
}

/**
 * An #[Image(inspector: true)] value: preview with replace and remove, or a
 * button to select an image.
 *
 * @param {Object}   props
 * @param {Object}   props.control
 * @param {?number}  props.value    Attachment ID.
 * @param {Function} props.onChange
 * @param {string}   [props.label]
 */
function ImageValueControl( { control, value, onChange, label } ) {
	const name = label ?? control.label;
	const id = Number.isInteger( value ) ? value : null;
	const media = useSelect(
		( select ) =>
			id === null ? undefined : select( 'core' ).getMedia( id ),
		[ id ]
	);
	const onSelect = ( selected ) => {
		if ( Number.isInteger( selected?.id ) ) {
			onChange( selected.id );
		}
	};

	return (
		<div className="wp-app-framework-control">
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ id ?? undefined }
					onSelect={ onSelect }
					render={ ( { open } ) =>
						id === null ? (
							<Button variant="secondary" onClick={ open }>
								{ `Select ${ name }` }
							</Button>
						) : (
							<div className="wp-app-framework-image">
								{ media?.source_url && (
									<img
										className="wp-app-framework-image__preview"
										src={ media.source_url }
										alt={ media.alt_text ?? '' }
									/>
								) }
								<Flex justify="flex-start">
									<Button
										variant="secondary"
										aria-label={ `Replace ${ name }` }
										onClick={ open }
									>
										Replace
									</Button>
									<Button
										variant="tertiary"
										isDestructive
										aria-label={ `Remove ${ name }` }
										onClick={ () => onChange( null ) }
									>
										Remove
									</Button>
								</Flex>
							</div>
						)
					}
				/>
			</MediaUploadCheck>
		</div>
	);
}

function EnumControl( { control, value, onChange, label } ) {
	const options = control.options.map( ( option ) => ( {
		label: option.label,
		value: String( option.value ),
	} ) );
	if ( control.nullable ) {
		options.unshift( { label: '—', value: '' } );
	}
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label ?? control.label }
			value={
				value === null || value === undefined ? '' : String( value )
			}
			options={ options }
			onChange={ ( selected ) =>
				onChange(
					selected === ''
						? null
						: control.options.find(
								( option ) =>
									String( option.value ) === selected
							).value
				)
			}
		/>
	);
}

/**
 * The values of a value class, each with its control.
 *
 * @param {Object}   props
 * @param {Object}   props.control
 * @param {Object}   props.value
 * @param {Function} props.onChange
 * @param {Object}   props.schema
 */
function ObjectFields( { control, value, onChange, schema } ) {
	return Object.entries( control.fields ).map( ( [ name, field ] ) => (
		<ValueControl
			key={ name }
			control={ field }
			schema={ schema.properties[ name ] }
			value={ value[ name ] }
			onChange={ ( next ) => onChange( { ...value, [ name ]: next } ) }
		/>
	) );
}

function ObjectControl( { control, value, onChange, schema, label } ) {
	const name = label ?? control.label;
	const objectSchema = {
		...schema,
		type: Array.isArray( schema.type )
			? schema.type.find( ( type ) => type !== 'null' )
			: schema.type,
	};

	return (
		<div className="wp-app-framework-group">
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			{ value === null || value === undefined ? (
				<Button
					variant="secondary"
					icon="plus"
					onClick={ () =>
						onChange(
							createValue( {
								...objectSchema,
								default: undefined,
							} )
						)
					}
				>
					{ `Add ${ name }` }
				</Button>
			) : (
				<>
					<ObjectFields
						control={ control }
						value={ value }
						onChange={ onChange }
						schema={ schema }
					/>
					{ control.nullable && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => onChange( null ) }
						>
							{ `Remove ${ name }` }
						</Button>
					) }
				</>
			) }
		</div>
	);
}

function hasItemControls( control ) {
	return (
		control.type !== 'object' || Object.keys( control.fields ).length > 0
	);
}

/**
 * Editor of a list: one card per item with its title, an actions menu (move,
 * remove) and the controls of the item; inline values of items are edited in
 * the canvas. Items are keyed by index, so reordering remounts their controls.
 *
 * @param {Object}   props
 * @param {Object}   props.control
 * @param {Array}    props.value
 * @param {Function} props.onChange
 * @param {Object}   props.schema   JSON schema of the list.
 * @param {string}   props.path     Pattern of the list, e.g. "buttons".
 */
export function ListControl( { control, value, onChange, schema, path } ) {
	const fields = useContext( BlockDataContext )?.block.fields ?? {};
	const items = value ?? [];
	const move = ( from, to ) => {
		const next = [ ...items ];
		next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
		onChange( next );
	};
	const replace = ( index, next ) =>
		onChange(
			items.map( ( other, otherIndex ) =>
				otherIndex === index ? next : other
			)
		);

	return (
		<div className="wp-app-framework-list">
			<BaseControl.VisualLabel>{ control.label }</BaseControl.VisualLabel>
			{ items.map( ( item, index ) => (
				<Card
					size="small"
					className="wp-app-framework-list__item"
					key={ index }
				>
					<CardHeader size="xSmall">
						<Flex>
							<FlexBlock className="wp-app-framework-list__title">
								{ itemTitle( item, index, path, fields ) }
							</FlexBlock>
							<FlexItem>
								<DropdownMenu
									icon="ellipsis"
									label={ `Actions for item ${ index + 1 }` }
									controls={ [
										{
											title: 'Move up',
											icon: 'arrow-up-alt2',
											isDisabled: index === 0,
											onClick: () =>
												move( index, index - 1 ),
										},
										{
											title: 'Move down',
											icon: 'arrow-down-alt2',
											isDisabled:
												index === items.length - 1,
											onClick: () =>
												move( index, index + 1 ),
										},
										{
											title: 'Remove',
											icon: 'trash',
											onClick: () =>
												onChange(
													items.filter(
														( _, other ) =>
															other !== index
													)
												),
										},
									] }
								/>
							</FlexItem>
						</Flex>
					</CardHeader>
					{ hasItemControls( control.item ) && (
						<CardBody
							size="small"
							className="wp-app-framework-list__body"
						>
							{ control.item.type === 'object' ? (
								<ObjectFields
									control={ control.item }
									value={ item }
									onChange={ ( next ) =>
										replace( index, next )
									}
									schema={ schema.items }
								/>
							) : (
								<ValueControl
									control={ control.item }
									schema={ schema.items }
									value={ item }
									path={ `${ path }.*` }
									label={ `Item ${ index + 1 }` }
									onChange={ ( next ) =>
										replace( index, next )
									}
								/>
							) }
						</CardBody>
					) }
				</Card>
			) ) }
			<div>
				<Button
					variant="secondary"
					icon="plus"
					aria-label={ `Add item to ${ control.label }` }
					onClick={ () =>
						onChange( [ ...items, createValue( schema.items ) ] )
					}
				>
					Add item
				</Button>
			</div>
		</div>
	);
}

/**
 * The control of a value, chosen by its manifest description.
 *
 * @param {Object}   props
 * @param {Object}   props.control  Control description from the manifest.
 * @param {Object}   props.schema   JSON schema of the value.
 * @param {*}        props.value
 * @param {Function} props.onChange
 * @param {string}   [props.label]  Overrides the label (list items).
 * @param {string}   [props.path]   Pattern of the value, for list item titles.
 */
export function ValueControl( props ) {
	const { control, value, onChange, label } = props;
	switch ( control.type ) {
		case 'enum':
			return <EnumControl { ...props } />;
		case 'bool':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ label ?? control.label }
					checked={ !! value }
					onChange={ onChange }
				/>
			);
		case 'int':
		case 'float':
			return <NumberValueControl { ...props } />;
		case 'string':
		case 'plain':
			return <TextValueControl { ...props } />;
		case 'rich':
			return <RichTextValueControl { ...props } />;
		case 'image':
			return <ImageValueControl { ...props } />;
		case 'object':
			return <ObjectControl { ...props } />;
		case 'list':
			return <ListControl { ...props } />;
		default:
			throw new Error(
				`wp-app-framework: unknown control type ${ JSON.stringify( control.type ) }.`
			);
	}
}

/**
 * Generated inspector of a block: content controls in the "content" group
 * (which stays visible in contentOnly mode), all others in the default group,
 * plus the controls and panels the theme registered.
 *
 * @param {Object} props
 * @param {Object} props.registry Registered controls and panels.
 */
export function Inspector( { registry } ) {
	const { name, block, attributes, setAttributes } = useBlockData();
	const mode = useBlockEditingMode();
	if ( mode === 'disabled' ) {
		return null;
	}

	const schemas = getBlockType( name )?.attributes ?? {};
	const controlled = registry.getControlledAttributes( name );
	const entries = [];
	for ( const [ attribute, control ] of Object.entries( block.controls ) ) {
		if ( controlled.includes( attribute ) ) {
			continue;
		}
		entries.push( {
			content: control.content,
			element: (
				<ValueControl
					key={ attribute }
					control={ control }
					schema={ schemas[ attribute ] }
					value={ attributes[ attribute ] }
					path={ attribute }
					onChange={ ( value ) =>
						setAttributes( { [ attribute ]: value } )
					}
				/>
			),
		} );
	}
	for ( const attribute of controlled ) {
		const Control = registry.getControl( name, attribute );
		entries.push( {
			content: schemas[ attribute ]?.role === 'content',
			element: (
				<Control
					key={ attribute }
					value={ attributes[ attribute ] }
					onChange={ ( value ) =>
						setAttributes( { [ attribute ]: value } )
					}
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			),
		} );
	}

	const content = entries.filter( ( entry ) => entry.content );
	const settings =
		mode === 'default'
			? entries.filter( ( entry ) => ! entry.content )
			: [];
	const panels = mode === 'default' ? registry.getPanels( name ) : [];

	return (
		<>
			{ content.length > 0 && (
				<InspectorControls group="content">
					<PanelBody title="Content">
						<div className="wp-app-framework-controls">
							{ content.map( ( entry ) => entry.element ) }
						</div>
					</PanelBody>
				</InspectorControls>
			) }
			{ ( settings.length > 0 || panels.length > 0 ) && (
				<InspectorControls>
					{ settings.length > 0 && (
						<PanelBody title="Settings">
							<div className="wp-app-framework-controls">
								{ settings.map( ( entry ) => entry.element ) }
							</div>
						</PanelBody>
					) }
					{ panels.map( ( Panel, index ) => (
						<Panel
							key={ index }
							attributes={ attributes }
							setAttributes={ setAttributes }
						/>
					) ) }
				</InspectorControls>
			) }
		</>
	);
}
