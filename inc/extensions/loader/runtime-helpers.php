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
	 * A custom SVG reduced to the sanitizer's policy (shared by button-icons and the icon library's uploads).
	 *
	 * This is the security boundary: both callers store or render the result
	 * with no further filtering. The steps:
	 *
	 * 1. Refuse anything that is not a non-empty string mentioning `<svg`.
	 * 2. Parse it as XML behind a prolog this function supplies, with no
	 *    network, no entity substitution, no DTD loading and no recovery —
	 *    an entity reference stays an unexpanded node that step 4 drops.
	 * 3. Take the first element whose local name is `svg` as the root.
	 * 4. Walk the root's subtree: text stays, allowed elements stay (their
	 *    attributes judged by blocklane_pro_ext_svg_attribute_kept()), every
	 *    other node goes with its subtree.
	 * 5. Serialize the root alone.
	 *
	 * Never throws, prints or warns, and puts libxml's error mode back the way
	 * it found it.
	 *
	 * @param mixed $svg Raw SVG markup.
	 * @return string The scrubbed SVG, or '' when the input is not SVG markup or does not parse.
	 */
	function blocklane_pro_ext_sanitize_svg( mixed $svg ): string {
		if ( ! is_string( $svg ) || '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return '';
		}

		$dom          = new DOMDocument();
		$quiet_before = libxml_use_internal_errors( true );
		// NONET and NOBLANKS only. Leaving out NOENT, DTDLOAD, DTDATTR,
		// DTDVALID, RECOVER and PARSEHUGE is the point: see step 2 above.
		$parsed = $dom->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $svg, LIBXML_NONET | LIBXML_NOBLANKS );
		libxml_clear_errors();
		libxml_use_internal_errors( $quiet_before );

		if ( ! $parsed ) {
			return '';
		}

		$root = blocklane_pro_ext_svg_find_root( $dom );
		if ( null === $root ) {
			return '';
		}

		// The root is judged as an svg element whatever its prefix.
		blocklane_pro_ext_svg_judge_attributes( $root, 'svg' );
		blocklane_pro_ext_svg_scrub_children( $root );

		$markup = $dom->saveXML( $root );

		return is_string( $markup ) ? $markup : '';
	}

	/**
	 * The policy: each allowed element name (lowercased) mapped to the
	 * attribute names (lowercased) it may keep. An element absent from this
	 * map is removed with its subtree.
	 *
	 * @return array<string, list<string>>
	 */
	function blocklane_pro_ext_svg_allowed(): array {
		static $policy = null;
		if ( null !== $policy ) {
			return $policy;
		}

		$shape = array(
			// Geometry.
			'd',
			'points',
			'x',
			'y',
			'x1',
			'y1',
			'x2',
			'y2',
			'cx',
			'cy',
			'r',
			'rx',
			'ry',
			'width',
			'height',
			// Paint.
			'fill',
			'fill-opacity',
			'fill-rule',
			'clip-rule',
			'opacity',
			'stroke',
			'stroke-width',
			'stroke-opacity',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-dasharray',
			'stroke-dashoffset',
			// Placement.
			'transform',
			'class',
		);

		$policy = array(
			'svg'      => array( 'xmlns', 'xmlns:xlink', 'viewbox', 'width', 'height', 'fill', 'class' ),
			'g'        => array( 'id', 'class', 'transform', 'clip-path', 'fill', 'stroke', 'stroke-width', 'opacity' ),
			'defs'     => array( 'id' ),
			'clippath' => array( 'id', 'clippathunits' ),
			'use'      => array( 'href', 'xlink:href', 'x', 'y', 'width', 'height' ),
			'title'    => array(),
			'desc'     => array(),
		);
		foreach ( array( 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon' ) as $drawing ) {
			$policy[ $drawing ] = $shape;
		}

		return $policy;
	}

	/**
	 * The first element in document order whose local name is exactly `svg`
	 * (any prefix, lowercase only), or null when there is none.
	 *
	 * @param DOMDocument $dom The parsed document.
	 */
	function blocklane_pro_ext_svg_find_root( DOMDocument $dom ): ?DOMElement {
		$pending = array();
		if ( $dom->documentElement instanceof DOMElement ) {
			$pending[] = $dom->documentElement;
		}
		// Depth-first, children pushed in reverse so they pop in document order.
		while ( $pending ) {
			$node = array_pop( $pending );
			if ( 'svg' === $node->localName ) {
				return $node;
			}
			$children = array();
			foreach ( $node->childNodes as $child ) {
				if ( $child instanceof DOMElement ) {
					$children[] = $child;
				}
			}
			foreach ( array_reverse( $children ) as $child ) {
				$pending[] = $child;
			}
		}

		return null;
	}

	/**
	 * Remove every child of $parent the policy does not allow, recursing into
	 * the elements it keeps.
	 *
	 * @param DOMElement $parent A kept element.
	 */
	function blocklane_pro_ext_svg_scrub_children( DOMElement $parent ): void {
		$policy = blocklane_pro_ext_svg_allowed();

		// Snapshot first: removing a node while iterating a live list skips its sibling.
		$children = array();
		foreach ( $parent->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			// nodeType, not instanceof DOMText: a CDATA section is a DOMText subclass.
			if ( XML_TEXT_NODE === $child->nodeType ) {
				continue;
			}
			if ( $child instanceof DOMElement ) {
				$name = strtolower( $child->nodeName );
				if ( isset( $policy[ $name ] ) ) {
					blocklane_pro_ext_svg_judge_attributes( $child, $name );
					blocklane_pro_ext_svg_scrub_children( $child );
					continue;
				}
			}
			$parent->removeChild( $child );
		}
	}

	/**
	 * Strip from $element every attribute the policy row $row does not keep.
	 *
	 * @param DOMElement $element The element whose attributes are judged.
	 * @param string     $row     Its row in blocklane_pro_ext_svg_allowed().
	 */
	function blocklane_pro_ext_svg_judge_attributes( DOMElement $element, string $row ): void {
		$allowed = blocklane_pro_ext_svg_allowed()[ $row ] ?? array();

		// Gather the attribute nodes before removing any, and remove the
		// nodes themselves: two attributes may share a local name (x:d, d).
		$doomed = array();
		foreach ( $element->attributes as $attribute ) {
			if ( ! blocklane_pro_ext_svg_attribute_kept( strtolower( $attribute->nodeName ), $attribute->value, $allowed ) ) {
				$doomed[] = $attribute;
			}
		}
		foreach ( $doomed as $attribute ) {
			$element->removeAttributeNode( $attribute );
		}
	}

	/**
	 * Whether one attribute survives. Removed when its name is not allowed on
	 * the element, names an event handler, its value opens with a
	 * script-capable scheme, or it is a reference that is not a same-document
	 * fragment: an href/xlink:href that does not start with `#`, or a CSS
	 * `url()` ANYWHERE in ANY attribute's value (fill, stroke, clip-path,
	 * mask, filter, or whatever a widened list admits) whose target does not
	 * start with `#`. A remote or data: target there is a request the
	 * visitor's browser makes on every page view (#1847). The url() test
	 * reads the value as CSS does: CSS escapes decoded first (`\75 rl(` is
	 * `url(` to a browser), any case, blanks before `(`, inside it and around
	 * an optional quote; every url() in the value must name a fragment.
	 *
	 * The `on` test is redundant with today's allow-list and kept on purpose:
	 * it still holds the day someone widens the list.
	 *
	 * @param string       $name    Qualified attribute name, lowercased.
	 * @param string       $value   Attribute value as the parser decoded it.
	 * @param list<string> $allowed Names the element may keep.
	 */
	function blocklane_pro_ext_svg_attribute_kept( string $name, string $value, array $allowed ): bool {
		if ( ! in_array( $name, $allowed, true ) || str_starts_with( $name, 'on' ) ) {
			return false;
		}

		// Whitespace here means space, tab, LF, CR, FF and VT — spelled out
		// rather than \s, whose meaning shifts with PCRE version and locale.
		if ( preg_match( '/^[\x20\t\n\r\f\x0B]*(?:javascript|data|vbscript)[\x20\t\n\r\f\x0B]*:/i', $value ) ) {
			return false;
		}

		if ( 'href' === $name || 'xlink:href' === $name ) {
			return str_starts_with( ltrim( $value, " \t\n\r\f\v" ), '#' );
		}

		// Only ASCII decides whether a url() is there and where it points, so
		// an escape of anything wider becomes '?', which is never `#`.
		$css = (string) preg_replace_callback(
			'/\\\\(?:([0-9a-f]{1,6})[\x20\t\n\r\f]?|(.))/is',
			static fn( array $m ): string => '' !== $m[1] ? ( hexdec( $m[1] ) <= 0x7F ? chr( (int) hexdec( $m[1] ) ) : '?' ) : $m[2],
			$value
		);
		preg_match_all( '/url[\x20\t\n\r\f\x0B]*\([\x20\t\n\r\f\x0B]*["\']?[\x20\t\n\r\f\x0B]*(.?)/is', $css, $targets );
		foreach ( $targets[1] as $first ) {
			if ( '#' !== $first ) {
				return false;
			}
		}

		return true;
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
