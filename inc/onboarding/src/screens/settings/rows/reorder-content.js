/**
 * Settings row: Reorder Content (`reorder-content`).
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
import { CheckboxControl, Notice } from '@wordpress/components';

/**
 * Which content types are ordered by hand, under the row while it is ON. A
 * choice of none is said out loud: on with nothing chosen changes nothing.
 *
 * @param {Object}                                    props
 * @param {(key: string) => string[]}                 props.sub       Reads a sub-setting.
 * @param {(key: string, value: string[]) => Promise} props.setSub    Writes one.
 * @param {Array<{slug: string, label: string}>}      props.postTypes The eligible types.
 * @return {JSX.Element} The options.
 */
const ReorderOptions = ( { sub, setSub, postTypes } ) => {
	const chosen = sub( 'reorder-post-types' ) || [];
	const toggle = ( slug, on ) =>
		setSub(
			'reorder-post-types',
			on ? [ ...chosen, slug ] : chosen.filter( ( s ) => s !== slug )
		);
	return (
		<div className="blocklane-reorder">
			{ chosen.length ? null : (
				<div data-notice="reorder-empty">
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Choose at least one content type. Until you do, this setting changes nothing.',
							'blocklane'
						) }
					</Notice>
				</div>
			) }
			<fieldset className="blocklane-reorder__types">
				<legend>
					{ __( 'Content types to order by hand', 'blocklane' ) }
				</legend>
				{ postTypes.map( ( type ) => (
					<CheckboxControl
						__nextHasNoMarginBottom
						key={ type.slug }
						label={ type.label }
						checked={ chosen.includes( type.slug ) }
						onChange={ ( on ) => toggle( type.slug, on ) }
					/>
				) ) }
			</fieldset>
		</div>
	);
};

export default {
	slug: 'reorder-content',
	store: 'advanced',
	title: __( 'Reorder Content', 'blocklane' ),
	short: __(
		'Lets you set the order of posts, pages or custom items by dragging them in the admin list.',
		'blocklane'
	),
	about: {
		intro: __(
			'Lets you arrange items of the content types you choose by dragging them, and uses that order on the site.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'A Sort by Order view on the admin list of each chosen content type.',
						'blocklane'
					),
					__(
						'Drag rows into place, or use Move up and Move down.',
						'blocklane'
					),
					__(
						"For pages and other nested types, Indent and Outdent change an item's parent.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'On the site', 'blocklane' ),
				items: [
					__(
						'Lists of those types follow the saved order unless a query asks for its own sort.',
						'blocklane'
					),
					__(
						"The order is stored in WordPress's own menu order field.",
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						"Reordering needs permission to edit other users' items of that type.",
						'blocklane'
					),
					__(
						'Items you cannot edit stay in place; a move that would shift one is refused.',
						'blocklane'
					),
					__(
						'Nested lists over 500 items open the full tree from the Sort by Order link.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/site-utilities/',
	},
	options: ReorderOptions,
};
