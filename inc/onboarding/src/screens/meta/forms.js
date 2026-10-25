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
	// Says what the screen holds without promising storage, which a form or
	// the site default can turn off (design spec, "Code changes owed" item 7;
	// spec 2026-10-12-c-forms.md DF1).
	subtitle: __(
		'Submissions from your forms, and the defaults every form starts from.',
		'blocklane'
	),
	icon,
	deepLinkParam: 'formstab',
	deepLinkProp: 'initialTab',
	tabParam: 'formstab',
	// "Submissions" matches every other surface's noun (the route, the copy,
	// the abilities) — deliberately not "Entries".
	tabs: [
		{ slug: 'submissions', label: __( 'Submissions', 'blocklane' ) },
		{ slug: 'settings', label: __( 'Settings', 'blocklane' ) },
	],
};
