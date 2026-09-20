/**
 * Advanced row: Popups (popups) — owned by the `module:popups` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'popups',
	title: __( 'Popups', 'blocklane' ),
	short: __(
		'Design popups and slide-ins in the block editor.',
		'blocklane'
	),
	description: __(
		'Adds a Popups screen where each popup is a normal block-editor document — design it with any blocks, then set its position (a centered modal or a corner slide-in), trigger (time on page, scroll depth, exit intent, or a manual link), display rules, and how often returning visitors see it. Popups keep firing even if the plugin is later deactivated.',
		'blocklane'
	),
};
