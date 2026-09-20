/**
 * Extensions screen — the toggle list for the block-editor extensions.
 *
 * Core admin-ui page arrangement (ScreenHeader + ScreenTabs, shared with
 * SEO, Forms, Content Types, and Advanced): a page header, then one tab
 * per category (Styling & Effects, Layout & Responsive, Workflow & Tools),
 * then the tab's content — a toolbar (Enable/Disable All for the tab, a
 * search box scoped to the tab) above the tab's feature rows (toggle ·
 * title · short description · chevron). Beside the rows, the help drawer:
 * overview copy by default; clicking a row pins its detail there (big
 * toggle, full description, and for some extensions their settings —
 * breakpoints, canvas grid tools). Hovering a row previews it while
 * nothing is pinned. Escape, re-clicking the pinned row, the drawer
 * handle, or switching tabs unpins. Toggles save immediately and roll
 * back on failure; a snackbar confirms each one.
 *
 * Every row's display copy is OWNED by its `extension:*` unit: it lives in
 * extensions/rows/<slug>.js behind the one-line-per-row index
 * extensions/rows.js, so an edition that does not carry the extension carries
 * neither the file nor the line and the copy is never in the bundle. This
 * screen keeps DISPLAY_ORDER (the slugs are shared data, like PHP's
 * KNOWN_SLUGS) and lists a slug only when it has BOTH a row and a server-side
 * `enabled` entry.
 *
 * URL: ?screen=extensions&exttab=<category> (the default tab clears the
 * param); ?ext=<slug> pins that extension on its own tab — the editor's
 * "Manage breakpoints" link lands here; an unknown slug pins nothing and
 * is dropped from the URL. All of that — the registry entry, the tab
 * shell, the pinned item and its param, bucketing and search — is
 * useFeatureTabs (components/use-feature-tabs.js); this screen types its
 * registry slug once and never writes the URL. The tab model
 * (feature-tabs.js): an item's tab is its category; a slug with no mapping
 * lands on the last tab, so a new extension still appears without a code
 * change here. Enable/Disable All writes the tab's rows one at a time
 * behind useSerialWrites: while the loop runs every toggle on the screen
 * and the button itself are disabled, and the button also waits for any
 * single-row write still in flight, so the two write paths never
 * interleave in either order.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import {
	Button,
	Flex,
	FlexItem,
	FlexBlock,
	Notice,
	Snackbar,
	Spinner,
	ToggleControl,
	__experimentalDivider as Divider,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

import { extensions as extensionsApi } from '../api/client';
import { errorMessage } from '../api/errors';
import { FeatureItem } from '../components/FeatureItem';
import { FeatureDescription } from '../components/FeatureDescription';
import { FeatureToolbar } from '../components/FeatureToolbar';
import { HelpTab, useHelpPreference } from '../components/HelpTab';
import { ScreenHeader } from '../components/ScreenHeader';
import { ScreenTabs, ScreenTabPanel } from '../components/ScreenTabs';
import { useFeatureTabs } from '../components/use-feature-tabs';
import { useSerialWrites } from '../components/use-serial-writes';
import { pluginName } from '../edition';
// Every extension row, one re-export per line — the index the generator
// filters. Namespace import on purpose: the object is whatever lines
// survived, and nothing here names an absent unit.
import * as ROW_MODULES from './extensions/rows';

/* ROWS — display data for each extension slug. PHP remains the source of
   truth for which slugs exist (Extensions_Handler::get_enabled_extensions);
   slugs not in this map fall back to a humanized version of the slug.

   Every row is OWNED by its `extension:*` unit and lives in
   screens/extensions/rows/<slug>.js, re-exported one line per row by
   screens/extensions/rows.js — an edition that does not carry the extension
   carries neither the line nor the file, so the copy for an extension the
   artifact does not contain is never in the bundle. Keyed by each row's OWN
   slug, so the key comes from the row data and not from an export name that
   could drift from it. */
