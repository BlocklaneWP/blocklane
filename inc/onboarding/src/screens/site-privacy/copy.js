/**
 * Every sentence the Site Visibility screen chooses between, in one table
 * (the C-site-privacy spec (2026-10-12) §4). Each one is either
 * unchanged shipped copy or a string an amendment of the design spec
 * decided: D26 (no HTTP codes), D27 ("Coming soon"), W11/X4 ("not live
 * yet"), V2 and W5/R3b (load and save failures), V5 (the field error), and
 * code item 6 (the Maintenance preview help, decision D-SV2).
 *
 * Functions, so each string is translated when it is drawn.
 */
import { __ } from '@wordpress/i18n';

/**
 * The status Badge's sentence for a status key (status.js).
 *
 * @param {string} key public | removing | needs-password | coming-soon | maintenance.
 * @return {string} The sentence.
 */
export const statusSentence = ( key ) =>
	( {
		public: __( 'Public — anyone can view the site.', 'blocklane' ),
		removing: __(
			'Removing the password returns the site to public.',
			'blocklane'
		),
		'needs-password': __( 'Coming soon — not live yet', 'blocklane' ),
		'coming-soon': __(
			'Coming soon — visitors see a password page.',
			'blocklane'
		),
		maintenance: __(
			'Maintenance — visitors see a maintenance page; search engines are told to come back later.',
			'blocklane'
		),
	} )[ key ];

/**
 * The site password's help line: one of five mutually exclusive cases.
 *
 * @param {Object}  field
 * @param {boolean} field.clearPassword Removal is checked.
 * @param {boolean} field.hasPassword   A password is saved.
 * @param {boolean} field.canReveal     The saved password can be shown.
 * @param {boolean} field.changed       The field holds a value other than the saved one.
 * @return {string} The help.
 */
export const passwordHelp = ( {
	clearPassword,
	hasPassword,
	canReveal,
	changed,
} ) => {
	if ( clearPassword ) {
		return __(
			'The saved password will be removed and Coming soon switched off, returning the site to public.',
			'blocklane'
		);
	}
	if ( hasPassword && changed ) {
		return __(
			'Saving will set the site password to this value. Or remove it below.',
			'blocklane'
		);
	}
	if ( hasPassword && canReveal ) {
		return __(
			'A site password is set — use the eye to reveal it, type to replace it, or remove it below.',
			'blocklane'
		);
	}
	if ( hasPassword ) {
		return __(
			'A password was set before this update, so it can’t be shown — type a new one to change and reveal it.',
			'blocklane'
		);
	}
	return __(
		'Visitors enter this to view the site. Coming soon won’t go live until you set one.',
		'blocklane'
	);
};

/**
 * The shareable preview link's help, by mode: the link opens the site in
 * either mode (the gate's cookie covers both), so only Coming soon has a
 * password to skip.
 *
 * @param {string} mode coming-soon | maintenance.
 * @return {string} The help.
 */
export const previewHelp = ( mode ) =>
	'maintenance' === mode
		? __(
				'Anyone with this link can view the site while it’s closed for maintenance. Regenerate it to revoke links you’ve already shared.',
				'blocklane'
			)
		: __(
				'Anyone with this link can view the site without the password. Regenerate it to revoke links you’ve already shared.',
				'blocklane'
			);

/** @return {string} The load failure (V2, R3a). */
export const loadError = () =>
	__(
		'Site Visibility settings could not be loaded. Your site’s visibility hasn’t changed.',
		'blocklane'
	);

/** @return {string} A failed write: Save, or Regenerate link (W5/R3b, V2). */
export const saveError = () =>
	__(
		'Your changes could not be saved. Your site’s visibility hasn’t changed.',
		'blocklane'
	);

/** @return {string} The field error when Coming soon has no password (V5). */
export const passwordRequired = () =>
	__( 'Enter a site password.', 'blocklane' );
