/**
 * Settings row: Basic SEO (`seo`).
 *
 * Owned by the `module:seo` unit: an edition without the unit carries
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
	slug: 'seo',
	store: 'advanced',
	title: __( 'Basic SEO', 'blocklane' ),
	short: __(
		'Adds search titles, descriptions, sharing tags and structured data, edited per post.',
		'blocklane'
	),
	about: {
		intro: __(
			'Adds basic SEO: per-post search titles and descriptions, sharing tags, structured data and sitemap settings.',
			'blocklane'
		),
		groups: [
			{
				label: __( 'What it adds', 'blocklane' ),
				items: [
					__(
						'Per post: a search title, description, structured data type and an option to stay out of search.',
						'blocklane'
					),
					__(
						"A meta description, Open Graph tags and JSON-LD structured data in each page's head.",
						'blocklane'
					),
					__(
						"Article and FAQ structured data, plus breadcrumb data from core's Breadcrumbs block.",
						'blocklane'
					),
					__(
						'Sitemap settings, plus an optional HTML sitemap page, llms.txt file and IndexNow pings.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Where it shows', 'blocklane' ),
				items: [
					__(
						'An SEO panel in the block editor, and SEO columns on post and page lists.',
						'blocklane'
					),
					__(
						'Site-wide settings on the SEO screen, which appears while this is on.',
						'blocklane'
					),
				],
			},
			{
				label: __( 'Good to know', 'blocklane' ),
				items: [
					__(
						"While one of six popular SEO plugins is active, Blocklane's tags and structured data stand down.",
						'blocklane'
					),
					__(
						"Titles and descriptions can be copied from five other SEO plugins' saved data without overwriting values set here.",
						'blocklane'
					),
					__(
						'Turning it off stops all SEO output; saved titles and descriptions stay.',
						'blocklane'
					),
				],
			},
		],
		note: __(
			'Ships off. Leave it off if another SEO plugin already handles the site.',
			'blocklane'
		),
		learnMoreUrl: 'https://blocklanewp.com/seo/',
	},
	screen: 'seo',
};
