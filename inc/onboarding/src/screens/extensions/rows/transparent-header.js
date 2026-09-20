/**
 * Extensions row: Transparent Header (transparent-header) — owned by the `extension:transparent-header` unit.
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
	slug: 'transparent-header',
	title: __( 'Transparent Header', 'blocklane' ),
	shortDescription: __(
		'Overlay the header on the first section of the page, solidifying on scroll.',
		'blocklane'
	),
	description: [
		__(
			'Transparent Header overlays your header on the first section of the page with its background removed — the classic hero treatment — and brings the background back once the page scrolls past it. The toggle appears in its own Transparent Header panel on the outermost Group of a header template part.',
			'blocklane'
		),
		{ type: 'heading', content: __( 'Features', 'blocklane' ) },
		{
			type: 'list',
			items: [
				__(
					'Transparent at the top - The header sits on the first section with no background of its own',
					'blocklane'
				),
				__(
					'Solid on Scroll - Pin the header and restore its background once the page scrolls past it, or let it scroll away',
					'blocklane'
				),
				__(
					'Uses your design - The solid state brings back the background set on the Group itself; no separate color settings',
					'blocklane'
				),
				__(
					'Per-page override - A "Style" control in the page\'s Header panel overrides the header\'s default in either direction',
					'blocklane'
				),
				__(
					'Top Offset - Float the header below the top edge for an inset look',
					'blocklane'
				),
				__(
					'Editor preview - The Site Editor template view shows the overlay exactly as the front end renders it',
					'blocklane'
				),
			],
		},
		{ type: 'heading', content: __( 'Good to know', 'blocklane' ) },
		{
			type: 'list',
			items: [
				__(
					'Give the first section of each page enough top padding to sit under the header',
					'blocklane'
				),
				__(
					'Solid on Scroll needs a background on the header Group — without one the header stays transparent',
					'blocklane'
				),
			],
		},
	],
};
