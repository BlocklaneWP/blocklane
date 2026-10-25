/**
 * The track's editor: the viewport the page draws, its slides (static) or
 * a preview of the posts its query returns (dynamic), and the warning a
 * second track in one carousel shows.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BlockContextProvider,
	useBlockProps,
	useInnerBlocksProps,
	Warning,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import { Button, Notice, Spinner } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import TrackInspector, {
	buildPreviewArgs,
	isPlainObject,
	numberOr,
	queryableTypes,
	queryPostType,
	useCurrentPostId,
	useDeviceType,
	useQueryTypes,
	useTaxonomies,
} from './inspector';

const TRACK = 'blocklane/carousel-track';
const SLIDE = 'blocklane/slide';

/** Only slides may be added to a static track. */
const SLIDES_ONLY = [ SLIDE ];

/** Nothing may be added to a dynamic track's template. */
const NOTHING = [];

const LOADING = { status: 'loading' };
const EMPTY = { status: 'empty' };

/**
 * The slides per view at a device preview.
 *
 * @param {Object} attributes The track's attributes.
 * @param {string} device     'Desktop', 'Tablet' or 'Mobile'.
 * @return {number} The slides per view.
 */
function perViewAt( attributes, device ) {
	const desktop = numberOr( attributes.slidesPerView, 3 );
	if ( 'Tablet' === device ) {
		return numberOr( attributes.tabletSlidesPerView, desktop );
	}
	if ( 'Mobile' === device ) {
		return numberOr( attributes.mobileSlidesPerView, 1 );
	}
	return desktop;
}

/**
 * A stored spacing value as CSS: a spacing preset becomes its custom
 * property, anything else is used as it is.
 *
 * @param {string} value The stored value.
 * @return {string} The CSS value.
 */
function spacingToCss( value ) {
	if ( ! value.includes( 'var:preset|spacing|' ) ) {
		return value;
	}
	const slug = value
		.slice( value.lastIndexOf( '|' ) + 1 )
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /[^A-Za-z0-9]+/g, '-' )
		.toLowerCase()
		.replace( /^-+|-+$/g, '' );
	return `var(--wp--preset--spacing--${ slug })`;
}

/**
 * The gap between slides, from the block gap, or null when it is not set.
 *
 * @param {Object} attributes The track's attributes.
 * @return {string|null} The CSS value.
 */
function slideGap( attributes ) {
	let gap = attributes.style?.spacing?.blockGap;
	if ( isPlainObject( gap ) ) {
		gap = gap.left || gap.top;
	}
	return 'string' === typeof gap && '' !== gap ? spacingToCss( gap ) : null;
}

/**
 * The classes the front end gives the track's element.
 *
 * @param {Object} attributes The track's attributes.
 * @return {string} The classes.
 */
function viewportClasses( attributes ) {
	const classes = [ 'blocklane-carousel-viewport' ];
	if ( attributes.overflowVisible ) {
		classes.push( 'blocklane-carousel-overflow-visible' );
	}
	if ( attributes.fadeOut ) {
		classes.push( 'blocklane-carousel-fade-out' );
		if ( 'end' === attributes.fadeOutEdges ) {
			classes.push( 'blocklane-carousel-fade-out--end' );
		}
	}
	if ( true === attributes.rtl ) {
		classes.push( 'blocklane-carousel-rtl' );
	}
	return classes.join( ' ' );
}

/**
 * The custom properties the front end gives the track's element.
 *
 * @param {Object} attributes The track's attributes.
 * @param {string} device     The device preview.
 * @return {Object} The inline style.
 */
function viewportStyle( attributes, device ) {
	const perView = perViewAt( attributes, device );
	const style = { '--blocklane-carousel-per-view': String( perView ) };
	const gap = slideGap( attributes );
	if ( gap ) {
		style[ '--blocklane-carousel-gap' ] = gap;
	}
	const maxWidth = numberOr( attributes.slideMaxWidth, null );
	if ( null !== maxWidth ) {
		style[ '--blocklane-carousel-slide-max-width' ] = `${ maxWidth }px`;
	}
	return style;
}

/**
 * A new slide holding one empty paragraph.
 *
 * @return {Object} The block.
 */
function emptySlide() {
	return createBlock( SLIDE, {}, [ createBlock( 'core/paragraph' ) ] );
}

/**
 * The template slide a dynamic track starts from: the post's featured
 * image, title and excerpt.
 *
 * @return {Object} The block.
 */
function templateSlide() {
	return createBlock( SLIDE, {}, [
		createBlock( 'core/post-featured-image' ),
		createBlock( 'core/post-title' ),
		createBlock( 'core/post-excerpt' ),
	] );
}

/**
 * The buttons after the last static slide: add one, or copy the last.
 *
 * @param {Object} props          The props.
 * @param {string} props.clientId The track.
 * @return {Element} The buttons.
 */
