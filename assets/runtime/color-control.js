import { BaseControl, ColorPalette } from '@wordpress/components';

import { COLOR_PATTERN, hexColors } from './colors';

/**
 * The control of a #[Color] value: the colors of the theme's palette and a
 * picker for any color with transparency; "Clear" stores '' (no color). The
 * palette comes from the block editor's settings in the editor and from the
 * page (FormAssets in PHP) on the settings page and in meta boxes, so these
 * do not load the block editor.
 *
 * @param {() => (Array<{name?: string, color?: string}>|undefined)} usePalette Hook that returns the palette.
 * @return {Object} The control component.
 */
export function createColorControl( usePalette ) {
	return function ColorValueControl( { control, value, onChange, label } ) {
		const colors = hexColors( usePalette() );
		const name = label ?? control.label;
		return (
			<div className="wp-app-framework-control">
				<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
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
		);
	};
}
