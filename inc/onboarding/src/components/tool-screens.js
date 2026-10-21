/**
 * Whether a Site Tools screen is enabled — TOGGLE state, read from the
 * localized flag map, and nothing else.
 *
 * This is not an edition test and must never be asked to be one. Which
 * screens and cards EXIST in this artifact is decided by construction: a
 * screen this edition lacks has no line in screens/meta.js or components.js,
 * a card for it has no line in screens/home/tool-cards.js, so no caller ever
 * asks about a slug the edition does not carry. Until 2026-09-25 the Home
 * dynamic-values card did — compiled into the free bundle and hidden by this
 * flag alone (#979) — which is the Pro-UI-behind-a-flag shape the manifest
 * forbids. Fails open (true) when the flag is absent: that fail-open now only
 * ever concerns a PRESENT screen, and never strands one closed.
 *
 * A screen whose flag is off renders BlocklanePage's `disabled` treatment.
 *
 * @param {string} slug Toggle slug (matches the screen slug).
 * @return {boolean} Whether the screen is on.
 */
export const isToolScreenOn = ( slug ) => {
	const map = ( window.blocklaneProAdmin || {} ).toolScreens || {};
	return !! ( map[ slug ] ?? true );
};
