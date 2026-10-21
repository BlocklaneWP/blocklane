/**
 * Settings row: Forms (`forms`).
 *
 * Owned by the `module:forms` unit: an edition without the unit carries
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
	slug: 'forms',
	store: 'advanced',
	title: __( 'Forms', 'blocklane' ),
	short: __(
		'Adds form blocks with email notifications, spam protection and a submissions inbox.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds form blocks for building forms in the editor, with email notifications and a submissions inbox.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'Blocks for text, message, dropdown and choice fields, a submit button and a notification area.',
						'blocklane'
					),
					__(
						'Email notifications, sent to the site admin address unless other recipients are set.',
						'blocklane'
					),
					__(
						'An optional automatic reply, and a message or redirect after a successful submission.',
						'blocklane'
					),
					__(
						'A honeypot field and timing check against spam, with optional Cloudflare Turnstile.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						"The form blocks in the editor's inserter, in their own category.",
						'blocklane'
					),
					__(
						'Submissions in the inbox on the Forms screen, with CSV export.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Submissions are stored by default; each form can turn storing off.',
						'blocklane'
					),
					__(
						'Turning Forms off unregisters the blocks, so forms on the site stop rendering.',
						'blocklane'
					),
					__(
						'The File Upload and Form Step blocks are part of Blocklane Pro.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'Ships off. Leave it off if the site already uses another form plugin.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/forms/',
	},
	screen: 'forms',
};