function SlideButtons( { clientId } ) {
	const lastSlideId = useSelect(
		( select ) => {
			const order = select( blockEditorStore ).getBlockOrder( clientId );
			return order.length ? order[ order.length - 1 ] : null;
		},
		[ clientId ]
	);
	const registry = useRegistry();
	const { insertBlock } = useDispatch( blockEditorStore );

	/**
	 * Append a slide holding one empty paragraph.
	 */
	const addSlide = () => {
		insertBlock( emptySlide(), undefined, clientId, false );
	};

	/**
	 * Append a copy of the last slide.
	 */
	const duplicateLast = () => {
		const { getBlock } = registry.select( blockEditorStore );
		const last = lastSlideId ? getBlock( lastSlideId ) : null;
		if ( last ) {
			insertBlock( cloneBlock( last ), undefined, clientId, false );
		}
	};

	return (
		<div className="blocklane-carousel-slide-actions">
			<Button
				__next40pxDefaultSize
				variant="secondary"
				onClick={ addSlide }
			>
				{ __( 'Add Slide', 'blocklane' ) }
			</Button>
			{ lastSlideId && (
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					onClick={ duplicateLast }
				>
					{ __( 'Duplicate Last Slide', 'blocklane' ) }
				</Button>
			) }
		</div>
	);
}

/**
 * A static track's slides, in one row, with the buttons after them.
 *
 * @param {Object} props          The props.
 * @param {string} props.clientId The track.
 * @return {Element} The slides' container.
 */
function StaticSlides( { clientId } ) {
	const renderAppender = useCallback(
		() => <SlideButtons clientId={ clientId } />,
		[ clientId ]
	);
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'blocklane-carousel-container' },
		{
			allowedBlocks: SLIDES_ONLY,
			orientation: 'horizontal',
			renderAppender,
		}
	);
	return <div { ...innerBlocksProps } />;
}

/**
 * A dynamic track's editable template: its slides, of which the first is
 * shown.
 *
 * @return {Element} The template's container.
 */
function TemplateSlides() {
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'blocklane-carousel-template' },
		{ allowedBlocks: NOTHING, renderAppender: false }
	);
	return <div { ...innerBlocksProps } />;
}

/**
 * One post's preview: activating it moves the editable template there.
 *
 * @param {Object}                          props            The props.
 * @param {Object}                          props.post       The post.
 * @param {(...args: unknown[]) => unknown} props.onActivate Makes the post the active one.
 * @return {Element} The preview.
 */
function PostPreview( { post, onActivate } ) {
	const title =
		'string' === typeof post?.title?.raw
			? post.title.raw
			: decodeEntities( String( post?.title?.rendered ?? '' ) );
	return (
		<div
			className="blocklane-carousel-item blocklane-carousel-preview"
			role="button"
			tabIndex={ 0 }
			aria-label={ __( 'Edit this slide design', 'blocklane' ) }
			onClick={ onActivate }
			onKeyDown={ ( event ) => {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					onActivate();
				}
			} }
		>
			<span className="blocklane-carousel-preview-title">{ title }</span>
		</div>
	);
}

/**
 * The posts a dynamic track's query previews, as the page would show them.
 *
 * @param {unknown} query The stored query.
 * @return {Object} { status: 'loading' | 'empty' | 'ready', posts, postType }.
 */
function usePreviewPosts( query ) {
	const postType = queryPostType( query );
	const currentId = useCurrentPostId();
	const { types, typesResolved } = useQueryTypes();
	const { taxonomies, taxonomiesResolved: taxonomiesReady } = useTaxonomies();
	const canQuery = useMemo(
		() => queryableTypes( types ).some( ( { slug } ) => slug === postType ),
		[ types, postType ]
	);
	const args = useMemo(
		() =>
			canQuery && taxonomiesReady
				? buildPreviewArgs( query, postType, taxonomies, currentId )
				: null,
		[ canQuery, taxonomiesReady, query, postType, taxonomies, currentId ]
	);
	const { posts, postsResolved } = useSelect(
		( select ) => {
			if ( ! args ) {
				return { posts: null, postsResolved: false };
			}
			const { getEntityRecords, hasFinishedResolution } =
				select( coreStore );
			return {
				posts: getEntityRecords( 'postType', postType, args ),
				postsResolved: hasFinishedResolution( 'getEntityRecords', [
					'postType',
					postType,
					args,
				] ),
			};
		},
		[ args, postType ]
	);
	if ( ! typesResolved ) {
		return LOADING;
	}
	if ( ! canQuery ) {
		return EMPTY;
	}
	if ( ! taxonomiesReady || ! postsResolved ) {
		return LOADING;
	}
	if ( ! Array.isArray( posts ) || ! posts.length ) {
		return EMPTY;
	}
	return { status: 'ready', posts, postType };
}

/**
 * A dynamic track's canvas: one item per post, the active one holding the
 * editable template in that post's context.
 *
 * @param {Object}  props       The props.
 * @param {unknown} props.query The stored query.
 * @return {Element} The preview.
 */
