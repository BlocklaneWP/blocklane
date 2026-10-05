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
		'Generate a child theme so you can fork without touching the parent.',
		'blocklane'
	),
	icon,
	fullBleed: true,
};
