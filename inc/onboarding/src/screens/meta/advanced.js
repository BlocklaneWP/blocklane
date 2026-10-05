/**
 * The Advanced screen's registry entry — owned by module:advanced (present in
 * both editions today; moving it is one manifest line). A feature-tab
 * screen: declares BOTH deepLinkParam and tabParam, which useFeatureTabs
 * requires. Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { tool as icon } from '@wordpress/icons';

export default {
	slug: 'advanced',
	label: __( 'Advanced', 'blocklane' ),
	title: __( 'Advanced', 'blocklane' ),
	subtitle: __(
		'Opt-in admin, content, and security enhancements that work right inside core WordPress.',
		'blocklane'
	),
	icon,
	fullBleed: true,
	deepLinkParam: 'adv',
	deepLinkProp: 'initialFeature',
	tabParam: 'advtab',
};
