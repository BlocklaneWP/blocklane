/**
 * Settings row: Block User Enumeration (`block-user-enumeration`).
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
	slug: 'block-user-enumeration',
	store: 'security',
	title: __( 'Block User Enumeration', 'blocklane' ),
	short: __(
		'Stops logged-out visitors from finding usernames through author archives and the REST API.',
		'blocklane'
	),
	about: {
		intro: __(
			"Closes common ways of listing a site's usernames: author archive URLs, the users REST endpoint and the users sitemap.",
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Logged-out requests for author archives, including ?author= links, redirect to the home page.',
						'blocklane'
					),
					__(
						'The users REST endpoints are hidden from anyone who cannot edit posts.',
						'blocklane'
					),
					__(
						"The users sitemap is removed from the site's XML sitemap.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						"Users who can edit posts keep access, so the block editor's author settings still work.",
						'blocklane'
					),
					__(
						'Logged-in users can still open author archives.',
						'blocklane'
					),
					__(
						'The redirect is temporary, so archives return when this is turned off.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/block-user-enumeration/',
	},
};
