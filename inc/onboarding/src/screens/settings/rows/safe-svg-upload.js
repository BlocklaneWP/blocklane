/**
 * Settings row: Safe SVG Uploads (`safe-svg-upload`).
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
	slug: 'safe-svg-upload',
	store: 'advanced',
	title: __( 'Safe SVG Uploads', 'blocklane' ),
	short: __(
		'Allows SVG uploads and cleans each file before it is stored.',
		'blocklane'
	),
	about: {
		intro: __(
			'Lets users who can upload files add SVG images, cleaning each file before WordPress stores it.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Removes scripts, event handlers and references to outside files from every uploaded SVG.',
						'blocklane'
					),
					__(
						'Refuses SVGs whose styles use escape sequences or load anything from outside the file.',
						'blocklane'
					),
					__(
						'Refuses SVGs too large to check safely.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'In the media library', 'blocklane' ),
				items: [
					__(
						'SVGs get a width and height, so they display at a sensible size.',
						'blocklane'
					),
					__(
						'SVGs can be used as the Site Icon and in header image crops.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'SVGs sent through XML-RPC or the importer are refused, because they cannot be cleaned first.',
						'blocklane'
					),
					__(
						'An SVG that loads images from another site loses those images.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/safe-svg-upload/',
	},
	bulk: false,
	bulkReason: 'turning it on widens what other users may upload',
};
