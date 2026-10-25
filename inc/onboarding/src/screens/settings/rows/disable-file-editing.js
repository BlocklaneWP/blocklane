/**
 * Settings row: Disable Theme & Plugin File Editing (`disable-file-editing`).
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
	slug: 'disable-file-editing',
	store: 'security',
	title: __( 'Disable Theme & Plugin File Editing', 'blocklane' ),
	short: __(
		'Turns off the theme and plugin code editors in the WordPress admin.',
		'blocklane'
	),
	about: {
		intro: __(
			'Turns off the built-in theme and plugin file editors, so no one can change code from the admin.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						"Defines WordPress's DISALLOW_FILE_EDIT constant for the site.",
						'blocklane'
					),
					__(
						'The Theme File Editor and Plugin File Editor are removed from the admin.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Installing and updating plugins and themes still works.',
						'blocklane'
					),
					__(
						'If wp-config.php already turns the editors off, this setting cannot turn them back on.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
};
