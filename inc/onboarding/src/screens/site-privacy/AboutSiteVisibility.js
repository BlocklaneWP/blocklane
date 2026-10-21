/**
 * The Site Visibility screen's About (decision D-SV4; design spec Amendment
 * AN 1: one About layout on every screen), drawn by the shared About
 * component: the video slot, one intro line, labeled groups, a muted note
 * and the one Learn more link. The shell draws the card, its title and
 * close.
 *
 * Written fresh from what the screen and the site-lock gate do; PENDING
 * APPROVAL in the Site Visibility About copy (2026-10-13). Its one link is
 * the Settings row's own Learn more URL, read from the row, never retyped.
 */
import { __ } from '@wordpress/i18n';

import { About } from '../../components/About';
import siteVisibilityRow from '../settings/rows/site-privacy';

export const ABOUT = {
	intro: __(
		'This screen decides who can see the front of your site while you build it or fix it.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'The three states', 'blocklane' ),
			items: [
				__( 'Public: everyone sees the site as usual.', 'blocklane' ),
				__(
					'Coming soon: visitors get a page with a password field, and the right password lets them in for 14 days.',
					'blocklane'
				),
				__(
					'Maintenance mode: visitors get a page with no password field, and search engines are told the site is down for now and will be back.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Who gets past the page', 'blocklane' ),
			items: [
				__(
					'Anyone signed in with an account that can edit posts sees the real site.',
					'blocklane'
				),
				__(
					'Anyone with the preview link, in either state.',
					'blocklane'
				),
				__(
					'A signup form on the Coming soon page keeps accepting submissions while locked; Maintenance accepts nothing.',
					'blocklane'
				),
				__(
					'Changing the password or regenerating the preview link signs out every visitor who came in the old way.',
					'blocklane'
				),
				__(
					'Your dashboard and the login page are never blocked.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Search engines', 'blocklane' ),
			items: [
				__(
					'The Coming soon and Maintenance mode pages are sent with a header that keeps them out of search results.',
					'blocklane'
				),
				__(
					'The search engine checkbox on this screen and the one in Settings → Reading are the same setting.',
					'blocklane'
				),
				__(
					'A classic theme cannot run the gate: switching to one makes the site public, and your settings wait for a block theme.',
					'blocklane'
				),
			],
		},
	],
	note: __(
		'Switching Site Visibility off in Blocklane Settings hides this screen once the site is public. It never opens a site that is closed.',
		'blocklane'
	),
	learnMoreUrl: siteVisibilityRow.about.learnMoreUrl,
};

export const LEARN_MORE_LABEL = __(
	'Learn more about Site Visibility',
	'blocklane'
);

/** @return {JSX.Element} The details card's body. */
export const AboutSiteVisibility = () => (
	<About
		intro={ ABOUT.intro }
		groups={ ABOUT.groups }
		note={ ABOUT.note }
		learnMore={ { url: ABOUT.learnMoreUrl, label: LEARN_MORE_LABEL } }
	/>
);

export default AboutSiteVisibility;
