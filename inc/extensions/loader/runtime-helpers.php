<?php
/**
 * Extensions runtime helpers — the settings-reading layer the front-end loader
 * files share.
 *
 * SELF-CONTAINED on purpose: no plugin namespace or classes, only
 * feature-detected BLOCKLANE_PRO_* defines. Kept that way deliberately — the
 * runtime is the render path and must not grow a dependency on the admin half.
 *
 * Declarations stay function_exists-wrapped: cheap, and it keeps a
 * double-require from fataling the front end.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_ext_base_path' ) ) {

	/** Absolute base path of the extensions runtime. */
	function blocklane_pro_ext_base_path() {
		return BLOCKLANE_PRO_PATH . '/inc/extensions';
	}

	/** Base URL of the extensions runtime — front-end assets resolve against this. */
	function blocklane_pro_ext_base_url() {
		return BLOCKLANE_PRO_URL . '/inc/extensions';
	}

	/** Cache-buster for enqueued runtime assets. */
	function blocklane_pro_ext_version() {
		return defined( 'BLOCKLANE_PRO_VERSION' ) ? BLOCKLANE_PRO_VERSION : '1.0';
	}

	/**
	 * Read a settings option.
	 *
	 * The second argument is vestigial — it named this setting's key in the
	 * generated runtime's settings snapshot, which no longer exists. Kept so
	 * the call sites need not all change in the same commit; drop it when
	 * they do.
	 *
	 * @param string $option       Option name.
	 * @param string $snapshot_key Unused.
	 * @return mixed|null Null when the row is absent.
	 */
	function blocklane_pro_ext_setting( $option, $snapshot_key = '' ) {
		unset( $snapshot_key );

		return get_option( $option, null );
	}

	/**
	 * Whether an extension is enabled: its option row, else the shipped default.
	 *
	 * @param string $slug    Extension slug.
	 * @param bool   $default Shipped default for the slug.
	 * @return bool
	 */
	function blocklane_pro_ext_enabled( $slug, $default ) {
		$stored = get_option( 'blocklane_pro_ext_' . $slug, null );

		return null !== $stored ? (bool) $stored : (bool) $default;
	}

	/**
	 * Parse a theme.json viewport breakpoint (numeric px/em/rem string) to px.
	 * Mirrors core's WP_Theme_JSON::get_viewport_breakpoint_value_in_pixels()
	 * (16px em/rem base). Returns null for anything else.
	 *
	 * @param mixed $value Raw settings.viewport value.
	 * @return int|null Pixels, or null when invalid.
	 */
	function blocklane_pro_ext_viewport_px( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(?:\d+|\d*\.\d+)(px|em|rem)$/', trim( $value ), $m ) ) {
			return null;
		}
		$number = (float) trim( $value );
		return (int) round( 'px' === $m[1] ? $number : $number * 16 );
	}

	/**
	 * The global responsive breakpoints. Since core 7.1, theme.json's
	 * settings.viewport (via wp_get_global_settings) is the source of truth —
	 * it drives core's responsive style states and block visibility, so our
	 * media queries must be built from the same numbers. The legacy
	 * blocklane_pro_breakpoints option (and, in the baked home, the snapshot)
	 * remains the fallback for sites whose theme doesn't define a viewport.
	 *
	 * @return array{tablet:int,mobile:int}
	 */
	function blocklane_pro_ext_breakpoints() {
		// Core's setting first. When settings.viewport is undefined,
		// wp_get_global_settings() returns the whole settings tree (no such
		// path), so only trust the result when both keys parse as lengths.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$viewport  = wp_get_global_settings( array( 'viewport' ) );
			$vp_tablet = is_array( $viewport ) ? blocklane_pro_ext_viewport_px( $viewport['tablet'] ?? null ) : null;
			$vp_mobile = is_array( $viewport ) ? blocklane_pro_ext_viewport_px( $viewport['mobile'] ?? null ) : null;
			if ( null !== $vp_tablet && null !== $vp_mobile && $vp_mobile < $vp_tablet ) {
				return array(
					'tablet' => $vp_tablet,
					'mobile' => $vp_mobile,
				);
			}
		}

		$stored = blocklane_pro_ext_setting( 'blocklane_pro_breakpoints', 'breakpoints' );
		$stored = is_array( $stored ) ? $stored : array();

		$tablet = isset( $stored['tablet'] ) ? (int) $stored['tablet'] : 768;
		$mobile = isset( $stored['mobile'] ) ? (int) $stored['mobile'] : 480;

		$tablet = max( 360, min( 2000, $tablet ) );
		$mobile = max( 240, min( 1600, $mobile ) );
		if ( $mobile >= $tablet ) {
			$mobile = 480 < $tablet ? 480 : max( 240, (int) round( $tablet * 0.6 ) );
		}

		return array(
			'tablet' => $tablet,
			'mobile' => $mobile,
		);
	}

	/**
	 * The CORE viewport slots only — per-slot nullable, NO option fallback.
	 * The advanced-grid migration gates on this, never on
	 * blocklane_pro_ext_breakpoints(): that helper's option fallback would map
	 * values into core storage core cannot render (settings.viewport absent →
	 * get_viewport_media_queries() emits nothing) — a silent-miss. A null slot
	 * means core has no such band; values stay in the Blocklane layer.
	 *
	 * @return array{tablet:int|null,mobile:int|null}
	 */
	function blocklane_pro_ext_core_viewport_px() {
		$tablet = null;
		$mobile = null;
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$viewport = wp_get_global_settings( array( 'viewport' ) );
			if ( is_array( $viewport ) ) {
				$tablet = blocklane_pro_ext_viewport_px( $viewport['tablet'] ?? null );
				$mobile = blocklane_pro_ext_viewport_px( $viewport['mobile'] ?? null );
			}
		}

		return array(
			'tablet' => $tablet,
			'mobile' => $mobile,
		);
	}

	/**
	 * Whether the editor's canvas grid tools (drag-move, resize handles, cell
	 * movers) are switched on. Mirrors Extensions_Handler::get_grid_canvas_tools()
	 * (the admin-side owner of the option) so both homes agree.
	 *
	 * OFF by default: turning this on changes core's editing UX for every grid
	 * block on the site, and the underlying core feature is still experiment-
	 * flagged for maturity reasons (keyboard access, perf on large grids), so
	 * it is the author's call rather than ours.
	 *
	 * @return bool
	 */
	function blocklane_pro_ext_grid_canvas_tools() {
		return (bool) blocklane_pro_ext_setting( 'blocklane_pro_grid_canvas_tools', 'grid_canvas_tools' );
	}

	/**
	 * Animation Designer presets.
	 *
	 * @return array
	 */
	function blocklane_pro_ext_animation_presets() {
		$presets = blocklane_pro_ext_setting( 'blocklane_pro_animation_presets', 'animation_presets' );

		return is_array( $presets ) ? $presets : array();
	}

	/**
	 * CSS Class Manager classes.
	 *
	 * Historical note: this data lived under 'blocklane_pro_pro_css_classes' (a
	 * doubled-'pro' slug) until 2026-07; the class-manager loader migrates the
	 * row on init.
	 *
	 * @return array
	 */
	function blocklane_pro_ext_css_classes() {
		$classes = blocklane_pro_ext_setting( 'blocklane_pro_css_classes', 'css_classes' );

		return is_array( $classes ) ? $classes : array();
	}
}

