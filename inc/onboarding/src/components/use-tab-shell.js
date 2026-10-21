/**
 * The tab-shell state machine shared by the tabbed screens (SEO, Forms, the
 * content-types screen, Settings — BlocklanePage renders the row):
 * real-anchor tabs (?<param>=slug) that ⌘/middle-click like any link but
 * switch client-side on a plain click, URL sync that keeps the active tab
 * bookmarkable (and normalizes an invalid param on mount), one-shot
 * section targeting for banner/CTA deep links, and the Space-key restore
 * the ARIA tabs pattern expects on anchor-rendered tabs. One
 * implementation on purpose — an a11y or behavior fix here lands on every
 * screen at once instead of drifting between near-identical copies.
 */
import {
	useState,
	useEffect,
	useCallback,
	useRef,
	useMemo,
} from '@wordpress/element';
import { routeUrl, isModifiedClick } from '../router';
import { requireScreen } from '../screens/registry';

/**
 * The registry entry of a tabbed screen. Throws for an entry that declares no
 * tabs or no tab param: the tabs live in screens/meta/<slug>.js (registry.js,
 * "Entry shape"), never in the screen component, so a screen that asks for a
 * tab shell without declaring them is a programming error with a name.
 *
 * @param {string} slug      Registry slug.
 * @param {Array}  [screens] The registry (injectable for tests).
 * @return {Object} The entry.
 */
export const requireTabScreen = ( slug, screens ) => {
	const entry = requireScreen( slug, screens );
	if (
		! entry.tabParam ||
		! Array.isArray( entry.tabs ) ||
		! entry.tabs.length
	) {
		throw new Error(
			`Tabbed screen "${ slug }" must declare tabParam and tabs in its screens/meta file`
		);
	}
	return entry;
};

/**
 * @param {Object}                 options
 * @param {string}                 options.screen       Screen slug (?screen=…); its registry entry
 *                                                      supplies the tabs, the param and the default.
 * @param {string}                 [options.initialTab] Deep-linked tab from the URL.
 * @param {string}                 [options.defaultTab] A default the screen DERIVES (Settings'
 *                                                      live-first tab, SD16); absent, the
 *                                                      first tab is the default.
 * @param {(slug: string) => void} [options.onSwitch]   Extra per-screen switch effect.
 * @param {Array}                  [options.linkClears] Params a tab LINK drops (the
 *                                                      screen's pinned-item param, e.g.
 *                                                      'ext') — see tabHref.
 * @return {Object} { tab, tabs, targetPanel, tabHref, switchTab, onTabClick,
 *                  onTabKeyDown } — the two handlers are stable across
 *                  renders and read the tab's slug from the anchor's
 *                  data-slug attribute (BlocklanePage sets it).
 */