export const ROWS = Object.fromEntries(
	Object.values( ROW_MODULES ).map( ( row ) => [ row.slug, row ] )
);

/* The user-facing toggle list, alphabetical by title to match BlocklanePro's admin
   display order (so the two lists scan straight down side by side; BlocklanePro's
   extra rows — BlocklanePro AI on top, Pattern Library after Keyboard Shortcuts — are
   the only gaps, since we skip those). The handler also tracks the always-on/
   hidden slug (advanced-paragraph) that loads but is intentionally not shown
   here. */
export const DISPLAY_ORDER = [
	'advanced-grid',
	'advanced-group',
	'animation-designer',
	'background-url',
	'icon-library',
	'button-icons',
	'class-manager',
	'hover-colors',
	'responsive-controls',
	'smart-sync',
	'advanced-tabs',
	'text-wrap',
	'transparent-header',
	'video-modal',
];

/* The tabs. CATEGORIES is the tab order (the first is the default tab);
   CATEGORY_OF maps each slug to its tab. A slug with no mapping lands on the
   last tab (feature-tabs.js), so new extensions still appear without a code
   change here. */
const CATEGORIES = [
	{ slug: 'styling', label: __( 'Styling & Effects', 'blocklane' ) },
	{ slug: 'layout', label: __( 'Layout & Responsive', 'blocklane' ) },
	{ slug: 'workflow', label: __( 'Workflow & Tools', 'blocklane' ) },
];

const CATEGORY_OF = {
	'animation-designer': 'styling',
	'hover-colors': 'styling',
	'button-icons': 'styling',
	'advanced-tabs': 'styling',
	'text-wrap': 'styling',
	'background-url': 'styling',
	'icon-library': 'styling',
	'video-modal': 'styling',
	'advanced-group': 'layout',
	'advanced-grid': 'layout',
	'responsive-controls': 'layout',
	'transparent-header': 'layout',
	'smart-sync': 'workflow',
	'class-manager': 'workflow',
};

const humanizeSlug = ( slug ) =>
	slug
		.split( '-' )
		.map( ( w ) => w.charAt( 0 ).toUpperCase() + w.slice( 1 ) )
		.join( ' ' );

/* Global responsive breakpoints — shown inside the Responsive Controls detail.
   The front-end CSS is generated from these (media queries can't read CSS vars),
   so they're a global, site-wide setting rather than per-block. */
