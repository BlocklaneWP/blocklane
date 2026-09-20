<?php
/**
 * Dynamic Values — runtime (canonical source).
 *
 * SELF-CONTAINED on purpose: it does NOT reference the plugin's namespace,
 * classes or constants, only core WordPress. That rule was written when this
 * file was stamped into a must-use plugin and ran outside the plugin; the
 * stamping was removed in 2026-08 and the rule is kept, because a binding
 * source registered without needing a plugin class loaded cannot be tripped
 * by load order.
 *
 * The plugin requires this file in-process, so tokens resolve only while
 * Blocklane Pro is active. Tokens come from the option, and an absent row
 * means none — there is no second source.
 *
 * Because both copies are separate files loaded in the same request, ALL
 * declarations live inside one `if ( ! function_exists( ... ) )` block. Top-level
 * function declarations are bound at COMPILE time, so a runtime `return` guard
 * above them cannot prevent a redeclaration fatal — wrapping them makes them
 * conditionally (late) bound, so the second copy is a clean no-op.
 *
 * It provides, from the `blocklane_pro_dynamic_values` option: the Block Bindings
 * source `blocklane-pro/dynamic-value`, the `[blocklane-value]` shortcode, and the
 * inline-token resolver (<span data-blocklane-value="key">). Values are sanitized by
 * type on save (see the Dynamic_Values store) and escaped on output — no user
 * markup is ever emitted.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safe mode silences every Blocklane Pro surface, the generated runtimes included —
// define BLOCKLANE_PRO_SAFE_MODE in wp-config to troubleshoot with all of it off
// (see the plugin's Modules class). The declarations below are conditionally
// (late) bound, so returning here cleanly skips them.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

if ( ! defined( 'BLOCKLANE_PRO_DV_OPTION' ) ) {
	define( 'BLOCKLANE_PRO_DV_OPTION', 'blocklane_pro_dynamic_values' );
}

if ( ! function_exists( 'blocklane_pro_dv_tokens' ) ) {

	/**
	 * Read the token list from the option Blocklane Pro maintains.
	 *
	 * An absent row means no tokens are defined. There is no second source to
	 * consult: the snapshot fallback belonged to the generated mu-plugin, and
	 * that whole system was removed in 2026-08.
	 *
	 * @return array<int,array{key:string,label:string,value:string,type:string}>
	 */
	function blocklane_pro_dv_tokens() {
		$stored = get_option( BLOCKLANE_PRO_DV_OPTION, null );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Resolve a full token (value + type) by key.
	 *
	 * @param string $key Token key.
	 * @return array{key:string,label:string,value:string,type:string}|null
	 */
	function blocklane_pro_dv_token( $key ) {
		$key = sanitize_key( (string) $key );

		foreach ( blocklane_pro_dv_tokens() as $token ) {
			if ( isset( $token['key'] ) && $token['key'] === $key ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Format a value for output by type. email/phone/url become accessible,
	 * SEO-friendly links (mailto: / tel: / href) when $link is true; everything
	 * else (and link=false) is plain escaped text. Values are sanitized on save,
	 * and hrefs go through esc_url (protocol-checked), so output is safe.
	 *
	 * @param string $value Stored value.
	 * @param string $type  Value type.
	 * @param bool   $link  Whether to wrap email/phone/url in a link.
	 * @return string
	 */
	function blocklane_pro_dv_format_value( $value, $type, $link = true ) {
		if ( '' === (string) $value ) {
			return '';
		}

		if ( $link ) {
			switch ( $type ) {
				case 'email':
					return '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';
				case 'phone':
					$dial = preg_replace( '/[^\d+]/', '', $value );
					if ( '' === $dial ) {
						break; // No dialable digits — fall through to plain text.
					}
					return '<a href="' . esc_url( 'tel:' . $dial ) . '">' . esc_html( $value ) . '</a>';
				case 'url':
					return '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
			}
		}

		return esc_html( $value );
	}

	/**
	 * Block Bindings source callback: resolve { key } to the token value; an
	 * unknown key yields '' so a stale binding renders empty rather than leaking
	 * the placeholder.
	 *
	 * @param array     $source_args    Binding args (expects `key`).
	 * @param \WP_Block $block_instance Block instance (unused).
	 * @param string    $attribute_name Bound attribute name.
	 * @return string
	 */
	function blocklane_pro_dv_source_value( $source_args, $block_instance = null, $attribute_name = '' ) {
		unset( $block_instance );

		$key   = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
		$token = blocklane_pro_dv_token( $key );
		$value = $token ? (string) $token['value'] : '';

		// Core inserts `url` bindings via WP_HTML_Tag_Processor::set_attribute(),
		// which attribute-escapes but does NOT protocol-filter — so a text-type
		// token holding e.g. `javascript:...` would land unfiltered in an href/src.
		// Resolve by token type when the target is a link — a phone becomes a
		// tel: link, an email a mailto: link — then enforce a safe URL
		// regardless (idempotent for url/image tokens, esc_url_raw'd on save).
		if ( 'url' === $attribute_name ) {
			$type = $token ? (string) $token['type'] : 'text';
			if ( 'phone' === $type && '' !== $value ) {
				$value = 'tel:' . preg_replace( '/[^0-9+]/', '', $value );
			} elseif ( 'email' === $type && '' !== $value ) {
				$value = 'mailto:' . $value;
			}
			return esc_url_raw( $value );
		}

		return $value;
	}

	/**
	 * Register the Block Bindings source. Must run on `init`.
	 */
	function blocklane_pro_dv_register_source() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		// Idempotent: skip if already registered (init can fire more than once in
		// a request) so we don't trip a "_doing_it_wrong" notice.
		if (
			class_exists( '\WP_Block_Bindings_Registry' )
			&& \WP_Block_Bindings_Registry::get_instance()->is_registered( 'blocklane-pro/dynamic-value' )
		) {
			return;
		}

		register_block_bindings_source(
			'blocklane-pro/dynamic-value',
			array(
				'label'              => __( 'Dynamic Value', 'blocklane' ),
				'get_value_callback' => 'blocklane_pro_dv_source_value',
			)
		);
	}

	/**
	 * Resolve inline value tokens (<span data-blocklane-value="key">) to the global
	 * value, dropping the wrapper. Authored via the editor's "Insert value"
	 * merge-tag format. Values are global, so there's no record context to read.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block (unused — values are global).
	 * @return string
	 */
	function blocklane_pro_dv_resolve_inline( $block_content, $block ) {
		unset( $block );

		if ( false === strpos( (string) $block_content, 'data-blocklane-value' ) ) {
			return $block_content;
		}

		return preg_replace_callback(
			// Inner content is plain label text — match [^<]* (not .*?/s) to avoid
			// pathological backtracking and never span across nested markup.
			'/<span\b[^>]*\bdata-blocklane-value="([^"]+)"[^>]*>[^<]*<\/span>/i',
			static function ( $m ) {
				$token = blocklane_pro_dv_token( $m[1] );
				return $token
					? blocklane_pro_dv_format_value( $token['value'], $token['type'] )
					: '';
			},
			$block_content
		);
	}

	/**
	 * [blocklane-value key="…" link="true|false"] — output a value, linked by type
	 * (mailto/tel/href) unless link="false".
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	function blocklane_pro_dv_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'  => '',
				'link' => 'true',
			),
			$atts,
			'blocklane-value'
		);

		$token = blocklane_pro_dv_token( $atts['key'] );
		if ( null === $token ) {
			return '';
		}

		$link = ! in_array( strtolower( (string) $atts['link'] ), array( 'false', '0', 'no' ), true );

		return blocklane_pro_dv_format_value( $token['value'], $token['type'], $link );
	}

	add_action( 'init', 'blocklane_pro_dv_register_source' );
	add_shortcode( 'blocklane-value', 'blocklane_pro_dv_shortcode' );
	add_filter( 'render_block', 'blocklane_pro_dv_resolve_inline', 10, 2 );
}
