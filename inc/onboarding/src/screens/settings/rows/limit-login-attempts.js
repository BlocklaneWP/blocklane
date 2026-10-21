/**
 * Settings row: Limit Login Attempts (`limit-login-attempts`).
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
	slug: 'limit-login-attempts',
	store: 'security',
	title: __( 'Limit Login Attempts', 'blocklane' ),
	short: __(
		'Refuses logins from an address for a while after repeated attempts, even with the right password.',
		'blocklane'
	),
	about: {
		intro: __(
			'Refuses further login attempts from an address after five tries within 15 minutes, even with the right password.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'How it works', 'blocklane' ),
				items: [
					__(
						'Each login attempt with a username and password counts toward the limit.',
						'blocklane'
					),
					__( 'A successful login clears the count.', 'blocklane' ),
					__(
						'Counts are kept per IP address; IPv6 addresses are grouped by network.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Logins through XML-RPC and application passwords count too.',
						'blocklane'
					),
					__(
						'If an attempt cannot be counted, it is refused.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'Behind a proxy or CDN, visitors may share one address unless the site passes the real address through the blocklane_pro_client_ip filter.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/limit-login-attempts/',
	},
};
