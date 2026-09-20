/**
 * Advanced row: Basic SEO (seo) — owned by the `module:seo` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'seo',
	title: __( 'Basic SEO', 'blocklane' ),
	short: __(
		'Search titles, descriptions, schema, and a live preview.',
		'blocklane'
	),
	description: __(
		'Adds an SEO panel to the page and post editor: a custom search title, a meta description, a schema type (Article or FAQ), a hide-from-search-engines switch, and a live preview of the search result. The front end emits the matching title tag, meta description, Open Graph tags for social sharing, and schema.org structured data. If a dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress…) is active, Blocklane’s output stands down automatically so tags are never doubled.',
		'blocklane'
	),
};
