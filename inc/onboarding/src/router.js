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
 * Set or clear a query param on the current URL (preserving the rest, e.g.
 * `page`). Uses replaceState so it doesn't stack history entries.
 *
 * @param {string}      key   Param name.
 * @param {string|null} value Value, or a falsy value to remove the param.
 */
export const setRouteParam = ( key, value ) =>
	window.history.replaceState( {}, '', buildRouteUrl( { [ key ]: value } ) );

/**
 * Build a URL for the current page with one or more query params overridden
 * (preserving the rest, e.g. `page`). A falsy value removes that param. Like
 * setRouteParam, but returns the URL instead of navigating — for real `href`
 * links that stay client-side-switchable (right/middle/⌘-click open the tab).
 *
 * @param {Object} overrides Map of param name → value (falsy removes it).
 * @return {string} The resulting URL (pathname, plus query when non-empty).
 */
export const buildRouteUrl = ( overrides ) => {
	const params = new URLSearchParams( window.location.search );

	Object.entries( overrides ).forEach( ( [ key, value ] ) => {
		if ( value ) {
			params.set( key, value );
		} else {
			params.delete( key );
		}
	} );

	const query = params.toString();
	return query
		? `${ window.location.pathname }?${ query }`
		: window.location.pathname;
};

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
