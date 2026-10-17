import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
	useBlockEditingMode,
	useSettings,
} from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { BaseControl, PanelBody } from '@wordpress/components';

import { createColorControl } from './color-control';
import { useBlockData } from './context';
import { useBufferedText, ValueControl } from './controls';
import { groupControls } from './groups';
import { createImageControl } from './image-control';
import { LinkValueControl } from './link-control';
import { __ } from '@wordpress/i18n';

const formatText = ( value ) => value ?? '';

/**
 * A #[RichText(inspector: true)] value: a rich text input with the allowed
 * formats of the field.
 *
 * @param {Object}                                            props
 * @param {Object}                                            props.control
 * @param {string}                                            props.value
 * @param {(value: import('./paths').AttributeValue) => void} props.onChange
 * @param {string}                                            [props.label]
 */
function RichTextValueControl( { control, value, onChange, label } ) {
	const { text, change } = useBufferedText(
		value,
		onChange,
		formatText,
		( html ) => html
	);
	return (
		<div className="wp-app-framework-control">
			<BaseControl.VisualLabel>
				{ label ?? control.label }
			</BaseControl.VisualLabel>
			<RichText
				tagName="div"
				className="wp-app-framework-rich-input"
				aria-label={ label ?? control.label }
				value={ text }
				onChange={ change }
				allowedFormats={ control.allowedFormats }
			/>
		</div>
	);
}

/** Controls of block fields, links and colors, which need the block editor. */
const FIELD_CONTROLS = {
	rich: RichTextValueControl,
	image: createImageControl( { MediaUpload, MediaUploadCheck } ),
	link: LinkValueControl,
	color: createColorControl( () => useSettings( 'color.palette' )[ 0 ] ),
};

/**
 * Generated inspector of a block: content controls in the "content" group
 * (which stays visible in contentOnly mode), controls of block supports in
 * the Styles tab or the default group, all others in the default group, plus
 * the controls and panels the theme or plugin registered.
 *
 * @param {Object} props
 * @param {Object} props.registry       Registered controls and panels.
 * @param {Object} props.componentProps Props of every registered component: attributes, setAttributes, clientId
 *                                      and isPreview.
 */
export function Inspector( { registry, componentProps } ) {
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
			group: control.group,
			element: (
				<ValueControl
					key={ attribute }
					control={ control }
					schema={ schemas[ attribute ] }
					value={ attributes[ attribute ] }
					path={ attribute }
					onChange={ ( value ) =>
						setAttributes( { [ attribute ]: value } )
					}
					fieldControls={ FIELD_CONTROLS }
				/>
			),
		} );
	}
	for ( const attribute of controlled ) {
		const Control = registry.getControl( name, attribute );
		entries.push( {
			content: schemas[ attribute ]?.role === 'content',
			group: block.controls[ attribute ]?.group,
			element: (
				<Control
					key={ attribute }
					{ ...componentProps }
					value={ attributes[ attribute ] }
					onChange={ ( value ) =>
						setAttributes( { [ attribute ]: value } )
					}
				/>
			),
		} );
	}

	const { content, styles, settings } = groupControls( entries, mode );
	const panels = mode === 'default' ? registry.getPanels( name ) : [];

	return (
		<>
			{ content.length > 0 && (
				<InspectorControls group="content">
					<PanelBody title={ __( 'Content', 'wp-app-framework' ) }>
						<div className="wp-app-framework-controls">
							{ content.map( ( entry ) => entry.element ) }
						</div>
					</PanelBody>
				</InspectorControls>
			) }
			{ styles.length > 0 && (
				<InspectorControls group="styles">
					<PanelBody title={ __( 'Appearance', 'wp-app-framework' ) }>
						<div className="wp-app-framework-controls">
							{ styles.map( ( entry ) => entry.element ) }
						</div>
					</PanelBody>
				</InspectorControls>
			) }
			{ ( settings.length > 0 || panels.length > 0 ) && (
				<InspectorControls>
					{ settings.length > 0 && (
						<PanelBody
							title={ __( 'Settings', 'wp-app-framework' ) }
						>
							<div className="wp-app-framework-controls">
								{ settings.map( ( entry ) => entry.element ) }
							</div>
						</PanelBody>
					) }
					{ panels.map( ( Panel, index ) => (
						<Panel key={ index } { ...componentProps } />
					) ) }
				</InspectorControls>
			) }
		</>
	);
}
