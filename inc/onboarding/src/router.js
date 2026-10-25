/**
 * Minimal URL-param routing for the admin app.
 *
 * The app's screens are switched via state, not real routes, so the page URL
 * (admin.php?page=…) otherwise always lands on Home. These helpers read/write a
 * `screen` (and per-screen sub-view) query param via replaceState so each view is
 * bookmarkable/shareable and deep links (e.g. from the editor) land correctly —
 * without adding browser-history entries.
 *
 * @package
 */

/**
 * Read a query param from the current URL.
 *
 * @param {string} key Param name.
 * @return {string|null} The value, or null.
 */
export const getRouteParam = ( key ) =>
	new URLSearchParams( window.location.search ).get( key );

/**
 * THE one route URL builder (spec 2026-10-12-c-settings.md §3.6, Amendment 1
 * F11): the admin page's own path with its `page` param, then the params
 * given. A falsy value leaves a param out. `from` picks the starting point:
 * 'page' (the default) keeps only `page`, so a link to a screen carries
 * nothing of the screen it was built on; 'current' keeps every param of the
 * current URL (a tab link and the URL sync, which must not drop what the
 * screen they live on is mirroring).
 *
 * @param {Object} params         Param name → value (falsy leaves it out).
 * @param {Object} [options]
 * @param {string} [options.from] 'page' or 'current'.
 * @return {string} The URL (pathname, plus query when non-empty).
 */
export const routeUrl = ( params, { from = 'page' } = {} ) => {
	const current = new URLSearchParams( window.location.search );
	const out = 'current' === from ? current : new URLSearchParams();
	if ( 'current' !== from && current.get( 'page' ) ) {
		out.set( 'page', current.get( 'page' ) );
	}
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value ) {
			out.set( key, value );
		} else {
			out.delete( key );
		}
	} );
	const query = out.toString();
	return query
		? `${ window.location.pathname }?${ query }`
		: window.location.pathname;
};

/**
 * The current URL with params overridden (the rest preserved, e.g. `page`):
 * routeUrl from the current URL. For real `href` links that stay
 * client-side-switchable (right/middle/⌘-click open the tab).
 *
 * @param {Object} overrides Map of param name → value (falsy removes it).
 * @return {string} The resulting URL.
 */
export const buildRouteUrl = ( overrides ) =>
	routeUrl( overrides, { from: 'current' } );

/**
 * Set or clear a query param on the current URL (preserving the rest, e.g.
 * `page`). Uses replaceState so it doesn't stack history entries.
 *
 * @param {string}      key   Param name.
 * @param {string|null} value Value, or a falsy value to remove the param.
 */
export const setRouteParam = ( key, value ) =>
	window.history.replaceState( {}, '', buildRouteUrl( { [ key ]: value } ) );

/**
 * True for any click that must reach the browser instead of the app's
 * client-side navigation: already-handled, non-primary-button, or modified
 * (new tab/window/download). The one guard shared by every real-href link
 * the app intercepts, so tab links and admin-menu links can't drift.
 *
 * @param {MouseEvent} event Click event (native or React synthetic).
 * @return {boolean} Whether the click should fall through to the browser.
 */
export const isModifiedClick = ( event ) =>
	event.defaultPrevented ||
	event.button !== 0 ||
	event.metaKey ||
	event.ctrlKey ||
	event.shiftKey ||
	event.altKey;
