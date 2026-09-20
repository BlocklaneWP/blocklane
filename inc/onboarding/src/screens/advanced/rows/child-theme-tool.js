/**
 * Advanced row: Child Theme Generator (child-theme-tool) — owned by the `service:child-theme` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __, sprintf } from '@wordpress/i18n';

import { pluginName } from '../../../edition';

export default {
	slug: 'child-theme-tool',
	title: __( 'Child Theme Generator', 'blocklane' ),
	short: __( 'Show the Create Child Theme tool in the menu.', 'blocklane' ),
	// The unit ships in both editions, so the menu it names is whichever
	// artifact this is — pluginName(), never a literal.
	description: sprintf(
		/* translators: %s: the plugin name (its wp-admin menu). */
		__(
			'Shows the Create Child Theme screen in the %s menu. It’s usually a one-time tool — turn this on when you need to generate a child theme, and off again after to tidy the menu and unregister the tool’s theme-writing endpoints. Turning it off never touches a child theme you’ve already generated.',
			'blocklane'
		),
		pluginName()
	),
};
