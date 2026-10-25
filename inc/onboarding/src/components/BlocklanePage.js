/**
 * BlocklanePage — the one page every dashboard screen goes through.
 *
 * Authored for C1 of the admin-dashboard redesign (spec "Blocklane Admin",
 * 2026-10-10: §3, §7 C1, Amendments D, E, G, AF, AG), from the Figma frames
 * (Settings `41:1454`, SEO `64:1962`, a module screen `104:31419`, Home
 * `104:37820`, the AI & MCP disabled frame `105:45904`, `Settings/Header`
 * `173:194700`, `BL/DetailsCard` `166:3503`) and core's own components. It is
 * the ONLY renderer of: the page h1 (admin-ui `Page`), the header and its
 * actions, the breadcrumb slot, the tab row, the content area, the details
 * column, the toast host and the disabled-screen treatment. ESLint refuses an
 * `<h1>` anywhere else in the app (eslint.config.cjs), so a second header
 * cannot be written by copying this one.
 *
 * Layout (Amendment D, as amended by AG, AJ and the follow-up's item 9):
 * the app region is a frame in the admin scheme's menu color that never
 * scrolls; on it sit the page card (admin-ui `Page`, the frame's height minus
 * its gaps and the ONE scroll container of the page's content, its header
 * sticky at the card's top) and, when open, the 400px details card, which
 * scrolls on its own beside it. That is core's boot layout (Appearance →
 * Fonts), mirrored in blocklane-page/style.css under a MIRROR CONTRACT, with
 * ONE deliberate departure (decision F-2, 2026-10-13): in a window under
 * about 800px tall core lets the document scroll, and this frame stays
 * pinned to the viewport, so the card's corners and the toast host never
 * leave the screen. The contract's comment names it; it is not drift.
 * Gutters are core's: header and content pad 16/24
 * (`--wpds-dimension-padding-lg` / `-2xl`), with no wrapper and no 48 anywhere
 * (Amendment AG). Values come from `--wpds-*` tokens in blocklane-page.css.
 *
 * Decisions this file records (the open items Amendment AF left to C1):
 * - Focus. `Page` forwards no ref to its h1, so the page reads the h1 from its
 *   own root after mount and focuses it (tabIndex -1: a focus target, never a
 *   Tab stop), on mount and whenever `focusKey` changes. Like core's own
 *   programmatic focus targets (the `[role=region]` NavigableRegion), it
 *   paints no ring: core draws one on a region only in its region-navigation
 *   mode (components/style.css `.is-focusing-regions [role=region]:focus`),
 *   never for an ordinary programmatic move. The heading is still focused
 *   and announced.
 * - A non-string title must come with `ariaLabel` (the region's name); the
 *   page throws without one rather than ship an unnamed region.
 * - The visual sits where admin-ui puts it, before the h1 and outside it,
 *   decorative (aria-hidden): the Home frame draws the mark 32px left of the
 *   title, the same place. Only Home carries one; the SEO frame dropped its
 *   mark.
 * - Sticky. admin-ui's header is `position: sticky` at its scroll
 *   container's top. The card is that container, so the header holds the
 *   card's top while the content scrolls under it (blocklane-page/style.css;
 *   figma-parity's PREFLIGHT#scroll-card fails a header that scrolls away).
 *   The tab row stays pinned with it (decision F-4, 2026-10-13): it is
 *   sticky too, offset by the header's own height, which this page measures
 *   (`--blocklane-page-header-block-size` on the card) because a subtitle
 *   that wraps makes the header taller. The same preflight fails a tab row
 *   that scrolls away under a pinned header.
 * - Disabled. A turned-off screen keeps its title AND its subtitle (the off
 *   frames draw both); only the actions, breadcrumbs, tabs and details
 *   leave. Its notice sits in a `data-notice="info"` wrapper inside a
 *   `data-column` body, the frames' `@notice:info` inside `@column`.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	useState,
	useRef,
	useCallback,
	useLayoutEffect,
} from '@wordpress/element';
import { Button, Notice, SnackbarList } from '@wordpress/components';
import { help, closeSmall } from '@wordpress/icons';
import { Page } from '@wordpress/admin-ui';

import { requireScreen } from '../screens/registry';
import { settingsHref } from '../screens/settings/href';
import { StatusBadge } from './StatusBadge';

/** The width at and above which the details card starts open (Amendment F 4c). */
const DETAILS_OPEN_MIN_WIDTH = 1280;

