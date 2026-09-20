/**
 * Advanced row: Forms (forms) — owned by the `module:forms` unit.
 *
 * The row lives in its own file because its toggle belongs to a unit: an
 * edition that does not carry the unit does not carry this file, and its line
 * in screens/advanced/rows.js is filtered out, so the copy never reaches the
 * bundle. AdvancedScreen owns the display ORDER (ROW_ORDER) and merges
 * whatever the index still re-exports.
 */

import { __ } from '@wordpress/i18n';

export default {
	slug: 'forms',
	title: __( 'Forms', 'blocklane' ),
	short: __(
		'Build forms from blocks — fields, submit, messages.',
		'blocklane'
	),
	description: __(
		'Adds a Form block suite to the editor: text, email, phone, URL, number, date, hidden, and consent fields, dropdowns, radio and checkbox groups, a submit button, and success and error messages authored as blocks. Forms live in your content — drop one in as a pattern, restyle it with the theme, no shortcodes. Submissions are validated on the server, emailed to you, and kept in a Forms inbox (with a per-form auto-responder) even when email delivery fails.',
		'blocklane'
	),
};
