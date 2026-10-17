import {
	BaseControl,
	Button,
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import {
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

import { DateValueControl } from './date-control';
import { BlockDataContext, ControlOverridesContext } from './context';
import { createValue } from './defaults';
import { createItem } from './identifiers';
import { formFields, listItemTitle, listLimits, moveItem } from './list';
import { ListCards, useListCards } from './list-cards';
import { LimitNotice } from './list-notice';
import { ReferenceControl, ReferencesControl } from './reference-control';

/**
 * Controls of values described by the manifest, used by the block inspector
 * and the settings page. Controls of block fields (rich text and images in
 * the inspector) and links need the block editor or differ outside of it;
 * the inspector, the settings page and the meta forms pass them in as
 * `fieldControls`, so the settings page does not load the block editor.
 */

/** Delay before a typed inspector value is applied (and the block re-rendered). */
const TYPING_DELAY = 300;

/**
 * Keeps the typed text locally and applies it after a pause or on blur, so
 * typing does not re-render the block on every key. External changes (undo)
 * replace the local text.
 *
 * @param {import('./paths').AttributeValue}                               value    Current attribute value.
 * @param {(value: import('./paths').AttributeValue) => void}              onChange Applies a value.
 * @param {(value: import('./paths').AttributeValue) => string}            format   Value → text.
 * @param {(text: string) => (import('./paths').AttributeValue|undefined)} parse    Text → value, undefined if invalid.
 * @return {{text: string, change: (text: string) => void, flush: () => void}} Buffered text.
 */
export function useBufferedText( value, onChange, format, parse ) {
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
	if ( control.multiline ) {
		return (
			<TextareaControl
				__nextHasNoMarginBottom
				label={ label ?? control.label }
				value={ text }
				onChange={ change }
				onBlur={ flush }
			/>
		);
	}
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
 * A control that a settings script registered (registerSettingsControl())
 * instead of the generated one, which it can still render as DefaultControl.
 *
 * @param {Object}                                            props
 * @param {import('react').ComponentType<Object>}             props.component     The registered control.
 * @param {import('./paths').AttributeValue}                  props.value
 * @param {(value: import('./paths').AttributeValue) => void} props.onChange
 * @param {Array<string|number>}                              props.at            Path of the value, with list indices.
 * @param {() => import('@wordpress/element').Element}        props.renderDefault Renders the generated control.
 */
function OverriddenControl( {
	component: Component,
	value,
	onChange,
	at,
	renderDefault,
} ) {
	const overrides = useContext( ControlOverridesContext );
	const latest = useRef( renderDefault );
	latest.current = renderDefault;
	// One component for the life of the control, so the generated control keeps its state.
	const DefaultControl = useMemo(
		() =>
			function Generated() {
				return latest.current();
			},
		[]
	);
	return (
		<Component
			value={ value }
			onChange={ onChange }
			path={ at }
			groupValue={ overrides.groupValue }
			DefaultControl={ DefaultControl }
		/>
	);
}

/**
 * The control registered for a value pattern, if the page has overrides.
 *
 * @param {string|undefined} pattern e.g. "schemes.*".
 * @return {(import('react').ComponentType<Object>)|undefined} Registered control.
 */
function useOverride( pattern ) {
	const overrides = useContext( ControlOverridesContext );
	return pattern === undefined ? undefined : overrides?.get( pattern );
}

/**
 * The values of a value class, each with its control.
 *
 * @param {Object}                                            props
 * @param {Object}                                            props.control
 * @param {Object}                                            props.value
 * @param {(value: import('./paths').AttributeValue) => void} props.onChange
 * @param {Object}                                            props.schema
 * @param {string}                                            [props.path]          Pattern of the value.
 * @param {Array<string|number>}                              [props.at]            Path of the value, with list indices.
 * @param {Object}                                            [props.fieldControls] Controls of block fields by type.
 */
function ObjectFields( {
	control,
	value,
	onChange,
	schema,
	path,
	at,
	fieldControls,
} ) {
	return Object.entries( control.fields ).map( ( [ name, field ] ) => (
		<ValueControl
			key={ name }
			control={ field }
			schema={ schema.properties[ name ] }
			value={ value[ name ] }
			path={ path === undefined ? undefined : `${ path }.${ name }` }
			at={ [ ...( at ?? [] ), name ] }
			onChange={ ( next ) => onChange( { ...value, [ name ]: next } ) }
			fieldControls={ fieldControls }
		/>
	) );
}

function ObjectControl( {
	control,
	value,
	onChange,
	schema,
	label,
	path,
	at,
	fieldControls,
} ) {
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
						path={ path }
						at={ at }
						fieldControls={ fieldControls }
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
 * Editor of a list: one collapsed card per item (ListCards) with its title,
 * the controls of the item when expanded, drag and drop and an actions menu;
 * inline values of items are edited in the canvas. A new item is expanded.
 * The limits of the list (`min`, `max`) disable adding and removing; items a
 * stored list has beyond `max` are marked as unused.
 *
 * @param {Object}                                            props
 * @param {Object}                                            props.control
 * @param {Array}                                             props.value
 * @param {(value: import('./paths').AttributeValue) => void} props.onChange
 * @param {Object}                                            props.schema          JSON schema of the list.
 * @param {string}                                            props.path            Pattern of the list, e.g. "buttons".
 * @param {Array<string|number>}                              [props.at]            Path of the list, with list indices.
 * @param {Object}                                            [props.fieldControls] Controls of block fields by type.
 */
export function ListControl( {
	control,
	value,
	onChange,
	schema,
	path,
	at,
	fieldControls,
} ) {
	const blockData = useContext( BlockDataContext );
	// Outside the block editor, the text fields of the items have controls of their own.
	const fields = blockData?.block.fields ?? formFields( path, control.item );
	const ItemOverride = useOverride(
		path === undefined ? undefined : `${ path }.*`
	);
	const items = value ?? [];
	const limits = listLimits( control, items.length );
	const cards = useListCards(
		items.length,
		() => blockData?.takeAddedItem?.( path ) ?? false
	);
	const replace = ( index, next ) =>
		onChange(
			items.map( ( other, otherIndex ) =>
				otherIndex === index ? next : other
			)
		);
	const generatedBody = ( index ) => {
		if ( ! hasItemControls( control.item ) ) {
			return null;
		}
		const itemPath = path === undefined ? undefined : `${ path }.*`;
		return control.item.type === 'object' ? (
			<ObjectFields
				control={ control.item }
				value={ items[ index ] }
				onChange={ ( next ) => replace( index, next ) }
				schema={ schema.items }
				path={ itemPath }
				at={ [ ...( at ?? [] ), index ] }
				fieldControls={ fieldControls }
			/>
		) : (
			<GeneratedControl
				control={ control.item }
				schema={ schema.items }
				value={ items[ index ] }
				path={ itemPath }
				at={ [ ...( at ?? [] ), index ] }
				label={ `${ control.itemLabel } ${ index + 1 }` }
				onChange={ ( next ) => replace( index, next ) }
				fieldControls={ fieldControls }
			/>
		);
	};
	const body = ( index ) =>
		ItemOverride ? (
			<OverriddenControl
				component={ ItemOverride }
				value={ items[ index ] }
				onChange={ ( next ) => replace( index, next ) }
				at={ [ ...( at ?? [] ), index ] }
				renderDefault={ () => generatedBody( index ) }
			/>
		) : (
			generatedBody( index )
		);

	return (
		<div className="wp-app-framework-list">
			<BaseControl.VisualLabel>{ control.label }</BaseControl.VisualLabel>
			<LimitNotice limits={ limits } />
			<ListCards
				cards={ cards }
				limits={ limits }
				title={ ( index ) =>
					listItemTitle(
						control,
						items[ index ],
						index,
						path,
						fields
					)
				}
				actionsLabel={ ( index ) => `Actions for item ${ index + 1 }` }
				body={ body }
				onMove={ ( from, to ) =>
					onChange( moveItem( items, from, to ) )
				}
				onRemove={ ( index ) =>
					onChange( items.filter( ( _, other ) => other !== index ) )
				}
			/>
			<div>
				<Button
					variant="secondary"
					icon="plus"
					aria-label={ `Add item to ${ control.label }` }
					disabled={ ! limits.canAdd }
					accessibleWhenDisabled
					onClick={ () => {
						cards.add();
						onChange( [
							...items,
							createItem(
								schema.items,
								control.identifier,
								items
							),
						] );
					} }
				>
					Add item
				</Button>
			</div>
		</div>
	);
}

/**
 * The control of a value: the one a settings script registered for its
 * pattern on the settings page (registerSettingsControl()), else the
 * generated one.
 *
 * @param {Object} props See GeneratedControl.
 */
export function ValueControl( props ) {
	const Override = useOverride( props.path );
	if ( ! Override ) {
		return <GeneratedControl { ...props } />;
	}
	return (
		<OverriddenControl
			component={ Override }
			value={ props.value }
			onChange={ props.onChange }
			at={ props.at ?? [] }
			renderDefault={ () => <GeneratedControl { ...props } /> }
		/>
	);
}

/**
 * The generated control of a value, chosen by its manifest description.
 *
 * @param {Object}                                            props
 * @param {Object}                                            props.control         Control description from the manifest.
 * @param {Object}                                            props.schema          JSON schema of the value.
 * @param {import('./paths').AttributeValue}                  props.value
 * @param {(value: import('./paths').AttributeValue) => void} props.onChange
 * @param {string}                                            [props.label]         Overrides the label (list items).
 * @param {string}                                            [props.path]          Pattern of the value, for list item titles and
 *                                                                                  the controls of settings scripts.
 * @param {Array<string|number>}                              [props.at]            Path of the value with list indices (settings page).
 * @param {Object}                                            [props.fieldControls] Controls of block fields, links and colors by
 *                                                                                  type ("rich", "image", "link", "color"), passed in by the
 *                                                                                  inspector, the settings page and the meta forms.
 */
function GeneratedControl( props ) {
	const { control, value, onChange, label, fieldControls } = props;
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
		case 'date':
			return <DateValueControl { ...props } />;
		case 'rich':
		case 'image':
		case 'link':
		case 'color': {
			const FieldControl = fieldControls?.[ control.type ];
			if ( ! FieldControl ) {
				throw new Error(
					`wp-app-framework: no control of type ${ control.type } was passed in (fieldControls); the inspector, the settings page and the meta forms pass one.`
				);
			}
			return <FieldControl { ...props } />;
		}
		case 'object':
			return <ObjectControl { ...props } />;
		case 'list':
			return <ListControl { ...props } />;
		case 'reference':
			return <ReferenceControl { ...props } />;
		case 'references':
			return <ReferencesControl { ...props } />;
		default:
			throw new Error(
				`wp-app-framework: unknown control type ${ JSON.stringify( control.type ) }.`
			);
	}
}
