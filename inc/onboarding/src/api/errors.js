/**
 * One reading of a REST/apiFetch failure for the dashboard's notices.
 *
 * The fallback is translated and non-empty on purpose. Consumers render
 * `{ error ? <p role="alert">{ error }</p> : null }`, so an empty string
 * for a real failure would roll state back with nothing on screen — a
 * failure disguised as the success type. No error at all (null/undefined)
 * still reads as '', which is "nothing to show", not a failure.
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {unknown} error A thrown value, a WP_Error-shaped object, or a string.
 * @return {string} The message to show; '' when there is no error.
 */
export const errorMessage = ( error ) => {
	if ( ! error ) {
		return '';
	}
	if ( typeof error === 'string' ) {
		return error;
	}
	return (
		error.message ||
		error.code ||
		__( 'Something went wrong.', 'blocklane' )
	);
};
