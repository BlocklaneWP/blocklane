/**
 * The Site Visibility screen's registry entry — owned by module:site-lock
 * (present in both editions today; moving it is one manifest line). The slug
 * is the screen's, `site-privacy`, not the unit's. Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { lock as icon } from '@wordpress/icons';

export default {
	slug: 'site-privacy',
	label: __( 'Site Visibility', 'blocklane' ),
	title: __( 'Site Visibility', 'blocklane' ),
	subtitle: __(
		'Control who can see the site — a password or maintenance page for visitors, plus search-engine indexing.',
		'blocklane'
	),
	icon,
	fullBleed: true,
};
