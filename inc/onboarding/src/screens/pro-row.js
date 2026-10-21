/**
 * A row for a feature this artifact does NOT contain.
 *
 * THE ONE PLACE a Pro row is built. The Settings screen renders whatever its
 * row index (screens/settings/rows.js) re-exports; in the free build the
 * generator replaces each absent unit's import line with a call to
 * `proRow()` (grammar B in bin/generate-edition.php), so a Pro row can only
 * ever come from here. A second way to build one would be a second place for
 * its mark to go missing.
 *
 * WHY THE BUNDLE CARRIES NO PRO COPY. The only argument is a unit id. Labels,
 * blurbs and About copy live in inc/edition.php — generated, data-only, and
 * outside the scope dist-check scans for forbidden UI strings — and reach the
 * client on the localized `edition.absent` payload, keyed by that id. So the
 * compiled free bundle contains `proRow( 'module:content-types', … )` and
 * never a catalog label or a row's copy — dist-check derives the strings it
 * refuses from the manifest (bin/ui-needles.php); quote none of them here,
 * the source scan reads comments too (#1061).
 *
 * WHAT THE ROW IS. The Settings row shape (title, short, about), built from
 * the catalog, plus a private mark. FeatureRow reads the mark through the
 * stores door's kindOf() and draws the Pro kind: the name, the "Pro" badge
 * (visible word hidden from assistive technology, the sentence "Part of
 * Blocklane Pro. Not included in this plugin." read instead), the one-line
 * description, and an inert switch in the off position that is presentation
 * only — aria-hidden, no role, no focus, no handler (Amendment 1 SD18,
 * design spec Z4; CHARTER.md). Its details card shows the feature's About and
 * nothing else.
 */

import { edition } from '../edition.js';
import { isLearnMoreUrl } from '../components/about-shape.js';

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
 * Whether a row was built by this factory — the ONE predicate the stores
 * door branches on for the row's kind, the write refusals and the bulk
 * skips. Reads the private mark, never a property a row file can set.
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
 * The card a Pro row opens: the catalog's own About (the manifest's `about`,
 * localized by Settings::about_payload()), admitted key by key — intro,
 * groups of { label, items }, note, and a Learn more URL only when the one
 * predicate admits it (isLearnMoreUrl: no query, no fragment, our host). A
 * key outside that shape never rides along. No About at all (a stale build)
 * falls back to the blurb as the intro, so the card still says what the
 * feature does.
 *
 * @param {Object|undefined} about The catalog entry's about.
 * @param {string}           blurb The catalog entry's blurb.
 * @return {Object} The row's about.
 */
const catalogAbout = ( about, blurb ) => {
	if ( ! about || 'string' !== typeof about.intro || ! about.intro ) {
		return { intro: blurb };
	}
	const out = { intro: about.intro };
	if ( Array.isArray( about.groups ) && about.groups.length ) {
		out.groups = about.groups.map( ( group ) => ( {
			label: String( group.label ),
			items: ( group.items || [] ).map( String ),
		} ) );
	}
	if ( 'string' === typeof about.note && about.note ) {
		out.note = about.note;
	}
	if ( isLearnMoreUrl( about.learnMoreUrl ) ) {
		out.learnMoreUrl = about.learnMoreUrl;
	}
	return out;
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
 * The row has the Settings shape: `title` (the label), `short` (the blurb)
 * and `about` (the catalog's own About; the blurb as its intro when the
 * catalog carries none).
 *
 * @param {string} unitId Manifest unit id, e.g. 'module:content-types'.
 * @param {string} slug   The row slug the screen places by — the unit's
 *                        `toggle` or `toggle_slug`. Passed in because it is
 *                        NOT derivable from the unit id: `module:site-lock`'s
 *                        toggle is `site-privacy`.
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
		title: entry.label,
		short: blurb,
		about: catalogAbout( entry.about, blurb ),
	};
	// The row belongs to a product this build is not. The mark is what the
	// stores door branches on (isProRow) for the Pro kind and to refuse a
	// write; a row without it is a live control by definition, and nothing
	// outside this module can set it.
	row[ PRO_ROW ] = true;
	return row;
};

export default proRow;
