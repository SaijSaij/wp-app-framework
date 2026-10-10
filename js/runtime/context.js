import { createContext, useContext } from '@wordpress/element';

/**
 * Data of the edited block for the components that replace marked elements,
 * so the parsed markup does not have to be recreated on every attribute change.
 */
export const BlockDataContext = createContext( null );

/**
 * @return {{name: string, block: Object, attributes: Object, setAttributes: (attributes: Object) => void}} Block data.
 */
export function useBlockData() {
	const data = useContext( BlockDataContext );
	if ( data === null ) {
		throw new Error(
			'wp-app-framework: useBlockData() outside a block of the editor runtime.'
		);
	}
	return data;
}
