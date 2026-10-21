/**
 * Settings row: Limit Post Revisions (`limit-revisions`).
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
 * How many revisions to keep, under the row while it is ON.
 *
 * @param {Object}                                  props
 * @param {(key: string) => number}                 props.sub    Reads a sub-setting.
 * @param {(key: string, value: number) => Promise} props.setSub Writes one.
 * @return {JSX.Element} The options.
 */
const RevisionsOptions = ( { sub, setSub } ) => (
	<RangeControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		label={ __( 'Revisions to keep', 'blocklane' ) }
		help={ __( 'Set it to 0 to keep none.', 'blocklane' ) }
		min={ 0 }
		max={ 100 }
		value={ sub( 'revisions-to-keep' ) }
		onChange={ ( next ) =>
			setSub( 'revisions-to-keep', 'number' === typeof next ? next : 0 )
		}
	/>
);

export default {
	slug: 'limit-revisions',
	store: 'advanced',
	title: __( 'Limit Post Revisions', 'blocklane' ),
	short: __(
		'Caps how many revisions WordPress keeps for each post.',
		'blocklane'
	),
	about: {
		intro: __(
			'Sets how many revisions WordPress keeps for each post, page or other item.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it does', 'blocklane' ),
				items: [
					__(
						'Keeps between 0 and 100 revisions per item; the default is 10.',
						'blocklane'
					),
					__( 'Setting 0 turns revisions off.', 'blocklane' ),
					__(
						'Applies to every content type that keeps revisions.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						"Turning it off returns to WordPress's usual revision behavior.",
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/limit-revisions/',
	},
	options: RevisionsOptions,
};
