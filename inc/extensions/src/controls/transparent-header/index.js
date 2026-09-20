/**
 * Transparent Header
 *
 * Overlay the header on the first section of the page with its background
 * stripped while at the top; with "Solid on scroll" (the default) the header
 * stays pinned and its own background returns once the page scrolls past it.
 *
 * The controls live in their own ToolsPanel in the styles inspector group
 * (NOT core's Position group — see TransparentHeaderControls) and are exposed
 * on the top-level groups of a header template part, so the feature can't be
 * mis-applied to inner groups. The server overlays whichever group carries
 * the attribute, falling back to the part's first group when a page override
 * lights a header that is off — the canvas mirror follows the same choice
 * (see headerTargetClientId). Attributes register unconditionally so saved
 * content keeps validating when the extension is off; the UI and the canvas
 * classes are gated by the enabled flag. The front-end classes are added at
 * render (loader/transparent-header/).
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	useSelect,
	useDispatch,
	createReduxStore,
	register,
} from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';
import {
	SelectControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';
import DimensionControl from '../../components/dimension-control';
import useToolsPanelDropdownMenuProps from '../../../../shared/use-tools-panel-dropdown-menu-props';
import { isInHeaderArea } from '../../components/is-in-header';
import { presetColorToCss } from '../../components/preset-color';
import TransparentHeaderColors from './colors-controls';
// Per-block transparent-state options (navigation, site title, site logo) —
// spec 2026-08-20-transparent-header-block-extensions.
import './blocks';
import schema from './attributes.json';

const isEnabled =
	window.blocklaneProExtensions?.enabled?.[ 'transparent-header' ] ?? true;

/**
 * The overlaid header's background mode. Mirror of PHP's
 * blocklane_pro_transparent_header_background() — canvas and front end must
 * not be able to disagree about what mode a header is in.
 *
 * The legacy mapping is the load-bearing line: "Solid on scroll: off" meant
 * overlaid and never solidified, so it reads as 'transparent', NOT as the new
 * 'solid'. See the PHP docblock for why that distinction matters to already-
 * published headers.
 *
 * Exported for advanced-group, which needs the same answer to decide whether
 * its sticky controls are live on this group.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} '' (transparent, solid on scroll), 'transparent' or 'solid'.
 */
export function thBackground( attributes ) {
	const mode = attributes.blocklaneProThBackground ?? '';
	if ( 'transparent' === mode || 'solid' === mode ) {
		return mode;
	}
	if ( attributes.blocklaneProTransparentSolidOnScroll === false ) {
		return 'transparent';
	}
	return '';
}

/**
 * The header group's own attributes: group 0 of attributes.json (the one
 * source the PHP registrar renders too; the groups after it are the per-block
 * rosters ./blocks.js registers). Prefixed (like blocklaneProMaxWidth) because
 * "transparentHeader" is exactly what core would name a future header support
 * — a collision there would corrupt saves.
 *
 * - blocklaneProThBackground: three background modes for an overlaid header —
 *   '' (transparent at the top, solid once scrolled — the shipped default),
 *   'transparent' (never solidifies) and 'solid' (always painted with the
 *   group's own background: the floating bar, out of flow but never
 *   see-through). Only serialized when the author moves off the default.
 * - blocklaneProTransparentSolidOnScroll: RETIRED by blocklaneProThBackground
 *   — read-never-write, see thBackground().
 * - blocklaneProTransparentBackground: the transparent-state background/scrim,
 *   applied only while `is-th-transparent` is on; the solid state stays the
 *   group's own core colors. Palette picks store as var:preset|color|{slug}.
 * - blocklaneProTransparentTextColor, blocklaneProTransparentLogo: RETIRED
 *   slots, registered for saved-content validation only (the
 *   blocklane_pro_solid_header pattern: read-never-write, no bulk migration).
 *   Text color moved to the blocks that carry text and the logo treatment to
 *   core/site-logo (./blocks.js, spec 2026-08-20-transparent-header-block-
 *   extensions); nothing reads these at render any more, and reset/toggle-off
 *   still clears them from saved content.
 */
const [ HEADER_GROUP ] = schema.groups;
const ATTRIBUTES = HEADER_GROUP.attributes;

/**
 * Ephemeral canvas-only preview state per client id: '' (context default —
 * transparent in a template, solid in part isolation), 'top' (transparent)
 * or 'solid'. Without it neither state is previewable non-destructively:
 * toggling the overlay off clears every sub-attribute by design, and in
 * isolation the transparent state needs an explicit opt-in because the bare
 * white canvas would render its white chrome invisible. Never serialized;
 * resets per session.
 */
