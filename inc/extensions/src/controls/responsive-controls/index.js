/**
 * Responsive Controls
 *
 * Since core 7.1, per-breakpoint styling is CORE's job: the editor's
 * "Responsive styles" mode (View menu) scopes every standard panel —
 * typography, spacing, dimensions, layout, text alignment — to the selected
 * viewport, storing overrides in the block's `style['@tablet']` /
 * `style['@mobile']` states. There is ONE breakpoint switcher: core's
 * Desktop/Tablet/Mobile device preview (available everywhere since 7.1,
 * template-part focus mode included) — every Blocklane extra keys off it via
 * the public device type, so our controls and core's viewport states always
 * agree.
 *
 * The Blocklane layer is what core has no property for:
 *  - **Display order** per breakpoint for flex-Row children (no `order` in
 *    core's style engine) — rendered into the LAYOUT group slot, which core
 *    also shows inside its responsive-state inspector.
 *  - **Max width** per breakpoint (no max-width in the style engine) — in the
 *    DIMENSIONS group slot, likewise present in both modes.
 *  - A device-scoped **Hide on Desktop/Tablet/Mobile** toggle — writing
 *    CORE's `metadata.blockVisibility.viewport` (core renders it; core's own
 *    hide-a-visible-block affordance is buried in the block's options menu) —
 *    in the LAYOUT slot under Display order, followed at Tablet/Mobile by the
 *    breakpoint-size hint with the inline admin editor. There is no separate
 *    Blocklane panel: everything lives in core's panels, scoped to the
 *    View-menu device.
 *
 * Order/maxWidth ride the blocklaneProResponsive attribute exactly as
 * before, emitted as CSS vars + marker classes consumed by the static
 * media-query stylesheet. Legacy blocklaneProResponsive keys (fontSize,
 * spacing, textAlign, justify/orientation, hidden, minHeight) migrate into
 * the core formats at parse time (blocks.getBlockAttributes); unmigrated
 * content is converted at render time by the server shim in
 * loader/responsive-controls.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	InspectorControls,
	HeightControl,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect, useDispatch, useRegistry } from '@wordpress/data';
import {
	Button,
	Icon,
	Dropdown,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { info } from '@wordpress/icons';
import {
	useState,
	useEffect,
	useRef,
	useMemo,
	createPortal,
} from '@wordpress/element';
import { useDeviceType, useBreakpoint } from './use-device-type';
import { RESPONSIVE_STORE } from './store';
import {
	updateResponsiveValue,
	resolvePresetValue,
	migrateResponsiveAttributes,
} from './responsive-utils';
import schema from './attributes.json';

// Check if extension is enabled.
const isEnabled =
	window.blocklaneProExtensions?.enabled?.[ 'responsive-controls' ] ?? true;

// Shipped defaults (the inline editor's "reset" target) + the settings page URL
// (localized from PHP). The live breakpoint sizes live in RESPONSIVE_STORE.
const DEFAULT_BREAKPOINTS = { tablet: 768, mobile: 480 };
const SETTINGS_URL = window.blocklaneProExtensions?.settingsUrl || '';
// Only admins (manage_options) may change the site-wide breakpoints inline.
const CAN_MANAGE_BREAKPOINTS =
	!! window.blocklaneProExtensions?.canManageBreakpoints;

// Reject CSS values that could inject extra declarations or break out of the
// editor <style> / inline style (mirrors the PHP $as_length guard). Anything
// with ; { } < > or quotes is dropped; var()/calc()/clamp()/<number><unit> pass.
const CSS_VALUE_UNSAFE = /[;{}<>"']/;
const safeCssValue = ( value ) =>
	typeof value === 'string' && ! CSS_VALUE_UNSAFE.test( value ) ? value : '';

/**
 * A virtual popover anchor at the enclosing ToolsPanel's left edge,
 * vertically level with the trigger — so popovers fly out to the LEFT of the
 * sidebar exactly like core's inspector dropdowns (color pickers, tools-panel
 * menus), instead of opening inside the narrow panel.
 * getBoundingClientRect reads the ref lazily (floating-ui calls it on every
 * reposition), so the panel/trigger only need to exist by the time it opens.
 *
 * @param {Object} ref Ref to the trigger element.
 * @return {Object} A floating-ui virtual element.
 */
