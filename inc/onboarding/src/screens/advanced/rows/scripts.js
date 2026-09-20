/**
 * Advanced row: Scripts (scripts) — owned by the `service:scripts` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'scripts',
	title: __( 'Scripts', 'blocklane' ),
	short: __( 'Show the Scripts editor in the menu.', 'blocklane' ),
	description: __(
		'Shows the Scripts screen for adding header, body, and footer code — analytics, pixels, and the like. Turning it off only hides the editor; your scripts keep printing on the front end. Use the master switch inside the Scripts screen to actually stop output.',
		'blocklane'
	),
};
