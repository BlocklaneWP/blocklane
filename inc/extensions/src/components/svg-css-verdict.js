/**
 * The one verdict on CSS inside an SVG, in the editor: the browser-side twin
 * of blocklane_pro_svg_css_verdict() (inc/svg-css-policy.php).
 *
 * MIRROR CONTRACT. Both implement the same four rules, in the same order,
 * and answer the same refusal code for the same text; one table,
 * bin/fixtures/svg-css.json, drives both (jest beside this file, the review
 * battery's WV1 on the server). The server copy is the security boundary;
 * this one keeps the editor's previews to what the server will store.
 *
 * It admits; it does not recognize. Nothing is decoded:
 *   1. any `\` → blocklane_svg_css_escape;
 *   2. `@import`, ASCII case-insensitive → blocklane_svg_css_import;
 *   3. image-set(, -webkit-image-set(, image(, cross-fade(,
 *      -webkit-cross-fade(, element( or -moz-element( at an identifier
 *      boundary → blocklane_svg_css_function;
 *   4. a url( or src( (blanks allowed before the parenthesis) whose target,
 *      after blanks, at most one quote and blanks again, does not start
 *      with `#` → blocklane_svg_css_reference.
 * Blanks are space, tab, LF, CR, FF and VT, spelled out as the server does.
 */

const IMPORT = /@import/i;
const FETCHING_FUNCTION =
	/(?<![a-z0-9_-])(?:-webkit-image-set|image-set|image|-webkit-cross-fade|cross-fade|-moz-element|element)\(/i;
const REFERENCE =
	/(?:url|src)[\x20\t\n\r\f\v]*\([\x20\t\n\r\f\v]*["']?[\x20\t\n\r\f\v]*([\s\S]?)/gi;

/**
 * Whether CSS text inside an SVG may stay.
 *
 * @param {string} css An attribute value or a `<style>` element's text, as parsed.
 * @return {true|{code: string}} True when admitted, else the first rule it breaks.
 */
export default function cssVerdict( css ) {
	if ( css.includes( '\\' ) ) {
		return { code: 'blocklane_svg_css_escape' };
	}
	if ( IMPORT.test( css ) ) {
		return { code: 'blocklane_svg_css_import' };
	}
	if ( FETCHING_FUNCTION.test( css ) ) {
		return { code: 'blocklane_svg_css_function' };
	}
	for ( const match of css.matchAll( REFERENCE ) ) {
		if ( match[ 1 ] !== '#' ) {
			return { code: 'blocklane_svg_css_reference' };
		}
	}
	return true;
}
