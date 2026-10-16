/**
 * Entry of the meta boxes of a framework instance on the classic edit screen.
 * Built by the consumer's build (webpack.config.js of the framework), which
 * defines WP_APP_FRAMEWORK_SLUG; the forms come from
 * `wpAppFramework[slug].metaBoxes` (MetaBoxes in PHP). Each box writes its
 * values as JSON into the hidden field next to it, which is saved with the post.
 */
/* global WP_APP_FRAMEWORK_SLUG */
import domReady from '@wordpress/dom-ready';
import { createRoot, useState } from '@wordpress/element';
import { MediaUpload } from '@wordpress/media-utils';

import { ClassicRichTextControl } from './runtime/classic-rich-text';
import { createColorControl } from './runtime/color-control';
import { createImageControl } from './runtime/image-control';
import { LinkFormControl } from './runtime/link-form-control';
import { MetaForm } from './runtime/meta-form';
import { formValue } from './runtime/meta-data';

import './meta-box.scss';

/**
 * Controls of rich text (classic editor), images (media library of
 * `@wordpress/media-utils`), links (a search form) and colors (the palette of
 * the page, `wpAppFramework[slug].palette`).
 */
const FIELD_CONTROLS = {
	rich: ClassicRichTextControl,
	image: createImageControl( { MediaUpload } ),
	link: LinkFormControl,
	color: createColorControl(
		() => window.wpAppFramework?.[ WP_APP_FRAMEWORK_SLUG ]?.palette
	),
};

/**
 * @param {Object}           props
 * @param {Object}           props.form  Form of the meta class.
 * @param {HTMLInputElement} props.input Hidden field with the values as JSON.
 */
function MetaBox( { form, input } ) {
	const [ value, setValue ] = useState( () => {
		const stored = JSON.parse( input.value );
		return formValue( form, ( field, name ) => stored[ name ] );
	} );
	return (
		<MetaForm
			form={ form }
			value={ value }
			onChange={ ( name, next ) => {
				const changed = { ...value, [ name ]: next };
				setValue( changed );
				input.value = JSON.stringify( changed );
			} }
			fieldControls={ FIELD_CONTROLS }
		/>
	);
}

domReady( () => {
	const forms = window.wpAppFramework?.[ WP_APP_FRAMEWORK_SLUG ]?.metaBoxes;
	const boxes = document.querySelectorAll(
		`.wp-app-framework-meta-box[data-instance="${ WP_APP_FRAMEWORK_SLUG }"]`
	);
	for ( const box of boxes ) {
		const form = forms?.[ box.dataset.meta ];
		const input = box.nextElementSibling;
		if ( ! form || ! ( input instanceof window.HTMLInputElement ) ) {
			throw new Error(
				`wp-app-framework: the meta box "${ box.dataset.meta }" of ${ WP_APP_FRAMEWORK_SLUG } has no form or value field.`
			);
		}
		createRoot( box ).render( <MetaBox form={ form } input={ input } /> );
	}
} );
