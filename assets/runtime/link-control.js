import { LinkControl } from '@wordpress/block-editor';
import { BaseControl, Button, Dropdown } from '@wordpress/components';

import {
	displayUrl,
	emptyLink,
	fromLinkControlValue,
	toLinkControlValue,
} from './links';
import { __ } from '@wordpress/i18n';

/**
 * A link value (`Link`) in the block editor (inspector, meta panels of the
 * post editor): the label and the shortened URL, a click opens LinkControl in
 * a popover. LinkControl searches posts, terms and media files with the
 * search the post and site editors set in the block editor settings; a chosen
 * result stays synced (stored with its ID, the input is locked until "Unsync
 * and edit"). "Open in new tab" applies with "Apply".
 *
 * @param {Object}                  props
 * @param {Object}                  props.control  Control description.
 * @param {?Object}                 props.value    Stored link.
 * @param {(value: Object) => void} props.onChange
 * @param {string}                  [props.label]
 */
export function LinkValueControl( { control, value, onChange, label } ) {
	const name = label ?? control.label;
	const url = value?.url ?? '';
	return (
		<div
			className="wp-app-framework-control"
			role="group"
			aria-label={ name }
		>
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			<Dropdown
				popoverProps={ {
					placement: 'left-start',
					offset: 36,
					shift: true,
				} }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="wp-app-framework-link"
						variant="secondary"
						__next40pxDefaultSize
						aria-expanded={ isOpen }
						aria-label={ `${ name }: ${
							url === ''
								? __( 'Add link', 'wp-app-framework' )
								: url
						}` }
						onClick={ onToggle }
					>
						<span className="wp-app-framework-link__url">
							{ url === ''
								? __( 'Add link', 'wp-app-framework' )
								: displayUrl( url ) }
						</span>
					</Button>
				) }
				renderContent={ ( { onClose } ) => (
					<LinkControl
						value={ toLinkControlValue( value ) }
						settings={ LinkControl.DEFAULT_LINK_SETTINGS }
						handleEntities
						onChange={ ( next ) =>
							onChange( fromLinkControlValue( next ) )
						}
						onRemove={ () => {
							onChange( emptyLink() );
							onClose();
						} }
					/>
				) }
			/>
		</div>
	);
}
