/**
 * Settings row: Clean Uninstall (`clean-uninstall`).
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
	slug: 'clean-uninstall',
	store: 'advanced',
	title: __( 'Clean Uninstall', 'blocklane' ),
	short: __(
		'Makes deleting the plugin also remove saved icons, custom script code and the install ID.',
		'blocklane'
	),
	about: {
		intro: __(
			'When the plugin is deleted, also removes what Blocklane normally keeps: saved icons, custom script code and its install ID.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What is removed', 'blocklane' ),
				items: [
					__(
						'Saved custom icons, and their copies in the uploads folder.',
						'blocklane'
					),
					__(
						'Header, body and footer code saved on this site.',
						'blocklane'
					),
					__(
						"This site's install ID for the license.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'What stays', 'blocklane' ),
				items: [
					__(
						'Posts, pages, content type entries and terms.',
						'blocklane'
					),
					__(
						'Form submissions, which have their own delete setting in Forms.',
						'blocklane'
					),
					__(
						'Child themes made with Create Child Theme.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Other Blocklane settings are removed on every delete; this adds the items above.',
						'blocklane'
					),
					__(
						'It applies only when the plugin is deleted, never when it is deactivated.',
						'blocklane'
					),
					__(
						'While another Blocklane plugin is still installed, shared settings are kept and this does nothing.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'Removed data cannot be recovered, so copy any custom code you want to keep before deleting the plugin.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
	bulk: false,
	bulkReason: 'turning it on changes what Delete destroys',
};
