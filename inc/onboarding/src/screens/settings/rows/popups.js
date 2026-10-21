/**
 * Settings row: Popups (`popups`).
 *
 * Owned by the `module:popups` unit: an edition without the unit carries
 * neither this file nor its line in ../rows.js, which the generator
 * rewrites into a Pro row built from the localized catalog.
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'popups',
	store: 'advanced',
	title: __( 'Popups', 'blocklane' ),
	short: __(
		'Lets you design popups in the block editor and choose when and where they appear.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds popups designed in the block editor, with triggers, display rules and limits on how often visitors see them.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'Nine screen positions: centered, along an edge, or in a corner.',
						'blocklane'
					),
					__(
						'Triggers: time on the page, or a link or class you add to any element.',
						'blocklane'
					),
					__(
						'Display rules for the whole site or chosen pages and posts.',
						'blocklane'
					),
					__(
						'A set number of days before a popup shows again after a visitor closes it.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						'Popups are listed under the Blocklane menu and edited in the block editor.',
						'blocklane'
					),
					__(
						'Only administrators can create or edit popups.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Centered popups act as dialogs: focus stays inside, Escape closes them, and focus returns afterward.',
						'blocklane'
					),
					__(
						'Popups stay off the Coming soon and Maintenance pages unless a popup opts in.',
						'blocklane'
					),
					__(
						'Turning this off stops popups from showing; saved popups are kept.',
						'blocklane'
					),
					__(
						'Scroll and exit-intent triggers and some display rules are part of Blocklane Pro.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/popups/',
	},
	chrome: true,
};
