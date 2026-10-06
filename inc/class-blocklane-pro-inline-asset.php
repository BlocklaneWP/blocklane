<?php
/**
 * Inline_Asset — the door for the inline styles and inline scripts this
 * plugin used to print as literal markup. It is not the only path inline
 * CSS takes: block branding, responsive controls, the class manager and the
 * carousel presets register a src-less handle and call core's
 * wp_add_inline_style() on it directly, and the content-types admin CSS is
 * added the same way to core's wp-admin handle. Core prints all of that in
 * its own style queue, the queue style() below enqueues into. (The
 * content-types gallery's CSS goes through style().)
 *
 * Those literal prints each sat where their author knew the timing worked,
 * and each carried a `phpcs:ignore` that named a source the reader could
 * not see. Plugin Check honored the annotations; the wordpress.org
 * reviewers read past them (spec of 2026-10-06, "inline assets: one
 * door"). Here a print is WordPress's own: a src-less registered handle
 * whose inline data core prints in its style queue, or core's inline-script
 * printer — so every tag is built by WP_HTML_Tag_Processor, which refuses
 * text that would close it early.
 *
 * Two shapes, chosen by WHEN the CSS must land:
 *
 *   style()       queue it for the document's next style print (the head,
 *                 or core's late print in the footer when the head has
 *                 passed). For CSS whose position among other styles is
 *                 the queue's to decide.
 *   print_style() print it now, in place (the splash after wp_head(), the
 *                 popups' support CSS right before their markup, a footer
 *                 failsafe). For CSS whose position relative to markup is
 *                 the contract.
 *
 * Every refusal is a typed WP_Error and one debug-log line; nothing here
 * ever echoes, and nothing here hooks anything. PHPStan holds the two core
 * inline-script printers to this file (blocklane.chokepointCall), and
 * bin/dist-check.php refuses a literal style or script opening tag in any
 * string or inline HTML of a PHP file in the free zip (a tag spelled whole
 * in one token; comments are not read).
 *
 * Side-effect free until called: no state, no hooks. Resolved by the
 * classmap autoloader, so the self-contained runtimes (SEO, popups, content
 * types) can call it without depending on any module having booted.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Inline_Asset {

	/**
	 * Queue CSS for the current document's next style print.
	 *
	 * Registers a src-less handle (versioned, so no MissingVersion
	 * annotation), adds the CSS as its inline data and enqueues it. A second
	 * call with the same handle appends, as core's inline data does.
	 *
	 * @param string $handle The style handle; core prints `<handle>-inline-css`.
	 * @param string $css    The CSS, without a style tag.
	 * @return true|\WP_Error True when queued; blocklane_inline_asset_empty,
	 *                        _unsafe, _taken or _late otherwise.
	 */
	public static function style( string $handle, string $css ): bool|\WP_Error {
		$refused = self::refuse_css( $handle, $css );
		if ( null === $refused && did_action( is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts' ) ) {
			$refused = self::refusal( 'blocklane_inline_asset_late', $handle, 'the document has printed its last styles, so nothing would ever print this' );
		}
		if ( null !== $refused ) {
			return $refused;
		}
		self::register( $handle, $css );
		wp_enqueue_style( $handle );
		return true;
	}

	/**
	 * Print CSS now, in place, through core's style printer.
	 *
	 * @param string $handle The style handle; core prints `<handle>-inline-css`.
	 * @param string $css    The CSS, without a style tag.
	 * @return true|\WP_Error True when printed; blocklane_inline_asset_empty,
	 *                        _unsafe, _taken or _spent otherwise.
	 */
	public static function print_style( string $handle, string $css ): bool|\WP_Error {
		$refused = self::refuse_css( $handle, $css );
		if ( null === $refused && wp_style_is( $handle, 'done' ) ) {
			$refused = self::refusal( 'blocklane_inline_asset_spent', $handle, 'this handle already printed in this document, and a second print would print nothing' );
		}
		if ( null !== $refused ) {
			return $refused;
		}
		self::register( $handle, $css );
		wp_print_styles( array( $handle ) );
		return true;
	}

	/**
	 * Print a JSON-LD script tag now, in place. `<`, `>` and `&` are encoded
	 * as JSON Unicode escapes (\u003C, \u003E, \u0026), so no value can
	 * close the tag; slashes stay unescaped.
	 *
	 * @param array<mixed> $data The structured data.
	 * @return true|\WP_Error True when printed; blocklane_inline_asset_json
	 *                        when the data cannot be encoded, _unsafe when
	 *                        core refuses the payload.
	 */
	public static function print_json_ld( array $data ): bool|\WP_Error {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) {
			return self::refusal( 'blocklane_inline_asset_json', 'application/ld+json', 'the structured data cannot be encoded as JSON' );
		}
		return self::print_script( $json, array( 'type' => 'application/ld+json' ) );
	}

	/**
	 * Print an inline script tag now, in place, through core's printer.
	 * Core escapes a JavaScript body so it cannot close its tag; for any
	 * other type it refuses a body that would, and that refusal is returned.
	 *
	 * @param string                     $js    The script body.
	 * @param array<string, string|bool> $attrs Tag attributes.
	 * @return true|\WP_Error True when printed; blocklane_inline_asset_unsafe
	 *                        when core refuses the body.
	 */
	public static function print_script( string $js, array $attrs = array() ): bool|\WP_Error {
		// The stubs type this return non-empty; core returns '' when its tag
		// processor refuses the body (wp_get_inline_script_tag(), WP 7.1).
		// @phpstan-ignore identical.alwaysFalse
		if ( '' === wp_get_inline_script_tag( $js, $attrs ) ) {
			return self::refusal( 'blocklane_inline_asset_unsafe', (string) ( $attrs['type'] ?? 'script' ), 'core refused the body: it would close its own tag' );
		}
		wp_print_inline_script_tag( $js, $attrs );
		return true;
	}

	/**
	 * The refusals style() and print_style() share, or null.
	 */
	private static function refuse_css( string $handle, string $css ): ?\WP_Error {
		if ( '' === $css ) {
			return self::refusal( 'blocklane_inline_asset_empty', $handle, 'there is no CSS to print' );
		}
		if ( false !== stripos( $css, '</style' ) ) {
			// Core 7.1 would print an EMPTY tag here, silently
			// (WP_Styles::do_item() ignores set_modifiable_text()'s refusal).
			return self::refusal( 'blocklane_inline_asset_unsafe', $handle, 'the CSS contains a closing style tag' );
		}
		$registered = wp_styles()->query( $handle, 'registered' );
		if ( $registered instanceof \_WP_Dependency && is_string( $registered->src ) && '' !== $registered->src ) {
			return self::refusal( 'blocklane_inline_asset_taken', $handle, 'the handle is registered with a stylesheet of its own' );
		}
		return null;
	}

	/**
	 * Register the handle src-less (once) and add the CSS as its inline data.
	 */
	private static function register( string $handle, string $css ): void {
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, false, array(), BLOCKLANE_PRO_VERSION );
		}
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * One refusal: the typed error, and one debug-log line naming it.
	 */
	private static function refusal( string $code, string $what, string $why ): \WP_Error {
		blocklane_pro_log( "Blocklane: inline asset {$what} not printed ({$code}): {$why}." );
		return new \WP_Error( $code, $why, array( 'handle' => $what ) );
	}
}
