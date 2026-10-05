/**
 * useFeatureTabs — the composition a toggle screen (Extensions, Advanced)
 * used to assemble by hand: the registry entry, the tab shell, the pinned
 * item and its URL param, bucketing and search for the active tab.
 *
 * ONE owner for everything the URL mirrors on such a screen — the active
 * tab (through useTabShell) and the pinned item (here) — keyed by the
 * registry entry. A screen types its registry slug exactly once, never a
 * param name, and never writes the URL itself. Before this, both screens
 * re-derived all of it and the copies had begun to drift: three docblocks
 * promised "an unknown slug is dropped from the URL" and none did it,
 * because no single place owned the item param.
 *
 * Owns: the registry read (once, and loud on a typo), the initial tab and
 * item resolution (once), the tab shell call with linkClears derived from
 * the registry, the pinned item and its URL mirror (the one writer of
 * ?ext= / ?adv=), Escape, bucketing, search. Does NOT own: the help
 * preference, hover preview, the drawer, any write path — the screen keeps
 * what the URL does not mirror (the parked core-help-tabs spec moves the
 * drawer's help half later without touching this).
 */
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { SCREENS, requireScreen } from '../screens/registry';
import { getRouteParam, setRouteParam } from '../router';
import { useTabShell } from './use-tab-shell';
import {
	allOn as allOnOf,
	bucketByCategory,
	matchesSearch,
	resolveInitialTab,
} from './feature-tabs';

/**
 * The registry entry for a feature-tab screen. Throws for an unknown slug
 * and for an entry missing the two params this hook mirrors — the
 * guarantee names its closure, and a typo lands in the screen's
 * ErrorBoundary with a message instead of rendering the Dashboard title.
 *
 * @param {string} slug      Registry slug.
 * @param {Array}  [screens] The registry (injectable for tests).
 * @return {Object} The entry.
 */
export const requireFeatureScreen = ( slug, screens = SCREENS ) => {
	const entry = requireScreen( slug, screens );
	if ( ! entry.deepLinkParam || ! entry.tabParam ) {
		throw new Error(
			`Feature-tab screen "${ slug }" must declare deepLinkParam and tabParam in screens/registry.js`
		);
	}
	return entry;
};

/**
 * @param {Object}                     options
 * @param {string}                     options.screen     Registry slug — the ONE literal a toggle screen types.
 * @param {Array}                      options.categories [ { slug, label } ], module constant; [0] is the default tab.
 * @param {Object}                     options.categoryOf Item slug → category slug, module constant.
 * @param {Array}                      options.knownSlugs Every item slug the screen can list; a deep link is checked against it.
 * @param {Array}                      options.slugs      What to list, in order (memoized by the caller).
 * @param {string}                     [options.deepLink] The Shell's deep-link prop, raw and unvalidated.
 * @param {(slug: string) => string[]} options.textOf     ( slug ) → string[] the search box matches; module-level.
 * @param {(slug: string) => boolean}  options.isOn       ( slug ) → boolean; read in render only.
 * @return {Object} { entry, screen, shell, tab, tabLabel, tabSlugs,
 *                  visibleSlugs, allOn, search, setSearch, pinned, pin,
 *                  open, unpin }
 */
export function useFeatureTabs( {
	screen,
	categories,
	categoryOf,
	knownSlugs,
	slugs,
	deepLink,
	textOf,
	isOn,
} ) {
	// Read once; loud on a typo.
	const [ entry ] = useState( () => requireFeatureScreen( screen ) );
	const defaultTab = categories[ 0 ].slug;

	// Resolved once, lazily: the item deep link is validated against the
	// screen's own slugs, and the tab comes from the item first, then the
	// tab param, then the default (feature-tabs.js). Later prop changes are
	// ignored by design — the Shell remounts a screen on navigation.
	const [ initial ] = useState( () => {
		const item =
			deepLink && knownSlugs.includes( deepLink ) ? deepLink : null;
		return {
			item,
			tab: resolveInitialTab( {
				categories,
				categoryOf,
				defaultTab,
				tabParam: getRouteParam( entry.tabParam ),
				itemSlug: item,
				knownSlugs,
			} ),
		};
	} );

	// Derived from the registry, stable identity (useTabShell memoizes on it).
	const linkClears = useMemo( () => [ entry.deepLinkParam ], [ entry ] );

	const [ pinned, setPinned ] = useState( initial.item );
	const unpin = useCallback( () => setPinned( null ), [] );
	// Toggle semantics: pinning the pinned row unpins it (the row's details
	// button). open() pins without toggling (the row's switch, which must
	// settle the panel on the row it just changed, never close it).
	const pin = useCallback(
		( slug ) => setPinned( ( prev ) => ( prev === slug ? null : slug ) ),
		[]
	);
	const open = useCallback( ( slug ) => setPinned( slug ), [] );

	// The URL mirror — the one writer of the item param. Its mount-time run
	// drops an unknown deep link from the URL (the item resolved to null),
	// rewrites a valid one unchanged, and mirrors every later pin/unpin.
	// The same pattern the tab shell uses for the tab param; both build
	// from the live URL, so their order does not matter.
	useEffect( () => {
		setRouteParam( entry.deepLinkParam, pinned );
	}, [ entry, pinned ] );

	const shell = useTabShell( {
		screen: entry.slug,
		param: entry.tabParam,
		tabs: categories,
		defaultTab,
		initialTab: initial.tab,
		// Leaving a tab unpins — the pinned row would be on a tab that is no
		// longer showing.
		onSwitch: unpin,
		linkClears,
	} );

	// Escape unpins while something is pinned.
	useEffect( () => {
		if ( ! pinned ) {
			return undefined;
		}
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				unpin();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ pinned, unpin ] );

	// This tab's rows: bucketed by category (every slug lands on exactly one
	// tab, every tab has a key), then the search box narrows them — search is
	// scoped to the tab it sits in, like core's Plugins screen search, and
	// the query persists across a switch.
	const [ search, setSearch ] = useState( '' );
	const tabSlugs = useMemo(
		() => bucketByCategory( slugs, categories, categoryOf )[ shell.tab ],
		[ slugs, categories, categoryOf, shell.tab ]
	);
	const visibleSlugs = useMemo(
		() =>
			tabSlugs.filter( ( s ) => matchesSearch( search, ...textOf( s ) ) ),
		[ tabSlugs, search, textOf ]
	);
	const tabLabel = categories.find( ( c ) => c.slug === shell.tab ).label;

	return {
		entry,
		screen: entry.slug,
		shell,
		tab: shell.tab,
		tabLabel,
		tabSlugs,
		visibleSlugs,
		allOn: allOnOf( tabSlugs, isOn ),
		search,
		setSearch,
		pinned,
		pin,
		open,
		unpin,
	};
}
