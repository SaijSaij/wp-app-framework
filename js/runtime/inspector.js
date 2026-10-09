import {
	InspectorControls,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

import { useBlockData } from './context';
import { createValue } from './defaults';

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
	const latest = useRef( { text, onChange } );
	latest.current = { text, onChange };

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
		const parsed = parse( latest.current.text );
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

function TextValueControl( { control, value, onChange } ) {
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
			label={ control.label }
			type={ control.url ? 'url' : 'text' }
			value={ text }
			onChange={ change }
			onBlur={ flush }
		/>
	);
}

function NumberValueControl( { control, value, onChange } ) {
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
			label={ control.label }
			type="number"
			step={ control.type === 'int' ? 1 : 'any' }
			value={ text }
			onChange={ change }
			onBlur={ flush }
		/>
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

function ObjectControl( { control, value, onChange, schema, label } ) {
	const objectSchema = {
		...schema,
		type: Array.isArray( schema.type )
			? schema.type.find( ( type ) => type !== 'null' )
			: schema.type,
	};
	if ( value === null || value === undefined ) {
		return (
			<fieldset className="wp-app-framework-object-control">
				<legend>{ label ?? control.label }</legend>
				<Button
					variant="secondary"
					onClick={ () =>
						onChange(
							createValue( {
								...objectSchema,
								default: undefined,
							} )
						)
					}
				>
					{ `Add ${ label ?? control.label }` }
				</Button>
			</fieldset>
		);
	}
	return (
		<fieldset className="wp-app-framework-object-control">
			{ ( label ?? control.label ) && (
				<legend>{ label ?? control.label }</legend>
			) }
			{ Object.entries( control.fields ).map( ( [ name, field ] ) => (
				<ValueControl
					key={ name }
					control={ field }
					schema={ schema.properties[ name ] }
					value={ value[ name ] }
					onChange={ ( next ) =>
						onChange( { ...value, [ name ]: next } )
					}
				/>
			) ) }
			{ control.nullable && (
				<Button
					variant="link"
					isDestructive
					onClick={ () => onChange( null ) }
				>
					{ `Remove ${ label ?? control.label }` }
				</Button>
			) }
		</fieldset>
	);
}

/**
 * Editor of a list: add, remove and reorder items; each item with the
 * controls of its values. Inline values of items are edited in the canvas.
 *
 * Items are keyed by index, so reordering remounts their controls.
 *
 * @param {Object}   props
 * @param {Object}   props.control
 * @param {Array}    props.value
 * @param {Function} props.onChange
 * @param {Object}   props.schema   JSON schema of the list.
 */
export function ListControl( { control, value, onChange, schema } ) {
	const items = value ?? [];
	const move = ( from, to ) => {
		const next = [ ...items ];
		next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
		onChange( next );
	};

	return (
		<fieldset className="wp-app-framework-list-control">
			<legend>{ control.label }</legend>
			<div>
				{ items.map( ( item, index ) => (
					<div className="wp-app-framework-list-item" key={ index }>
						<div className="wp-app-framework-list-item__header">
							<span>{ `Item ${ index + 1 }` }</span>
							<Button
								size="small"
								icon="arrow-up-alt2"
								label={ `Move item ${ index + 1 } up` }
								disabled={ index === 0 }
								onClick={ () => move( index, index - 1 ) }
							/>
							<Button
								size="small"
								icon="arrow-down-alt2"
								label={ `Move item ${ index + 1 } down` }
								disabled={ index === items.length - 1 }
								onClick={ () => move( index, index + 1 ) }
							/>
							<Button
								size="small"
								icon="trash"
								label={ `Remove item ${ index + 1 }` }
								isDestructive
								onClick={ () =>
									onChange(
										items.filter(
											( _, other ) => other !== index
										)
									)
								}
							/>
						</div>
						<ValueControl
							control={ control.item }
							schema={ schema.items }
							value={ item }
							label={
								control.item.type === 'object'
									? ''
									: `Item ${ index + 1 }`
							}
							onChange={ ( next ) =>
								onChange(
									items.map( ( other, otherIndex ) =>
										otherIndex === index ? next : other
									)
								)
							}
						/>
					</div>
				) ) }
				<Button
					variant="secondary"
					aria-label={ `Add item to ${ control.label }` }
					onClick={ () =>
						onChange( [ ...items, createValue( schema.items ) ] )
					}
				>
					Add item
				</Button>
			</div>
		</fieldset>
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
			return <TextValueControl { ...props } />;
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
						{ content.map( ( entry ) => entry.element ) }
					</PanelBody>
				</InspectorControls>
			) }
			{ ( settings.length > 0 || panels.length > 0 ) && (
				<InspectorControls>
					{ settings.length > 0 && (
						<PanelBody title="Settings">
							{ settings.map( ( entry ) => entry.element ) }
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