const ResponsiveBreakpointsSetting = () => {
	const [ values, setValues ] = useState( null );
	const [ defaults, setDefaults ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ note, setNote ] = useState( '' );
	const [ loadError, setLoadError ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		extensionsApi
			.getBreakpoints()
			.then( ( res ) => {
				if ( cancelled ) {
					return;
				}
				setValues( res?.breakpoints || null );
				setDefaults( res?.defaults || null );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadError( true );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	// A failed fetch must not leave the spinner forever — degrade to a short
	// error line instead.
	if ( loadError ) {
		return (
			<VStack spacing={ 3 } className="blocklane-pro-breakpoints-setting">
				<Divider />
				<h2>{ __( 'Breakpoints', 'blocklane' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Could not load the breakpoints. Reload the page to try again.',
						'blocklane'
					) }
				</Notice>
			</VStack>
		);
	}

	// Render the section shell immediately (with a spinner) so it doesn't pop in
	// a beat later once the breakpoints fetch resolves.
	if ( ! values ) {
		return (
			<VStack spacing={ 3 } className="blocklane-pro-breakpoints-setting">
				<Divider />
				<h2>{ __( 'Breakpoints', 'blocklane' ) }</h2>
				<Spinner />
			</VStack>
		);
	}

	const invalid = Number( values.mobile ) >= Number( values.tablet );

	const save = async () => {
		setBusy( true );
		setNote( '' );
		try {
			const res = await extensionsApi.saveBreakpoints(
				parseInt( values.tablet, 10 ),
				parseInt( values.mobile, 10 )
			);
			if ( res?.breakpoints ) {
				setValues( res.breakpoints );
				setNote(
					__(
						'Saved. The front end uses these breakpoints; reload the editor to refresh previews.',
						'blocklane'
					)
				);
			}
		} catch ( err ) {
			setNote( __( 'Could not save breakpoints.', 'blocklane' ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<VStack spacing={ 3 } className="blocklane-pro-breakpoints-setting">
			<Divider />
			<h2>{ __( 'Breakpoints', 'blocklane' ) }</h2>
			<p>
				{ __(
					'The max-widths where Tablet and Mobile overrides take effect on the front end. Global — applies to every block.',
					'blocklane'
				) }
			</p>
			<HStack spacing={ 3 } alignment="flex-end" justify="flex-start">
				<FlexBlock>
					<NumberControl
						label={ __( 'Tablet (px)', 'blocklane' ) }
						value={ values.tablet }
						min={ 360 }
						max={ 2000 }
						step={ 1 }
						onChange={ ( v ) =>
							setValues( ( p ) => ( { ...p, tablet: v } ) )
						}
						__next40pxDefaultSize
					/>
				</FlexBlock>
				<FlexBlock>
					<NumberControl
						label={ __( 'Mobile (px)', 'blocklane' ) }
						value={ values.mobile }
						min={ 240 }
						max={ 1600 }
						step={ 1 }
						onChange={ ( v ) =>
							setValues( ( p ) => ( { ...p, mobile: v } ) )
						}
						__next40pxDefaultSize
					/>
				</FlexBlock>
			</HStack>
			{ invalid ? (
				<p className="blocklane-pro-breakpoints-setting__warn">
					{ __(
						'Mobile must be smaller than Tablet — it will be adjusted on save.',
						'blocklane'
					) }
				</p>
			) : null }
			<HStack spacing={ 2 } justify="flex-start">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ busy }
					disabled={ busy }
					__next40pxDefaultSize
				>
					{ __( 'Save breakpoints', 'blocklane' ) }
				</Button>
				{ defaults ? (
					<Button
						variant="tertiary"
						onClick={ () => setValues( { ...defaults } ) }
						__next40pxDefaultSize
					>
						{ __( 'Reset to defaults', 'blocklane' ) }
					</Button>
				) : null }
			</HStack>
			{ note ? (
				<p className="blocklane-pro-breakpoints-setting__note">
					{ note }
				</p>
			) : null }
		</VStack>
	);
};

/* Canvas grid tools — shown inside the Advanced Grid detail.

   Switches on editing affordances that already ship (dormant) inside core: drag
   to move, resize handles and cell movers on grid blocks. They write core's own
   columnStart/columnSpan/rowStart/rowSpan attributes, so nothing here creates
   Blocklane-shaped data.

   OFF by default, and a separate switch from the extension itself: it changes
   how every grid block in the editor behaves, and core still keeps the feature
   behind an experiment flag while keyboard access and large-grid performance
   are settled. That's the author's call to make, not a side effect of wanting
   custom breakpoints. */
const GridCanvasToolsSetting = () => {
	const [ enabled, setEnabled ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ note, setNote ] = useState( '' );
	const [ loadError, setLoadError ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		extensionsApi
			.getGridCanvasTools()
			.then( ( res ) => {
				if ( ! cancelled ) {
					setEnabled( !! res?.enabled );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadError( true );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	if ( loadError ) {
		return (
			<VStack spacing={ 3 } className="blocklane-pro-grid-tools-setting">
				<Divider />
				<h2>{ __( 'Canvas grid tools', 'blocklane' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Could not load this setting. Reload the page to try again.',
						'blocklane'
					) }
				</Notice>
			</VStack>
		);
	}

	if ( enabled === null ) {
		return (
			<VStack spacing={ 3 } className="blocklane-pro-grid-tools-setting">
				<Divider />
				<h2>{ __( 'Canvas grid tools', 'blocklane' ) }</h2>
				<Spinner />
			</VStack>
		);
	}

	// A toggle saves on change — no Save button, matching the extension
	// toggles themselves. The previous value is restored if the write fails,
	// so the switch never shows a state the site is not actually in.
	const toggle = async ( next ) => {
		const previous = enabled;
		setEnabled( next );
		setBusy( true );
		setNote( '' );
		try {
			const res = await extensionsApi.saveGridCanvasTools( next );
			setEnabled( !! res?.enabled );
			setNote(
				__(
					'Saved. Reload the editor for the change to take effect.',
					'blocklane'
				)
			);
		} catch ( err ) {
			setEnabled( previous );
			setNote( __( 'Could not save this setting.', 'blocklane' ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<VStack spacing={ 3 } className="blocklane-pro-grid-tools-setting">
			<Divider />
			<h2>{ __( 'Canvas grid tools', 'blocklane' ) }</h2>
			<p>
				{ __(
					'Drag grid items to move them, and drag their edges to resize — directly on the canvas, instead of setting spans in the sidebar.',
					'blocklane'
				) }
			</p>
			<ToggleControl
				label={ __( 'Enable canvas grid tools', 'blocklane' ) }
				help={ __(
					'These tools are built into WordPress but switched off while their keyboard support and performance on large grids are still being finished. They save standard WordPress grid settings, so anything you build keeps working if you switch them back off.',
					'blocklane'
				) }
				checked={ enabled }
				onChange={ toggle }
				disabled={ busy }
				__nextHasNoMarginBottom
			/>
			{ note ? (
				<p className="blocklane-pro-grid-tools-setting__note">
					{ note }
				</p>
			) : null }
		</VStack>
	);
};

/* Display data for a slug — its row, or a humanized fallback for a slug this
   artifact has no row for (PHP owns the slug list). The fallback stays because
   a slug can outrun the map; the rendered list is filtered to `s in ROWS`
   first, so it is a guard, not the display path. */
const metaFor = ( slug ) =>
	ROWS[ slug ] || { title: humanizeSlug( slug ), shortDescription: '' };

/* What the tab's search box matches. */
const textOf = ( slug ) => {
	const meta = metaFor( slug );
	return [ meta.title, meta.shortDescription ];
};

export const ExtensionsScreen = ( { initialExtension } = {} ) => {
	const [ enabled, setEnabled ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ pending, setPending ] = useState( {} );
	const [ snackbarMessage, setSnackbarMessage ] = useState( '' );
	const pageStart = useRef( null );
	// Mouse-only enhancement: the card the cursor is over previews in the right
	// panel. Pinning (click/Enter) is the hook's `pinned` below.
	const [ hovered, setHovered ] = useState( null );
	// A brief hover-intent delay so quickly sweeping the cursor across cards
	// doesn't strobe the panel — only a card you settle on for ~90ms previews.
	const hoverTimer = useRef( null );
	const previewOnHover = useCallback( ( slug ) => {
		clearTimeout( hoverTimer.current );
		hoverTimer.current = setTimeout( () => setHovered( slug ), 90 );
	}, [] );
	const clearHover = useCallback( () => {
		clearTimeout( hoverTimer.current );
		setHovered( null );
	}, [] );
	useEffect( () => () => clearTimeout( hoverTimer.current ), [] );

	// Show only the curated, ordered display set; the always-on slugs the
	// handler also returns are intentionally not listed. A slug needs BOTH
	// halves: a row this artifact carries (`s in ROWS` — the unit is present)
	// and a server-side entry (`s in enabled` — the handler offers it). Either
	// one alone would render a toggle for an extension that is not here.
	// Memoized so the fallback [] keeps a stable identity for the hook below.
	const slugs = useMemo(
		() =>
			enabled
				? DISPLAY_ORDER.filter( ( s ) => s in ROWS && s in enabled )
				: [],
		[ enabled ]
	);

	// The tabbed composition: registry entry, tab shell, the pinned item
	// (?ext=) and its tab, this tab's rows and search.
	const ft = useFeatureTabs( {
		screen: 'extensions',
		categories: CATEGORIES,
		categoryOf: CATEGORY_OF,
		// Validated against the whole display set on purpose: `slugs` is []
		// until the first fetch lands, and this resolves ONCE, on first
		// render — validating against it would refuse every deep link.
		knownSlugs: DISPLAY_ORDER,
		slugs,
		// ...but a slug this artifact has no row for is not pinnable: the row
		// filter above is one door, the deep link is the other.
		deepLink: initialExtension in ROWS ? initialExtension : null,
		textOf,
		isOn: ( s ) => !! enabled?.[ s ],
	} );
	const {
		pinned,
		pin,
		open,
		unpin,
		tab,
		tabLabel,
		tabSlugs,
		visibleSlugs,
		allOn,
		search,
		setSearch,
	} = ft;

	// Pinning a row settles the panel on it; a hover preview in progress ends.
	useEffect( () => {
		if ( pinned ) {
			clearHover();
		}
	}, [ pinned, clearHover ] );

	// The help sidebar's core-style pull tab preference (see HelpTab.js). The
	// pinned card owns the panel; hover only previews while nothing is
	// pinned — otherwise drifting across the list would swap a pinned card's
	// detail out from under the user. Hover is also inert while the help
	// sidebar is closed (it must not yank the panel open).
	const [ helpPref, setHelpPref ] = useHelpPreference( ft.screen );
	const helpPanelId = `blocklane-pro-help-panel-${ ft.screen }`;
	const helpOpen = helpPref || !! pinned;
	const hoverEnabled = helpOpen && ! pinned;
	const detailSlug = pinned || ( hoverEnabled ? hovered : null );

	const toggleHelp = useCallback( () => {
		if ( helpOpen ) {
			setHelpPref( false );
			clearHover();
			unpin();
		} else {
			setHelpPref( true );
		}
	}, [ helpOpen, setHelpPref, clearHover, unpin ] );

	useEffect( () => {
		pageStart.current?.focus();
	}, [] );

	const loadExtensions = useCallback( async () => {
		setLoading( true );
		setError( '' );
		try {
			const result = await extensionsApi.list();
			setEnabled( result?.enabled || {} );
		} catch ( err ) {
			setError( errorMessage( err ) );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadExtensions();
	}, [ loadExtensions ] );

	const handleToggle = useCallback( async ( slug, next ) => {
		// Capture `previous` from the latest committed state inside the
		// functional updater so a rapid double-toggle rolls back to the
		// correct value rather than a stale render-time snapshot.
		let previous;
		setEnabled( ( prev ) => {
			previous = prev?.[ slug ];
			return { ...prev, [ slug ]: next };
		} );
		setPending( ( prev ) => ( { ...prev, [ slug ]: true } ) );
		setError( '' );

		setSnackbarMessage(
			next
				? sprintf(
						/* translators: %s: the feature or extension name. */
						__( '%s enabled', 'blocklane' ),
						metaFor( slug ).title
					)
				: sprintf(
						/* translators: %s: the feature or extension name. */
						__( '%s disabled', 'blocklane' ),
						metaFor( slug ).title
					)
		);

		try {
			const result = await extensionsApi.toggle( slug, next );
			if ( result?.enabled ) {
				setEnabled( result.enabled );
			}
		} catch ( err ) {
			setEnabled( ( prev ) => ( { ...prev, [ slug ]: previous } ) );
			setError( errorMessage( err ) );
			setSnackbarMessage( '' );
		} finally {
			setPending( ( prev ) => {
				const n = { ...prev };
				delete n[ slug ];
				return n;
			} );
		}
	}, [] );

	// Enable/Disable All acts on the tab it sits in, one write per row in
	// order (each is its own option row). useSerialWrites refuses a second
	// run while one is in flight; the optimistic merge is applied only after
	// it accepts, so a refused click leaves no trace and the first click's
	// direction stands. While `busy`, every toggle on the screen and the
	// button are disabled — a row toggled mid-loop would otherwise be
	// overwritten by the loop's later write for it. The map is then taken
	// from the last response, the server's own state; a failure refetches.
	const { busy, run } = useSerialWrites();
	const handleToggleAll = useCallback( async () => {
		const next = ! allOn;
		const targets = tabSlugs;
		const loop = run( targets, ( s ) => extensionsApi.toggle( s, next ) );
		if ( ! loop ) {
			return;
		}
		const updated = {};
		targets.forEach( ( s ) => {
			updated[ s ] = next;
		} );
		setEnabled( ( prev ) => ( { ...prev, ...updated } ) );
		setError( '' );
		setSnackbarMessage(
			next
				? sprintf(
						/* translators: %s: the tab (category) name. */
						__( 'All %s extensions enabled', 'blocklane' ),
						tabLabel
					)
				: sprintf(
						/* translators: %s: the tab (category) name. */
						__( 'All %s extensions disabled', 'blocklane' ),
						tabLabel
					)
		);
		try {
			const last = await loop;
			if ( last?.enabled ) {
				setEnabled( last.enabled );
			}
		} catch ( err ) {
			setError( errorMessage( err ) );
			setSnackbarMessage( '' );
			loadExtensions();
		}
	}, [ allOn, tabSlugs, tabLabel, run, loadExtensions ] );

	return (
		<div className="blocklane-pro-page">
			<ScreenHeader screen={ ft.screen } pageStart={ pageStart } />
			<ScreenTabs
				screen={ ft.screen }
				label={ __( 'Extension categories', 'blocklane' ) }
				tabs={ CATEGORIES }
				shell={ ft.shell }
			/>

			<Flex
				align="stretch"
				gap="0"
				className={
					helpOpen
						? 'blocklane-pro-extensions'
						: 'blocklane-pro-extensions is-help-closed'
				}
			>
				<FlexItem className="blocklane-pro-extensions__sidebar">
					<ScreenTabPanel
						screen={ ft.screen }
						slug={ tab }
						className="blocklane-pro-extensions__section"
					>
						<FeatureToolbar
							allOn={ allOn }
							onToggleAll={ handleToggleAll }
							disabled={
								loading ||
								! tabSlugs.length ||
								Object.keys( pending ).length > 0
							}
							busy={ busy }
							search={ search }
							onSearch={ setSearch }
							searchLabel={ __(
								'Search extensions',
								'blocklane'
							) }
							searchPlaceholder={ __(
								'Search extensions…',
								'blocklane'
							) }
						/>

						<div
							className="blocklane-pro-extensions__fields"
							onMouseLeave={ clearHover }
						>
							{ loading && (
								<>
									{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics when list-style:none, so restore the role explicitly. */ }
									<ul
										className="blocklane-pro-feature-list"
										role="list"
									>
										{ Array.from( { length: 6 } ).map(
											( _, i ) => (
												<li
													key={ i }
													className="blocklane-pro-feature-item is-skeleton"
													aria-hidden="true"
												>
													<div className="blocklane-pro-feature-item__skel-toggle" />
													<div className="blocklane-pro-feature-item__skel-text">
														<div className="blocklane-pro-feature-item__skel-title" />
														<div className="blocklane-pro-feature-item__skel-body" />
													</div>
													<div className="blocklane-pro-feature-item__skel-chevron" />
												</li>
											)
										) }
									</ul>
								</>
							) }
							{ ! loading && visibleSlugs.length === 0 && (
								<p className="blocklane-pro-feature-list__empty">
									{ search.trim()
										? __(
												'No extensions in this tab match your search.',
												'blocklane'
											)
										: __(
												'No extensions in this tab.',
												'blocklane'
											) }
								</p>
							) }
							{ ! loading && visibleSlugs.length > 0 && (
								<>
									{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics when list-style:none, so restore the role explicitly. */ }
									<ul
										className="blocklane-pro-feature-list"
										role="list"
									>
										{ visibleSlugs.map( ( slug ) => {
											const meta = metaFor( slug );
											return (
												<FeatureItem
													key={ slug }
													title={ meta.title }
													description={
														meta.shortDescription
													}
													checked={
														!! enabled[ slug ]
													}
													disabled={
														busy ||
														!! pending[ slug ]
													}
													isActive={ pinned === slug }
													itemKey={ slug }
													badge={ meta.badge }
													onChange={ ( v ) =>
														handleToggle( slug, v )
													}
													onLearnMore={ pin }
													onOpen={ open }
													onHover={
														hoverEnabled
															? previewOnHover
															: undefined
													}
												/>
											);
										} ) }
									</ul>
								</>
							) }
						</div>

						{ error ? (
							<p
								className="blocklane-pro-extensions__error"
								role="alert"
							>
								{ error }
							</p>
						) : null }
					</ScreenTabPanel>
				</FlexItem>

				<HelpTab
					isOpen={ helpOpen }
					onToggle={ toggleHelp }
					panelId={ helpPanelId }
				/>
				<FlexItem
					className="blocklane-pro-extensions__preview"
					id={ helpPanelId }
					aria-hidden={ ! helpOpen }
				>
					<section>
						{ ! detailSlug ? (
							<div className="blocklane-pro-extensions__preview-content">
								<p>
									<strong>
										{ sprintf(
											/* translators: %s: the plugin name. */
											__(
												'What are %s Extensions?',
												'blocklane'
											),
											pluginName()
										) }
									</strong>
								</p>
								<p>
									{ __(
										'Extensions are powerful features that enhance your WordPress editing experience. Some add new settings to existing blocks, while others add entirely new blocks and creation tools.',
										'blocklane'
									) }
								</p>
								<p>
									{ __(
										"All extensions are enabled by default, but you can always disable the ones you don't need to curate your building experience.",
										'blocklane'
									) }
								</p>
								<Divider />
								<p>
									<strong>
										{ __(
											'Learn about each extension',
											'blocklane'
										) }
									</strong>
								</p>
								<p>
									{ __(
										'Click each feature to learn more about it and watch a video tutorial to learn how to use it.',
										'blocklane'
									) }
								</p>
							</div>
						) : (
							<div
								className="blocklane-pro-extensions__preview-content"
								data-extension={ detailSlug }
							>
								{ /* No close X: Escape, re-clicking the pinned
								     row, and the drawer handle all dismiss —
								     a third X crowded the toggle row. */ }
								<ToggleControl
									label={ metaFor( detailSlug ).title }
									checked={ !! enabled?.[ detailSlug ] }
									disabled={
										busy || !! pending[ detailSlug ]
									}
									onChange={ ( v ) =>
										handleToggle( detailSlug, v )
									}
									className="blocklane-pro-extensions__detail-toggle"
									__nextHasNoMarginBottom
								/>
								<div className="blocklane-pro-extensions__detail-desc">
									<FeatureDescription
										description={
											metaFor( detailSlug ).description ||
											metaFor( detailSlug )
												.shortDescription
										}
									/>
									{ /* Only mount the interactive breakpoints control
									     (which fetches) once the card is pinned — a
									     transient hover-peek shows the description only. */ }
									{ detailSlug === 'responsive-controls' &&
									pinned === 'responsive-controls' ? (
										<ResponsiveBreakpointsSetting />
									) : null }
									{ detailSlug === 'advanced-grid' &&
									pinned === 'advanced-grid' ? (
										<GridCanvasToolsSetting />
									) : null }
								</div>
							</div>
						) }
					</section>
				</FlexItem>

				{ snackbarMessage ? (
					<Snackbar
						onRemove={ () => setSnackbarMessage( '' ) }
						actions={ [] }
						className="blocklane-pro-extensions__snackbar"
					>
						{ snackbarMessage }
					</Snackbar>
				) : null }
			</Flex>
		</div>
	);
};
