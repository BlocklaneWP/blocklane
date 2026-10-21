/**
 * What the site will be once the form is saved: the ONE derivation the
 * status Badge, the Save check (R6) and the password field read (spec
 * the C-site-privacy spec (2026-10-12) §4). Pure.
 *
 * It follows the store's own rule (the invariant in `Site_Lock::save()`,
 * inc/site-lock/class-blocklane-pro-site-lock.php): Coming soon is a gate
 * only when a password exists, and removing the password switches the gate
 * off. The server still enforces it; this is what the screen says first.
 */

/**
 * The status Badge's intent per status (design spec Amendment O, X4):
 * Public and Removing are Success, Coming soon is Info, Maintenance and
 * "not live yet" are Warning. The values are `@wordpress/ui` Badge intents;
 * the frames' icons and `-weak` fills are the WPDS 22.3 library's, and the
 * runtime Badge draws neither (Amendment E, a recorded exception).
 */
export const STATUS_INTENT = Object.freeze( {
	public: 'stable',
	removing: 'stable',
	'needs-password': 'medium',
	'coming-soon': 'informational',
	maintenance: 'medium',
} );

/**
 * Whether the field holds a password other than the saved one. A password
 * the eye revealed and nobody edited is the saved one (code item 6).
 *
 * @param {Object}      draft          The form.
 * @param {string}      draft.password What the field holds.
 * @param {string|null} draft.revealed The cleartext the reveal returned, if any.
 * @return {boolean} Changed.
 */
export const passwordChanged = ( { password, revealed } ) =>
	'' !== password && password !== revealed;

/**
 * @param {Object}  draft             The form: enabled, mode, password, revealed, clearPassword.
 * @param {Object}  saved             The last GET or save reply.
 * @param {boolean} saved.hasPassword A password is stored.
 * @return {string} public | removing | needs-password | coming-soon | maintenance.
 */
export const visibilityStatus = ( draft, saved ) => {
	if ( ! draft.enabled ) {
		return 'public';
	}
	if ( 'maintenance' === draft.mode ) {
		return 'maintenance';
	}
	if ( draft.clearPassword ) {
		return 'removing';
	}
	const willHavePassword = '' !== draft.password || !! saved.hasPassword;
	return willHavePassword ? 'coming-soon' : 'needs-password';
};
