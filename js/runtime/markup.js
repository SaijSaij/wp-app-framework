import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useMemo } from '@wordpress/element';
import { attributesToProps, domToReact } from 'html-react-parser';

import { FIELD, INNER_BLOCKS, isElement } from './analyze';
import { FieldBoundary, ImageField, TextField } from './fields';
import { matchesPattern } from './paths';

function elementProps( node ) {
	const {
		[ FIELD ]: field,
		[ INNER_BLOCKS ]: innerBlocks,
		...attribs
	} = node.attribs;
	return attributesToProps( attribs, node.name );
}

function InnerBlocksElement( { tagName: Tag, props } ) {
	return <Tag { ...useInnerBlocksProps( props ) } />;
}

function createOptions( fields ) {
	const options = {
		replace( node ) {
			if ( ! isElement( node ) ) {
				return undefined;
			}
			if ( INNER_BLOCKS in node.attribs ) {
				return (
					<FieldBoundary label="Inner blocks">
						<InnerBlocksElement
							tagName={ node.name }
							props={ elementProps( node ) }
						/>
					</FieldBoundary>
				);
			}
			const path = node.attribs[ FIELD ];
			if ( path === undefined ) {
				return undefined;
			}
			const pattern = Object.keys( fields ).find( ( candidate ) =>
				matchesPattern( path, candidate )
			);
			const field = fields[ pattern ];
			const FieldComponent =
				field.kind === 'image' ? ImageField : TextField;
			return (
				<FieldBoundary label={ `Field ${ path }` }>
					<FieldComponent
						tagName={ node.name }
						elementProps={ elementProps( node ) }
						path={ path }
						field={ field }
					>
						{ field.kind === 'image'
							? domToReact( node.children, options )
							: null }
					</FieldComponent>
				</FieldBoundary>
			);
		},
	};
	return options;
}

function rootProps( node, extraProps ) {
	const props = elementProps( node );
	return {
		...props,
		...extraProps,
		style: { ...props.style, ...extraProps.style },
	};
}

function RootElement( { node, fields, extraProps } ) {
	const Tag = node.name;
	const children = useMemo(
		() => domToReact( node.children, createOptions( fields ) ),
		[ node, fields ]
	);
	return (
		<Tag { ...useBlockProps( rootProps( node, extraProps ) ) }>
			{ children }
		</Tag>
	);
}

function RootInnerBlocksElement( { node, extraProps } ) {
	const Tag = node.name;
	return (
		<Tag
			{ ...useInnerBlocksProps(
				useBlockProps( rootProps( node, extraProps ) )
			) }
		/>
	);
}

/**
 * The block's root element (from analyzeMarkup()) with the block props of the editor; marked
 * descendants are replaced by inline editors.
 *
 * @param {Object} props
 * @param {Object} props.node       Root node from analyzeMarkup().
 * @param {Object} props.fields     Inline fields by pattern.
 * @param {Object} props.extraProps Additional props of the root element (loading state).
 */
export function BlockMarkup( { node, fields, extraProps } ) {
	return INNER_BLOCKS in node.attribs ? (
		<RootInnerBlocksElement node={ node } extraProps={ extraProps } />
	) : (
		<RootElement
			node={ node }
			fields={ fields }
			extraProps={ extraProps }
		/>
	);
}
