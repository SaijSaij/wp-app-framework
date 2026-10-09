/**
 * A new value for an attribute schema, e.g. an item added in the list editor:
 * the schema default, else null for nullable types, else an empty value of
 * the type (first enum value, empty string, 0, false, [] or an object with
 * its properties).
 *
 * @param {Object} schema JSON schema of the value.
 * @return {*} New value.
 */
export function createValue( schema ) {
	if ( 'default' in schema ) {
		return structuredClone( schema.default );
	}
	const types = Array.isArray( schema.type ) ? schema.type : [ schema.type ];
	if ( types.includes( 'null' ) ) {
		return null;
	}
	if ( Array.isArray( schema.enum ) ) {
		return schema.enum[ 0 ];
	}
	switch ( types[ 0 ] ) {
		case 'string':
			return '';
		case 'integer':
		case 'number':
			return 0;
		case 'boolean':
			return false;
		case 'array':
			return [];
		case 'object': {
			const value = {};
			for ( const [ name, property ] of Object.entries(
				schema.properties ?? {}
			) ) {
				value[ name ] = createValue( property );
			}
			return value;
		}
		default:
			throw new Error(
				`wp-app-framework: cannot create a value of type ${ JSON.stringify( schema.type ) }.`
			);
	}
}
