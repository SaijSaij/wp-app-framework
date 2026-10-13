import { ValueControl } from './controls';

/**
 * The form of a meta class: one control per field with a control.
 *
 * @param {Object}                                                props
 * @param {Object}                                                props.form          Form of the meta class (`fields`, `controls`).
 * @param {Object}                                                props.value         Values by field name.
 * @param {(name: string, value: unknown) => void}                props.onChange
 * @param {Object<string, import('react').ComponentType<Object>>} props.fieldControls Controls of rich text and images.
 */
export function MetaForm( { form, value, onChange, fieldControls } ) {
	return (
		<div className="wp-app-framework-controls">
			{ Object.entries( form.controls ).map( ( [ name, control ] ) => (
				<ValueControl
					key={ name }
					control={ control }
					schema={ form.fields[ name ].schema }
					value={ value[ name ] }
					path={ name }
					onChange={ ( next ) => onChange( name, next ) }
					fieldControls={ fieldControls }
				/>
			) ) }
		</div>
	);
}