const detailsKey = ( screen ) => `blocklane-pro-help:${ screen }`;

const tabId = ( screen, slug ) => `blocklane-pro-tab-${ screen }-${ slug }`;
const panelId = ( screen, slug ) =>
	`blocklane-pro-tabpanel-${ screen }-${ slug }`;
/**
 * The details card's id for a screen: the one a row's info button names in
 * `aria-controls` while its card is open.
 *
 * @param {string} screen Registry slug.
 * @return {string} The id.
 */
export const detailsIdOf = ( screen ) => `blocklane-page-details-${ screen }`;

/**
 * The details card's open/closed preference for a screen, persisted under the
 * key the parity capture sets (`blocklane-pro-help:<screen>`, spec §6). With
 * nothing stored it starts open on a wide viewport and closed below 1280px,
 * where a 400px card would squeeze the list (Amendment F 4c), unless the
 * screen's registry entry says `detailsDefault: 'closed'` (the Dashboard,
 * decision D-H5): then it starts closed at every width. This hook is the
 * one door for that default; a screen never passes its own. Storage
 * failures (private windows) only mean the choice does not stick.
 *
 * @param {string} screen Registry slug.
 * @return {[boolean, (open: boolean) => void]} [open, setOpen].
 */
export const useDetailsPreference = ( screen ) => {
	const [ open, setOpenState ] = useState( () => {
		try {
			const stored = window.localStorage.getItem( detailsKey( screen ) );
			if ( 'open' === stored || 'closed' === stored ) {
				return 'open' === stored;
			}
		} catch ( e ) {
			// Fall through to the screen's default.
		}
		if ( 'closed' === requireScreen( screen ).detailsDefault ) {
			return false;
		}
		return window.innerWidth >= DETAILS_OPEN_MIN_WIDTH;
	} );
	const setOpen = useCallback(
		( next ) => {
			setOpenState( next );
			try {
				window.localStorage.setItem(
					detailsKey( screen ),
					next ? 'open' : 'closed'
				);
			} catch ( e ) {
				// The preference just won't persist this session.
			}
		},
		[ screen ]
	);
	return [ open, setOpen ];
};

/**
 * The toast state a screen hands to its page: `show( message )` queues a
 * snackbar, `remove( id )` drops one. Every message gets its own id, so the
 * same sentence twice in a row is a new toast (and a fresh timer), not an
 * update to one already showing.
 *
 * @return {{ notices: Array, show: (content: string) => void, remove: (id: string) => void, clear: () => void }} Toasts.
 */
export const useToasts = () => {
	const [ notices, setNotices ] = useState( [] );
	const next = useRef( 0 );
	const show = useCallback( ( content ) => {
		next.current += 1;
		const id = `blocklane-toast-${ next.current }`;
		// One toast at a time: a newer message replaces the one showing, the
		// way the screens' single snackbar always behaved.
		setNotices( [ { id, content } ] );
	}, [] );
	const remove = useCallback(
		( id ) => setNotices( ( list ) => list.filter( ( n ) => n.id !== id ) ),
		[]
	);
	const clear = useCallback( () => setNotices( [] ), [] );
	return { notices, show, remove, clear };
};

/**
 * The tab row: real anchors (⌘/middle-click open the tab's own URL, a plain
 * click switches client-side through the shell, Space activates), each with
 * `aria-selected`. Not `Button`: it strips `aria-selected` from an anchor.
 * Only the selected tab names its panel (`aria-controls`), because only the
 * active panel is mounted.
 *
 * @param {Object} props
 * @param {string} props.screen Registry slug.
 * @param {Array}  props.items  The entry's tabs, [ { slug, label } ].
 * @param {Object} props.tabs   { label, shell }.
 */
const TabRow = ( { screen, items, tabs } ) => {
	const { label, shell } = tabs;
	return (
		<div
			role="tablist"
			aria-label={ label }
			className="blocklane-page__tabs"
		>
			{ items.map( ( item ) => {
				const selected = shell.tab === item.slug;
				return (
					<a
						key={ item.slug }
						id={ tabId( screen, item.slug ) }
						data-slug={ item.slug }
						role="tab"
						href={ shell.tabHref( item.slug ) }
						aria-selected={ selected }
						aria-controls={
							selected ? panelId( screen, item.slug ) : undefined
						}
						className="blocklane-page__tab"
						onClick={ shell.onTabClick }
						onKeyDown={ shell.onTabKeyDown }
					>
						{ item.label }
					</a>
				);
			} ) }
		</div>
	);
};

