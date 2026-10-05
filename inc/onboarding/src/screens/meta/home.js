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
		'Quickly access the tools and resources you need to build with FSE.',
		'blocklane'
	),
	icon,
	fullBleed: false,
	isHome: true,
};
