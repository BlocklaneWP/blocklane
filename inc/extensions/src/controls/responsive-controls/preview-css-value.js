/**
 * The editor preview's one door from a stored length to a CSS value.
 *
 * A stored `var:preset|…` reference is resolved by core's style engine, the
 * same resolver core's own block supports use, so the preview names the
 * custom property core prints for that preset. The result is then refused
 * when it could end the declaration, open or close a rule, or leave the
 * <style> element (; { } < > and quotes), which mirrors the server's
 * blocklane_pro_responsive_length(). Anything that is not a string comes out
 * as ''.
 *
 * @package
 */

import { getCSSValueFromRawStyle } from '@wordpress/style-engine';

const CSS_VALUE_UNSAFE = /[;{}<>"']/;

/**
 * Resolve a stored value for the preview <style>, or '' when it is refused.
 *
 * @param {unknown} value The stored value.
 * @return {string} A CSS value safe to print inside a declaration, or ''.
 */
export function previewCssValue( value ) {
	const resolved = getCSSValueFromRawStyle( value );
	return typeof resolved === 'string' && ! CSS_VALUE_UNSAFE.test( resolved )
		? resolved
		: '';
}
