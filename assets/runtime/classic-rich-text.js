import { BaseControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEffect, useRef } from '@wordpress/element';

import { editorSettings } from './classic-editor-settings';

/**
 * A #[RichText] setting or meta field, edited with WordPress' classic editor
 * (loaded by wp_enqueue_editor()): the RichText of the block editor only works
 * inside a selected block. WordPress provides the classic editor as
 * wp.oldEditor, also in the block editor, where wp.editor is the editor package.
 *
 * @param {Object}                  props
 * @param {Object}                  props.control  Control description (`allowedFormats`).
 * @param {string}                  props.value    HTML.
 * @param {(value: string) => void} props.onChange
 * @param {string}                  [props.label]
 */
export function ClassicRichTextControl( { control, value, onChange, label } ) {
	const id = `wp-app-framework-rich-${ useInstanceId(
		ClassicRichTextControl
	) }`;
	const latest = useRef( onChange );
	latest.current = onChange;

	useEffect( () => {
		window.wp.oldEditor.initialize( id, {
			tinymce: {
				...editorSettings( control.allowedFormats ?? null ),
				setup( editor ) {
					editor.on( 'change keyup input', () =>
						latest.current( editor.getContent() )
					);
				},
			},
			quicktags: false,
			mediaButtons: false,
		} );
		return () => window.wp.oldEditor.remove( id );
	}, [ id, control.allowedFormats ] );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ id }
			label={ label ?? control.label }
		>
			<textarea id={ id } defaultValue={ value ?? '' } />
		</BaseControl>
	);
}
