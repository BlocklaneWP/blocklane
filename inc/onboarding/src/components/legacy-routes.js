/**
 * Legacy screen addresses. Extensions and Advanced (and, before them, the
 * Security screen) became one Settings screen (spec
 * 2026-10-12-c-settings.md §3.6; design spec Amendment F), so every old
 * bookmark, editor link and server-built URL must keep landing on the same
 * row.
 *
 * The rewrite happens ONCE, before anything renders from the URL (the
 * Shell's state initializer), and it REWRITES the address rather than
 * aliasing it in memory: an in-memory alias was the old defect (the tab
 * shell rewrote the screen while the legacy item param stayed, and the next
 * load no longer recognized it). Everything downstream then reads one
 * canonical URL. It is idempotent: a rewritten address is not legacy.
 *
 * The rules, in order:
 *   1. `screen` becomes `settings`;
 *   2. `item` is the current-form `item`, else the legacy screen's item param;
 *   3. `tab` is the current-form `tab`, else the legacy tab mapped through the
 *      table, else the legacy screen's default tab — set even with an item,
 *      so an item the screen does not know still lands on the old screen's
 *      tab — and cleared when it is Settings' first tab (a default the
 *      screen derives instead is cleared by the tab shell's own URL sync);
 *   4. every legacy param of that screen is deleted.
 * Unrelated params are left alone.
 *
 * Pure: the router functions are injected, so jest drives it with a fake URL.
 */

/** Old screen → its item param, its tab param, its default tab and its tab map. */
export const LEGACY = Object.freeze( {
	security: { item: 'sec', defaultTab: 'security' },
	advanced: {
		item: 'adv',
		tab: 'advtab',
		defaultTab: 'content',
		tabs: {
			content: 'content',
			tools: 'tools',
			updates: 'updates',
			security: 'security',
		},
	},
	extensions: {
		item: 'ext',
		tab: 'exttab',
		defaultTab: 'extensions',
		tabs: {
			styling: 'extensions',
			layout: 'extensions',
			workflow: 'extensions',
		},
	},
} );

/**
 * @param {(key: string) => string|null}              getParam The router's reader.
 * @param {(key: string, value: string|null) => void} setParam The router's writer.
 * @param {string}                                    firstTab Settings' first tab (its meta).
 * @return {{screen: string, deepLink: string|null}|null} The route the URL now
 *         describes, or null when nothing was legacy.
 */
export function normalizeLegacyRoute( getParam, setParam, firstTab ) {
	const from = LEGACY[ getParam( 'screen' ) ];
	if ( ! from ) {
		return null;
	}
	const item = getParam( 'item' ) || getParam( from.item ) || null;
	const tab =
		getParam( 'tab' ) ||
		( from.tab && from.tabs[ getParam( from.tab ) ] ) ||
		from.defaultTab;

	setParam( 'screen', 'settings' );
	setParam( 'tab', tab === firstTab ? null : tab );
	setParam( 'item', item );
	for ( const legacyParam of [ from.item, from.tab ].filter( Boolean ) ) {
		setParam( legacyParam, null );
	}
	return { screen: 'settings', deepLink: item };
}
