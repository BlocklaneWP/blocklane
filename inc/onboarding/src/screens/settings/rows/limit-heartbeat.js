/**
 * Settings row: Limit the WordPress Heartbeat (`limit-heartbeat`).
 *
 * A core row: every edition carries it.
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 * Its options are defined here, so they leave a build with the row (RW5).
 */

import { __ } from '@wordpress/i18n';
import { RangeControl } from '@wordpress/components';

/**
 * The admin Heartbeat interval, under the row while it is ON.
 *
 * @param {Object}                                  props
 * @param {(key: string) => number}                 props.sub    Reads a sub-setting.
 * @param {(key: string, value: number) => Promise} props.setSub Writes one.
 * @return {JSX.Element} The options.
 */
const HeartbeatOptions = ( { sub, setSub } ) => (
	<RangeControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		label={ __( 'Heartbeat interval (seconds)', 'blocklane' ) }
		min={ 15 }
		max={ 100 }
		step={ 5 }
		value={ sub( 'heartbeat-interval' ) }
		onChange={ ( next ) =>
			setSub( 'heartbeat-interval', 'number' === typeof next ? next : 60 )
		}
	/>
);

export default {
	slug: 'limit-heartbeat',
	store: 'advanced',
	title: __( 'Limit the WordPress Heartbeat', 'blocklane' ),
	short: __(
		'Slows the WordPress Heartbeat in the admin and turns it off on the front end.',
		'blocklane'
	),
	about: {
		intro: __(
			'Reduces background requests from the WordPress Heartbeat, which handles autosave and post locking.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Sets how often the Heartbeat runs in the admin; the default is every 60 seconds.',
						'blocklane'
					),
					__(
						'Removes the Heartbeat script from front-end pages.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Autosave and post locking keep working in the admin, just less often.',
						'blocklane'
					),
					__(
						'The interval stays between 15 and 100 seconds.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/limit-heartbeat/',
	},
	options: HeartbeatOptions,
};
