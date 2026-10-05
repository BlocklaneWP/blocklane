/**
 * The Home card that opens the Site Visibility screen — owned by
 * module:site-lock. `slug` is the screen it opens and the toggle
 * isToolScreenOn() reads; `icon` is the `@wordpress/icons` glyph.
 */

import { __ } from '@wordpress/i18n';
import { seen as icon } from '@wordpress/icons';

export default {
	slug: 'site-privacy',
	title: __( 'Site Visibility', 'blocklane' ),
	description: __(
		'Control who can see the site — public, private, or password-gated while you build.',
		'blocklane'
	),
	action: __( 'Manage Visibility', 'blocklane' ),
	icon,
};