if ( ! function_exists( 'blocklane_pro_ext_autoload_ok' ) ) {
	/**
	 * Autoload budget for a cache row the front end reads on every request.
	 *
	 * Small rows ride alloptions for free; past this size autoloading
	 * inverts, taxing every request on the site — including the admin, REST
	 * and cron requests that never read the row — with the full payload.
	 *
	 * Mirrors blocklane_pro\Helper::autoload_ok(). Duplicated deliberately:
	 * this file is a runtime and must not reach into the plugin's classes.
	 * Keep the number in sync.
	 *
	 * @param mixed $value The value about to be stored.
	 * @return bool Whether the option should autoload.
	 */
	function blocklane_pro_ext_autoload_ok( $value ) {
		return strlen( maybe_serialize( $value ) ) < 32768;
	}
}

if ( ! function_exists( 'blocklane_pro_ext_sanitize_svg' ) ) {
	/**
	 * Whitelist-sanitise a custom SVG (shared by button-icons and icon-library): strip disallowed elements/attributes, event
	 * handlers, and dangerous URI schemes.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string Sanitised SVG, or '' on failure.
	 */
	function blocklane_pro_ext_sanitize_svg( $svg ) {
		if ( empty( $svg ) || ! is_string( $svg ) || stripos( $svg, '<svg' ) === false ) {
			return '';
		}

		$allowed_elements = array(
			'svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon',
			'ellipse', 'g', 'defs', 'clippath', 'use', 'title', 'desc',
		);
		$shape_attrs      = array(
			'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
			'width', 'height', 'points', 'fill', 'stroke', 'stroke-width',
			'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule',
			'opacity', 'transform', 'class', 'fill-opacity', 'stroke-opacity',
			'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit',
		);
		$allowed_attrs    = array(
			'svg'      => array( 'viewbox', 'xmlns', 'width', 'height', 'fill', 'class', 'xmlns:xlink' ),
			'path'     => $shape_attrs,
			'circle'   => $shape_attrs,
			'rect'     => $shape_attrs,
			'line'     => $shape_attrs,
			'polyline' => $shape_attrs,
			'polygon'  => $shape_attrs,
			'ellipse'  => $shape_attrs,
			'g'        => array( 'id', 'clip-path', 'transform', 'fill', 'class', 'opacity', 'stroke', 'stroke-width' ),
			'defs'     => array( 'id' ),
			'clippath' => array( 'id', 'clippathunits' ),
			'use'      => array( 'href', 'xlink:href', 'x', 'y', 'width', 'height' ),
			'title'    => array(),
			'desc'     => array(),
		);

		$prev   = libxml_use_internal_errors( true );
		$doc    = new DOMDocument();
		$loaded = $doc->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $svg, LIBXML_NONET | LIBXML_NOBLANKS );
		libxml_use_internal_errors( $prev );

		if ( ! $loaded ) {
			return '';
		}

		$svg_elements = $doc->getElementsByTagName( 'svg' );
		if ( 0 === $svg_elements->length ) {
			return '';
		}

		$root = $svg_elements->item( 0 );
		// The root <svg>'s own attributes, then its descendants — sanitize_node only
		// walks children, so a bare <svg onload="…"> would otherwise slip through.
		blocklane_pro_ext_sanitize_attrs( $root, isset( $allowed_attrs['svg'] ) ? $allowed_attrs['svg'] : array() );
		blocklane_pro_ext_sanitize_node( $root, $allowed_elements, $allowed_attrs );

		$output = $doc->saveXML( $root );

		return empty( $output ) ? '' : $output;
	}

	/**
	 * Strip disallowed / dangerous attributes from a single element in place.
	 *
	 * @param \DOMElement $element Element to clean.
	 * @param array       $allowed Allowed attribute names (lowercase) for its tag.
	 */
	function blocklane_pro_ext_sanitize_attrs( $element, $allowed ) {
		if ( ! $element->hasAttributes() ) {
			return;
		}

		$to_remove = array();
		foreach ( $element->attributes as $attr ) {
			$name = strtolower( $attr->nodeName );
			// href/xlink:href (only allowed on <use>) may reference LOCAL
			// fragments only (#id): remote http(s)/protocol-relative and
			// file-based refs are stripped, matching the upload pipeline's
			// removeRemoteReferences(true). Cross-origin <use> is inert in
			// modern browsers anyway — the two sanitizers must simply agree.
			$is_local_ref_attr = in_array( $name, array( 'href', 'xlink:href' ), true );
			if (
				strpos( $name, 'on' ) === 0 ||
				preg_match( '/^\s*(javascript|data|vbscript)\s*:/i', $attr->nodeValue ) ||
				( $is_local_ref_attr && '#' !== substr( ltrim( (string) $attr->nodeValue ), 0, 1 ) ) ||
				! in_array( $name, $allowed, true )
			) {
				$to_remove[] = $attr->nodeName;
			}
		}

		foreach ( $to_remove as $name ) {
			$element->removeAttribute( $name );
		}
	}

	/**
	 * Recursively sanitise a DOM node against the allow-lists.
	 *
	 * @param \DOMNode $node             Node to sanitise.
	 * @param array    $allowed_elements Allowed element names (lowercase).
	 * @param array    $allowed_attrs    Allowed attributes per element.
	 */
	function blocklane_pro_ext_sanitize_node( $node, $allowed_elements, $allowed_attrs ) {
		if ( ! $node->hasChildNodes() ) {
			return;
		}

		$children = array();
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				$node->removeChild( $child );
				continue;
			}

			$tag = strtolower( $child->nodeName );
			if ( ! in_array( $tag, $allowed_elements, true ) ) {
				$node->removeChild( $child );
				continue;
			}

			blocklane_pro_ext_sanitize_attrs( $child, isset( $allowed_attrs[ $tag ] ) ? $allowed_attrs[ $tag ] : array() );
			blocklane_pro_ext_sanitize_node( $child, $allowed_elements, $allowed_attrs );
		}
	}
}

