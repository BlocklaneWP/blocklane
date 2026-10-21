/**
 * Settings row: Transparent Header (`transparent-header`).
 *
 * Owned by the `extension:transparent-header` unit: an edition without the unit carries
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
	slug: 'transparent-header',
	store: 'extensions',
	title: __( 'Transparent Header', 'blocklane' ),
	short: __(
		'Lets a header sit over the top of the page with its background cleared.',
		'blocklane'
	),
	about: {
		intro: __(
			'Lets a header group sit over the first section of a page, with its background removed at the top.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'Solid on scroll, the default, pins the header and brings its background back after scrolling.',
						'blocklane'
					),
					__(
						'The header can also stay transparent and scroll away, or float over the page with its background.',
						'blocklane'
					),
					__(
						'Alternate text, navigation, button, social icon and logo colors for the transparent state.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						'A Transparent Header panel on the top-level groups of a header template part.',
						'blocklane'
					),
					__(
						"Each page can follow the header's setting, force the overlay on, or force a solid header.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Its script loads only on pages that render a transparent header.',
						'blocklane'
					),
					__(
						'Turning it off renders headers solid again; their settings stay saved.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/transparent-header/',
	},
};
