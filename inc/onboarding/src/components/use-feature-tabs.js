/**
 * useFeatureTabs — the Settings screen's composition of the URL-mirrored
 * state: the registry entry, the tab shell, the selected row and its `item`
 * param, Escape, the tab's rows from the placement table, and one search
 * (spec 2026-10-12-c-settings.md §3.4, §3.6).
 *
 * ONE owner for everything the URL mirrors on the screen — the tab (through
 * useTabShell) and the selected row (here, the one writer of `item`) — keyed
 * by the registry entry, so the screen types its slug once and never a param
 * name. The tabs and their sections are the entry's (screens/meta/); where a
 * row sits is LAYOUT's (the placement table). Does not own the details
 * card's open preference or any write path.
 */
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { SCREENS, requireScreen } from '../screens/registry';
import { getRouteParam, setRouteParam } from '../router';
import { useTabShell } from './use-tab-shell';
import { matchesSearch, resolveInitialTab } from './feature-tabs';

/**
 * The registry entry for a screen this hook drives. Throws for an unknown
 * slug and for an entry missing either param it mirrors.
 *
 * @param {string} slug      Registry slug.
 * @param {Array}  [screens] The registry (injectable for tests).
 * @return {Object} The entry.
 */
export const requireFeatureScreen = ( slug, screens = SCREENS ) => {
	const entry = requireScreen( slug, screens );
	if ( ! entry.deepLinkParam || ! entry.tabParam ) {
		throw new Error(
			`Feature-tab screen "${ slug }" must declare deepLinkParam and tabParam in its screens/meta file`
		);
	}
	return entry;
};

/**
 * @param {Object}                     options
 * @param {string}                     options.screen       Registry slug.
 * @param {Array}                      options.placement    LAYOUT: [ { slug, tab, section? } ].
 * @param {string[]}                   options.knownSlugs   The rows this build lists.
 * @param {string}                     [options.deepLink]   The Shell's item prop, unvalidated.
 * @param {(slug: string) => string[]} options.textOf       What search matches, per row.
 * @param {string}                     [options.defaultTab] A derived default tab (SD16).
 * @return {Object} { entry, screen, shell, tab, tabMeta, tabSlugs, visibleSlugs,
 *                  search, setSearch, selected, select, unselect }
 */
export function useFeatureTabs( {
	screen,
	placement,
	knownSlugs,
	deepLink,
	textOf,
	defaultTab,
} ) {
	const [ entry ] = useState( () => requireFeatureScreen( screen ) );
	const fallbackTab = defaultTab ?? entry.tabs[ 0 ].slug;

	// Resolved once, at mount: the Shell remounts a screen on navigation.
	const [ initial ] = useState( () => {
		const item =
			deepLink && knownSlugs.includes( deepLink ) ? deepLink : null;
		return {
			item,
			tab: resolveInitialTab( {
				tabs: entry.tabs,
				placement,
				defaultTab: fallbackTab,
				tabParam: getRouteParam( entry.tabParam ),
				itemSlug: item,
				knownSlugs,
			} ),
		};
	} );

	const linkClears = useMemo( () => [ entry.deepLinkParam ], [ entry ] );

	const [ selected, setSelected ] = useState( initial.item );
	const unselect = useCallback( () => setSelected( null ), [] );
	// Selecting the selected row unselects it (the info button toggles).
	const select = useCallback(
		( slug ) => setSelected( ( prev ) => ( prev === slug ? null : slug ) ),
		[]
	);

	// The one writer of the item param. Its first run drops an unknown item.
	useEffect( () => {
		setRouteParam( entry.deepLinkParam, selected );
	}, [ entry, selected ] );

	const shell = useTabShell( {
		screen: entry.slug,
		initialTab: initial.tab,
		defaultTab,
		// A tab switch unselects: the row would be on a tab no longer showing.
		onSwitch: unselect,
		linkClears,
	} );

	useEffect( () => {
		if ( ! selected ) {
			return undefined;
		}
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				unselect();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ selected, unselect ] );

	// One query for every tab: it persists across a switch.
	const [ search, setSearch ] = useState( '' );
	const tabSlugs = useMemo(
		() =>
			placement
				.filter(
					( p ) =>
						p.tab === shell.tab && knownSlugs.includes( p.slug )
				)
				.map( ( p ) => p.slug ),
		[ placement, knownSlugs, shell.tab ]
	);
	const visibleSlugs = useMemo(
		() =>
			tabSlugs.filter( ( s ) => matchesSearch( search, ...textOf( s ) ) ),
		[ tabSlugs, search, textOf ]
	);

	return {
		entry,
		screen: entry.slug,
		shell,
		tab: shell.tab,
		tabMeta: entry.tabs.find( ( t ) => t.slug === shell.tab ),
		tabSlugs,
		visibleSlugs,
		search,
		setSearch,
		selected,
		select,
		unselect,
	};
}
