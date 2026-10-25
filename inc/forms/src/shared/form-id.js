/**
 * A form's id (`formId`): minted by the editor, stored in the content, and
 * found by the submission route.
 *
 * The route sanitizes a posted id with sanitize_title() and finds the copies
 * of a form by searching stored content for `"formId":"<sanitized id>"`. So
 * an id that sanitize_title() would rewrite — a character outside letters,
 * digits, `_` and `-`, a doubled `-`, or a `-` at either end — can never be
 * found, and every submission of the form is lost (case is the one
 * difference the search tolerates). The editor regenerates such an id when
 * the form is next loaded (spec 2026-10-06-forms-form-ownership F15, NP20).
 */

/**
 * A fresh id: `f` and eight characters of a random UUID's hex digits.
 *
 * @return {string} The id.
 */
export const generateFormId = () =>
	'f' +
	( window.crypto?.randomUUID?.() || Math.random().toString( 36 ).slice( 2 ) )
		.replace( /-/g, '' )
		.slice( 0, 8 );

/**
 * Whether a stored id is one the server can never find (and so must be
 * regenerated). An empty id matches none of the patterns: the editor mints
 * one anyway.
 *
 * @param {unknown} id The stored formId.
 * @return {boolean} True when sanitize_title() would rewrite it beyond case.
 */
export const formIdNeedsRegenerating = ( id ) =>
	'string' === typeof id &&
	( /[^A-Za-z0-9_-]/.test( id ) ||
		id.includes( '--' ) ||
		/^-|-$/.test( id ) );
