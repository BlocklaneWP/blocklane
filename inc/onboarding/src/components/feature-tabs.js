/**
 * The toggle screens' tab model (Extensions, Advanced), as pure functions:
 * the category list IS the tab list, an item's tab is its category, and a
 * slug with no mapping lands on the LAST tab so a new item never vanishes.
 * No React, no i18n — the two screens share these instead of each keeping
 * a copy, and jest exercises them as plain functions (feature-tabs.test.js).
 *
 * `categories` is [ { slug, label } ] in tab order; `categoryOf` maps an
 * item slug to a category slug.
 */

/**
 * The tab an item belongs on. A slug with no mapping — or one mapped to a
 * category that is not in the tab list (a typo, or a category renamed
 * without its map) — lands on the LAST tab: an item must never vanish, and
 * a bad map entry must never take the screen down.
 *
 * @param {string} slug       Item slug.
 * @param {Array}  categories Tab list.
 * @param {Object} categoryOf Item slug → category slug.
 * @return {string} Category slug.
 */
export const tabForSlug = ( slug, categories, categoryOf ) => {
	const mapped = categoryOf[ slug ];
	return mapped && categories.some( ( c ) => c.slug === mapped )
		? mapped
		: categories[ categories.length - 1 ].slug;
};

/**
 * Bucket items under their tab, preserving order within each tab. Every
 * tab is present (possibly empty), and every item lands in exactly one.
 *
 * @param {Array}  slugs      Item slugs in display order.
 * @param {Array}  categories Tab list.
 * @param {Object} categoryOf Item slug → category slug.
 * @return {Object} Category slug → item slugs.
 */
export const bucketByCategory = ( slugs, categories, categoryOf ) => {
	const buckets = {};
	categories.forEach( ( c ) => {
		buckets[ c.slug ] = [];
	} );
	slugs.forEach( ( s ) => {
		buckets[ tabForSlug( s, categories, categoryOf ) ].push( s );
	} );
	return buckets;
};

/**
 * Which tab a screen opens on. An item deep link (?ext=, ?adv=) wins over
 * a tab param — it names a row, and pinning a row you cannot see is the
 * one outcome the tabs must never produce. An unknown item is ignored —
 * useFeatureTabs pins nothing and its URL mirror drops the param on mount;
 * an unknown tab param falls back to the default.
 *
 * @param {Object} options
 * @param {Array}  options.categories Tab list.
 * @param {Object} options.categoryOf Item slug → category slug.
 * @param {string} options.defaultTab Tab when nothing else applies.
 * @param {string} [options.tabParam] The tab param from the URL.
 * @param {string} [options.itemSlug] The item param from the URL.
 * @param {Array}  options.knownSlugs Every item slug the screen lists.
 * @return {string} Category slug.
 */
export const resolveInitialTab = ( {
	categories,
	categoryOf,
	defaultTab,
	tabParam,
	itemSlug,
	knownSlugs,
} ) => {
	if ( itemSlug && knownSlugs.includes( itemSlug ) ) {
		return tabForSlug( itemSlug, categories, categoryOf );
	}
	if ( tabParam && categories.some( ( c ) => c.slug === tabParam ) ) {
		return tabParam;
	}
	return defaultTab;
};

/**
 * Case-insensitive substring match of a search query against any of the
 * given texts; an empty query matches everything.
 *
 * @param {string}    query Search query.
 * @param {...string} texts Texts to match against (nullish is skipped).
 * @return {boolean} Whether any text matches.
 */
export const matchesSearch = ( query, ...texts ) => {
	const q = ( query || '' ).trim().toLowerCase();
	if ( ! q ) {
		return true;
	}
	return texts.some( ( t ) => ( t || '' ).toLowerCase().includes( q ) );
};

/**
 * Whether every listed item is on — false for an empty list, so an empty
 * tab never reads as "all on" (`[].every` is true).
 *
 * @param {Array}    slugs Item slugs on the tab.
 * @param {Function} isOn  ( slug ) → boolean.
 * @return {boolean} Whether all are on.
 */
export const allOn = ( slugs, isOn ) => slugs.length > 0 && slugs.every( isOn );
