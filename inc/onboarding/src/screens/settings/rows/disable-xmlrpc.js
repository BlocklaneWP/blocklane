/**
 * Settings row: Disable XML-RPC (`disable-xmlrpc`).
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
	slug: 'disable-xmlrpc',
	store: 'security',
	title: __( 'Disable XML-RPC', 'blocklane' ),
	short: __(
		'Turns off XML-RPC logins and pingbacks, a common target for password guessing.',
		'blocklane'
	),
	about: {
		intro: __(
			'Turns off the XML-RPC methods that need a login, removes the pingback methods and drops the X-Pingback header.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'XML-RPC requests that need a username and password are refused.',
						'blocklane'
					),
					__(
						'Pingback methods are removed from XML-RPC.',
						'blocklane'
					),
					__(
						'The X-Pingback header is no longer sent.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Apps and services that sign in through XML-RPC stop working while this is on.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
};
