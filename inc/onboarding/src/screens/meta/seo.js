/**
 * The SEO screen's registry entry — owned by module:seo (present in both
 * editions today; moving it is one manifest line). Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
// The SEO mark — same glyph as the editor sidebar's toolbar icon, from the
// shared module so the two can't drift.
import { seoIcon as icon } from '../../../../shared/seo-ui';

export default {
	slug: 'seo',
	label: __( 'SEO', 'blocklane' ),
	title: __( 'SEO', 'blocklane' ),
	subtitle: __(
		'Visibility tools for your site — sitemaps, search-engine settings, and more, in one place.',
		'blocklane'
	),
	icon,
	deepLinkParam: 'seotab',
	deepLinkProp: 'initialTab',
	tabParam: 'seotab',
	tabs: [
		{ slug: 'overview', label: __( 'Overview', 'blocklane' ) },
		{ slug: 'content', label: __( 'Content', 'blocklane' ) },
		{ slug: 'settings', label: __( 'Settings', 'blocklane' ) },
	],
};