/**
 * The one disabled-screen treatment: a turned-off screen keeps its page title
 * and says why, with a link to the toggle that governs it. The copy is the
 * screen's (each says what "off" leaves in place); the notice and the link
 * label default to the shared sentences.
 *
 * @param {Object} props
 * @param {Object} props.disabled { body, slug, notice?, actionLabel?, href? }.
 */
const DisabledBody = ( { disabled } ) => (
	<div className="blocklane-page__disabled" data-column="disabled">
		<div data-notice="info">
			<Notice status="info" isDismissible={ false }>
				{ disabled.notice ??
					__( 'This screen is turned off.', 'blocklane' ) }
			</Notice>
		</div>
		<p className="blocklane-page__disabled-body">{ disabled.body }</p>
		<div>
			<Button
				variant="primary"
				__next40pxDefaultSize
				href={ disabled.href ?? settingsHref( disabled.slug ) }
			>
				{ disabled.actionLabel ??
					__( 'Open Blocklane Settings', 'blocklane' ) }
			</Button>
		</div>
	</div>
);

/**
 * The page.
 *
 * @param {Object}                  props
 * @param {string}                  props.screen                Registry slug; title and subtitle come from it.
 * @param {string|JSX.Element}      [props.title]               Overrides the registry title.
 * @param {string}                  [props.subtitle]            Overrides the registry subtitle (null hides it).
 * @param {string}                  [props.ariaLabel]           The page region's name; required when the title is not a string.
 * @param {JSX.Element}             [props.visual]              A decorative mark before the title.
 * @param {JSX.Element}             [props.actions]             Header actions, before the details toggle.
 * @param {JSX.Element}             [props.breadcrumbs]         A `@wordpress/ui` Breadcrumb trail.
 * @param {Object}                  [props.tabs]                { label, shell } — the tab row and its panel; the
 *                                                              tabs themselves are the registry entry's.
 * @param {Object}                  [props.details]             { content, title?, badge?, about?, header? } — the
 *                                                              details card; `badge` is { label, status } (StatusBadge),
 *                                                              `about` the row slug it describes (data-about), and
 *                                                              `header: false` when the content brings its own (the
 *                                                              SEO row editor).
 * @param {boolean}                 [props.detailsOpen]         Controlled open state (with onDetailsOpenChange).
 * @param {(open: boolean) => void} [props.onDetailsOpenChange] Controlled setter.
 * @param {Object}                  [props.toasts]              useToasts() value (or { notices, remove }).
 * @param {Object}                  [props.disabled]            { body, slug, notice?, actionLabel?, href? }.
 * @param {boolean}                 [props.hasPadding]          Content padding (default true); false for edge-to-edge lists.
 * @param {string}                  [props.focusKey]            The h1 is refocused whenever this changes.
 * @param {JSX.Element|null}        [props.children]            The content.
 */
