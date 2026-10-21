/**
 * Settings row: Site Visibility (`site-privacy`).
 *
 * Owned by the `module:site-lock` unit: an edition without the unit carries
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
	slug: 'site-privacy',
	store: 'advanced',
	title: __( 'Site Visibility', 'blocklane' ),
	short: __(
		'Shows visitors a Coming soon or Maintenance page while you work on the site.',
		'blocklane'
	),
	about: {
		intro: __(
			'Keeps the site from visitors behind a Coming soon page with a password, or behind a Maintenance page.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What visitors see', 'blocklane' ),
				items: [
					__(
						'Coming soon: an editable page with a password form; the password lets a visitor in for 14 days.',
						'blocklane'
					),
					__(
						'Maintenance: an editable page with no form, sent as a temporary 503 response.',
						'blocklane'
					),
					__(
						'Both pages ask search engines not to index them.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Who still gets in', 'blocklane' ),
				items: [
					__(
						'Logged-in users who can edit posts see the site as usual.',
						'blocklane'
					),
					__(
						'The admin and the login page are never blocked.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Responses are marked not to be cached, and known page caches are cleared when the lock changes.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Switching it off', 'blocklane' ),
				items: [
					__(
						'Hides the Site Visibility screen and its routes while no lock is on.',
						'blocklane'
					),
					__(
						'Does not lift a lock that is on: visitors keep seeing the Coming soon or Maintenance page.',
						'blocklane'
					),
					__(
						'While a lock is on, the screen stays available so the lock can be turned off.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'The lock runs only with a block theme. Activating a classic theme turns it off, and the Themes screen warns before that happens.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/site-privacy/',
	},
	screen: 'site-privacy',
};