const TH_PREVIEW_STORE = 'blocklane-pro/th-preview';
register(
	createReduxStore( TH_PREVIEW_STORE, {
		reducer: ( state = {}, action ) =>
			action.type === 'SET_TH_PREVIEW'
				? { ...state, [ action.clientId ]: action.mode }
				: state,
		actions: {
			setPreview: ( clientId, mode ) => ( {
				type: 'SET_TH_PREVIEW',
				clientId,
				mode,
			} ),
		},
		selectors: {
			getPreview: ( state, clientId ) => state[ clientId ] || '',
		},
	} )
);

/**
 * Register the attributes. Unconditional so saved content keeps validating
 * even when the extension is disabled.
 */
addFilter(
	'blocks.registerBlockType',
	'blocklane-pro/transparent-header/attributes',
	( settings ) => {
		if ( ! HEADER_GROUP.blocks.includes( settings.name ) ) {
			return settings;
		}
		return {
			...settings,
			attributes: {
				...settings.attributes,
				...ATTRIBUTES,
			},
		};
	}
);

/**
 * Whether this group is a top-level group of a header template part — the
 * blocks the transparent-header toggles are exposed on. A header part can
 * carry several (an announcement bar above the header row), and the toggle
 * works on any of them: the server overlays the one that carries the
 * attribute. Pattern and synced-pattern wrappers (the theme's header content
 * often lives behind a reference) are climbed past, matching how the server
 * sees through them; the remaining parent must be the header template part
 * itself, or the editor root when the part is edited in isolation.
 *
 * @param {Function} select   Registry select (from useSelect).
 * @param {string}   clientId Block client id.
 * @return {boolean} Whether to expose the controls.
 */
function isHeaderOuterGroup( select, clientId ) {
	if ( ! isInHeaderArea( select, clientId ) ) {
		return false;
	}

	const { getBlockRootClientId, getBlockName } =
		select( 'core/block-editor' );

	let rootId = getBlockRootClientId( clientId );
	while (
		rootId &&
		( getBlockName( rootId ) === 'core/pattern' ||
			getBlockName( rootId ) === 'core/block' )
	) {
		rootId = getBlockRootClientId( rootId );
	}

	return ! rootId || getBlockName( rootId ) === 'core/template-part';
}

/**
 * The client id of the group the overlay targets in the header part around
 * `clientId`: the first group (document order) carrying the attribute, or the
 * part's first group when none does. Mirrors the server's target resolver
 * exactly — the canvas must light the group the front end will actually
 * overlay, and only that one, or the two disagree: keyed off each group's own
 * attribute, a page override of "on" would light every group in the part.
 *
 * Exported so advanced-group's sticky gate can ask the SAME question the
 * server does. Keeping two copies of the rule is how the editor and the front
 * end drift apart.
 *
 * @param {Function} select   Registry select (from useSelect).
 * @param {string}   clientId Block client id inside the part.
 * @return {?string} The target group's client id, or null.
 */
export function headerTargetClientId( select, clientId ) {
	const {
		getBlockParentsByBlockName,
		getBlockOrder,
		getBlockName,
		getBlockAttributes,
	} = select( 'core/block-editor' );

	// Outermost enclosing part (parents come top-down); the editor root when
	// the part is edited in isolation.
	const parts = getBlockParentsByBlockName( clientId, 'core/template-part' );
	const rootId = parts.length ? parts[ 0 ] : '';

	let first = null;
	const find = ( ids ) => {
		for ( const id of ids ) {
			if ( getBlockName( id ) === 'core/group' ) {
				if ( ! first ) {
					first = id;
				}
				if ( getBlockAttributes( id )?.blocklaneProTransparentHeader ) {
					return id;
				}
			}
			const found = find( getBlockOrder( id ) );
			if ( found ) {
				return found;
			}
		}
		return null;
	};

	return find( getBlockOrder( rootId ) ) || first;
}

