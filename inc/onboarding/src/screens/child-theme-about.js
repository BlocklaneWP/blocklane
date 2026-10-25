/**
 * The Create Child Theme screen's About (design spec Amendment AN 1: one
 * About layout on every screen; the C-child-theme spec (2026-10-12),
 * "Decisions"), drawn by the shared About component: the video slot, one
 * intro line, labeled groups, a muted note and the one Learn more link.
 * The card's title stays D17's "About child themes" (meta/child-theme.js).
 *
 * Re-shaped fresh from what the generator and the import do; it replaces
 * the intro and four questions the card carried before (their hashes are
 * in child-theme-copy.test.js, which holds this copy to them). PENDING
 * APPROVAL in the Create Child Theme About copy (2026-10-13). Its one link
 * is the Settings row's own Learn more URL, read from the row, never
 * retyped.
 */
import { __ } from '@wordpress/i18n';

import childThemeRow from './settings/rows/child-theme-tool';

export const ABOUT = {
	intro: __(
		'A child theme borrows everything from its parent and keeps only what you add to it. This screen writes one for your parent theme and moves the site onto it.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'What gets written', 'blocklane' ),
			items: [
				__(
					'A new folder beside the parent theme, holding a style.css with your details, a functions.php that loads that stylesheet, and a screenshot.',
					'blocklane'
				),
				__(
					'Fields left empty take the value shown in them, drawn from your site and the parent.',
					'blocklane'
				),
				__(
					'With the import box ticked, the styles, templates and template parts saved in the Site Editor become files in the new folder.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Why make one', 'blocklane' ),
			items: [
				__(
					'A parent update replaces the parent’s files and leaves the child’s folder alone.',
					'blocklane'
				),
				__(
					'Work kept as files can go into version control or travel to another site.',
					'blocklane'
				),
				__(
					'The Site Editor saves its changes per theme, so a theme you switch to starts without them unless you import them.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Going back', 'blocklane' ),
			items: [
				__(
					'Activate any other theme under Appearance → Themes. The child is kept on that screen, ready to delete whenever you like.',
					'blocklane'
				),
				__(
					'This screen never writes over a theme folder that exists, so every new child needs a name or text domain of its own.',
					'blocklane'
				),
			],
		},
	],
	note: __(
		'Switching Create Child Theme off in Blocklane Settings takes this screen away; the child themes you made stay as they are.',
		'blocklane'
	),
	learnMoreUrl: childThemeRow.about.learnMoreUrl,
};

export const LEARN_MORE_LABEL = __(
	'Learn more about Create Child Theme',
	'blocklane'
);
