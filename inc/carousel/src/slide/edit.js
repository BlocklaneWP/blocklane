/**
 * The slide's editor: a container for any blocks, and "Apply layout to all
 * slides", which rebuilds every other slide of a static track to match this
 * one while each keeps its own content.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import { Button, PanelBody } from '@wordpress/components';
import { useRegistry, useSelect } from '@wordpress/data';

const TRACK = 'blocklane/carousel-track';
const SLIDE = 'blocklane/slide';

/** Blocks that only arrange others: a rebuilt one takes the source's attributes. */
const LAYOUT_BLOCKS = [
	'core/group',
	'core/columns',
	'core/column',
	'core/buttons',
	'core/spacer',
	'core/separator',
];

/** Each content block's own content, which a rebuilt one keeps. */
const CONTENT_ATTRIBUTES = {
	'core/paragraph': [ 'content' ],
	'core/heading': [ 'content' ],
	'core/image': [ 'url', 'id', 'alt', 'caption', 'title', 'href' ],
	'core/button': [ 'text', 'url', 'title', 'rel', 'linkTarget' ],
	'core/cover': [ 'url', 'id', 'alt', 'focalPoint' ],
	'core/video': [ 'src', 'id', 'poster', 'caption' ],
};

/**
 * The attributes a rebuilt block takes: a layout block the source's, a
 * content block the source's but its own content, any other block its own.
 *
 * @param {Object} source  The source block.
 * @param {Object} partner The sibling's block of the same name and occurrence.
 * @return {Object} The attributes.
 */
function rebuiltAttributes( source, partner ) {
	if ( LAYOUT_BLOCKS.includes( source.name ) ) {
		return { ...source.attributes };
	}
	const content = CONTENT_ATTRIBUTES[ source.name ];
	if ( ! content ) {
		return { ...partner.attributes };
	}
	const attributes = { ...source.attributes };
	for ( const key of content ) {
		delete attributes[ key ];
		if ( undefined !== partner.attributes[ key ] ) {
			attributes[ key ] = partner.attributes[ key ];
		}
	}
	return attributes;
}

/**
 * One level of a sibling slide rebuilt to the source's: the source's
 * blocks, in its order, each paired with the sibling's block of the same
 * name and occurrence; a source block with no partner is copied whole, and
 * a sibling block with no partner is dropped.
 *
 * @param {Object[]} sources  The source level's blocks.
 * @param {Object[]} siblings The sibling level's blocks.
 * @return {Object[]} The rebuilt level.
 */
function rebuildLevel( sources, siblings ) {
	const pool = {};
	for ( const block of siblings ) {
		if ( ! pool[ block.name ] ) {
			pool[ block.name ] = [];
		}
		pool[ block.name ].push( block );
	}
	const seen = {};
	return sources.map( ( source ) => {
		const occurrence = seen[ source.name ] ?? 0;
		seen[ source.name ] = occurrence + 1;
		const partner = pool[ source.name ]?.[ occurrence ];
		if ( ! partner ) {
			return cloneBlock( source );
		}
		return createBlock(
			source.name,
			rebuiltAttributes( source, partner ),
			rebuildLevel( source.innerBlocks ?? [], partner.innerBlocks ?? [] )
		);
	} );
}

/**
 * The slide's editor.
 *
 * @param {Object} props          The block's props.
 * @param {string} props.clientId The slide.
 * @return {Element} The editor.
 */
export default function Edit( { clientId } ) {
	const blockProps = useBlockProps( {
		className: 'blocklane-carousel-slide',
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps );
	const canApply = useSelect(
		( select ) => {
			const {
				getBlockRootClientId,
				getBlockName,
				getBlockAttributes,
				getBlockCount,
			} = select( blockEditorStore );
			const trackId = getBlockRootClientId( clientId );
			if ( ! trackId || TRACK !== getBlockName( trackId ) ) {
				return false;
			}
			return (
				'dynamic' !== getBlockAttributes( trackId )?.sourceType &&
				getBlockCount( trackId ) > 1
			);
		},
		[ clientId ]
	);
	const registry = useRegistry();

	/**
	 * Rebuild every other slide of the track to match this one, as one
	 * change.
	 */
	const applyLayout = () => {
		const { getBlock, getBlockOrder, getBlockRootClientId } =
			registry.select( blockEditorStore );
		const source = getBlock( clientId );
		const trackId = getBlockRootClientId( clientId );
		if ( ! source || ! trackId ) {
			return;
		}
		const { replaceInnerBlocks } = registry.dispatch( blockEditorStore );
		registry.batch( () => {
			for ( const id of getBlockOrder( trackId ) ) {
				const sibling = id === clientId ? null : getBlock( id );
				if ( sibling && SLIDE === sibling.name ) {
					replaceInnerBlocks(
						id,
						rebuildLevel( source.innerBlocks, sibling.innerBlocks ),
						false
					);
				}
			}
		} );
	};

	return (
		<>
			{ canApply && (
				<InspectorControls>
					<PanelBody title={ __( 'Slide Layout', 'blocklane' ) }>
						<p>
							{ __(
								'Rebuild every other slide to match this slide’s layout. Each slide keeps its own text, images, and links.',
								'blocklane'
							) }
						</p>
						<Button
							__next40pxDefaultSize
							variant="secondary"
							onClick={ applyLayout }
						>
							{ __( 'Apply layout to all slides', 'blocklane' ) }
						</Button>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...innerBlocksProps } />
		</>
	);
}