if ( ! function_exists( 'blocklane_pro_ext_safe_css_value' ) ) {

	/**
	 * Reject a CSS value that could inject extra declarations into an inline
	 * style (author-controlled custom colors / timing functions are not
	 * preset-resolved). esc_attr / the HTML tag processor stop attribute
	 * breakout but not CSS syntax, so drop anything with ; { } < > or quotes.
	 * Keeps hex/rgb/rgba/hsl, named colors, and cubic-bezier()/steps() timing
	 * functions.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return string Safe value, or '' if unsafe/non-string.
	 */
	function blocklane_pro_ext_safe_css_value( $value ) {
		if ( ! is_string( $value ) || preg_match( '/[;{}<>"\']/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Resolve a stored preset color (var:preset|color|{slug} or a raw CSS
	 * color) to a safe CSS value, or '' when unsafe. The ONE shared resolver:
	 * hover-color and transparent-header both render through it, and it must
	 * stay in lockstep with the JS presetColorToCss() the editor canvas uses
	 * (two PHP copies of this logic drifted once — #152).
	 *
	 * @param mixed $stored Stored attribute value.
	 * @return string
	 */
	function blocklane_pro_ext_resolve_preset_color( $stored ) {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		if ( 0 === strpos( $stored, 'var:preset|color|' ) ) {
			$slug = substr( $stored, strlen( 'var:preset|color|' ) );
			return sprintf( 'var(--wp--preset--color--%s)', sanitize_html_class( $slug ) );
		}
		return blocklane_pro_ext_safe_css_value( $stored );
	}
}

if ( ! function_exists( 'blocklane_pro_edition_has' ) ) {

	/**
	 * Whether this artifact carries a manifest unit.
	 *
	 * MIRROR CONTRACT with blocklane_pro\Edition::has()
	 * (inc/class-blocklane-pro-edition.php). The front-end runtimes are kept
	 * free of plugin classes and namespaces by ARCHITECTURE.md's rule, so this
	 * reads the same generated data file directly. Like the class, an unknown
	 * unit id throws: a typo must be a defect in our source, never a feature
	 * that quietly disappears.
	 *
	 * @param string $unit Manifest unit id, e.g. 'extension:hover-colors'.
	 * @throws \LogicException When the unit is not in the manifest.
	 */
	function blocklane_pro_edition_has( string $unit ): bool {
		static $units = null;
		if ( null === $units ) {
			$data  = BLOCKLANE_PRO_PATH . '/inc/edition.php';
			$read  = is_file( $data ) ? (array) require $data : array();
			$units = isset( $read['units'] ) && is_array( $read['units'] ) ? $read['units'] : array();
		}
		if ( ! array_key_exists( $unit, $units ) ) {
			// esc_html + brackets, mirroring Edition::has().
			throw new \LogicException( esc_html( 'Blocklane: unknown edition unit [' . $unit . '].' ) );
		}
		return false !== $units[ $unit ];
	}
}
