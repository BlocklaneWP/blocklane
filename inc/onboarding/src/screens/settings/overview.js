/**
 * The Settings screen's own About: the details card when no row is selected
 * (the C-settings spec (2026-10-12) §3.2), titled by the meta's
 * detailsTitle. The same shape as a row's About (components/about-shape.js)
 * and drawn by the same component. Drafted from how the screen works
 * (the C-settings About copy (2026-10-12), for approval). It
 * names no Pro feature: nothing about one may appear outside its row and its
 * own card (CHARTER.md).
 */
import { __ } from '@wordpress/i18n';

export const OVERVIEW = {
	intro: __(
		'Each row turns one Blocklane feature on or off for the whole site.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'How it works', 'blocklane' ),
			items: [
				__( 'A switch saves as soon as you change it.', 'blocklane' ),
				__(
					'Rows with extra options show them under the row while the feature is on.',
					'blocklane'
				),
				__(
					'The ⓘ button opens a card that explains the feature.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Good to know', 'blocklane' ),
			items: [
				__(
					'Rows marked Pro belong to Blocklane Pro and are not included in the free plugin.',
					'blocklane'
				),
				__(
					'Switching a feature off does not delete posts, blocks or saved settings.',
					'blocklane'
				),
			],
		},
	],
	note: __(
		"Some features keep showing saved content while off. Each feature's card says what turning it off does.",
		'blocklane'
	),
};

export default OVERVIEW;
