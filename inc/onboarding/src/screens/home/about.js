/**
 * The Dashboard's About (design spec Amendment AN 1: every screen's details
 * card is the shared About). The copy was drafted fresh and APPROVED (Garrett, 2026-10-13)
 * (the C-home spec (2026-10-13), "Home About copy"); it describes this page's
 * behavior in both editions and names no edition-only feature.
 */
import { __ } from '@wordpress/i18n';

export const HOME_ABOUT = {
	intro: __(
		'The Dashboard is the plugin’s front page: what it does, the tools you have switched on, and where to get help.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'On this page', 'blocklane' ),
			items: [
				__(
					'A welcome that says what the plugin adds to WordPress.',
					'blocklane'
				),
				__(
					'Essential tools: one card for each tool screen that is switched on.',
					'blocklane'
				),
				__(
					'Docs and help: the companion theme, until it is active, and a way to reach support.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Good to know', 'blocklane' ),
			items: [
				__(
					'A tool switched off in Settings has no card here; switch it on and its card returns.',
					'blocklane'
				),
				__(
					'The theme card disappears once the Blocklane theme is the active theme.',
					'blocklane'
				),
			],
		},
	],
	learnMore: {
		url: 'https://blocklanewp.com/resources/',
		label: __( 'Learn more about the Dashboard', 'blocklane' ),
	},
};
