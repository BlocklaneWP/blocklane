/**
 * The Forms screen's About card (design spec Amendment AN 1: one About
 * layout on every screen), drawn by the shared About component through
 * BlocklanePage's `details` door: the video slot, one intro line, labeled
 * groups, a muted note and the one Learn more link. The shell draws the
 * card, its title ("About Forms"), its close and the header's details
 * toggle.
 *
 * Written fresh from what the forms runtime, its controller and this screen
 * do; APPROVED (Garrett, 2026-10-13) in the Forms About copy for review.
 * Every sentence in this file is true in BOTH editions.
 *
 * What a contributing unit adds is not here. The Forms screen's
 * contributions index (./contributions.js) re-exports, one line per unit, a
 * file that unit owns, and bin/generate-edition.php drops the line and the
 * file from an edition without the unit (EDITION_DROP_INDEXES, grammar B in
 * DROP mode). So the file-upload sentences reach only a build that carries
 * block:form-file, by construction, and none of them is spelled in this
 * file, its test or any other core source (#2394). The one link is the
 * Settings row's own Learn more URL, read from the row, never retyped.
 */
import { __ } from '@wordpress/i18n';

import { About } from '../../components/About';
import formsRow from '../settings/rows/forms';
import * as contributions from './contributions';

export const ABOUT = {
	intro: __(
		'Read what visitors send through your forms, and set the values a form uses unless it sets its own.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'Submissions', 'blocklane' ),
			items: [
				__(
					'A submission keeps its field labels from the moment it arrived, even after the form is edited.',
					'blocklane'
				),
				__(
					'Export CSV downloads only the submissions that match the filters you have applied.',
					'blocklane'
				),
				__(
					'Submissions from a form with storing turned off never reach this screen.',
					'blocklane'
				),
				__(
					'Auto-delete runs once a day and removes submissions older than the number of days you set.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Email and spam', 'blocklane' ),
			items: [
				__(
					"A failed form email shows a notice on both tabs, with the mailer's error when it gives one.",
					'blocklane'
				),
				__(
					'One network address can send five submissions a minute; any more are refused.',
					'blocklane'
				),
				__(
					'Only administrators can read submissions or change the settings on this screen.',
					'blocklane'
				),
			],
		},
	],
	note: __(
		'Switching Forms off in Blocklane Settings hides this screen and removes the form blocks, so your forms disappear. Submissions stay, and auto-delete pauses.',
		'blocklane'
	),
	learnMoreUrl: formsRow.about.learnMoreUrl,
};

export const LEARN_MORE_LABEL = __( 'Learn more about Forms', 'blocklane' );

/**
 * The groups the contributing units in this build add, in the index's
 * order. A part says whether it applies (`present`, its unit's own
 * presence question) and may carry one group (`group`, the About shape's
 * { label, items }); a part with only screen copy adds none.
 *
 * @return {Array<{label: string, items: string[]}>} The groups to append.
 */
export const contributedGroups = () =>
	Object.values( contributions )
		.filter( ( part ) => part.group && part.present() )
		.map( ( part ) => part.group );

/** @return {JSX.Element} The details card's body. */
export const AboutForms = () => (
	<About
		intro={ ABOUT.intro }
		groups={ [ ...ABOUT.groups, ...contributedGroups() ] }
		note={ ABOUT.note }
		learnMore={ { url: ABOUT.learnMoreUrl, label: LEARN_MORE_LABEL } }
	/>
);

export default AboutForms;
