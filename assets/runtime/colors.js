/**
 * Colors of #[Color] values: hex colors with optional alpha, or '' for no
 * color. The same pattern as Color::PATTERN in PHP.
 */
export const COLOR_PATTERN =
	/^(#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8}))?$/;

/**
 * The entries of a palette (theme.json) that a #[Color] value can store, for
 * ColorPalette: hex colors only, named by their name or else their value.
 *
 * @param {Array<{name?: string, color?: string}>|undefined} palette
 * @return {Array<{name: string, color: string}>} Colors.
 */
export function hexColors( palette ) {
	return ( palette ?? [] )
		.filter(
			( entry ) =>
				typeof entry?.color === 'string' &&
				entry.color !== '' &&
				COLOR_PATTERN.test( entry.color )
		)
		.map( ( entry ) => ( {
			name: entry.name || entry.color,
			color: entry.color,
		} ) );
}
