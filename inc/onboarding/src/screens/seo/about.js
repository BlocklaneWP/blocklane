/**
 * The SEO screen's About card (design spec Amendment AN 1: one About layout
 * on every screen), drawn by the shared About component: the video slot, one
 * intro line, labeled groups, a muted note and the one Learn more link. The
 * shell draws the card, its title ("About SEO") and its close.
 *
 * Written fresh from what the SEO runtime, its controller and this screen
 * do, never from the help it replaces; APPROVED (Garrett, 2026-10-13) in the
 * SEO About copy for review. seo/about.test.js holds it to the shape, the
 * drafting limits and the copy rules, and keeps the retired help out by
 * hash. SEO is the same in both editions (Z2), and nothing here depends on
 * which brand marks the Overview draws. The one link is the Settings row's
 * own Learn more URL, read from the row, never retyped.
 */
import { __ } from '@wordpress/i18n';

import { About } from '../../components/About';
import seoRow from '../settings/rows/seo';

export const ABOUT = {
	intro: __(
		'Sets how search engines and shared links see your site, with the search details of everything you publish in one table.',
		'blocklane'
	),
	groups: [
		{
			label: __( 'On each tab', 'blocklane' ),
			items: [
				__(
					'Overview counts how many published items have each search field set, and which verification codes are saved.',
					'blocklane'
				),
				__(
					'Content lists everything you publish, so you can change a search title or description without opening the editor.',
					'blocklane'
				),
				__(
					'Settings holds the choices that apply to the whole site, such as sitemaps, title format and verification codes.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Built on WordPress', 'blocklane' ),
			items: [
				__(
					"Sitemap choices adjust WordPress's own sitemap; Blocklane never builds a second one.",
					'blocklane'
				),
				__(
					"Allowing search engines is WordPress's Settings → Reading option, so turning Basic SEO off leaves it as set.",
					'blocklane'
				),
				__(
					'IndexNow sends nothing while search engines are asked to stay away from the site.',
					'blocklane'
				),
			],
		},
		{
			label: __( 'Next to other SEO plugins', 'blocklane' ),
			items: [
				__(
					"Yoast SEO, Rank Math and four other SEO plugins, while active, turn off Blocklane's tags and structured data.",
					'blocklane'
				),
				__(
					'The four others are All in One SEO, SEOPress, The SEO Framework and Slim SEO.',
					'blocklane'
				),
				__(
					'Sitemap choices, the HTML sitemap, llms.txt and IndexNow keep working alongside them.',
					'blocklane'
				),
				__(
					'The import reads data other SEO plugins left behind, so it works after you deactivate them.',
					'blocklane'
				),
			],
		},
	],
	note: __(
		'Switching Basic SEO off in Blocklane Settings hides this screen and turns off everything it does on the site. Nothing you saved is deleted.',
		'blocklane'
	),
	learnMoreUrl: seoRow.about.learnMoreUrl,
};

export const LEARN_MORE_LABEL = __( 'Learn more about SEO', 'blocklane' );

/** @return {JSX.Element} The details card's body. */
export const AboutSeo = () => (
	<About
		intro={ ABOUT.intro }
		groups={ ABOUT.groups }
		note={ ABOUT.note }
		learnMore={ { url: ABOUT.learnMoreUrl, label: LEARN_MORE_LABEL } }
	/>
);

export default AboutSeo;
