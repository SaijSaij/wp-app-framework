import {
	BlockControls,
	MediaPlaceholder,
	MediaReplaceFlow,
	RichText,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Component } from '@wordpress/element';

import { useBlockData } from './context';
import { attributeOf, getPath, setPath } from './paths';
import { htmlToText, textToHtml } from './text';

const VOID_ELEMENTS = [
	'area',
	'base',
	'br',
	'col',
	'embed',
	'hr',
	'img',
	'input',
	'link',
	'meta',
	'source',
	'track',
	'wbr',
];

/**
 * Sets the value at a path through setAttributes() of the top-level attribute.
 *
 * @param {Object}                           attributes
 * @param {(attributes: Object) => void}     setAttributes
 * @param {string}                           path
 * @param {import('./paths').AttributeValue} value
 */
function setValue( attributes, setAttributes, path, value ) {
	const attribute = attributeOf( path );
	setAttributes( {
		[ attribute ]: setPath( attributes, path, value )[ attribute ],
	} );
}

/**
 * An inline text field: #[PlainText] (stored as text) or #[RichText] (stored as HTML).
 *
 * @param {Object} props
 * @param {string} props.tagName      Tag of the marked element.
 * @param {Object} props.elementProps Props of the marked element.
 * @param {string} props.path         Concrete path, e.g. "buttons.0.label".
 * @param {Object} props.field        Field definition: kind, label, allowedFormats, multiline.
 */
export function TextField( { tagName, elementProps, path, field } ) {
	const { attributes, setAttributes } = useBlockData();
	const plain = field.kind === 'plain';
	const multiline = plain && field.multiline === true;
	const value = getPath( attributes, path ) ?? '';

	return (
		<RichText
			{ ...elementProps }
			tagName={ tagName }
			identifier={ path }
			value={ plain ? textToHtml( value, multiline ) : value }
			onChange={ ( next ) =>
				setValue(
					attributes,
					setAttributes,
					path,
					plain ? htmlToText( next ) : next
				)
			}
			placeholder={ field.label }
			allowedFormats={ plain ? [] : field.allowedFormats }
			disableLineBreaks={ plain && ! multiline }
			withoutInteractiveFormatting={ plain }
		/>
	);
}

/**
 * An #[Image] field: the server markup of the image, a placeholder without
 * image, and replace/reset in the block toolbar.
 *
 * @param {Object}                    props
 * @param {string}                    props.tagName      Tag of the marked element (a wrapper, not the img).
 * @param {Object}                    props.elementProps Props of the marked element.
 * @param {string}                    props.path         Concrete path.
 * @param {Object}                    props.field        Field definition.
 * @param {import('react').ReactNode} props.children     Server markup inside the element.
 */
export function ImageField( {
	tagName: Tag,
	elementProps,
	path,
	field,
	children,
} ) {
	const { attributes, setAttributes } = useBlockData();
	if ( VOID_ELEMENTS.includes( Tag ) ) {
		throw new Error(
			`field( "${ path }" ) marks an <${ Tag }>; mark an element around the image instead, e.g. <figure>.`
		);
	}
	const id = getPath( attributes, path ) ?? null;
	// MediaReplaceFlow offers "Reset" only with the URL of the current media.
	const mediaUrl = useSelect(
		( select ) =>
			id === null
				? undefined
				: select( 'core' ).getEntityRecord(
						'postType',
						'attachment',
						id
					)?.source_url,
		[ id ]
	);
	const onSelect = ( media ) => {
		// While uploading, the media library first reports a temporary object without ID.
		if ( Number.isInteger( media?.id ) ) {
			setValue( attributes, setAttributes, path, media.id );
		}
	};

	if ( id === null ) {
		return (
			<Tag { ...elementProps }>
				<MediaPlaceholder
					icon="format-image"
					labels={ { title: field.label } }
					accept="image/*"
					allowedTypes={ [ 'image' ] }
					onSelect={ onSelect }
				/>
			</Tag>
		);
	}

	return (
		<>
			<Tag { ...elementProps }>{ children }</Tag>
			<BlockControls group="other">
				<MediaReplaceFlow
					mediaId={ id }
					mediaURL={ mediaUrl }
					name={ field.label }
					accept="image/*"
					allowedTypes={ [ 'image' ] }
					onSelect={ onSelect }
					onReset={ () =>
						setValue( attributes, setAttributes, path, null )
					}
				/>
			</BlockControls>
		</>
	);
}

/**
 * Catches errors of one replaced element, so the rest of the block stays editable.
 */
export class FieldBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null };
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error ) {
		// eslint-disable-next-line no-console
		console.error(
			`wp-app-framework: ${ this.props.label } failed:`,
			error
		);
	}

	render() {
		if ( this.state.error ) {
			return (
				<span className="wp-app-framework-field-error" role="alert">
					{ `${ this.props.label }: ${ this.state.error.message }` }
				</span>
			);
		}
		return this.props.children;
	}
}
