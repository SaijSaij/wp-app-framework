/**
 * Values of the meta forms (panels in the block editor, meta boxes of the
 * classic edit screen), without React. A form describes the fields of a meta
 * class by name: meta key, schema, default and whether the field is an ID
 * (reference or image), which WordPress stores as 0 or not at all when it is
 * empty.
 */

/**
 * The value of a field from its stored value.
 *
 * @param {Object}  field  Field of the form (`schema`, `default`, `id`).
 * @param {unknown} stored Stored value; undefined if missing.
 * @return {unknown} Value for the control.
 */
export function fieldValue( field, stored ) {
	if ( field.id ) {
		return stored === undefined || stored === null || stored === 0
			? null
			: stored;
	}
	return stored === undefined || stored === null ? field.default : stored;
}

/**
 * The values of a form by field name.
 *
 * @param {Object}                                   form Form of a meta class (`fields`).
 * @param {(field: Object, name: string) => unknown} read Stored value of a field.
 * @return {Object} Values by field name.
 */
export function formValue( form, read ) {
	return Object.fromEntries(
		Object.entries( form.fields ).map( ( [ name, field ] ) => [
			name,
			fieldValue( field, read( field, name ) ),
		] )
	);
}

/**
 * The values of a form from the meta of a post in the REST API (by meta key).
 *
 * @param {Object} form
 * @param {Object} meta Meta of the post entity.
 * @return {Object} Values by field name.
 */
export function valueFromMeta( form, meta ) {
	return formValue( form, ( field ) => meta?.[ field.key ] );
}

/**
 * The meta of a post entity with one field changed: null deletes an ID.
 *
 * @param {Object}  form
 * @param {Object}  meta  Meta of the post entity.
 * @param {string}  name  Field name.
 * @param {unknown} value Value of the control.
 * @return {Object} Changed meta.
 */
export function changeMeta( form, meta, name, value ) {
	return { ...meta, [ form.fields[ name ].key ]: value };
}
