/**
 * The carousel's editor (the shell): its inner blocks, a placeholder that
 * offers the carousel's designs while it is empty, and its settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BlockPreview,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { cloneBlock, createBlock, parse } from '@wordpress/blocks';
import {
	Button,
	Modal,
	PanelBody,
	Placeholder,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { carouselIcon } from '../shared/icons';

const CAROUSEL = 'blocklane/carousel';

/** The attributes a design never owns: they stay as they were. */
const KEPT_ATTRIBUTES = [ 'metadata', 'lock', 'anchor', 'className' ];

/**
 * The first carousel block in a tree: a root block of that name, else the
 * first one nested.
 *
 * @param {Object[]} blocks The blocks.
 * @return {Object|null} The carousel block, or null.
 */
function findCarousel( blocks ) {
	const root = blocks.find( ( block ) => CAROUSEL === block.name );
	if ( root ) {
		return root;
	}
	for ( const block of blocks ) {
		const nested = findCarousel( block.innerBlocks ?? [] );
		if ( nested ) {
			return nested;
		}
	}
	return null;
}

/**
 * The blocks of a blank carousel: arrows, a track that cannot be removed
 * holding one slide, and dots.
 *
 * @return {Object[]} The blocks.
 */
function blankCarousel() {
	const slide = createBlock( 'blocklane/slide', {}, [
		createBlock( 'core/paragraph' ),
	] );
	const track = createBlock(
		'blocklane/carousel-track',
		{ lock: { move: false, remove: true } },
		[ slide ]
	);
	return [
		createBlock( 'blocklane/carousel-controls' ),
		track,
		createBlock( 'blocklane/carousel-controls', { navType: 'dots' } ),
	];
}

/**
 * The carousel's designs, each a button showing a preview and its title.
 *
 * @param {Object}                          props          The props.
 * @param {Object[]}                        props.designs  The designs: { name, title, blocks }.
 * @param {(...args: unknown[]) => unknown} props.onChoose Receives the chosen design.
 * @return {Element|null} The choices.
 */
function DesignChoices( { designs, onChoose } ) {
	if ( ! designs.length ) {
		return null;
	}
	return (
		<div className="blocklane-carousel-designs">
			{ designs.map( ( design ) => (
				<Button
					key={ design.name }
					className="blocklane-carousel-design"
					aria-label={ design.title }
					onClick={ () => onChoose( design ) }
				>
					<BlockPreview
						blocks={ design.blocks }
						viewportWidth={ 1200 }
					/>
					<span className="blocklane-carousel-design-title">
						{ design.title }
					</span>
				</Button>
			) ) }
		</div>
	);
}

/**
 * The shell's element, holding its inner blocks.
 *
 * @param {Object}  props           The props.
 * @param {boolean} props.centerNav Whether the arrows sit on the sides.
 * @return {Element} The element.
 */
function ShellContent( { centerNav } ) {
	const blockProps = useBlockProps( {
		className: centerNav ? 'blocklane-carousel-center-nav' : undefined,
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps );
	return <div { ...innerBlocksProps } />;
}

/**
 * The empty shell's placeholder: the designs, and Start blank.
 *
 * @param {Object}                          props              The props.
 * @param {Object[]}                        props.designs      The designs.
 * @param {(...args: unknown[]) => unknown} props.onChoose     Receives the chosen design.
 * @param {(...args: unknown[]) => unknown} props.onStartBlank Starts a blank carousel.
 * @return {Element} The placeholder.
 */
function ShellPlaceholder( { designs, onChoose, onStartBlank } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<Placeholder
				className="blocklane-carousel-placeholder"
				icon={ carouselIcon }
				label={ __( 'Carousel', 'blocklane' ) }
				instructions={ __(
					'Pick a design to start from, or start blank.',
					'blocklane'
				) }
			>
				<DesignChoices designs={ designs } onChoose={ onChoose } />
				<Button
					__next40pxDefaultSize
					variant="primary"
					onClick={ onStartBlank }
				>
					{ __( 'Start blank', 'blocklane' ) }
				</Button>
			</Placeholder>
		</div>
	);
}

