/**
 * Settings row: Duplicate Content (`duplicate-content`).
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
	slug: 'duplicate-content',
	store: 'advanced',
	title: __( 'Duplicate Content', 'blocklane' ),
	short: __(
		'Adds a Clone link to admin lists that copies an item as a new draft.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds a Clone action to admin lists that copies a post, page or custom item into a new draft.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it copies', 'blocklane' ),
				items: [
					__(
						'The title, content, excerpt, categories, tags and other terms.',
						'blocklane'
					),
					__(
						'Custom fields, apart from a few internal WordPress keys such as edit locks.',
						'blocklane'
					),
					__(
						"The original's author, parent and menu order.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						'In the row actions under each title, beside Edit and Trash.',
						'blocklane'
					),
					__(
						'After cloning, the new draft opens in the editor.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Cloning needs permission to edit the original and to create items of its type.',
						'blocklane'
					),
					__(
						"The draft keeps the original's author, not the person who cloned it.",
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
};
