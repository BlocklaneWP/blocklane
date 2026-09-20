/**
 * Advanced row: Site Visibility (site-privacy) — owned by the `module:site-lock` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'site-privacy',
	title: __( 'Site Visibility', 'blocklane' ),
	short: __( 'Show the Site Visibility controls in the menu.', 'blocklane' ),
	description: __(
		'Shows the Site Visibility screen — a password or maintenance page for visitors, plus search-engine indexing. Turning it off only hides the screen; whatever visibility you’ve set stays in effect. While a lock is active the screen stays visible no matter this toggle, so a private site can never be stranded with no way to unlock it.',
		'blocklane'
	),
};
