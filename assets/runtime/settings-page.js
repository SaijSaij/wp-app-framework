/* global WP_APP_FRAMEWORK_SLUG */
import {
	Button,
	Notice,
	SnackbarList,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { MediaUpload } from '@wordpress/media-utils';
import { store as noticesStore } from '@wordpress/notices';

import { useMemo } from '@wordpress/element';

import { ClassicRichTextControl } from './classic-rich-text';
import { createColorControl } from './color-control';
import { ControlOverridesContext } from './context';
import { ValueControl } from './controls';
import { createImageControl } from './image-control';
import { LinkFormControl } from './link-form-control';
import { editedGroups, groupValue, saveErrorMessage } from './settings-data';

/**
 * Controls of #[Image] settings (media library of `@wordpress/media-utils`),
 * #[RichText] settings (classic editor), links (a search form; the block
 * editor's LinkControl is not available here) and colors (the palette of the
 * page, `wpAppFramework[slug].palette`).
 */
const FIELD_CONTROLS = {
	image: createImageControl( { MediaUpload } ),
	rich: ClassicRichTextControl,
	link: LinkFormControl,
	color: createColorControl(
		() => window.wpAppFramework?.[ WP_APP_FRAMEWORK_SLUG ]?.palette
	),
};

/** Marks the tab of a group with unsaved changes. */
const EDITED_MARK = ' •';

/**
 * The form of one settings group, with the controls its settings script
 * registered.
 *
 * @param {Object}                  props
 * @param {Object}                  props.group    Group configuration.
 * @param {Object}                  props.value    Value of the group.
 * @param {(value: Object) => void} props.onChange
 * @param {Object}                  props.registry Controls of the settings scripts (createSettingsRegistry()).
 */
function GroupForm( { group, value, onChange, registry } ) {
	const overrides = useMemo(
		() => ( {
			get: ( pattern ) =>
				registry.getSettingsControl( group.name, pattern ),
			groupValue: value,
		} ),
		[ registry, group.name, value ]
	);
	return (
		<ControlOverridesContext.Provider value={ overrides }>
			<GroupFields
				group={ group }
				value={ value }
				onChange={ onChange }
			/>
		</ControlOverridesContext.Provider>
	);
}

/**
 * @param {Object}                  props
 * @param {Object}                  props.group
 * @param {Object}                  props.value
 * @param {(value: Object) => void} props.onChange
 */
function GroupFields( { group, value, onChange } ) {
	return (
		<div className="wp-app-framework-settings__group">
			{ group.description !== '' && <p>{ group.description }</p> }
			<div className="wp-app-framework-controls">
				{ Object.entries( group.controls ).map(
					( [ name, control ] ) => (
						<ValueControl
							key={ name }
							control={ control }
							schema={ group.schema.properties[ name ] }
							value={ value[ name ] }
							path={ name }
							at={ [ name ] }
							onChange={ ( next ) =>
								onChange( { ...value, [ name ]: next } )
							}
							fieldControls={ FIELD_CONTROLS }
						/>
					)
				) }
			</div>
		</div>
	);
}

/**
 * Notices of the page: errors above the form, success messages as snackbars.
 */
function Notices() {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	return (
		<>
			{ notices
				.filter( ( notice ) => notice.type !== 'snackbar' )
				.map( ( notice ) => (
					<Notice
						key={ notice.id }
						status={ notice.status }
						onRemove={ () => removeNotice( notice.id ) }
					>
						{ notice.content }
					</Notice>
				) ) }
			<SnackbarList
				notices={ notices.filter(
					( notice ) => notice.type === 'snackbar'
				) }
				onRemove={ removeNotice }
			/>
		</>
	);
}

/**
 * The settings page of a theme or plugin: the forms of its groups (tabs if
 * there are several), saved together through the site entity of core-data
 * (/wp/v2/settings, one request with the changed groups).
 *
 * @param {Object} props
 * @param {Object} props.config   Page configuration from `wpAppFramework[slug].settings`.
 * @param {Object} props.registry Controls of the settings scripts (createSettingsRegistry()).
 */
export function SettingsPage( { config, registry } ) {
	const { groups } = config;
	const { site, edits, saving } = useSelect(
		( select ) => ( {
			site: select( coreStore ).getEditedEntityRecord( 'root', 'site' ),
			edits: select( coreStore ).getEntityRecordEdits( 'root', 'site' ),
			saving: select( coreStore ).isSavingEntityRecord( 'root', 'site' ),
		} ),
		[]
	);
	const { editEntityRecord, saveEditedEntityRecord } =
		useDispatch( coreStore );
	const { createSuccessNotice, createErrorNotice, removeAllNotices } =
		useDispatch( noticesStore );

	if ( ! site || Object.keys( site ).length === 0 ) {
		return <Spinner />;
	}

	const edited = editedGroups( groups, edits );
	const save = async () => {
		removeAllNotices();
		try {
			await saveEditedEntityRecord( 'root', 'site', undefined, {
				throwOnError: true,
			} );
			createSuccessNotice( 'Settings saved.', { type: 'snackbar' } );
		} catch ( error ) {
			createErrorNotice( saveErrorMessage( error, groups ) );
		}
	};
	const form = ( group ) => (
		<GroupForm
			group={ group }
			registry={ registry }
			value={ groupValue( group, site[ group.option ] ?? null ) }
			onChange={ ( value ) =>
				editEntityRecord( 'root', 'site', undefined, {
					[ group.option ]: value,
				} )
			}
		/>
	);

	return (
		<div className="wp-app-framework-settings">
			<Notices />
			{ groups.length === 1 ? (
				form( groups[ 0 ] )
			) : (
				<TabPanel
					className="wp-app-framework-settings__tabs"
					tabs={ groups.map( ( group ) => ( {
						name: group.name,
						title:
							group.title +
							( edited.includes( group.name )
								? EDITED_MARK
								: '' ),
					} ) ) }
				>
					{ ( tab ) =>
						form(
							groups.find( ( group ) => group.name === tab.name )
						)
					}
				</TabPanel>
			) }
			<div className="wp-app-framework-settings__actions">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving || edited.length === 0 }
					accessibleWhenDisabled
					onClick={ save }
				>
					Save
				</Button>
			</div>
		</div>
	);
}
