/**
 * Settings row: Disable Comments (`disable-comments`).
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
	slug: 'disable-comments',
	store: 'advanced',
	title: __( 'Disable Comments', 'blocklane' ),
	short: __(
		'Turns off comments and pingbacks across the site and removes the comment screens.',
		'blocklane'
	),
	about: {
		intro: __(
			'Turns off public comments and pingbacks site-wide and removes the comment screens, without deleting existing comments.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'On the site', 'blocklane' ),
				items: [
					__(
						'Comment forms, comment lists and Latest Comments blocks render nothing.',
						'blocklane'
					),
					__( 'Comment feeds return a 404.', 'blocklane' ),
					__(
						'Comment REST requests are refused, except those for editorial notes.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'In the admin', 'blocklane' ),
				items: [
					__(
						'The Comments menu, dashboard widget and toolbar item are removed.',
						'blocklane'
					),
					__(
						'Comment support is removed from every content type.',
						'blocklane'
					),
					__(
						'The Discussion settings for comments are hidden, with a notice explaining why.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Existing comments stay in the database and return when this is turned off.',
						'blocklane'
					),
					__(
						'Editorial notes in the block editor keep working.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
	chrome: true,
};
