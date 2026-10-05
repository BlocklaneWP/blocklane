/**
 * A row for a feature this artifact does NOT contain.
 *
 * THE ONE PLACE a Pro row is built. Both row screens (Advanced, Extensions)
 * render whatever their index re-exports; in the free build the generator
 * replaces each absent unit's import line with a call to `proRow()` (grammar B
 * in bin/generate-edition.php), so a Pro row can only ever come from here.
 * That is deliberate: a second way to build one is a second place for the
 * badge to go missing, and the badge is load-bearing (see below).
 *
 * WHY THE BUNDLE CARRIES NO PRO COPY. The only argument is a unit id. Labels
 * and blurbs live in inc/edition.php — generated, data-only, and outside the
 * scope dist-check scans for forbidden UI strings — and reach the client on
 * the localized `edition.absent` payload, keyed by that id. So the compiled
 * free bundle contains `proRow( 'module:content-types' )` and never a catalog
 * label or a row's copy — dist-check derives the labels and blurbs it refuses
 * from the manifest (bin/ui-needles.php) and hand-lists the rest; quote
 * neither here, the source scan reads comments too (#1061). The needles must
 * not be relaxed to make a Pro row possible: if one ever fires here, the fix
 * is to move the copy back to the catalog, not to delete the needle.
 *
 * WHY THERE IS NO SWITCH, AND WHY THE BADGE IS NOT DECORATION. The row has
 * the feature's name, the "Pro" badge and its description, and no switch of
 * any kind: a switch beside a feature this plugin does not include — even a
 * grayed, inoperable one — reads as "this is yours, but locked", the shape
 * guideline 9 names ("implying users must pay to unlock included features").
 * The badge is what says the row belongs to Blocklane Pro. The feature's
 * code is genuinely not in this artifact — its unit left the build — but a
 * reviewer cannot see absence from a screenshot, so the row says it.
 * CHARTER.md's "will not ship" bullet states the same rule.
 *
 * What the markup does with that (components/FeatureItem.js, ProBadge.js):
 * the toggle column is an empty cell; the visible "Pro" badge is
 * aria-hidden, because one word names an owner and not a state; and the
 * state a screen reader gets is the visually hidden sentence ProBadge
 * renders inside the details button: "Part of Blocklane Pro. Not included in
 * this plugin." — which product, and honestly that it is not here.
 */

import { __ } from '@wordpress/i18n';

import { edition } from '../edition.js';

/**
 * THE identity of a Pro row — module-private, never exported. A row file
 * cannot forge what it cannot import, so a `pro` flag written by hand in a
 * row file is inert (#986): the safe direction reverses — a forgery is
 * IGNORED, not honored — and the screens no longer read a row's own word
 * about itself.
 * An own Symbol key survives object spread (own enumerable symbol keys are
 * copied), which is why a Symbol and not a WeakSet.
 */
const PRO_ROW = Symbol( 'blocklane.proRow' );

/**
 * Whether a row was built by this factory — the ONE predicate every screen
 * branches on for the badge, the missing switch, the write refusals and the
 * bulk skips. Reads the private mark, never a property a row file can set.
 *
 * @param {Object|null|undefined} row A row descriptor from either index.
 * @return {boolean} True only for a row proRow() built.
 */
export const isProRow = ( row ) => true === row?.[ PRO_ROW ];

/**
 * The catalog entry for a unit this build does not carry.
 *
 * @param {string} unitId Manifest unit id, e.g. 'module:content-types'.
 * @return {{kind: string, label: string, blurb: string}|null} The entry, or
 *         null when the payload has none — a Pro build (where `absent` is
 *         empty by construction), a screen rendered outside wp-admin, a test.
 */
const catalogEntry = ( unitId ) => {
	const absent = edition().absent;

	// An array here means the payload predates the unit-id keying, or is a
	// Pro build's empty list. Either way there is nothing to look up, and the
	// harmless answer is no row rather than a row with blank copy.
	if ( ! absent || Array.isArray( absent ) ) {
		return null;
	}

	return absent[ unitId ] || null;
};

/**
 * Build the row descriptor for an absent unit.
 *
 * Returns null when the catalog has no entry, and every caller drops a null
 * row rather than rendering a blank one — an absent catalog entry is a build
 * fault — bin/dist-check.php refuses a free zip whose inc/edition.php lacks
 * the entry for a unit whose row pair the bundle carries — and the run-time
 * answer to a build fault is to show nothing, never an unlabeled control.
 *
 * Both screens' row shapes are served at once: Advanced reads `short`,
 * Extensions reads `shortDescription`. Setting both keeps this factory from
 * needing to know which screen asked, which is one fewer thing a new caller
 * can get wrong.
 *
 * @param {string} unitId Manifest unit id, e.g. 'module:content-types'.
 * @param {string} slug   The row slug the screen orders by — the unit's
 *                        `toggle` for Advanced, its extension slug for
 *                        Extensions. Passed in because it is NOT derivable
 *                        from the unit id: `module:site-lock`'s toggle is
 *                        `site-privacy`.
 * @return {Object|null} A row descriptor marked as a Pro row (isProRow()), or
 *                       null. It carries no `pro` property: the mark is the
 *                       private Symbol, and rows.test.js asserts no row in
 *                       either index says `pro` about itself.
 */
export const proRow = ( unitId, slug ) => {
	const entry = catalogEntry( unitId );

	if ( ! entry || ! entry.label ) {
		return null;
	}

	const blurb = entry.blurb || '';

	const row = {
		slug,
		unitId,
		badge: __( 'Pro', 'blocklane' ),
		title: entry.label,
		short: blurb,
		shortDescription: blurb,
		description: blurb,
	};
	// The row belongs to a product this build is not. The mark is what every
	// screen branches on (isProRow) to render the badge with no switch
	// and to refuse a write; a row without it is a live control by
	// definition, and nothing outside this module can set it.
	row[ PRO_ROW ] = true;
	return row;
};

export default proRow;
