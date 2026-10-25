/**
 * Settings row: Disable Blog Features (`disable-blog-features`).
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
	slug: 'disable-blog-features',
	store: 'advanced',
	title: __( 'Disable Blog Features', 'blocklane' ),
	short: __(
		'Turns off Post Formats, Post via Email and update pings for sites that are not blogs.',
		'blocklane'
	),
	about: {
		intro: __(
			'Removes blogging features many sites never use: Post Formats, Post via Email and update service pings.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it turns off', 'blocklane' ),
				items: [
					__(
						"Post Formats, by removing the theme's support for them.",
						'blocklane'
					),
					__( 'The Post via Email settings.', 'blocklane' ),
					__(
						'The update service pings sent when a post is published.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'The related Writing settings are hidden, with a notice explaining why.',
						'blocklane'
					),
					__(
						'Nothing is deleted; everything returns when this is turned off.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
};