/**
 * The transparent-header controls, in their own ToolsPanel.
 *
 * Deliberately NOT filled into core's Position group. That group is one
 * ToolsPanelItem wrapping a single slot, so anything added lands inside that
 * item as a plain sibling of core's own position select: no spacing owner (the
 * controls butt straight against the Sticky dropdown), no ⋮ entry, no reset,
 * and a nested ToolsPanelItem can never register because the group passes no
 * panelId. Owning the panel gives all of that back, and the feature is
 * substantial enough — three settings that reshape how the header renders —
 * to read as its own thing rather than an appendix to Position.
 *
 * It sits in the styles group so it lands next to Position, which is the
 * setting it interacts with.
 *
 * @param {Object}   props
 * @param {string}   props.clientId      Block client id (panel identity).
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 */
function TransparentHeaderControls( { clientId, attributes, setAttributes } ) {
	const {
		blocklaneProTransparentHeader: transparent,
		blocklaneProTransparentTopOffset: topOffset,
		blocklaneProTransparentZIndex: zIndex,
	} = attributes;
	const background = thBackground( attributes );
	// Solid is the one mode that keeps advanced-group's sticky and hide-on-
	// scroll working, because it leaves the group `position: absolute` for the
	// wrapper to pin. Say so where the choice is made rather than leaving the
	// author to discover which combinations are live.
	const backgroundHelp =
		'solid' === background
			? __(
					'The header keeps its own background and stays out of the flow, so the section below starts at the top of the page. Sticky and hide-on-scroll on this group stay available.',
					'blocklane'
				)
			: __(
					'How the overlaid header paints at the top of the page. Solid on scroll brings its own background back — set one on this group — once the page scrolls past it.',
					'blocklane'
				);
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const previewMode = useSelect(
		( select ) => select( TH_PREVIEW_STORE ).getPreview( clientId ),
		[ clientId ]
	);
	// The unset default depends on context (the mirror's rule): transparent
	// in a template, solid in part isolation — the toggle must show the
	// state actually painted, not a stale label.
	const inTemplateContext = useSelect(
		( select ) =>
			select( 'core/block-editor' ).getBlockParentsByBlockName(
				clientId,
				'core/template-part'
			).length > 0,
		[ clientId ]
	);
	const effectivePreview =
		previewMode || ( inTemplateContext ? 'top' : 'solid' );
	const { setPreview } = useDispatch( TH_PREVIEW_STORE );

	return (
		<InspectorControls group="styles">
			<ToolsPanel
				label={ __( 'Transparent Header', 'blocklane' ) }
				resetAll={ () =>
					setAttributes( {
						blocklaneProTransparentHeader: undefined,
						blocklaneProThBackground: undefined,
						blocklaneProTransparentSolidOnScroll: undefined,
						blocklaneProTransparentTopOffset: undefined,
						blocklaneProTransparentZIndex: undefined,
						blocklaneProTransparentTextColor: undefined,
						blocklaneProTransparentBackground: undefined,
						blocklaneProTransparentLogo: undefined,
					} )
				}
				panelId={ clientId }
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					isShownByDefault
					label={ __( 'Overlay the header', 'blocklane' ) }
					hasValue={ () => !! transparent }
					onDeselect={ () =>
						setAttributes( {
							blocklaneProTransparentHeader: undefined,
							blocklaneProThBackground: undefined,
							blocklaneProTransparentSolidOnScroll: undefined,
							blocklaneProTransparentTopOffset: undefined,
							blocklaneProTransparentZIndex: undefined,
							blocklaneProTransparentTextColor: undefined,
							blocklaneProTransparentBackground: undefined,
							blocklaneProTransparentLogo: undefined,
						} )
					}
					panelId={ clientId }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Overlay the header', 'blocklane' ) }
						help={ __(
							'Sit the header on top of the first section with its background removed. Give that first section enough top padding to clear it. Replaces sticky positioning on this group.',
							'blocklane'
						) }
						checked={ !! transparent }
						onChange={ ( value ) =>
							setAttributes(
								value
									? { blocklaneProTransparentHeader: true }
									: {
											blocklaneProTransparentHeader:
												undefined,
											blocklaneProThBackground: undefined,
											blocklaneProTransparentSolidOnScroll:
												undefined,
											blocklaneProTransparentTopOffset:
												undefined,
											blocklaneProTransparentZIndex:
												undefined,
											blocklaneProTransparentTextColor:
												undefined,
											blocklaneProTransparentBackground:
												undefined,
											blocklaneProTransparentLogo:
												undefined,
										}
							)
						}
					/>
				</ToolsPanelItem>
				{ /* Withheld in Solid: that mode has no transparent state to
				preview, so the toggle would be a dead knob — the duplicate-
				control lesson from the 2026-07-26 polish round. */ }
				{ !! transparent && 'solid' !== background && (
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Preview', 'blocklane' ) }
						hasValue={ () => '' !== previewMode }
						onDeselect={ () => setPreview( clientId, '' ) }
						panelId={ clientId }
					>
						<ToggleGroupControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Preview', 'blocklane' ) }
							help={ __(
								'Canvas preview only — never saved. Solid shows the header as it renders once scrolled; previewing Transparent while editing the header alone adds a dark placeholder background so light text stays visible.',
								'blocklane'
							) }
							value={ effectivePreview }
							isBlock
							onChange={ ( value ) =>
								setPreview(
									clientId,
									'solid' === value ? 'solid' : 'top'
								)
							}
						>
							<ToggleGroupControlOption
								value="top"
								label={ __( 'Transparent', 'blocklane' ) }
							/>
							<ToggleGroupControlOption
								value="solid"
								label={ __( 'Solid', 'blocklane' ) }
							/>
						</ToggleGroupControl>
					</ToolsPanelItem>
				) }
				{ !! transparent && (
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Background', 'blocklane' ) }
						hasValue={ () => '' !== background }
						onDeselect={ () =>
							setAttributes( {
								blocklaneProThBackground: undefined,
								blocklaneProTransparentSolidOnScroll: undefined,
							} )
						}
						panelId={ clientId }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Background', 'blocklane' ) }
							help={ backgroundHelp }
							value={ background }
							options={ [
								{
									value: '',
									label: __(
										'Transparent, solid on scroll',
										'blocklane'
									),
								},
								{
									value: 'transparent',
									label: __(
										'Always transparent',
										'blocklane'
									),
								},
								{
									value: 'solid',
									label: __( 'Solid', 'blocklane' ),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									blocklaneProThBackground:
										'' === value ? undefined : value,
									// The boolean this select replaces is read
									// for display but never written. Clearing it
									// on any explicit choice keeps one source of
									// truth in the saved markup — otherwise a
									// legacy header edited here would carry both,
									// and the resolver's precedence would be the
									// only thing keeping them consistent.
									blocklaneProTransparentSolidOnScroll:
										undefined,
								} )
							}
						/>
					</ToolsPanelItem>
				) }
				{ !! transparent && (
					<ToolsPanelItem
						label={ __( 'Top offset', 'blocklane' ) }
						hasValue={ () => !! topOffset }
						onDeselect={ () =>
							setAttributes( {
								blocklaneProTransparentTopOffset: undefined,
							} )
						}
						panelId={ clientId }
					>
						<DimensionControl
							label={ __( 'Top offset', 'blocklane' ) }
							help={ __(
								'Float the header below the top edge. The admin bar is compensated automatically.',
								'blocklane'
							) }
							value={ topOffset || '0px' }
							onChange={ ( value ) =>
								setAttributes( {
									blocklaneProTransparentTopOffset:
										value && value !== '0px'
											? value
											: undefined,
								} )
							}
							units={ [ 'px', 'em', 'rem' ] }
							type="unit"
							max={ 500 }
						/>
					</ToolsPanelItem>
				) }
				{ !! transparent && (
					<ToolsPanelItem
						label={ __( 'Z-index', 'blocklane' ) }
						hasValue={ () =>
							zIndex !== undefined && zIndex !== null
						}
						onDeselect={ () =>
							setAttributes( {
								blocklaneProTransparentZIndex: undefined,
							} )
						}
						panelId={ clientId }
					>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Z-index', 'blocklane' ) }
							help={ __(
								'Stacking order against the rest of the page. Defaults to 100 — raise it to clear another fixed element, such as a third-party bar or chat widget.',
								'blocklane'
							) }
							value={ zIndex ?? '' }
							placeholder="100"
							onChange={ ( value ) =>
								setAttributes( {
									blocklaneProTransparentZIndex:
										value === '' || value === undefined
											? undefined
											: Number( value ),
								} )
							}
							step={ 1 }
						/>
					</ToolsPanelItem>
				) }
			</ToolsPanel>
		</InspectorControls>
	);
}

