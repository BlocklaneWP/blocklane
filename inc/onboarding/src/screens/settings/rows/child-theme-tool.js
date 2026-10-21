/**
 * Settings row: Create Child Theme (`child-theme-tool`).
 *
 * Owned by the `service:child-theme` unit: an edition without the unit carries
 * neither this file nor its line in ../rows.js, which the generator
 * rewrites into a Pro row built from the localized catalog.
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'child-theme-tool',
	store: 'advanced',
	title: __( 'Create Child Theme', 'blocklane' ),
	short: __(
		'Shows the Create Child Theme tool, which makes a child theme of the active parent theme.',
		'blocklane'
	),
	about: {
		intro: __(
			'Shows the Create Child Theme screen, which writes a child theme of the active parent theme and switches to it.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Creates a child theme with the name, author, version and text domain you enter.',
						'blocklane'
					),
					__(
						"Can copy Site Editor changes, such as global styles, templates and template parts, into the child theme's files.",
						'blocklane'
					),
					__(
						'Activates the new child theme once it is created.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Turning this off removes the screen and its file-writing routes; child themes already made are untouched.',
						'blocklane'
					),
					__(
						'Creating a theme needs permission to manage options, switch themes and install themes.',
						'blocklane'
					),
					__(
						'Images and fonts are not copied into the theme; they keep their media library addresses.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'Ships off. It is meant for a one-time setup and can be turned off again afterward.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/child-theme-tool/',
	},
	screen: 'child-theme',
	bulk: false,
	bulkReason: 'turning it on registers a route that writes a theme to disk',
};
