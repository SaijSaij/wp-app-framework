import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';

import { ClassicRichTextControl } from './classic-rich-text';
import { createImageControl } from './image-control';
import { LinkValueControl } from './link-control';
import { MetaForm } from './meta-form';
import { changeMeta, valueFromMeta } from './meta-data';

/**
 * Controls of rich text (classic editor), images (media library of the block
 * editor) and links (LinkControl of the block editor).
 */
const FIELD_CONTROLS = {
	rich: ClassicRichTextControl,
	image: createImageControl( { MediaUpload, MediaUploadCheck } ),
	link: LinkValueControl,
};

/**
 * The panel of one meta class in the document settings of the post editor.
 * The values are post meta of the edited post: the editor saves them with the
 * post, in the same request.
 *
 * @param {Object} props
 * @param {string} props.postType
 * @param {Object} props.form     Form of the meta class.
 */
function MetaPanel( { postType, form } ) {
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	if ( ! meta ) {
		return null;
	}
	return (
		<PluginDocumentSettingPanel
			name={ `meta-${ form.name }` }
			title={ form.title }
			className="wp-app-framework-meta-panel"
		>
			<MetaForm
				form={ form }
				value={ valueFromMeta( form, meta ) }
				onChange={ ( name, value ) =>
					setMeta( changeMeta( form, meta, name, value ) )
				}
				fieldControls={ FIELD_CONTROLS }
			/>
		</PluginDocumentSettingPanel>
	);
}

/**
 * The panels of the edited post type.
 *
 * @param {Object} props
 * @param {Object} props.panels Forms of the meta classes by post type.
 */
function MetaPanels( { panels } ) {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	// Post types without meta of this instance (and templates in the site editor) get no panel.
	if ( ! postType || ! ( postType in panels ) ) {
		return null;
	}
	return panels[ postType ].map( ( form ) => (
		<MetaPanel key={ form.name } postType={ postType } form={ form } />
	) );
}

/**
 * Adds the panels of the meta classes to the post editor.
 *
 * @param {string} slug   Slug of the instance; names the editor plugin.
 * @param {Object} panels Forms of the meta classes by post type.
 */
export function registerMetaPanels( slug, panels ) {
	if ( Object.keys( panels ).length === 0 ) {
		return;
	}
	registerPlugin( `${ slug }-meta`, {
		render: () => <MetaPanels panels={ panels } />,
	} );
}