/**
 * Hooks-bearing edit wrapper, mounted only for core/group so its hooks run
 * unconditionally.
 *
 * @param {Object}   props
 * @param {Function} props.BlockEdit The original BlockEdit component.
 */
function TransparentHeaderEdit( { BlockEdit, ...props } ) {
	const { clientId, attributes, setAttributes } = props;

	const exposeControls = useSelect(
		( select ) => isHeaderOuterGroup( select, clientId ),
		[ clientId ]
	);

	return (
		<>
			<BlockEdit { ...props } />
			{ exposeControls && (
				<TransparentHeaderControls
					clientId={ clientId }
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			) }
			{ exposeControls && !! attributes.blocklaneProTransparentHeader && (
				<TransparentHeaderColors
					clientId={ clientId }
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			) }
		</>
	);
}

const withTransparentHeaderControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) =>
		props.name === 'core/group' ? (
			<TransparentHeaderEdit BlockEdit={ BlockEdit } { ...props } />
		) : (
			<BlockEdit { ...props } />
		),
	'withTransparentHeaderControls'
);

/**
 * Mirror the front-end state in the editor canvas so the template view is
 * truthful: the header overlays the first section, background stripped. In a
 * template context the positioning class comes along; editing the header part
 * in isolation only the background strip is mirrored — an absolute header in
 * an otherwise empty canvas would collapse the part editor to nothing.
 */
