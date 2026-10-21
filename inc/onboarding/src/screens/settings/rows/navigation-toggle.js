/**
 * Settings row: Menu Button (`navigation-toggle`).
 *
 * Owned by the `extension:navigation-toggle` unit: an edition without the unit carries
 * neither this file nor its line in ../rows.js, which the generator
 * rewrites into a Pro row built from the localized catalog.
 * Its title and short are the unit's catalog label and blurb, one
 * sentence in both editions (SD8; rows.test.js RW12 holds them equal).
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The About was drafted
 * from the feature's implementation when feat/pattern-showcase and the
 * admin-screens redesign met (2026-10-17); APPROVED (Garrett, 2026-10-17).
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'navigation-toggle',
	store: 'extensions',
	title: __( 'Menu Button', 'blocklane' ),
	short: __(
		'Give the navigation’s menu button a label, a different icon, or a button look.',
		'blocklane'
	),
	about: {
		intro: __(
			'Changes the button that opens a collapsed Navigation block: its label, its icon and its look.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'A visible label beside the icon, which also names the button and its menu for screen readers.',
						'blocklane'
					),
					__(
						'A choice of five WordPress icons: Menu, Layout, Category, Grid or List.',
						'blocklane'
					),
					__(
						'A Button style that gives the toggle the theme’s button look.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Core still opens and closes the menu and returns focus; only the button’s contents change.',
						'blocklane'
					),
					__(
						'A Navigation block with none of these set renders exactly as core draws it.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/navigation-toggle/',
	},
};
