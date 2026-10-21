/**
 * Settings row: Last Login Column (`last-login-column`).
 *
 * A core row: every edition carries it.
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'last-login-column',
	store: 'advanced',
	title: __( 'Last Login Column', 'blocklane' ),
	short: __( 'Adds a Last Login column to the Users list.', 'blocklane' ),
	about: {
		intro: __(
			'Adds a Last Login column to the Users screen showing when each person last logged in.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Records the date and time each time a user logs in.',
						'blocklane'
					),
					__(
						'Shows the date and how long ago, or Never.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Recording starts when this is turned on, so earlier logins show as Never.',
						'blocklane'
					),
					__(
						'Logins through application passwords or XML-RPC are not recorded.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/last-login-column/',
	},
};