const withTransparentHeaderClasses = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		const { name, attributes, clientId } = props;
		const isGroup = name === 'core/group';
		const headerDefault = !! attributes?.blocklaneProTransparentHeader;

		// Mirror the per-page override, both directions: a page set to Solid
		// shows the header untouched even when the header is transparent, and
		// a page set to Transparent shows the overlay even when it is not.
		const pageOverride = useSelect(
			( select ) => {
				const editor = select( 'core/editor' );
				if (
					! isGroup ||
					! editor?.getCurrentPostType ||
					editor.getCurrentPostType() !== 'page'
				) {
					return '';
				}
				const meta = editor.getEditedPostAttribute?.( 'meta' );
				const stored = meta?.blocklane_pro_transparent_header;
				if ( stored === 'on' || stored === 'off' ) {
					return stored;
				}
				return meta?.blocklane_pro_solid_header ? 'off' : '';
			},
			[ isGroup ]
		);

		// Scoped to the server's target, not to each group's own attribute. A
		// page override of "on" has to be able to light up a header whose
		// attribute is off — the same reason the render path moved to the
		// template part — and a header part can hold several groups, of which
		// the server overlays exactly one. Keyed off the attribute alone, the
		// override would overlay every group on the page.
		const isTarget = useSelect(
			( select ) =>
				isGroup &&
				isInHeaderArea( select, clientId ) &&
				headerTargetClientId( select, clientId ) === clientId,
			[ isGroup, clientId ]
		);

		// If this group is the target it either carries the attribute or is
		// the positional fallback (nothing does), so its own attribute IS the
		// header default the page may override.
		let active = isTarget && headerDefault;
		if ( isTarget && pageOverride === 'on' ) {
			active = true;
		} else if ( pageOverride === 'off' ) {
			active = false;
		}

		const inTemplateContext = useSelect(
			( select ) =>
				isTarget &&
				select( 'core/block-editor' ).getBlockParentsByBlockName(
					clientId,
					'core/template-part'
				).length > 0,
			[ isTarget, clientId ]
		);

		// The ephemeral preview ('', 'top' or 'solid'; canvas-only, never
		// serialized). '' means the CONTEXT default: transparent in a
		// template (truthful — the header sits over the real hero), SOLID in
		// part isolation, where the canvas is bare white and the transparent
		// state's white chrome would be invisible white-on-white. Previewing
		// Transparent in isolation paints a dark placeholder backdrop
		// (editor.scss) behind the header so the chrome reads.
		const preview = useSelect(
			( select ) =>
				isGroup
					? select( TH_PREVIEW_STORE ).getPreview( clientId )
					: '',
			[ isGroup, clientId ]
		);
		// Two independent things to mirror, matching the render's own split:
		// the OVERLAY (out of flow, so the canvas shows the section below
		// starting at the top) and the TRANSPARENT STATE (background stripped).
		// The always-solid mode is overlaid but never transparent, so keying
		// both off one flag would draw it in flow here and out of flow on the
		// front end.
		//
		// Isolation keeps carrying no positioning — a bare part canvas has
		// nothing to overlay — which is why the overlay half wants
		// inTemplateContext and not `active` alone.
		const showOverlay = active && inTemplateContext;
		const showTransparent =
			active &&
			'solid' !== thBackground( attributes ) &&
			( 'top' === preview ||
				( 'solid' !== preview && inTemplateContext ) );

		if ( ! showTransparent && ! showOverlay ) {
			return <BlockListBlock { ...props } />;
		}

		const bgColor = showTransparent
			? presetColorToCss( attributes.blocklaneProTransparentBackground )
			: '';

		// Text color and logo treatment are per-block now (./blocks.js) — the
		// group mirror only carries the state class, the positioning class,
		// and the group-owned vars. The iso-preview class paints the dark
		// placeholder backdrop when Transparent is previewed in isolation.
		const className = [
			props.className,
			showTransparent && 'is-th-transparent',
			showOverlay && 'blocklane-pro-transparent-header',
			showTransparent &&
				! inTemplateContext &&
				'blocklane-pro-th-iso-preview',
		]
			.filter( Boolean )
			.join( ' ' );

		// Live vars so the canvas paints what the front end will: the Top
		// Offset, and the transparent-state background.
		const offset = attributes.blocklaneProTransparentTopOffset;
		const styleVars = {
			...( offset && offset !== '0px'
				? { '--blocklane-pro-th-offset': offset }
				: {} ),
			...( bgColor ? { '--blocklane-pro-th-bg': bgColor } : {} ),
		};
		const wrapperProps = Object.keys( styleVars ).length
			? {
					...props.wrapperProps,
					style: {
						...props.wrapperProps?.style,
						...styleVars,
					},
				}
			: props.wrapperProps;

		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ wrapperProps }
			/>
		);
	},
	'withTransparentHeaderClasses'
);

