/**
 * Settings row: Carousel (`carousel`).
 *
 * Owned by the `module:carousel` unit: an edition without the unit carries
 * neither this file nor its line in ../rows.js, which the generator
 * rewrites into a Pro row built from the localized catalog.
 * Its title and short are the unit's catalog label and blurb, one
 * sentence in both editions (SD8; rows.test.js RW12 holds them equal).
 *
 * One row of the Settings list (the C-settings spec (2026-10-12)
 * §3.2): its store, copy and About in one place. The copy is drafted from
 * the feature's behavior (the C-settings About copy (2026-10-12),
 * for approval).
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'carousel',
	store: 'advanced',
	title: __( 'Carousel', 'blocklane' ),
	short: __(
		'A block-native slider with arrows, dots and autoplay.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds a Carousel block for sliders built from blocks, with navigation and autoplay.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'A carousel made of a track, slides and a controls block.',
						'blocklane'
					),
					__(
						'Navigation as arrows, dots, bars or a scrollbar.',
						'blocklane'
					),
					__(
						'Slides per view and per group for desktop, tablet and mobile, plus loop, fade and autoplay.',
						'blocklane'
					),
					__(
						'Slides written by hand, or filled from a query of posts.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						'Ready-made carousel patterns are included, such as hero, testimonials and team.',
						'blocklane'
					),
					__(
						'Ships on. Turning it off unregisters the blocks, so carousels on the site stop rendering.',
						'blocklane'
					),
				],
			},
		],
		learnMoreUrl: 'https://blocklanewp.com/toolkit/',
	},
};
