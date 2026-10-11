/**
 * Distributes the controls of a block over the inspector: content controls in
 * the "content" group (visible in contentOnly mode), controls of block
 * supports with the group "styles" in the Styles tab, all others in the
 * default group. Only content controls are shown outside the default editing
 * mode.
 *
 * @param {Array<{content: boolean, group?: string}>} entries Controls in display order.
 * @param {string}                                    mode    Block editing mode.
 * @return {{content: Array<Object>, styles: Array<Object>, settings: Array<Object>}} Entries by group.
 */
export function groupControls( entries, mode ) {
	const editable = mode === 'default';
	return {
		content: entries.filter( ( entry ) => entry.content ),
		styles: editable
			? entries.filter(
					( entry ) => ! entry.content && entry.group === 'styles'
				)
			: [],
		settings: editable
			? entries.filter(
					( entry ) => ! entry.content && entry.group !== 'styles'
				)
			: [],
	};
}