/**
 * The per-page override: a tri-state "Style" select in the page sidebar's
 * Header panel — Default (inherits the header's own setting), Transparent, or
 * Solid, so a page can override the header in either direction. Registered
 * whenever the extension is on; gating it on a header already using the
 * feature was tried and reverted, because it made the override one-way (see
 * the comment at registerPlugin below).
 */
function TransparentHeaderPagePanel() {
	const { postType, postId } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postType: editor?.getCurrentPostType?.(),
			postId: editor?.getCurrentPostId?.(),
		};
	}, [] );

	// The post id is passed explicitly. Without it useEntityProp binds to
	// whatever entity the surrounding provider holds, which is the page in the
	// post editor but can be the TEMPLATE in the Site Editor's page view — so
	// the edit lands on the wrong record, the page never goes dirty, and Save
	// stays disabled while the control looks like it worked.
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType || 'page',
		'meta',
		postId
	);

	if ( postType !== 'page' ) {
		return null;
	}

	const defaultOn =
		!! window.blocklaneProExtensions?.transparentHeader?.defaultOn;

	// Pages saved before the tri-state existed carry the boolean opt-out; show
	// them as Solid so the control reflects what the front end actually does.
	const stored = meta?.blocklane_pro_transparent_header;
	let value = '';
	if ( stored === 'on' || stored === 'off' ) {
		value = stored;
	} else if ( meta?.blocklane_pro_solid_header ) {
		value = 'off';
	}

	const set = ( next ) =>
		setMeta( {
			...meta,
			blocklane_pro_transparent_header: next,
			// Clear the legacy key once this page has an explicit choice, so
			// the two can never disagree.
			...( meta?.blocklane_pro_solid_header
				? { blocklane_pro_solid_header: false }
				: {} ),
		} );

	return (
		<PluginDocumentSettingPanel
			name="blocklane-pro-transparent-header"
			title={ __( 'Header', 'blocklane' ) }
		>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Style', 'blocklane' ) }
				value={ value }
				options={ [
					{
						value: '',
						// Names what Default resolves to, so the choice is not
						// a guess — the header's own setting is not visible
						// from here.
						label: defaultOn
							? __( 'Default – Transparent', 'blocklane' )
							: __( 'Default – Solid', 'blocklane' ),
					},
					{
						value: 'on',
						label: __( 'Transparent', 'blocklane' ),
					},
					{ value: 'off', label: __( 'Solid', 'blocklane' ) },
				] }
				onChange={ ( next ) => set( next ) }
				help={ __(
					'Default follows the header itself. Transparent or Solid overrides it for this page only.',
					'blocklane'
				) }
			/>
		</PluginDocumentSettingPanel>
	);
}

if ( isEnabled ) {
	addFilter(
		'editor.BlockEdit',
		'blocklane-pro/transparent-header/controls',
		withTransparentHeaderControls
	);
	addFilter(
		'editor.BlockListBlock',
		'blocklane-pro/transparent-header/classes',
		withTransparentHeaderClasses
	);

	// Registered whenever the extension is on. It used to be gated on a header
	// part already using the feature, which made the override one-way: a page
	// could opt out of an overlay, but no page could opt IN while the header
	// was off, because the control was not there to do it with.
	registerPlugin( 'blocklane-pro-transparent-header-page', {
		render: TransparentHeaderPagePanel,
	} );
}
