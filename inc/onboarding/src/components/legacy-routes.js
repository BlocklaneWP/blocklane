/**
 * Legacy screen aliases. The Security screen merged into Advanced (2026-07),
 * so old bookmarks and deep links (?screen=security&sec=<slug>) must keep
 * landing on the same toggle inside Advanced.
 *
 * The alias is applied by REWRITING the URL to its current form once,
 * before anything renders from it (Shell.js calls normalizeLegacyRoute in
 * its state initializer). Everything downstream — the tab shell's URL
 * sync, the tab links, a reload or a shared bookmark of the resulting
 * address — then reads one canonical URL. Resolving the alias only in
 * memory was the defect: the tab shell rewrote screen=security to
 * screen=advanced on mount while sec= stayed, and the alias, keyed on the
 * raw screen name, was no longer consulted on the next load, so a legacy
 * link pinned exactly once.
 *
 * Pure: the router functions are injected, so jest exercises it with a
 * fake URL (legacy-routes.test.js).
 */

/** Old screen slug → the screen that absorbed it. */
export const LEGACY_SCREENS = { security: 'advanced' };

/** Old screen slug → the item param its deep links used. */
export const LEGACY_DEEP_LINK_PARAMS = { security: 'sec' };

/**
 * If the URL names a legacy screen, rewrite it to the current screen and
 * item param and return the resolved route; otherwise leave the URL alone
 * and return null. A current-form item param already present wins over
 * the legacy one. Idempotent: a second call sees a current URL and does
 * nothing.
 *
 * @param {(key: string) => string|null}              getParam        (key) → value|null, the router's reader.
 * @param {(key: string, value: string|null) => void} setParam        (key, value|null), the router's writer.
 * @param {(screen: string) => string|undefined}      deepLinkParamOf (screen) → that screen's item param, or
 *                                                                    undefined when it has none.
 * @return {{screen: string, deepLink: string|null}|null} The route the URL
 *         now describes, or null when nothing was legacy.
 */
export function normalizeLegacyRoute( getParam, setParam, deepLinkParamOf ) {
	const raw = getParam( 'screen' );
	const target = LEGACY_SCREENS[ raw ];
	if ( ! target ) {
		return null;
	}
	const legacyParam = LEGACY_DEEP_LINK_PARAMS[ raw ];
	const targetParam = deepLinkParamOf( target );
	const deepLink =
		( targetParam && getParam( targetParam ) ) ||
		( legacyParam && getParam( legacyParam ) ) ||
		null;

	setParam( 'screen', target );
	if ( targetParam ) {
		setParam( targetParam, deepLink );
	}
	if ( legacyParam ) {
		setParam( legacyParam, null );
	}
	return { screen: target, deepLink };
}
