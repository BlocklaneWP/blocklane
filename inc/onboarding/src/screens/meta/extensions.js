/**
 * The Extensions screen's registry entry — owned by module:extensions
 * (present in both editions today; moving it is one manifest line). A
 * feature-tab screen: declares BOTH deepLinkParam and tabParam, which
 * useFeatureTabs requires. Shape: see registry.js.
 */

import { __ } from '@wordpress/i18n';
import { settings as icon } from '@wordpress/icons';

export default {
	slug: 'extensions',
	label: __( 'Extensions', 'blocklane' ),
	title: __( 'Extensions', 'blocklane' ),
	subtitle: __(
		'Toggle block-editor enhancements for the core blocks you already use.',
		'blocklane'
	),
	icon,
	fullBleed: true,
	deepLinkParam: 'ext',
	deepLinkProp: 'initialExtension',
	tabParam: 'exttab',
};
