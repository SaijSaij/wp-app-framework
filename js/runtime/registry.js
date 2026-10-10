/**
 * Inspector controls and panels registered by the consumer's editor scripts
 * (`registerControl`, `registerPanel` of `@saij/wp-app-framework`).
 *
 * @param {Object}                               blocks       Block configuration of the instance by name.
 * @param {(name: string) => (Object|undefined)} getBlockType getBlockType() of `@wordpress/blocks`.
 * @return {Object} Registry.
 */
export function createRegistry( blocks, getBlockType ) {
	const controls = new Map();
	const panels = new Map();

	function knownBlock( name, caller ) {
		if ( ! ( name in blocks ) ) {
			// eslint-disable-next-line no-console
			console.warn(
				`wp-app-framework: ${ caller }( "${ name }" ): no block of this theme has that name.`
			);
			return false;
		}
		return true;
	}

	return {
		/**
		 * Replaces the generated control of a top-level attribute.
		 *
		 * @param {string}                                name      Block name.
		 * @param {string}                                attribute Attribute name.
		 * @param {import('react').ComponentType<Object>} Component Receives {value, onChange, attributes, setAttributes}.
		 */
		registerControl( name, attribute, Component ) {
			if ( ! knownBlock( name, 'registerControl' ) ) {
				return;
			}
			if (
				! ( attribute in ( getBlockType( name )?.attributes ?? {} ) )
			) {
				// eslint-disable-next-line no-console
				console.warn(
					`wp-app-framework: registerControl( "${ name }", "${ attribute }" ): the block has no attribute with that name.`
				);
				return;
			}
			controls.set( `${ name }\u0000${ attribute }`, Component );
		},

		/**
		 * Adds a panel to the inspector of a block.
		 *
		 * @param {string}                                name      Block name.
		 * @param {import('react').ComponentType<Object>} Component Receives {attributes, setAttributes}.
		 */
		registerPanel( name, Component ) {
			if ( ! knownBlock( name, 'registerPanel' ) ) {
				return;
			}
			panels.set( name, [ ...( panels.get( name ) ?? [] ), Component ] );
		},

		getControl( name, attribute ) {
			return controls.get( `${ name }\u0000${ attribute }` );
		},

		getControlledAttributes( name ) {
			const prefix = `${ name }\u0000`;
			return [ ...controls.keys() ]
				.filter( ( key ) => key.startsWith( prefix ) )
				.map( ( key ) => key.slice( prefix.length ) );
		},

		getPanels( name ) {
			return panels.get( name ) ?? [];
		},
	};
}