export const BlocklanePage = ( {
	screen,
	title,
	subtitle,
	ariaLabel,
	visual,
	actions,
	breadcrumbs,
	tabs,
	details,
	detailsOpen,
	onDetailsOpenChange,
	toasts,
	disabled,
	hasPadding = true,
	focusKey,
	children,
} ) => {
	const entry = requireScreen( screen );
	const heading = title ?? entry.title;
	const lede = undefined === subtitle ? entry.subtitle : subtitle;
	const regionLabel = 'string' === typeof heading ? heading : ariaLabel;
	if ( ! regionLabel ) {
		throw new Error(
			`BlocklanePage "${ screen }": a non-string title needs ariaLabel`
		);
	}

	// Open state: the screen's when it controls it (a pinned row forces the
	// card open on Settings), the stored preference otherwise.
	const [ storedOpen, setStoredOpen ] = useDetailsPreference( screen );
	const isControlled = undefined !== detailsOpen;
	const open =
		!! details && ! disabled && ( isControlled ? detailsOpen : storedOpen );
	const setOpen = isControlled ? onDetailsOpenChange : setStoredOpen;

	const root = useRef( null );
	useLayoutEffect( () => {
		const h1 = root.current?.querySelector( 'h1' );
		if ( ! h1 ) {
			return;
		}
		h1.tabIndex = -1;
		h1.focus( { preventScroll: true } );
	}, [ focusKey, disabled ] );

	// The card is about the SCREEN, whatever the header says on an editor
	// sub-page: the entry's own place name when it declares one (Settings:
	// "About Blocklane Settings"), else "About <title>". MIRROR: the sitemap
	// reads this same order (bin/dashboard-sitemap.mjs, SM2).
	const detailsTitle =
		details?.title ??
		entry.detailsTitle ??
		sprintf(
			/* translators: %s: the screen's title. */
			__( 'About %s', 'blocklane' ),
			entry.title
		);
	const bareDetails = false === details?.header;

	const toggleLabel = open
		? __( 'Hide details', 'blocklane' )
		: __( 'Show details', 'blocklane' );

	const headerActions =
		actions || ( details && ! disabled ) ? (
			<>
				{ disabled ? null : actions }
				{ details && ! disabled ? (
					<Button
						size="compact"
						icon={ help }
						label={ toggleLabel }
						aria-expanded={ open }
						aria-controls={
							open ? detailsIdOf( screen ) : undefined
						}
						className="blocklane-page__details-toggle"
						onClick={ () => setOpen( ! open ) }
					/>
				) : null }
			</>
		) : undefined;

	const showTabs = !! tabs && ! disabled;

	// The tab row is pinned under the sticky header (F-4): its offset is the
	// header's measured height, kept current as the header grows or shrinks.
	// Only a page with a tab row measures; without ResizeObserver (a test
	// environment) the row pins at the card's top, under the header.
	useLayoutEffect( () => {
		const card = root.current?.querySelector( '.blocklane-page__card' );
		const header = card?.firstElementChild;
		if ( ! showTabs || ! header ) {
			return undefined;
		}
		const pin = () =>
			card.style.setProperty(
				'--blocklane-page-header-block-size',
				`${ header.offsetHeight }px`
			);
		pin();
		if ( 'function' !== typeof window.ResizeObserver ) {
			return undefined;
		}
		const observer = new window.ResizeObserver( pin );
		observer.observe( header );
		return () => observer.disconnect();
	}, [ showTabs ] );
	const content = disabled ? (
		<DisabledBody disabled={ disabled } />
	) : (
		children
	);

	return (
		<div
			ref={ root }
			className={ 'blocklane-page' + ( open ? ' is-details-open' : '' ) }
		>
			<Page
				className="blocklane-page__card"
				title={ heading }
				subTitle={ lede || undefined }
				ariaLabel={ regionLabel }
				visual={ visual }
				actions={ headerActions }
				breadcrumbs={ disabled ? undefined : breadcrumbs }
				showSidebarToggle={ false }
			>
				{ showTabs ? (
					<TabRow
						screen={ screen }
						items={ entry.tabs }
						tabs={ tabs }
					/>
				) : null }
				<div
					className={
						'blocklane-page__content' +
						( hasPadding || disabled ? ' has-padding' : '' )
					}
				>
					{ showTabs ? (
						<div
							role="tabpanel"
							id={ panelId( screen, tabs.shell.tab ) }
							aria-labelledby={ tabId( screen, tabs.shell.tab ) }
							className="blocklane-page__panel"
						>
							{ content }
						</div>
					) : (
						content
					) }
				</div>
				<div className="blocklane-page__toasts">
					<SnackbarList
						notices={ toasts?.notices ?? [] }
						onRemove={ toasts?.remove ?? ( () => {} ) }
					/>
				</div>
			</Page>
			{ open ? (
				<aside
					id={ detailsIdOf( screen ) }
					data-about={ details.about || undefined }
					className={
						'blocklane-page__details' +
						( bareDetails ? ' is-bare' : '' )
					}
					aria-label={ detailsTitle }
				>
					{ bareDetails ? (
						details.content
					) : (
						<>
							<div className="blocklane-page__details-header">
								<div className="blocklane-page__details-heading">
									<h2 className="blocklane-page__details-title">
										{ detailsTitle }
									</h2>
									{ details.badge ? (
										<StatusBadge
											status={ details.badge.status }
										>
											{ details.badge.label }
										</StatusBadge>
									) : null }
								</div>
								<Button
									size="compact"
									icon={ closeSmall }
									label={ __( 'Close details', 'blocklane' ) }
									onClick={ () => {
										setOpen( false );
										// The card unmounts with the button that had
										// focus; hand focus back to the toggle that
										// reopens it, as core's sidebars do.
										root.current
											?.querySelector(
												'.blocklane-page__details-toggle'
											)
											?.focus();
									} }
								/>
							</div>
							<div className="blocklane-page__details-body">
								{ details.content }
							</div>
						</>
					) }
				</aside>
			) : null }
		</div>
	);
};
