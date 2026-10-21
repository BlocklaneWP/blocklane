/**
 * The Settings screen's registry entry — owned by module:advanced, which
 * `requires` module:extensions and module:security, the other two stores
 * its tabs read (edition-manifest.json; spec 2026-10-12-c-settings.md §3.2,
 * §3.7). Advanced and Extensions became this one screen (design spec
 * Amendment F). Shape: see registry.js; the keys this screen adds:
 *
 *   detailsTitle  the details card's title when no row is selected: the
 *                 page is "Settings" (F1) while the card names the place
 *                 "Blocklane Settings" (D20, Z1). BlocklanePage reads it,
 *                 and bin/dashboard-sitemap.mjs reads it through a checked
 *                 mirror (SM2).
 *   tabDefault    'live-first': the default tab is DERIVED, the first tab
 *                 whose live rows are at least its Pro rows (SD16;
 *                 components/feature-tabs.js liveFirstTab). In Pro that is
 *                 the first tab; in free, Content & editing.
 *   tabs[].noun   'extensions' | 'settings': which sentence set the toolbar,
 *                 the empty state and the toast use. A sentence never
 *                 composes a noun.
 *   tabs[].sections  a sectioned tab's sections, in order.
 *
 * Labels are sentence case (SD9, design rule R2); "Extensions" is the word
 * either way.
 */

import { __ } from '@wordpress/i18n';
import { settings as icon } from '@wordpress/icons';

export default {
	slug: 'settings',
	label: __( 'Settings', 'blocklane' ),
	title: __( 'Settings', 'blocklane' ),
	subtitle: __(
		'Block editor enhancements, plus opt-in settings for content, site tools, updates and security.',
		'blocklane'
	),
	detailsTitle: __( 'About Blocklane Settings', 'blocklane' ),
	icon,
	tabParam: 'tab',
	deepLinkParam: 'item',
	deepLinkProp: 'initialItem',
	tabDefault: 'live-first',
	tabs: [
		{
			slug: 'extensions',
			label: __( 'Extensions', 'blocklane' ),
			noun: 'extensions',
			sections: [
				{
					slug: 'layout',
					label: __( 'Layout & responsive', 'blocklane' ),
				},
				{
					slug: 'motion',
					label: __( 'Motion', 'blocklane' ),
				},
				{
					slug: 'styling',
					label: __( 'Styling & effects', 'blocklane' ),
				},
				{
					slug: 'workflow',
					label: __( 'Workflow & tools', 'blocklane' ),
				},
			],
		},
		{
			slug: 'content',
			label: __( 'Content & editing', 'blocklane' ),
			noun: 'settings',
		},
		{
			slug: 'tools',
			label: __( 'Site tools', 'blocklane' ),
			noun: 'settings',
		},
		{
			slug: 'updates',
			label: __( 'Updates & performance', 'blocklane' ),
			noun: 'settings',
		},
		{
			slug: 'security',
			label: __( 'Security & hardening', 'blocklane' ),
			noun: 'settings',
		},
	],
};
