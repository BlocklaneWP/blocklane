<?php
/**
 * The one verdict on CSS inside an SVG (the free security follow-up spec of
 * 2026-10-07, §3.A; #1857).
 *
 * A browser reads CSS in an SVG's presentation attributes, its style
 * attributes and its `<style>` sheets, and some of that CSS fetches: a
 * url() or src() pointing at another document, an @import, an image-set().
 * Three readers before this one each listed the spellings of a fetch they
 * knew about and decoded CSS escapes to find them, and each missed the next
 * spelling: a CR LF after a hex escape (`\75` CR LF `rl(` is `url(` to a
 * browser, #1857), a non-style attribute nobody decoded (V1), a tab inside a
 * URL's scheme (V3), `/\host` (V4). bin/svg-fetch-probe.mjs proved each one
 * fetching in a real browser.
 *
 * So this door ADMITS rather than recognizes. It decodes nothing, and it
 * answers true only for text it can prove fetches nothing; the first rule a
 * text breaks is its refusal:
 *
 *   1. any `\` — an escape is unreadable by policy (blocklane_svg_css_escape);
 *   2. `@import`, ASCII case-insensitive (blocklane_svg_css_import);
 *   3. a fetching function other than url()/src(), at an identifier
 *      boundary: image-set(, -webkit-image-set(, image(, cross-fade(,
 *      -webkit-cross-fade(, element(, -moz-element( (blocklane_svg_css_function);
 *   4. a url( or src( (ASCII case-insensitive, blanks allowed before the
 *      parenthesis, which over-refuses safely) whose target, after blanks,
 *      at most one quote and blanks again, does not start with `#`
 *      (blocklane_svg_css_reference). Blanks are space, tab, LF, CR, FF and
 *      VT, spelled out rather than \s.
 *
 * Fragment-only everywhere (decision D3): no data:, no relative path, no
 * other file on this site. What that refuses that a browser would have
 * rendered harmlessly is named in the spec's §7 (ND1, ND2).
 *
 * Its readers: the clean-room sanitizer (blocklane_pro_ext_sanitize_svg(),
 * every attribute), the upload sanitizer (\blocklane_pro\Advanced::clean_svg(),
 * every attribute value and every `<style>` text of the bytes it is about to
 * return), and — in the editor — its twin, inc/extensions/src/components/
 * svg-css-verdict.js. MIRROR CONTRACT: the twin implements these four rules
 * in this order, and one table, bin/fixtures/svg-css.json, drives both (the
 * review battery's WV1 here, jest there).
 *
 * blocklane_pro_svg_css_policy_id() names this policy. A change to these
 * rules, or to the clean-room sanitizer's element and attribute rules
 * (inc/extensions/loader/runtime-helpers.php), is a new id. The stored-icon
 * reader (inc/icon-collection/runtime.php) trusts an uploads mirror only by
 * a name derived from the policy token, which is this id AND a fingerprint
 * of both files' bytes, so a code change stops every earlier mirror being
 * read at once even when nobody bumps the id; the id stays the
 * human-readable name of the rules.
 *
 * THE MARKUP BOUND lives here too, beside the verdict both sanitizers read:
 * blocklane_pro_svg_markup_bound() and its three limits are the one
 * definition of what a sanitizer may be handed, read before parsing by
 * the upload sanitizer (\blocklane_pro\Advanced::clean_svg(), after an
 * .svgz is decompressed) and by the clean-room sanitizer
 * (blocklane_pro_ext_sanitize_svg(), which the stored-icon reader runs on
 * every request that has no current mirror). One bound, so no caller of
 * either sanitizer can be made to parse a file that exhausts the request.
 *
 * A core path, loaded in both editions by require_once from each reader. A
 * runtime file: no namespace, no class, declarations behind
 * function_exists(), so a second load under another path declares nothing.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_svg_css_verdict' ) ) {

	/**
	 * The policy's name: the day its rules last changed (see the file
	 * header for what counts as a change).
	 */
	function blocklane_pro_svg_css_policy_id(): string {
		return '2026-10-07';
	}

	/*
	 * The markup bound's limits (spec 2026-10-07-free-security-followup
	 * §13 A4, moved here by §18.13 S18-F3). The counts are of characters,
	 * not of elements or attributes: every "<" counts (an opening tag, a
	 * closing tag, a comment, CDATA, the XML declaration, a DOCTYPE), so a
	 * file of paired tags (<g>…</g>) is refused at about 50,000 elements;
	 * and every "=" counts (an attribute, a namespace declaration, one
	 * inside a URL, a style or a comment). Over-counting only refuses more.
	 * The values come from the build's probe under memory_limit=128M and
	 * max_execution_time=30: elements cost about 110 bytes each and fail on
	 * memory; attributes on ONE element cost time quadratically in the
	 * upload library (it removes them one by one) and fail on time. At
	 * these values the worst combined file peaked under 20 MB and ran in
	 * about a second. The cost of the attribute count: a drawing with more
	 * than 20,000 attributes in all is refused, legitimate or not.
	 */

	/** The most bytes of markup, plain or decompressed. */
	define( 'BLOCKLANE_PRO_SVG_MAX_BYTES', 4194304 );

	/** The most "<" characters in the markup: opening and closing tags, comments, CDATA and declarations alike. */
	define( 'BLOCKLANE_PRO_SVG_MAX_TAGS', 100000 );

	/** The most "=" characters in the markup: attributes, namespace declarations, and any inside a value. */
	define( 'BLOCKLANE_PRO_SVG_MAX_ATTRS', 20000 );

	/**
	 * Whether markup is inside the bound a sanitizer may parse: true, or
	 * blocklane_svg_too_large when any of the three counts is past its
	 * limit. Both sanitizers call it before parsing (see the file header).
	 *
	 * @param string $markup The markup, decompressed.
	 * @return bool|\WP_Error True when inside the bound (never false).
	 */
	function blocklane_pro_svg_markup_bound( string $markup ): bool|\WP_Error {
		if ( strlen( $markup ) > BLOCKLANE_PRO_SVG_MAX_BYTES
			|| substr_count( $markup, '<' ) > BLOCKLANE_PRO_SVG_MAX_TAGS
			|| substr_count( $markup, '=' ) > BLOCKLANE_PRO_SVG_MAX_ATTRS ) {
			return new \WP_Error( 'blocklane_svg_too_large', __( 'This SVG is too large to be checked safely, so it was not saved.', 'blocklane' ) );
		}
		return true;
	}

	/**
	 * Whether CSS text inside an SVG may stay: true when it can fetch nothing,
	 * else the first rule it breaks (see the file header).
	 *
	 * @param string $css An attribute value or a `<style>` element's text, as the XML parser decoded it.
	 * @return bool|\WP_Error True when admitted.
	 */
	function blocklane_pro_svg_css_verdict( string $css ): bool|\WP_Error {
		if ( str_contains( $css, '\\' ) ) {
			return new \WP_Error( 'blocklane_svg_css_escape', 'A CSS escape cannot be checked, so it is refused.' );
		}
		if ( false !== stripos( $css, '@import' ) ) {
			return new \WP_Error( 'blocklane_svg_css_import', 'A stylesheet import loads another file.' );
		}
		// The boundary: the name is not the tail of a longer identifier
		// (ASCII letters, digits, `_` and `-`). Anything else before it,
		// non-ASCII included, counts as a boundary, which can only refuse
		// more. A function token is a name and `(` with nothing between, so
		// `image (2)` in a label is not one.
		if ( 1 === preg_match( '/(?<![a-z0-9_\-])(?:-webkit-image-set|image-set|image|-webkit-cross-fade|cross-fade|-moz-element|element)\(/i', $css ) ) {
			return new \WP_Error( 'blocklane_svg_css_function', 'A CSS function that loads an image is refused.' );
		}
		preg_match_all( '/(?:url|src)[\x20\t\n\r\f\x0B]*\([\x20\t\n\r\f\x0B]*["\']?[\x20\t\n\r\f\x0B]*(.?)/is', $css, $targets );
		foreach ( $targets[1] as $first ) {
			if ( '#' !== $first ) {
				return new \WP_Error( 'blocklane_svg_css_reference', 'A CSS reference to anything but a fragment of this file is refused.' );
			}
		}
		return true;
	}
}
