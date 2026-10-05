/**
 * The Forms screen's registry entry — owned by module:forms (present in both
 * editions today; moving it is one manifest line). Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { envelope as icon } from '@wordpress/icons';

export default {
	slug: 'forms',
	label: __( 'Forms', 'blocklane' ),
	title: __( 'Forms', 'blocklane' ),
	subtitle: __(
		'Submissions from your forms — every entry is kept here even when email fails.',
		'blocklane'
	),
	icon,
	fullBleed: true,
	deepLinkParam: 'formstab',
	deepLinkProp: 'initialTab',
};
