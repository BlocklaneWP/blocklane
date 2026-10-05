/**
 * Screen registry — the single source of truth for dashboard screens.
 *
 * The COPY of a screen (label, title, subtitle, icon, params) lives in its
 * own file under screens/meta/, re-exported one line per screen by
 * screens/meta.js; the COMPONENT lives one line per screen in
 * screens/components.js. Both indexes are line grammars
 * bin/generate-edition.php filters by the manifest, so an edition that does
 * not carry a screen's unit carries neither its component nor its copy — the
 * label of a feature this artifact does not contain is never compiled into
 * its bundle (#979). This file names no unit and reads no edition: it derives
 * SCREENS from what the two indexes still export.
 *
 * To add a dashboard screen: a file under screens/meta/ and a line in
 * screens/meta.js, a line in screens/components.js (and the screen file), the
 * slug in SCREEN_ORDER below, and in edition-manifest.json the unit's
 * `screen` plus the meta path in its `paths` — the generator's rule 16 refuses
 * a unit that declares a screen without owning its meta file, so the omission
 * fails the build rather than shipping the label everywhere. PHP mirrors the
 * list in Settings::screens_manifest() for wp-admin's submenu.
 *
 * Home is eager (bundled in the main chunk) for instant first paint; every other
 * screen is code-split via `Component: lazyScreen(...)` and rendered inside the
 * Shell's Suspense boundary, so the dashboard stays light as features grow.
 *
 * Entry shape (each screens/meta/<slug>.js default-exports one):
 *   slug          URL ?screen= value + nav key (unique)
 *   label         sidebar nav label
 *   title         sidebar header title for the active screen
 *   subtitle      sidebar header subtitle for the active screen
 *   icon          `@wordpress/icons` glyph
 *   Component      the screen component (eager import, or lazyScreen(...)),
 *                  joined in from components.js
 *   fullBleed      hide the CanvasHeader (screen owns its own header) — default true
 *   isHome         receives the onNavigate prop; used as the default route
 *   deepLinkParam  optional URL param this screen consumes (e.g. 'ext')
 *   deepLinkProp   prop name the screen receives the deep-link value as
 *   tabParam       optional URL param a tabbed screen reads its own tab from
 *                  (e.g. 'exttab'); listed so the Shell clears it, like
 *                  deepLinkParam, when navigating away. SEO and Forms carry
 *                  theirs as deepLinkParam because the tab IS their deep link.
 *                  A feature-tab screen (Extensions, Advanced) must declare
 *                  BOTH deepLinkParam and tabParam — useFeatureTabs refuses
 *                  an entry without them.
 */

import * as META from './meta';
import { COMPONENTS } from './components';

/* Every screen this PRODUCT has, in nav order — including ones this artifact
   may not carry. Slugs are shared data (like AdvancedScreen's ROW_ORDER): a
   slug names a screen, it carries no copy. A namespace import is keyed
   alphabetically, so the order has to live here and not in meta.js.
   registry.test.js holds this list equal to the manifest's core_screens plus
   every unit's `screen`, so a typo here fails a test naming the slug rather
   than silently dropping a screen. */
export const SCREEN_ORDER = [
	'home',
	'dynamic-values',
	'content-types',
	'seo',
	'forms',
	'scripts',
	'site-privacy',
	'extensions',
	'advanced',
	'child-theme',
	'ai-mcp',
];

/* THIS artifact's entries, keyed by slug — whatever screens/meta.js still
   re-exports after the generator's filter. */
export const ENTRIES = Object.fromEntries(
	Object.values( META ).map( ( m ) => [ m.slug, m ] )
);

/**
 * The screens this artifact actually carries, in order.
 *
 * A screen survives only if BOTH indexes still have a line for it — which is
 * how the free build ends up without the module screens it does not carry,
 * copy included, without this file needing to know anything about editions.
 */
export const SCREENS = SCREEN_ORDER.filter(
	( s ) => s in ENTRIES && s in COMPONENTS
).map( ( s ) => ( { ...ENTRIES[ s ], Component: COMPONENTS[ s ] } ) );

/**
 * Soft lookup for the Shell's ROUTING only: an unknown slug falls back to
 * Home, because wp-admin's submenu (registered in PHP) hands the Shell
 * whatever ?screen= it carries and a PHP/JS registry drift must not
 * white-screen the dashboard. Components use requireScreen.
 *
 * @param {string} slug Screen slug.
 * @return {Object} The entry, or Home.
 */
export const getScreen = ( slug ) =>
	SCREENS.find( ( s ) => s.slug === slug ) || SCREENS[ 0 ];

/**
 * Strict lookup for components: a typo throws, so it lands in the screen's
 * ErrorBoundary with a message instead of silently rendering the Dashboard
 * title or a dangling aria-labelledby.
 *
 * @param {string} slug      Screen slug.
 * @param {Array}  [screens] The registry (injectable for tests).
 * @return {Object} The entry.
 */
export const requireScreen = ( slug, screens = SCREENS ) => {
	const entry = screens.find( ( s ) => s.slug === slug );
	if ( ! entry ) {
		throw new Error( `Unknown dashboard screen: ${ slug }` );
	}
	return entry;
};

export const HOME_SLUG = 'home';