function usePanelLeftAnchor( ref ) {
	return useMemo(
		() => ( {
			getBoundingClientRect() {
				const node = ref.current;
				const trigger = node?.getBoundingClientRect();
				if ( ! trigger ) {
					return new window.DOMRect();
				}
				const panel =
					node
						.closest( '.components-tools-panel' )
						?.getBoundingClientRect() || trigger;
				// Zero-width rect at the panel's left edge, on the trigger's
				// vertical line — left-start placement then flies the popover
				// out of the sidebar like core's inspector dropdowns, aligned
				// with the trigger's row regardless of where the (small)
				// trigger sits inside it.
				return new window.DOMRect(
					panel.left,
					trigger.top,
					0,
					trigger.height
				);
			},
			get ownerDocument() {
				return ref.current?.ownerDocument ?? document;
			},
		} ),
		[ ref ]
	);
}

/**
 * A small info glyph that opens a help Popover on click. Lets each control keep
 * a one-line label while the longer note lives in a popover, so the panel stays
 * compact (replaces inline `help` text). The toggle is a focusable <span>
 * (role="button") rather than a <button> so it's valid inside a control's
 * <label>, and it swallows the click/keypress so it never toggles the control
 * it annotates. Popover content portals out, so there's no nesting concern.
 *
 * @param {Object} props      Component props.
 * @param {string} props.text Help text shown in the popover.
 * @return {JSX.Element} The help glyph + popover.
 */
function HelpTip( { text } ) {
	const toggleRef = useRef( null );
	const anchor = usePanelLeftAnchor( toggleRef );
	const swallow = ( event ) => {
		event.preventDefault();
		event.stopPropagation();
	};

	return (
		<Dropdown
			className="blocklane-pro-help-tip"
			contentClassName="blocklane-pro-help-tip__popover"
			focusOnMount="container"
			// Fly out to the left of the sidebar like core's inspector
			// popovers (left-start + offset 36 mirrors core's LinkPicker /
			// color dropdowns), anchored to the panel edge so the small glyph
			// trigger doesn't matter. animate:false drops the framer-motion
			// scale transition, which otherwise leaves the box on a promoted
			// layer that renders the text slightly fuzzy.
			popoverProps={ {
				anchor,
				placement: 'left-start',
				offset: 36,
				shift: true,
				animate: false,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<span
					ref={ toggleRef }
					className="blocklane-pro-help-tip__toggle"
					role="button"
					tabIndex={ 0 }
					aria-label={ __( 'More information', 'blocklane' ) }
					aria-expanded={ isOpen }
					onClick={ ( event ) => {
						swallow( event );
						onToggle();
					} }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' || event.key === ' ' ) {
							swallow( event );
							onToggle();
						}
					} }
				>
					<Icon icon={ info } size={ 18 } />
				</span>
			) }
			renderContent={ () => (
				<p className="blocklane-pro-help-tip__text">{ text }</p>
			) }
		/>
	);
}

/**
 * Compose a control label with a trailing HelpTip.
 *
 * @param {string} label Visible label text.
 * @param {string} tip   Tooltip explanation.
 * @return {JSX.Element} A label node for a control's `label` prop.
 */
function labelWithTip( label, tip ) {
	return (
		<span className="blocklane-pro-label-with-tip">
			{ label }
			<HelpTip text={ tip } />
		</span>
	);
}

/**
 * Form body for the inline breakpoint editor. Edits only one device's
 * breakpoint (the other is sent unchanged), validated against the other so
 * mobile stays below tablet. Mirrors the server clamp (tablet 360–2000,
 * mobile 240–1600). The save writes both our option AND theme.json's
 * settings.viewport (the core 7.1 source of truth), so core's responsive
 * styles and visibility move with it.
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.device  Breakpoint being edited ('tablet' | 'mobile').
 * @param {Object}   props.current Current { tablet, mobile }.
 * @param {Function} props.onSaved Called with the saved { tablet, mobile }.
 * @return {JSX.Element} The form.
 */
