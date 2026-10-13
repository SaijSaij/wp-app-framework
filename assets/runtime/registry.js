/**
 * Inspector controls, panels and toolbar buttons registered by the consumer's
 * editor scripts (`registerControl`, `registerSupportControl`, `registerPanel`,
 * `registerToolbarButton` of `@saij/wp-app-framework`). A control registered for a block wins over a
 * control registered for a block support. The register functions take block names
 * without namespace (`hero`); the registry adds the namespace of the instance.
 *
 * @param {Object}                               blocks       Block configuration of the instance by full name.
 * @param {(name: string) => (Object|undefined)} getBlockType getBlockType() of `@wordpress/blocks`.
 * @param {string}                               namespace    Namespace of the blocks of the instance.
 * @return {Object} Registry.
 */
export function createRegistry( blocks, getBlockType, namespace ) {
	const controls = new Map();
	const supportControls = new Map();
	const panels = new Map();
	const toolbarButtons = new Map();

	function usesSupport( name, attribute ) {
		return ( blocks[ name ]?.supports ?? [] ).includes( attribute );
	}

	/**
	 * The full name of an own block, or null after a warning.
	 *
	 * @param {string} name   Block name without namespace.
	 * @param {string} caller Name of the register function.
	 * @return {string|null} Full block name.
	 */
	function ownBlock( name, caller ) {
		if ( name.includes( '/' ) ) {
			// eslint-disable-next-line no-console
			console.warn(
				`wp-app-framework: ${ caller }( "${ name }" ): use the block name without namespace, e.g. "${ name.slice(
					name.indexOf( '/' ) + 1
				) }"; the namespace of this theme or plugin is added.`
			);
			return null;
		}
		const fullName = `${ namespace }/${ name }`;
		if ( ! ( fullName in blocks ) ) {
			// eslint-disable-next-line no-console
			console.warn(
				`wp-app-framework: ${ caller }( "${ name }" ): no block of this theme or plugin has that name.`
			);
			return null;
		}
		return fullName;
	}

	return {
		/**
		 * Replaces the generated control of a top-level attribute.
		 *
		 * @param {string}                                localName Block name without namespace.
		 * @param {string}                                attribute Attribute name.
		 * @param {import('react').ComponentType<Object>} Component Receives {value, onChange, attributes, setAttributes}.
		 */
		registerControl( localName, attribute, Component ) {
			const name = ownBlock( localName, 'registerControl' );
			if ( name === null ) {
				return;
			}
			if (
				! ( attribute in ( getBlockType( name )?.attributes ?? {} ) )
			) {
				// eslint-disable-next-line no-console
				console.warn(
					`wp-app-framework: registerControl( "${ localName }", "${ attribute }" ): the block has no attribute with that name.`
				);
				return;
			}
			controls.set( `${ name }\u0000${ attribute }`, Component );
		},

		/**
		 * Replaces the generated control of a block support (`#[BlockSupport]`)
		 * in every block of the theme or plugin that uses it.
		 *
		 * @param {string}                                attribute Attribute of the support.
		 * @param {import('react').ComponentType<Object>} Component Receives {value, onChange, attributes, setAttributes}.
		 */
		registerSupportControl( attribute, Component ) {
			const used = Object.values( blocks ).some( ( block ) =>
				( block.supports ?? [] ).includes( attribute )
			);
			if ( ! used ) {
				// eslint-disable-next-line no-console
				console.warn(
					`wp-app-framework: registerSupportControl( "${ attribute }" ): no block of this theme or plugin uses a block support with that attribute.`
				);
				return;
			}
			supportControls.set( attribute, Component );
		},

		/**
		 * Adds a panel to the inspector of a block.
		 *
		 * @param {string}                                localName Block name without namespace.
		 * @param {import('react').ComponentType<Object>} Component Receives {attributes, setAttributes}.
		 */
		registerPanel( localName, Component ) {
			const name = ownBlock( localName, 'registerPanel' );
			if ( name === null ) {
				return;
			}
			panels.set( name, [ ...( panels.get( name ) ?? [] ), Component ] );
		},

		/**
		 * Adds a button to the toolbar of a block.
		 *
		 * @param {string}                                localName Block name without namespace.
		 * @param {import('react').ComponentType<Object>} Component Receives {attributes, setAttributes, clientId, isPreview}; renders toolbar buttons (`ToolbarButton` of `@wordpress/components`).
		 */
		registerToolbarButton( localName, Component ) {
			const name = ownBlock( localName, 'registerToolbarButton' );
			if ( name === null ) {
				return;
			}
			toolbarButtons.set( name, [
				...( toolbarButtons.get( name ) ?? [] ),
				Component,
			] );
		},

		getControl( name, attribute ) {
			return (
				controls.get( `${ name }\u0000${ attribute }` ) ??
				( usesSupport( name, attribute )
					? supportControls.get( attribute )
					: undefined )
			);
		},

		getControlledAttributes( name ) {
			const prefix = `${ name }\u0000`;
			const attributes = [ ...controls.keys() ]
				.filter( ( key ) => key.startsWith( prefix ) )
				.map( ( key ) => key.slice( prefix.length ) );
			for ( const attribute of supportControls.keys() ) {
				if (
					usesSupport( name, attribute ) &&
					! attributes.includes( attribute )
				) {
					attributes.push( attribute );
				}
			}
			return attributes;
		},

		getPanels( name ) {
			return panels.get( name ) ?? [];
		},

		getToolbarButtons( name ) {
			return toolbarButtons.get( name ) ?? [];
		},
	};
}
