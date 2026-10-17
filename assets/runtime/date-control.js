import {
	BaseControl,
	Button,
	DatePicker,
	DateTimePicker,
	Dropdown,
} from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';

import { dateFromPicker, is12HourFormat } from './dates';
import { __ } from '@wordpress/i18n';

/**
 * The control of a #[DateTime] value: a compact button with the date in the
 * site's format, like the color control; it opens the date picker of
 * WordPress (with time of day unless `time: false`) and "Clear" stores ''
 * (no date). The picker reads and reports the local time of the site, the
 * time the value stores. It needs no block editor, so the inspector, the
 * settings page and the meta forms share it.
 *
 * @param {Object}                  props
 * @param {Object}                  props.control  Control description from the manifest (`time`).
 * @param {string}                  props.value
 * @param {(value: string) => void} props.onChange
 * @param {string}                  [props.label]  Overrides the label (list items).
 */
export function DateValueControl( { control, value, onChange, label } ) {
	const name = label ?? control.label;
	const { formats, l10n } = getSettings();
	const format = control.time
		? `${ formats.date } ${ formats.time }`
		: formats.date;
	const shown = value
		? dateI18n( format, value )
		: __( 'No date', 'wp-app-framework' );
	const pickerProps = {
		currentDate: value || null,
		startOfWeek: l10n.startOfWeek,
		onChange: ( picked ) => {
			const next = dateFromPicker( picked, control.time );
			if ( next !== null ) {
				onChange( next );
			}
		},
	};
	return (
		<div className="wp-app-framework-control wp-app-framework-date">
			<BaseControl.VisualLabel>{ name }</BaseControl.VisualLabel>
			<Dropdown
				popoverProps={ { placement: 'left-start', offset: 36 } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="wp-app-framework-date__toggle"
						aria-expanded={ isOpen }
						aria-label={ `${ name }: ${ shown }` }
						onClick={ onToggle }
					>
						{ shown }
					</Button>
				) }
				renderContent={ () => (
					<div className="wp-app-framework-date__picker">
						{ control.time ? (
							<DateTimePicker
								{ ...pickerProps }
								is12Hour={ is12HourFormat( formats.time ) }
							/>
						) : (
							<DatePicker { ...pickerProps } />
						) }
						<Button
							variant="tertiary"
							disabled={ ! value }
							onClick={ () => onChange( '' ) }
						>
							{ __( 'Clear', 'wp-app-framework' ) }
						</Button>
					</div>
				) }
			/>
		</div>
	);
}
