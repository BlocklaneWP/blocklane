/**
 * The Create Child Theme screen's registry entry — owned by
 * service:child-theme (present in both editions today; moving it is one
 * manifest line). Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { archive as icon } from '@wordpress/icons';

export default {
	slug: 'child-theme',
	label: __( 'Create Child Theme', 'blocklane' ),
	title: __( 'Create Child Theme', 'blocklane' ),
	subtitle: __(
		'Create a child theme so your changes survive parent theme updates.',
		'blocklane'
	),
	// The details card names the subject, not the screen (design spec D17):
	// BlocklanePage reads it before its "About <title>" default, and the
	// sitemap reads the same key (bin/dashboard-sitemap.mjs, SM2).
	detailsTitle: __( 'About child themes', 'blocklane' ),
	icon,
};
