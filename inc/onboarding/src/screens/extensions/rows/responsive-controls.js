/**
 * Extensions row: Responsive Controls (responsive-controls) — owned by the `extension:responsive-controls` unit.
 *
 * The row's display copy lives in its own file because the extension it
 * describes belongs to a unit: an edition that does not carry the unit carries
 * neither this file nor its line in screens/extensions/rows.js, so the copy for
 * an extension the artifact does not contain is never in the bundle.
 * ExtensionsScreen keeps DISPLAY_ORDER and renders only the slugs it still has
 * a row AND a server-side `enabled` entry for.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'responsive-controls',
	title: __( 'Responsive Controls', 'blocklane' ),
	shortDescription: __(
		'Set a display order and a max width per breakpoint, inside core’s Layout and Dimensions panels.',
		'blocklane'
	),
	description: [
		__(
			'Responsive Controls adds tablet and mobile overrides for the two things core’s style engine still leaves out: the display order of a block inside a Row, Stack or Columns parent, and a max width on any block with a minimum-height control. Both live inside the existing Layout and Dimensions panels and follow the editor’s device preview.',
			'blocklane'
		),
		{ type: 'heading', content: __( 'Features', 'blocklane' ) },
		{
			type: 'list',
			items: [
				__(
					'Display order - Reorder a block per breakpoint without duplicating it',
					'blocklane'
				),
				__(
					'Max width - Cap a block’s width per breakpoint',
					'blocklane'
				),
				__(
					'Device preview - Switch the editor to tablet or mobile and the fields show that breakpoint’s values',
					'blocklane'
				),
				__(
					'Your theme’s breakpoints - The tablet and mobile widths come from your theme settings and can be edited inline',
					'blocklane'
				),
			],
		},
	],
};
