/**
 * Settings row: Replace Media (`media-replacement`).
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
	slug: 'media-replacement',
	store: 'advanced',
	title: __( 'Replace Media', 'blocklane' ),
	short: __(
		'Lets you swap a media file for a new one while keeping its URL.',
		'blocklane'
	),
	about: {
		intro: __(
			"Adds Replace file to the media library's attachment details, putting a new file at the same address.",
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'A Replace file link beside Download file in attachment details.',
						'blocklane'
					),
					__(
						'The attachment, file name and URL stay the same, so links and blocks keep working.',
						'blocklane'
					),
					__(
						'Image sizes are regenerated from the new file, and the old sizes are removed.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'The new file must be the same type as the original.',
						'blocklane'
					),
					__(
						"Replacements pass through WordPress's usual upload checks, including Safe SVG Uploads when it is on.",
						'blocklane'
					),
					__(
						'Replacing needs permission to upload files and to edit the attachment.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
	bulk: false,
	bulkReason:
		'it registers a write route that replaces a stored file in place',
};