/**
 * The shell's editor.
 *
 * @param {Object}                       props               The block's props.
 * @param {Object}                       props.attributes    The shell's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Stores a change to them.
 * @param {string}                       props.clientId      The shell.
 * @return {Element} The editor.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const [ isChoosing, setChoosing ] = useState( false );
	const { hasInnerBlocks, patterns } = useSelect(
		( select ) => {
			const store = select( blockEditorStore );
			return {
				hasInnerBlocks: store.getBlockCount( clientId ) > 0,
				patterns: store.getPatternsByBlockTypes(
					CAROUSEL,
					store.getBlockRootClientId( clientId )
				),
			};
		},
		[ clientId ]
	);
	const designs = useMemo(
		() =>
			( patterns ?? [] ).map( ( pattern ) => ( {
				name: pattern.name,
				title: pattern.title,
				blocks: pattern.blocks ?? parse( pattern.content ?? '' ),
			} ) ),
		[ patterns ]
	);
	const registry = useRegistry();
	const { updateBlockAttributes, replaceInnerBlocks } =
		useDispatch( blockEditorStore );

	/**
	 * Make a design's carousel this shell, as one change: its attributes
	 * (but the four a design never owns) and its inner blocks.
	 *
	 * @param {Object} design The design.
	 */
	const applyDesign = ( design ) => {
		const source = findCarousel( design.blocks ?? [] );
		if ( ! source ) {
			return;
		}
		const { getBlockAttributes } = registry.select( blockEditorStore );
		const current = getBlockAttributes( clientId ) ?? {};
		const keys = new Set( [
			...Object.keys( current ),
			...Object.keys( source.attributes ),
		] );
		const next = {};
		for ( const key of keys ) {
			next[ key ] = KEPT_ATTRIBUTES.includes( key )
				? current[ key ]
				: source.attributes[ key ];
		}
		registry.batch( () => {
			updateBlockAttributes( clientId, next );
			replaceInnerBlocks(
				clientId,
				source.innerBlocks.map( ( block ) => cloneBlock( block ) ),
				false
			);
		} );
	};

	/**
	 * Start a blank carousel, as one change.
	 */
	const startBlank = () => {
		registry.batch( () => {
			updateBlockAttributes( clientId, { align: 'wide' } );
			replaceInnerBlocks( clientId, blankCarousel(), false );
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Carousel', 'blocklane' ) }>
					<VStack spacing={ 4 } alignment="start">
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Centered arrows', 'blocklane' ) }
							help={ __(
								'Overlay arrow controls on the sides of the carousel and pagination below it.',
								'blocklane'
							) }
							checked={ !! attributes.centerNav }
							onChange={ ( centerNav ) =>
								setAttributes( { centerNav } )
							}
						/>
						<Button
							__next40pxDefaultSize
							variant="secondary"
							onClick={ () => setChoosing( true ) }
						>
							{ __( 'Change design', 'blocklane' ) }
						</Button>
					</VStack>
				</PanelBody>
			</InspectorControls>
			{ hasInnerBlocks ? (
				<ShellContent centerNav={ !! attributes.centerNav } />
			) : (
				<ShellPlaceholder
					designs={ designs }
					onChoose={ applyDesign }
					onStartBlank={ startBlank }
				/>
			) }
			{ isChoosing && (
				<Modal
					className="blocklane-carousel-design-modal"
					title={ __( 'Choose a carousel design', 'blocklane' ) }
					size="large"
					onRequestClose={ () => setChoosing( false ) }
				>
					<DesignChoices
						designs={ designs }
						onChoose={ ( design ) => {
							applyDesign( design );
							setChoosing( false );
						} }
					/>
					<Button
						__next40pxDefaultSize
						variant="secondary"
						onClick={ () => {
							startBlank();
							setChoosing( false );
						} }
					>
						{ __( 'Start blank', 'blocklane' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
