/**
 * The Settings list's tab model, as pure functions (spec
 * 2026-10-12-c-settings.md §3.2, §3.4; Amendment 1 SD16). ONE placement
 * table — screens/settings/layout.js's LAYOUT, every row's slug, tab and
 * section in display order — decides where a row is listed; the tabs and
 * sections themselves are the screen's meta entry. No React and no i18n:
 * jest runs these as plain functions (feature-tabs.test.js).
 *
 * `placement` is [ { slug, tab, section? } ]; a `tab` is
 * { slug, label, sections?: [ { slug, label } ] }.
 */

/**
 * The tab a row is listed on, or null when the placement table does not name
 * it (the row lockstep refuses that, RW1; nothing here guesses a tab).
 *
 * @param {string} slug      Row slug.
 * @param {Array}  placement LAYOUT.
 * @return {string|null} The tab's slug.
 */
export const tabOf = ( slug, placement ) =>
	placement.find( ( p ) => p.slug === slug )?.tab ?? null;

/**
 * A tab's rows, grouped by its sections in meta order, each section's rows in
 * placement order. A tab without sections is one unlabeled section. A section
 * with no rows is kept (FeatureList hides it), so the caller sees the shape.
 *
 * @param {string[]} slugs     The rows to group (any order).
 * @param {Object}   tab       The tab's meta entry.
 * @param {Array}    placement LAYOUT.
 * @return {Array} [ { slug, label|null, slugs } ].
 */
export const groupBySection = ( slugs, tab, placement ) => {
	const wanted = new Set( slugs );
	const placed = placement.filter(
		( p ) => p.tab === tab.slug && wanted.has( p.slug )
	);
	if ( ! tab.sections?.length ) {
		return [
			{
				slug: tab.slug,
				label: null,
				slugs: placed.map( ( p ) => p.slug ),
			},
		];
	}
	return tab.sections.map( ( section ) => ( {
		slug: section.slug,
		label: section.label,
		slugs: placed
			.filter( ( p ) => p.section === section.slug )
			.map( ( p ) => p.slug ),
	} ) );
};

/**
 * Live rows first, each half in its own order: a stable partition (F2).
 *
 * @param {string[]}                  slugs  Rows in display order.
 * @param {(slug: string) => boolean} isLive Whether a row is live here.
 * @return {string[]} The rows, live first.
 */
export const liveFirst = ( slugs, isLive ) => [
	...slugs.filter( isLive ),
	...slugs.filter( ( s ) => ! isLive( s ) ),
];

/**
 * The tab a screen opens on when nothing asks for another (SD16): the first
 * tab, in meta order, whose live rows are at least its Pro rows. In an
 * edition where every row is live that is the first tab; where a tab is
 * mostly features this build does not include, the screen opens on the first
 * tab that is mostly its own. No such tab: the first.
 *
 * @param {Array}                     tabs      The meta's tabs.
 * @param {Array}                     placement LAYOUT.
 * @param {string[]}                  present   The rows this build lists.
 * @param {(slug: string) => boolean} isPro     Whether a row is a Pro row.
 * @return {string} The tab's slug.
 */
export const liveFirstTab = ( tabs, placement, present, isPro ) => {
	const listed = new Set( present );
	const found = tabs.find( ( tab ) => {
		const rows = placement.filter(
			( p ) => p.tab === tab.slug && listed.has( p.slug )
		);
		const pro = rows.filter( ( p ) => isPro( p.slug ) ).length;
		return rows.length - pro >= pro;
	} );
	return ( found || tabs[ 0 ] ).slug;
};

/**
 * Which tab a screen opens on: a known item wins over the tab param (it
 * names a row, and selecting a row you cannot see is the one outcome the
 * tabs must never produce), then a known tab param, then the default.
 *
 * @param {Object} options
 * @param {Array}  options.tabs       The meta's tabs.
 * @param {Array}  options.placement  LAYOUT.
 * @param {string} options.defaultTab The default tab.
 * @param {string} [options.tabParam] The tab param from the URL.
 * @param {string} [options.itemSlug] The item param from the URL.
 * @param {Array}  options.knownSlugs The rows this build lists.
 * @return {string} The tab's slug.
 */
export const resolveInitialTab = ( {
	tabs,
	placement,
	defaultTab,
	tabParam,
	itemSlug,
	knownSlugs,
} ) => {
	if ( itemSlug && knownSlugs.includes( itemSlug ) ) {
		return tabOf( itemSlug, placement ) ?? defaultTab;
	}
	if ( tabParam && tabs.some( ( t ) => t.slug === tabParam ) ) {
		return tabParam;
	}
	return defaultTab;
};

/**
 * Case-insensitive substring match of a query against any of the texts; an
 * empty query matches everything.
 *
 * @param {string}    query The query.
 * @param {...string} texts Texts to match (nullish is skipped).
 * @return {boolean} Whether any matches.
 */
export const matchesSearch = ( query, ...texts ) => {
	const q = ( query || '' ).trim().toLowerCase();
	if ( ! q ) {
		return true;
	}
	return texts.some( ( t ) => ( t || '' ).toLowerCase().includes( q ) );
};