function DynamicSlides( { query } ) {
	const preview = usePreviewPosts( query );
	const [ activeId, setActiveId ] = useState( null );
	const posts = 'ready' === preview.status ? preview.posts : null;
	const activeGone =
		null !== activeId &&
		!! posts &&
		! posts.some( ( post ) => post.id === activeId );

	useEffect( () => {
		if ( activeGone ) {
			setActiveId( null );
		}
	}, [ activeGone ] );

	if ( 'loading' === preview.status ) {
		return (
			<div className="blocklane-carousel-container">
				<Spinner />
			</div>
		);
	}
	if ( ! posts ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'No posts found. Adjust the query settings.',
					'blocklane'
				) }
			</Notice>
		);
	}
	const active = activeGone || null === activeId ? posts[ 0 ].id : activeId;
	return (
		<div className="blocklane-carousel-container">
			{ posts.map( ( post ) =>
				post.id === active ? (
					<div
						key={ post.id }
						className="blocklane-carousel-item is-template"
					>
						<BlockContextProvider
							value={ {
								postId: post.id,
								postType: preview.postType,
							} }
						>
							<TemplateSlides />
						</BlockContextProvider>
					</div>
				) : (
					<PostPreview
						key={ post.id }
						post={ post }
						onActivate={ () => setActiveId( post.id ) }
					/>
				)
			) }
		</div>
	);
}

/**
 * A second track in one carousel: a warning, and the way to remove it.
 *
 * @param {Object} props            The props.
 * @param {Object} props.blockProps The track element's props.
 * @param {string} props.clientId   The track.
 * @return {Element} The warning.
 */
function DuplicateTrack( { blockProps, clientId } ) {
	const registry = useRegistry();

	/**
	 * Remove this track, lifting its removal lock first, as one change.
	 */
	const remove = () => {
		const { getBlockAttributes } = registry.select( blockEditorStore );
		const { updateBlockAttributes, removeBlock } =
			registry.dispatch( blockEditorStore );
		const lock = getBlockAttributes( clientId )?.lock;
		registry.batch( () => {
			updateBlockAttributes( clientId, {
				lock: {
					...( isPlainObject( lock ) ? lock : {} ),
					remove: false,
				},
			} );
			removeBlock( clientId, false );
		} );
	};

	return (
		<div { ...blockProps }>
			<Warning
				actions={ [
					<Button
						key="remove"
						__next40pxDefaultSize
						variant="primary"
						onClick={ remove }
					>
						{ __( 'Remove duplicate track', 'blocklane' ) }
					</Button>,
				] }
			>
				{ __(
					'A carousel can only have one track. This duplicate will not receive the carousel controls.',
					'blocklane'
				) }
			</Warning>
		</div>
	);
}

/**
 * The carousel's track: its canvas and its settings.
 *
 * @param {Object}                       props               The props.
 * @param {Object}                       props.attributes    The track's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Stores a change to them.
 * @param {Object}                       props.blockProps    The track element's props.
 * @param {string}                       props.clientId      The track.
 * @return {Element} The track.
 */
function TrackCanvas( { attributes, setAttributes, blockProps, clientId } ) {
	const isDynamic = 'dynamic' === attributes.sourceType;
	const slideCount = useSelect(
		( select ) => select( blockEditorStore ).getBlockCount( clientId ),
		[ clientId ]
	);
	const {
		replaceInnerBlocks,
		__unstableMarkNextChangeAsNotPersistent: markNextChangeAsNotPersistent,
	} = useDispatch( blockEditorStore );

	// A dynamic track with no slide gets its template; the seeding is not an
	// author's change, so it is neither an undo step nor an unsaved edit.
	useEffect( () => {
		if ( isDynamic && 0 === slideCount ) {
			markNextChangeAsNotPersistent();
			replaceInnerBlocks( clientId, [ templateSlide() ], false );
		}
	}, [
		isDynamic,
		slideCount,
		clientId,
		markNextChangeAsNotPersistent,
		replaceInnerBlocks,
	] );

	return (
		<>
			<TrackInspector
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<div { ...blockProps }>
				{ isDynamic ? (
					<DynamicSlides query={ attributes.query } />
				) : (
					<StaticSlides clientId={ clientId } />
				) }
			</div>
		</>
	);
}

/**
 * The track's editor.
 *
 * @param {Object}                       props               The block's props.
 * @param {Object}                       props.attributes    The track's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Stores a change to them.
 * @param {string}                       props.clientId      The track.
 * @return {Element} The editor.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const device = useDeviceType();
	const isDuplicate = useSelect(
		( select ) => {
			const { getBlockRootClientId, getBlockOrder, getBlockName } =
				select( blockEditorStore );
			const parent = getBlockRootClientId( clientId );
			const firstTrack = getBlockOrder( parent || '' ).find(
				( id ) => TRACK === getBlockName( id )
			);
			return undefined !== firstTrack && firstTrack !== clientId;
		},
		[ clientId ]
	);
	const blockProps = useBlockProps( {
		className: viewportClasses( attributes ),
		style: viewportStyle( attributes, device ),
	} );
	if ( isDuplicate ) {
		return (
			<DuplicateTrack blockProps={ blockProps } clientId={ clientId } />
		);
	}
	return (
		<TrackCanvas
			attributes={ attributes }
			setAttributes={ setAttributes }
			blockProps={ blockProps }
			clientId={ clientId }
		/>
	);
}