export function useTabShell( {
	screen,
	initialTab,
	defaultTab: derivedDefault,
	onSwitch,
	linkClears = [],
} ) {
	// The entry is read once: the tabs are module data and never change
	// while a screen is mounted. These three lines are what
	// bin/dashboard-sitemap.mjs mirrors (MIRROR#hooks): the param, the tab
	// list and the default tab all come from the meta entry, and the first
	// tab is the default unless the screen derives one (an entry that says so
	// declares `tabDefault`, which the sitemap reads too).
	const entry = useMemo( () => requireTabScreen( screen ), [ screen ] );
	const param = entry.tabParam;
	const tabs = entry.tabs;
	const defaultTab = derivedDefault ?? entry.tabs[ 0 ].slug;

	const [ tab, setTab ] = useState(
		tabs.some( ( t ) => t.slug === initialTab ) ? initialTab : defaultTab
	);
	// A banner/CTA can target a section inside the destination tab; the tab
	// scrolls to it on mount. Cleared on any plain switch — one-shot.
	const [ targetPanel, setTargetPanel ] = useState( null );

	// The latest tab and onSwitch live in refs so switchTab and the two
	// event handlers can be created once: the tab row re-renders with its
	// screen, and stable handlers keep every anchor's props identical
	// between renders instead of handing each tab fresh closures.
	const tabRef = useRef( tab );
	tabRef.current = tab;
	const onSwitchRef = useRef( onSwitch );
	onSwitchRef.current = onSwitch;

	// Each tab's URL — the default tab clears the param for a clean URL. The
	// single source of the tab→URL mapping: the anchors' hrefs and the
	// URL-sync effect both derive from it, so a left-click can never end at
	// a different URL than a ⌘-click opens. A tab LINK additionally drops
	// the screen's pinned-item param (linkClears): the item deep link wins
	// over the tab param on load, so a link that kept it would open the
	// item's tab, not the linked one. A left-click's onSwitch unpins, and
	// the item param's own mirror (useFeatureTabs) drops it in the same
	// commit as this sync, which is what keeps the two paths equal; the sync
	// effect itself never drops it, so a deep-linked item stays in the URL
	// while it is pinned.
	const buildTabUrl = useCallback(
		( slug, clears ) =>
			routeUrl(
				{
					screen,
					[ param ]: slug === defaultTab ? null : slug,
					...Object.fromEntries( clears.map( ( k ) => [ k, null ] ) ),
				},
				{ from: 'current' }
			),
		[ screen, param, defaultTab ]
	);
	const tabHref = useCallback(
		( slug ) => buildTabUrl( slug, linkClears ),
		[ buildTabUrl, linkClears ]
	);

	const switchTab = useCallback( ( slug, panel = null ) => {
		// Activating the tab that is already showing is a no-op: there is
		// nothing to switch, and the per-screen onSwitch — which unpins a
		// detail or closes a row editor — must not fire for it (clicking the
		// current tab's label used to discard unsaved edits in the drawer).
		// A same-tab call WITH a section target still targets the section.
		if ( slug === tabRef.current && null === panel ) {
			return;
		}
		if ( onSwitchRef.current ) {
			onSwitchRef.current( slug );
		}
		setTargetPanel( panel );
		setTab( slug );
	}, [] );

	// The URL mirrors the tab state so the active tab is always
	// bookmarkable/shareable. Owning this in an effect (rather than inside
	// switchTab) also normalizes the URL on mount: an invalid or stale
	// param fell back to a real tab in the state initializer above, and
	// this writes that resolution back so the address bar never claims a
	// view that isn't rendered.
	useEffect( () => {
		window.history.replaceState( {}, '', buildTabUrl( tab, [] ) );
	}, [ tab, buildTabUrl ] );

	// One handler serves every tab: the slug rides on the anchor as
	// data-slug (BlocklanePage sets it), so the handlers never close over a
	// tab and stay stable for the life of the screen.
	const slugOf = ( event ) => event.currentTarget.getAttribute( 'data-slug' );

	// Each tab is a real link to its own deep view, ⌘/middle/right-clickable
	// like any anchor; a plain left-click is intercepted for the no-reload
	// client-side switch.
	const onTabClick = useCallback(
		( event ) => {
			if ( isModifiedClick( event ) ) {
				return;
			}
			event.preventDefault();
			switchTab( slugOf( event ) );
		},
		[ switchTab ]
	);

	// Anchors don't synthesize a click on Space the way native buttons do —
	// but the ARIA tabs pattern expects Space to activate a focused
	// role="tab", so restore it here. Enter already works: it fires a plain
	// click that onTabClick intercepts.
	const onTabKeyDown = useCallback(
		( event ) => {
			if (
				event.key === ' ' &&
				! event.metaKey &&
				! event.ctrlKey &&
				! event.shiftKey &&
				! event.altKey
			) {
				event.preventDefault();
				switchTab( slugOf( event ) );
			}
		},
		[ switchTab ]
	);

	return {
		tab,
		tabs,
		targetPanel,
		tabHref,
		switchTab,
		onTabClick,
		onTabKeyDown,
	};
}
