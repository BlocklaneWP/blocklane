/**
 * The Dashboard (Home) screen's registry entry — a core screen, in every
 * edition (manifest `core_screens`). Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { home as icon } from '@wordpress/icons';

export default {
	slug: 'home',
	label: __( 'Dashboard', 'blocklane' ),
	title: __( 'Dashboard', 'blocklane' ),
	subtitle: __(
		'Quickly access the tools and resources you need to build with the Site Editor.',
		'blocklane'
	),
	// The details card names the place, not "About Dashboard" (the C-home
	// spec (2026-10-13), Home About copy approved 2026-10-13).
	detailsTitle: __( 'About the Dashboard', 'blocklane' ),
	// The details card starts closed here at every width (decision D-H5,
	// 2026-10-13); BlocklanePage's useDetailsPreference reads it.
	detailsDefault: 'closed',
	icon,
	isHome: true,
};
