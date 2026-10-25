/**
 * The About shape — one definition, read by the About component (which
 * draws no link it would refuse), the Settings row lockstep (RW9) and the
 * screen overview (the C-settings spec (2026-10-12) §3.2;
 * Amendment 1 F11). Pure: no React, no i18n.
 *
 *   intro         required, one non-empty line
 *   groups        optional, [ { label, items: [ string, … ] } ]
 *   note          optional, one line (drawn muted)
 *   learnMoreUrl  optional, the feature's own page: https://blocklanewp.com/
 *                 plus a path ending in `/`, with no query and no fragment —
 *                 no per-install identifier and no tracking parameter, ever
 *                 (wordpress.org guideline 11)
 *
 * Any other key is refused by name: a field nobody declared is a field no
 * reader renders, and an old shape (a single `description`) must fail
 * loudly rather than vanish.
 */

/** The keys an About admits. */
export const ABOUT_KEYS = Object.freeze( [
	'intro',
	'groups',
	'note',
	'learnMoreUrl',
] );

/** The one URL predicate (F11). */
const LEARN_MORE = /^https:\/\/blocklanewp\.com\/(?:[a-z0-9-]+\/)*$/;

/**
 * Whether a URL may be the About's one link.
 *
 * @param {string} url The URL.
 * @return {boolean} Whether it is https://blocklanewp.com/<path>/ with no query or fragment.
 */
export const isLearnMoreUrl = ( url ) =>
	'string' === typeof url && LEARN_MORE.test( url );

const line = ( v ) => 'string' === typeof v && v.trim().length > 0;

/**
 * Everything wrong with an About, one sentence each; none when it is admitted.
 *
 * @param {Object} about The About.
 * @return {string[]} The problems.
 */
export const aboutProblems = ( about ) => {
	if ( ! about || 'object' !== typeof about || Array.isArray( about ) ) {
		return [ 'about: not an object' ];
	}
	const out = Object.keys( about )
		.filter( ( k ) => ! ABOUT_KEYS.includes( k ) )
		.map(
			( k ) =>
				`about: the key "${ k }" is not admitted (${ ABOUT_KEYS.join(
					', '
				) })`
		);
	if ( ! line( about.intro ) ) {
		out.push( 'about: intro is required, one non-empty line' );
	}
	if ( undefined !== about.groups ) {
		if ( ! Array.isArray( about.groups ) ) {
			out.push( 'about: groups is a list' );
		} else {
			about.groups.forEach( ( g, i ) => {
				if (
					! g ||
					! line( g.label ) ||
					! Array.isArray( g.items ) ||
					! g.items.length ||
					! g.items.every( line )
				) {
					out.push(
						`about: group ${
							i + 1
						} needs a label and at least one item (strings)`
					);
				}
			} );
		}
	}
	if ( undefined !== about.note && ! line( about.note ) ) {
		out.push( 'about: note is one non-empty line' );
	}
	if (
		undefined !== about.learnMoreUrl &&
		! isLearnMoreUrl( about.learnMoreUrl )
	) {
		out.push(
			`about: learnMoreUrl "${ about.learnMoreUrl }" is not https://blocklanewp.com/<path>/ (no query, no fragment)`
		);
	}
	return out;
};