function BreakpointsForm( { device, current, onSaved } ) {
	const isTablet = device === 'tablet';
	const [ value, setValue ] = useState(
		isTablet ? current.tablet : current.mobile
	);
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	const n = Number( value );
	// Keep mobile < tablet by validating against the other (fixed) bound.
	const invalid =
		! n || ( isTablet ? n <= current.mobile : n >= current.tablet );

	const save = () => {
		if ( invalid || saving ) {
			return;
		}
		setSaving( true );
		setError( '' );
		const data = isTablet
			? { tablet: n, mobile: current.mobile }
			: { tablet: current.tablet, mobile: n };
		apiFetch( {
			path: '/blocklane-pro/v1/breakpoints',
			method: 'POST',
			data,
		} )
			.then( ( res ) => {
				setSaving( false );
				onSaved( res?.breakpoints || data );
			} )
			.catch( ( e ) => {
				setSaving( false );
				setError(
					e?.message ||
						__( 'Could not save. Please try again.', 'blocklane' )
				);
			} );
	};

	return (
		<div className="blocklane-pro-bp-editor__form">
			<p className="blocklane-pro-bp-editor__title">
				{ isTablet
					? __( 'Tablet breakpoint', 'blocklane' )
					: __( 'Mobile breakpoint', 'blocklane' ) }
			</p>
			<p className="blocklane-pro-bp-editor__note">
				{ __(
					'Applies site-wide — including core responsive styles. Reload the editor to see core panels follow.',
					'blocklane'
				) }
			</p>
			<NumberControl
				label={
					isTablet
						? __( 'Tablet (px)', 'blocklane' )
						: __( 'Mobile (px)', 'blocklane' )
				}
				value={ value }
				min={ isTablet ? 360 : 240 }
				max={ isTablet ? 2000 : 1600 }
				onChange={ setValue }
				__next40pxDefaultSize
			/>
			{ invalid && (
				<p className="blocklane-pro-bp-editor__warn">
					{ isTablet
						? sprintf(
								/* translators: %d: mobile breakpoint width in px. */
								__(
									'Must be larger than the mobile breakpoint (%dpx).',
									'blocklane'
								),
								current.mobile
							)
						: sprintf(
								/* translators: %d: tablet breakpoint width in px. */
								__(
									'Must be smaller than the tablet breakpoint (%dpx).',
									'blocklane'
								),
								current.tablet
							) }
				</p>
			) }
			{ error && (
				<p className="blocklane-pro-bp-editor__warn">{ error }</p>
			) }
			<div className="blocklane-pro-bp-editor__actions">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ invalid || saving }
					__next40pxDefaultSize
				>
					{ __( 'Save', 'blocklane' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () =>
						setValue(
							isTablet
								? DEFAULT_BREAKPOINTS.tablet
								: DEFAULT_BREAKPOINTS.mobile
						)
					}
					__next40pxDefaultSize
				>
					{ __( 'Reset to default', 'blocklane' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * Inline editor for a responsive breakpoint, surfaced in the Responsive
 * panel so admins can tune it without leaving the editor. Renders an "Edit"
 * link that opens the form in a popover flying out left of the sidebar.
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.device  Breakpoint to edit ('tablet' | 'mobile').
 * @param {Object}   props.current Current { tablet, mobile }.
 * @param {Function} props.onSaved Called with the saved { tablet, mobile }.
 * @return {JSX.Element} The trigger + popover.
 */
function BreakpointsEditor( { device, current, onSaved } ) {
	const toggleRef = useRef( null );
	const anchor = usePanelLeftAnchor( toggleRef );
	return (
		<Dropdown
			className="blocklane-pro-bp-editor"
			contentClassName="blocklane-pro-bp-editor__popover"
			focusOnMount="firstElement"
			popoverProps={ {
				anchor,
				placement: 'left-start',
				offset: 36,
				shift: true,
				animate: false,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<button
					ref={ toggleRef }
					type="button"
					className="blocklane-pro-bp-editor__trigger"
					aria-expanded={ isOpen }
					onClick={ onToggle }
				>
					{ __( 'Edit', 'blocklane' ) }
				</button>
			) }
			renderContent={ ( { onClose } ) => (
				<BreakpointsForm
					device={ device }
					current={ current }
					onSaved={ ( next ) => {
						onSaved( next );
						onClose();
					} }
				/>
			) }
		/>
	);
}

// Blocks that get the Max width extra (mirrors the old dimensions-panel gate:
// blocks with dimensions.minHeight support).
const SUPPORTED_BLOCKS = [
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/quote',
	'core/button',
	'core/site-title',
	'core/group',
	'core/columns',
	'core/column',
	'core/cover',
	'core/buttons',
	'core/post-content',
];

// Blocks that can sit inside a flex Row and get the responsive Order control.
// Kept separate so header children (logo, nav, image) get only the order
// control.
const ORDER_BLOCKS = [
	'core/site-logo',
	'core/navigation',
	'core/image',
	'core/social-links',
	'core/search',
	'core/separator',
	'core/spacer',
	'core/buttons',
	'core/group',
	'core/paragraph',
	'core/heading',
	'core/site-title',
	'core/columns',
	'core/column',
	'core/list',
	'core/quote',
	'core/cover',
	'core/button',
];

// Blocks that carry the blocklaneProResponsive attribute + preview HOC: the
// attribute schema's block list (attributes.json — the one source the PHP
// registrar renders too), which is the union of the two UI rosters above. The
// rosters decide only which control shows; a block in a roster but not in the
// schema mounts nothing, so the two cannot write an unregistered attribute.
const RESPONSIVE_BLOCKS = schema.groups[ 0 ].blocks;

const DEVICE_LABELS = {
	desktop: __( 'Desktop', 'blocklane' ),
	tablet: __( 'Tablet', 'blocklane' ),
	mobile: __( 'Mobile', 'blocklane' ),
};

/**
 * Add the blocklaneProResponsive attribute (from attributes.json) to supported
 * blocks.
 */
addFilter(
	'blocks.registerBlockType',
	'blocklane-pro/responsive/add-attributes',
	( settings ) => {
		if ( ! RESPONSIVE_BLOCKS.includes( settings.name ) ) {
			return settings;
		}
		return {
			...settings,
			attributes: {
				...settings.attributes,
				...schema.groups[ 0 ].attributes,
			},
		};
	}
);

if ( isEnabled ) {
	import( './index.scss' );

	/**
	 * Parse-time migration: fold the legacy per-breakpoint bag into core's
	 * style states / block visibility so a re-save persists the core idiom.
	 * (The server shim converts unmigrated content at render time.)
	 */
	addFilter(
		'blocks.getBlockAttributes',
		'blocklane-pro/responsive/migrate-attributes',
		( blockAttributes, blockType ) =>
			RESPONSIVE_BLOCKS.includes( blockType.name )
				? migrateResponsiveAttributes( blockAttributes )
				: blockAttributes
	);

	// ─── Visibility (writes CORE's metadata.blockVisibility.viewport) ───

	/**
	 * Write (or clear) one device's hide flag in CORE's
	 * metadata.blockVisibility.viewport, pruning empty objects.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Attribute setter.
	 * @param {string}   device        'desktop' | 'tablet' | 'mobile'.
	 * @param {boolean}  hidden        Whether to hide on that device.
	 */
	const writeViewportHide = ( attributes, setAttributes, device, hidden ) => {
		const metadata = { ...( attributes.metadata ?? {} ) };
		const nextVisibility =
			typeof metadata.blockVisibility === 'object' &&
			metadata.blockVisibility !== null
				? { ...metadata.blockVisibility }
				: {};
		const nextViewport = { ...( nextVisibility.viewport ?? {} ) };
		if ( hidden ) {
			nextViewport[ device ] = false;
		} else {
			delete nextViewport[ device ];
		}
		if ( Object.keys( nextViewport ).length ) {
			nextVisibility.viewport = nextViewport;
		} else {
			delete nextVisibility.viewport;
		}
		if ( Object.keys( nextVisibility ).length ) {
			metadata.blockVisibility = nextVisibility;
		} else {
			delete metadata.blockVisibility;
		}
		setAttributes( {
			metadata: Object.keys( metadata ).length ? metadata : undefined,
		} );
	};

	/**
	 * Device-scoped "Hide on {Device}" toggle, rendered in the LAYOUT group
	 * slot next to Display order — so it follows the View-menu device
	 * (Desktop included) and shows inside core's Responsive styles state
	 * inspector at that viewport. The data is CORE's — the same
	 * metadata.blockVisibility.viewport its render support, eye badge, and
	 * visibility modal use — but core's only affordance for hiding a
	 * *visible* block is a menu item in the block's options dropdown (the
	 * toolbar eye appears only once a block is already hidden somewhere), so
	 * we surface it per device.
	 *
	 * @param {Object}   props
	 * @param {string}   props.clientId      Block client ID.
	 * @param {Object}   props.attributes    Block attributes.
	 * @param {Function} props.setAttributes Attribute setter.
	 */
	function ResponsiveHideControl( { clientId, attributes, setAttributes } ) {
		const deviceType = useDeviceType();

		const blockVisibility = attributes.metadata?.blockVisibility;
		// Boolean false = core's "hidden everywhere" form — the per-viewport
		// toggle doesn't apply; core's own notice handles it.
		if ( blockVisibility === false ) {
			return null;
		}
		const device = deviceType.toLowerCase();
		const isHidden = blockVisibility?.viewport?.[ device ] === false;

		const label = sprintf(
			/* translators: %s: device (Desktop/Tablet/Mobile). */
			__( 'Hide on %s', 'blocklane' ),
			DEVICE_LABELS[ device ]
		);

		return (
			<ToolsPanelItem
				isShownByDefault
				panelId={ clientId }
				label={ label }
				hasValue={ () => isHidden }
				onDeselect={ () =>
					writeViewportHide(
						attributes,
						setAttributes,
						device,
						false
					)
				}
			>
				<ToggleControl
					label={ labelWithTip(
						label,
						__(
							'Removes this block at this screen size on the front end (still editable here). Stored as core block visibility — core’s eye badge and options-menu Hide show the same state.',
							'blocklane'
						)
					) }
					checked={ isHidden }
					onChange={ ( next ) =>
						writeViewportHide(
							attributes,
							setAttributes,
							device,
							next
						)
					}
					__nextHasNoMarginBottom
				/>
			</ToolsPanelItem>
		);
	}

	/**
	 * A metadata object with every per-viewport hide removed (pure — for the
	 * Layout panel's Reset all filter).
	 *
	 * @param {Object|undefined} metadata Block metadata attribute.
	 * @return {Object|undefined} Cleaned metadata (undefined when empty).
	 */
	const metadataWithoutViewportHide = ( metadata ) => {
		if (
			typeof metadata?.blockVisibility !== 'object' ||
			metadata.blockVisibility === null
		) {
			return metadata;
		}
		const next = { ...metadata };
		const visibility = { ...next.blockVisibility };
		delete visibility.viewport;
		if ( Object.keys( visibility ).length ) {
			next.blockVisibility = visibility;
		} else {
			delete next.blockVisibility;
		}
		return Object.keys( next ).length ? next : undefined;
	};

	// ─── Breakpoint size hint (below the Hide toggle, device-scoped) ────

	/**
	 * The active breakpoint's size + inline editor (admins) / settings link,
	 * shown under the Layout-panel extras at Tablet/Mobile — "Tablet ≤ 768px
	 * · Edit". Nothing at Desktop (no breakpoint applies).
	 *
	 * @return {JSX.Element|null} The hint line.
	 */
	function ResponsiveBreakpointHint() {
		const bpKey = useBreakpoint();
		const { setGlobalBreakpoints } = useDispatch( RESPONSIVE_STORE );
		const globalBp = useSelect(
			( select ) => select( RESPONSIVE_STORE ).getGlobalBreakpoints(),
			[]
		);

		if ( ! bpKey ) {
			return null;
		}

		return (
			<p className="blocklane-pro-responsive-editing__bp-hint">
				{ sprintf(
					/* translators: 1: device (Tablet/Mobile), 2: breakpoint width in px. */
					__( '%1$s ≤ %2$dpx', 'blocklane' ),
					DEVICE_LABELS[ bpKey ],
					globalBp[ bpKey ]
				) }
				{ CAN_MANAGE_BREAKPOINTS ? (
					<>
						{ ' · ' }
						<BreakpointsEditor
							device={ bpKey }
							current={ globalBp }
							onSaved={ ( next ) =>
								setGlobalBreakpoints( {
									tablet: next.tablet,
									mobile: next.mobile,
								} )
							}
						/>
					</>
				) : (
					SETTINGS_URL && (
						<>
							{ ' · ' }
							<a
								href={ SETTINGS_URL }
								target="_blank"
								rel="noreferrer"
							>
								{ __( 'Edit', 'blocklane' ) }
							</a>
						</>
					)
				) }
			</p>
		);
	}

	// ─── Display order (LAYOUT group slot — core renders this slot inside
	//     its responsive-state inspector too, so the extra sits right in
	//     core's viewport editing UI) ─────────────────────────────────────

	/**
	 * Per-breakpoint child order for flex-Row children, with renumbering so
	 * positions stay unique. Core has no flex `order` property — this stays
	 * a Blocklane extra, keyed off the SAME device core's viewport states
	 * use.
	 *
	 * @param {Object} props
	 * @param {string} props.clientId   Block client ID.
	 * @param {Object} props.attributes Block attributes.
	 */
	function ResponsiveOrderControl( { clientId, attributes } ) {
		const bpKey = useBreakpoint();
		const registry = useRegistry();
		const { updateBlockAttributes } = useDispatch( blockEditorStore );
		const { siblingIds, parentIsFlexRow } = useSelect(
			( select ) => {
				const be = select( blockEditorStore );
				const root = be.getBlockRootClientId( clientId );
				if ( ! root ) {
					return { siblingIds: [], parentIsFlexRow: false };
				}
				const parent = be.getBlock( root );
				return {
					siblingIds: be.getBlockOrder( root ),
					// core/columns is always a flex container but its default
					// flex layout isn't serialized to attributes.layout, so
					// detect it by name as well.
					parentIsFlexRow:
						parent?.name === 'core/columns' ||
						parent?.attributes?.layout?.type === 'flex',
				};
			},
			[ clientId ]
		);

		if ( ! bpKey || ! parentIsFlexRow || siblingIds.length < 2 ) {
			return null;
		}

		const orderValue =
			attributes.blocklaneProResponsive?.order?.[ bpKey ] || undefined;

		// Preselect the block's natural position (its index among siblings) so
		// the control is never blank — only an explicit override counts as
		// "set" (orderValue) for the reset menu.
		const naturalOrder = siblingIds.indexOf( clientId ) + 1;
		const orderDisplay = orderValue ?? ( naturalOrder || undefined );

		// This breakpoint's order on an arbitrary sibling, read live.
		const siblingOrder = ( id ) =>
			registry.select( blockEditorStore ).getBlock( id )?.attributes
				?.blocklaneProResponsive?.order?.[ bpKey ];

		// Write/clear this breakpoint's order on an arbitrary block.
		const writeOrder = ( id, value ) => {
			const block = registry.select( blockEditorStore ).getBlock( id );
			const resp = {
				...( block?.attributes?.blocklaneProResponsive ?? {} ),
			};
			const ord = { ...( resp.order ?? {} ) };
			if ( value ) {
				ord[ bpKey ] = value;
			} else {
				delete ord[ bpKey ];
			}
			if ( Object.keys( ord ).length ) {
				resp.order = ord;
			} else {
				delete resp.order;
			}
			updateBlockAttributes( id, {
				blocklaneProResponsive: Object.keys( resp ).length ? resp : {},
			} );
		};

		const setOrder = ( next ) => {
			const value = next ? Number( next ) : undefined;

			// Clearing: drop this breakpoint's order on EVERY sibling so the
			// sequence resets cleanly to natural order (no leftover gaps).
			if ( ! value ) {
				registry.batch( () =>
					siblingIds.forEach( ( id ) => writeOrder( id, undefined ) )
				);
				return;
			}

			// Auto-assign the whole set: take the current visual sequence
			// (explicit order first, then any unordered in DOM order), move
			// this block to the chosen 1-based slot, and renumber all 1..N — so
			// picking one position fills in the others automatically.
			const seq = [ ...siblingIds ].sort( ( a, b ) => {
				const oa = siblingOrder( a ) ?? 1000 + siblingIds.indexOf( a );
				const ob = siblingOrder( b ) ?? 1000 + siblingIds.indexOf( b );
				return oa - ob;
			} );
			const reordered = seq.filter( ( id ) => id !== clientId );
			reordered.splice(
				Math.min( value, reordered.length + 1 ) - 1,
				0,
				clientId
			);
			// One batched commit so N sibling writes cause a single re-render.
			registry.batch( () =>
				reordered.forEach( ( id, index ) =>
					writeOrder( id, index + 1 )
				)
			);
		};

		const label = sprintf(
			/* translators: %s: device (Tablet/Mobile). */
			__( 'Display order (%s)', 'blocklane' ),
			DEVICE_LABELS[ bpKey ]
		);

		return (
			<ToolsPanelItem
				isShownByDefault
				panelId={ clientId }
				label={ label }
				hasValue={ () => orderValue !== undefined }
				onDeselect={ () => setOrder( undefined ) }
			>
				<ToggleGroupControl
					label={ labelWithTip(
						label,
						__(
							'Reorders this item among its siblings at this screen size — works whether they sit in a row or stack. Visual only; keyboard and reading order are unchanged.',
							'blocklane'
						)
					) }
					value={ orderDisplay }
					onChange={ setOrder }
					isBlock
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				>
					{ Array.from(
						{ length: siblingIds.length },
						( _, i ) => i + 1
					).map( ( n ) => (
						<ToggleGroupControlOption
							key={ n }
							value={ n }
							label={ String( n ) }
						/>
					) ) }
				</ToggleGroupControl>
			</ToolsPanelItem>
		);
	}

	// ─── Max width (DIMENSIONS group slot — also present inside core's
	//     viewport state mode) ──────────────────────────────────────────

	/**
	 * Per-breakpoint max width (core's style engine has no max-width).
	 *
	 * @param {Object}   props
	 * @param {string}   props.clientId      Block client ID.
	 * @param {string}   props.name          Block name.
	 * @param {Object}   props.attributes    Block attributes.
	 * @param {Function} props.setAttributes Attribute setter.
	 */
	function ResponsiveMaxWidthControl( {
		clientId,
		name,
		attributes,
		setAttributes,
	} ) {
		const bpKey = useBreakpoint();

		// Mirrors the old dimensions-group gate (blocks with
		// dimensions.minHeight support get sizing controls).
		const blockType = wp.blocks.getBlockType( name );
		if ( ! bpKey || blockType?.supports?.dimensions?.minHeight !== true ) {
			return null;
		}

		const value =
			attributes.blocklaneProResponsive?.maxWidth?.[ bpKey ] ?? '';
		const label = sprintf(
			/* translators: %s: device (Tablet/Mobile). */
			__( 'Max width (%s)', 'blocklane' ),
			DEVICE_LABELS[ bpKey ]
		);

		return (
			<ToolsPanelItem
				isShownByDefault
				panelId={ clientId }
				label={ label }
				hasValue={ () => value !== '' }
				onDeselect={ () =>
					updateResponsiveValue(
						attributes,
						setAttributes,
						'maxWidth',
						bpKey,
						undefined
					)
				}
			>
				<HeightControl
					label={ label }
					value={ value }
					onChange={ ( v ) =>
						updateResponsiveValue(
							attributes,
							setAttributes,
							'maxWidth',
							bpKey,
							v
						)
					}
				/>
			</ToolsPanelItem>
		);
	}

	// ─── HOC: Inject the slot fills ─────────────────────────────────────

	/**
	 * Layout panel's Reset all: clear the order override + every viewport
	 * hide (runs alongside core's own layout reset filters; receives the
	 * accumulated attributes plus a context holding the full block
	 * attributes).
	 *
	 * @param {Object} attrs   Accumulated new attributes.
	 * @param {Object} context Reset context ({ attributes, clientId, name }).
	 * @return {Object} Attributes with the layout extras cleared.
	 */
	const layoutResetAllFilter = ( attrs, context ) => {
		const blockAttributes = context?.attributes ?? {};
		const bag = { ...( blockAttributes.blocklaneProResponsive ?? {} ) };
		delete bag.order;
		return {
			...attrs,
			blocklaneProResponsive: bag,
			metadata: metadataWithoutViewportHide( blockAttributes.metadata ),
		};
	};

	/**
	 * Dimensions panel's Reset all: clear the max-width override.
	 *
	 * @param {Object} attrs   Accumulated new attributes.
	 * @param {Object} context Reset context ({ attributes, clientId, name }).
	 * @return {Object} Attributes with maxWidth cleared.
	 */
	const dimensionsResetAllFilter = ( attrs, context ) => {
		const blockAttributes = context?.attributes ?? {};
		const bag = { ...( blockAttributes.blocklaneProResponsive ?? {} ) };
		delete bag.maxWidth;
		return {
			...attrs,
			blocklaneProResponsive: bag,
		};
	};

	const withResponsiveControls = createHigherOrderComponent(
		( BlockEdit ) => {
			return ( props ) => {
				if ( ! RESPONSIVE_BLOCKS.includes( props.name ) ) {
					return <BlockEdit { ...props } />;
				}

				const showOrder = ORDER_BLOCKS.includes( props.name );
				const showMaxWidth = SUPPORTED_BLOCKS.includes( props.name );

				return (
					<>
						<BlockEdit { ...props } />
						{ /* The Layout panel carries the device-scoped extras:
						     Display order (flex children, tablet/mobile), the
						     Hide toggle for the current device (desktop
						     included), and the breakpoint-size hint — all
						     following the View-menu device, rendered inside
						     core's Responsive styles state inspector too. */ }
						<InspectorControls
							group="layout"
							resetAllFilter={ layoutResetAllFilter }
						>
							{ showOrder && (
								<ResponsiveOrderControl
									clientId={ props.clientId }
									attributes={ props.attributes }
								/>
							) }
							<ResponsiveHideControl
								clientId={ props.clientId }
								attributes={ props.attributes }
								setAttributes={ props.setAttributes }
							/>
							<ResponsiveBreakpointHint />
						</InspectorControls>
						{ showMaxWidth && (
							<InspectorControls
								group="dimensions"
								resetAllFilter={ dimensionsResetAllFilter }
							>
								<ResponsiveMaxWidthControl
									clientId={ props.clientId }
									name={ props.name }
									attributes={ props.attributes }
									setAttributes={ props.setAttributes }
								/>
							</InspectorControls>
						) }
					</>
				);
			};
		},
		'withResponsiveControls'
	);

	addFilter(
		'editor.BlockEdit',
		'blocklane-pro/responsive/add-controls',
		withResponsiveControls
	);

	// ─── Editor Preview (order + max width — the Blocklane extras) ──────

	function cascadeValue( responsive, property, breakpoint ) {
		const val = responsive[ property ]?.[ breakpoint ];
		if ( val ) {
			return val;
		}
		if ( breakpoint === 'mobile' ) {
			return responsive[ property ]?.tablet || null;
		}
		return null;
	}

	function ResponsivePreviewStyle( { clientId, responsive } ) {
		const deviceType = useDeviceType();
		let breakpoint = null;
		if ( deviceType === 'Tablet' ) {
			breakpoint = 'tablet';
		} else if ( deviceType === 'Mobile' ) {
			breakpoint = 'mobile';
		}
		const [ headEl, setHeadEl ] = useState( null );

		// Find the correct document head (handles editor iframe).
		useEffect( () => {
			const find = () => {
				const el =
					document.getElementById( `block-${ clientId }` ) ??
					document
						.querySelector( 'iframe[name="editor-canvas"]' )
						?.contentDocument?.getElementById(
							`block-${ clientId }`
						);
				if ( el ) {
					setHeadEl( el.ownerDocument.head );
				}
			};
			find();
			// Re-check after a frame in case the iframe hasn't loaded yet.
			const raf = requestAnimationFrame( find );
			return () => cancelAnimationFrame( raf );
		}, [ clientId ] );

		if ( ! headEl || ! breakpoint ) {
			return null;
		}

		// Resolve presets, then drop anything that isn't a safe CSS value so a
		// crafted attribute can't inject declarations into this <style>.
		const r = ( value ) => safeCssValue( resolvePresetValue( value ) );
		const rules = [];

		// Max width — cascade.
		const mw = cascadeValue( responsive, 'maxWidth', breakpoint );
		if ( mw ) {
			rules.push( `max-width: ${ r( mw ) } !important` );
		}

		// Order — cascade. Applies to this block as a flex item of its row.
		const order = cascadeValue( responsive, 'order', breakpoint );
		if ( order ) {
			rules.push( `order: ${ parseInt( order, 10 ) } !important` );
		}

		if ( ! rules.length ) {
			return null;
		}

		return createPortal(
			<style data-blocklane-pro-preview={ clientId }>
				{ `#block-${ clientId } { ${ rules.join( '; ' ) }; }` }
			</style>,
			headEl
		);
	}

	const withResponsivePreview = createHigherOrderComponent(
		( BlockListBlock ) => {
			return ( props ) => {
				if ( ! RESPONSIVE_BLOCKS.includes( props.name ) ) {
					return <BlockListBlock { ...props } />;
				}

				const responsive = props.attributes.blocklaneProResponsive;

				if ( ! responsive || Object.keys( responsive ).length === 0 ) {
					return <BlockListBlock { ...props } />;
				}

				return (
					<>
						<ResponsivePreviewStyle
							clientId={ props.clientId }
							responsive={ responsive }
						/>
						<BlockListBlock { ...props } />
					</>
				);
			};
		},
		'withResponsivePreview'
	);

	addFilter(
		'editor.BlockListBlock',
		'blocklane-pro/responsive/add-preview',
		withResponsivePreview
	);
}
