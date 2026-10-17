import {
	BaseControl,
	Button,
	ColorIndicator,
	ColorPalette,
	Dropdown,
} from '@wordpress/components';

import { COLOR_PATTERN, hexColors } from './colors';

/**
 * The control of a #[Color] value: a compact button with a swatch and the
 * hex value, like the color controls of the block editor; it opens the
 * colors of the theme's palette and a picker for any color with
 * transparency; "Clear" stores '' (no color). The palette comes from the
 * block editor's settings in the editor and from the page (FormAssets in
 * PHP) on the settings page and in meta boxes, so these do not load the
 * block editor.
 *
 * @param {() => (Array<{name?: string, color?: string}>|undefined)} usePalette Hook that returns the palette.
 * @return {Object} The control component.
 */
export function createColorControl( usePalette ) {
	return function ColorValueControl( { control, value, onChange, label } ) {
		const colors = hexColors( usePalette() );
		const name = label ?? control.label;
		const named = colors.find( ( color ) => color.color === value );
		return (
			<div className="wp-app-framework-control wp-app-framework-color">
				<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
				<Dropdown
					popoverProps={ { placement: 'left-start', offset: 36 } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							className="wp-app-framework-color__toggle"
							aria-expanded={ isOpen }
							aria-label={ `${ name }: ${ value || 'no color' }` }
							onClick={ onToggle }
						>
							<ColorIndicator colorValue={ value || undefined } />
							<span className="wp-app-framework-color__value">
								{ named
									? `${ named.name } (${ value })`
									: value || 'No color' }
							</span>
						</Button>
					) }
					renderContent={ () => (
						<div className="wp-app-framework-color__picker">
							<ColorPalette
								aria-label={ name }
								colors={ colors }
								value={ value || undefined }
								onChange={ ( next ) => {
									// The picker produces #rrggbb or #rrggbbaa, the palette only hex colors.
									if ( COLOR_PATTERN.test( next ?? '' ) ) {
										onChange( next ?? '' );
									}
								} }
								enableAlpha
								clearable
							/>
						</div>
					) }
				/>
			</div>
		);
	};
}
