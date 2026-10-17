import { BaseControl, Button, Flex } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Passes its children through: the settings page needs no upload check, its
 * users have `manage_options`.
 *
 * @param {Object} props
 * @param {Object} props.children
 */
function NoCheck( { children } ) {
	return children;
}

/**
 * The control of an image value (#[Image(inspector: true)] in the inspector,
 * #[Image] on the settings page): preview with replace and remove, or a button
 * to select an image. The media components differ: the block editor's in the
 * inspector, those of `@wordpress/media-utils` on the settings page, so the
 * settings page does not load the block editor.
 *
 * @param {Object} components
 * @param {Object} components.MediaUpload        Opens the media library.
 * @param {Object} [components.MediaUploadCheck] Renders its children if the user may upload.
 * @return {Object} The control component.
 */
export function createImageControl( {
	MediaUpload,
	MediaUploadCheck = NoCheck,
} ) {
	return function ImageValueControl( { control, value, onChange, label } ) {
		const name = label ?? control.label;
		const id = Number.isInteger( value ) ? value : null;
		const media = useSelect(
			( select ) =>
				id === null
					? undefined
					: select( 'core' ).getEntityRecord(
							'postType',
							'attachment',
							id
						),
			[ id ]
		);
		const onSelect = ( selected ) => {
			if ( Number.isInteger( selected?.id ) ) {
				onChange( selected.id );
			}
		};

		return (
			<div className="wp-app-framework-control">
				<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
				<MediaUploadCheck>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						value={ id ?? undefined }
						onSelect={ onSelect }
						render={ ( { open } ) =>
							id === null ? (
								<Button variant="secondary" onClick={ open }>
									{ sprintf(
										/* translators: %s: label of an image */
										__( 'Select %s', 'wp-app-framework' ),
										name
									) }
								</Button>
							) : (
								<div className="wp-app-framework-image">
									{ media?.source_url && (
										<img
											className="wp-app-framework-image__preview"
											src={ media.source_url }
											alt={ media.alt_text ?? '' }
										/>
									) }
									<Flex justify="flex-start">
										<Button
											variant="secondary"
											aria-label={ sprintf(
												/* translators: %s: label of an image */
												__(
													'Replace %s',
													'wp-app-framework'
												),
												name
											) }
											onClick={ open }
										>
											{ __(
												'Replace',
												'wp-app-framework'
											) }
										</Button>
										<Button
											variant="tertiary"
											isDestructive
											aria-label={ sprintf(
												/* translators: %s: label of an image */
												__(
													'Remove %s',
													'wp-app-framework'
												),
												name
											) }
											onClick={ () => onChange( null ) }
										>
											{ __(
												'Remove',
												'wp-app-framework'
											) }
										</Button>
									</Flex>
								</div>
							)
						}
					/>
				</MediaUploadCheck>
			</div>
		);
	};
}
