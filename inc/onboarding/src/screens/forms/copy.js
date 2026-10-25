/**
 * The Forms screen's delete and storage copy — one table for every door that
 * deletes (the row action, the bulk action, the entry modal, "Delete all"),
 * for the uninstall help and for the upload cap control (the C-forms spec
 * (2026-10-12) §1; design spec R4a, V3, V17, R3a, Amendment N).
 *
 * Every sentence here is edition-neutral. The variants that mention files are
 * not spelled in this file: block:form-file owns them in
 * ./file-upload/copy.js, and ./contributions.js re-exports that file, which
 * bin/generate-edition.php drops with the unit (grammar B, DROP mode). So a
 * build without the unit carries none of them, and a free build shows the
 * plain sentence (Amendment N: free has no File Upload field) whatever the
 * payload says (#2394, decision DF4 of the C-forms spec). `contributed()`
 * picks a part's variant only while the part's own presence question,
 * keepsFiles(), says yes.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import * as contributions from './contributions';

export { keepsFiles } from './keeps-files';

/**
 * The variant a present contributing part gives for one of this table's
 * sentences, or null when no part in this build gives one. At most one part
 * offers each key (contributions.test.js FC1), so the first offer found is
 * the only one.
 *
 * @param {string} key The sentence's key in a part's `copy`.
 * @return {?string} The part's sentence.
 */
const contributed = ( key ) => {
	for ( const part of Object.values( contributions ) ) {
		if ( part.copy?.[ key ] && part.present() ) {
			return part.copy[ key ];
		}
	}
	return null;
};

/**
 * The title of a confirm for `count` chosen submissions (R4a).
 *
 * @param {number} count How many.
 * @return {string} "Delete 3 submissions?".
 */
export const deleteTitle = ( count ) =>
	sprintf(
		/* translators: %d: number of submissions. */
		_n(
			'Delete %d submission?',
			'Delete %d submissions?',
			count,
			'blocklane'
		),
		count
	);

/**
 * What deleting `count` chosen submissions does.
 *
 * @param {number} count How many.
 * @return {string} The confirm's body.
 */
export const deleteBody = ( count ) =>
	1 === count
		? ( contributed( 'deleteOne' ) ??
			__( 'This permanently deletes the submission.', 'blocklane' ) )
		: ( contributed( 'deleteMany' ) ??
			__(
				'This permanently deletes the selected submissions.',
				'blocklane'
			) );

/**
 * The title of the "Delete all" confirm (V17): the count when it is known.
 *
 * @param {number} total Every stored submission.
 * @return {string} "Delete all 10 submissions?".
 */
export const deleteAllTitle = ( total ) =>
	total > 0
		? sprintf(
				/* translators: %d: number of stored submissions. */
				_n(
					'Delete all %d submission?',
					'Delete all %d submissions?',
					total,
					'blocklane'
				),
				total
			)
		: __( 'Delete all submissions?', 'blocklane' );

/**
 * What "Delete all" does.
 *
 * @return {string} The confirm's body.
 */
export const deleteAllBody = () =>
	contributed( 'deleteAll' ) ??
	__( 'This permanently deletes every submission.', 'blocklane' );

/**
 * The toast after a delete, and the notice after "Delete all".
 *
 * @param {number} count How many the server deleted.
 * @return {string} "3 submissions deleted.".
 */
export const deletedMessage = ( count ) =>
	sprintf(
		/* translators: %d: number of deleted submissions. */
		_n(
			'%d submission deleted.',
			'%d submissions deleted.',
			count,
			'blocklane'
		),
		count
	);

/**
 * "Delete all" failed outright (design spec Amendment AE, decision 7; spec
 * DF3). Never the server's message: the operator needs to know nothing they
 * can act on beyond trying again.
 *
 * @return {string} The sentence.
 */
export const wipeFailedMessage = () =>
	__( 'Your submissions could not be deleted. Try again.', 'blocklane' );

/**
 * The help under "Delete data on uninstall".
 *
 * @return {string} The help line.
 */
export const uninstallHelp = () =>
	contributed( 'uninstallHelp' ) ??
	__(
		'Off keeps submissions if the plugin is ever uninstalled — they belong to the site. Turn on only if this site must leave nothing behind.',
		'blocklane'
	);

/**
 * The site-wide upload cap control's label and help, or null in a build
 * that cannot take uploads: the cap governs the File Upload field, so with
 * no field there is no control (a live control that does nothing is the
 * shape the charter rules out). Both strings are block:form-file's.
 *
 * @return {?{label: string, help: string}} The control's copy.
 */
export const uploadCap = () => {
	const label = contributed( 'uploadCapLabel' );
	return label ? { label, help: contributed( 'uploadCapHelp' ) } : null;
};

/**
 * The inbox with nothing in it and no filter applied (D22).
 *
 * @return {string} The sentence.
 */
export const emptyInbox = () => __( 'No submissions yet.', 'blocklane' );

/**
 * The load errors (R3a, V2): what could not be loaded, then what is safe.
 *
 * @param {string} tab The tab that failed to load ('submissions' or 'settings').
 * @return {string} The sentence.
 */
export const loadErrorMessage = ( tab ) =>
	'settings' === tab
		? __(
				'The Forms settings could not be loaded. Your saved settings are unchanged.',
				'blocklane'
			)
		: __(
				'Submissions could not be loaded. Your submissions are safe.',
				'blocklane'
			);
