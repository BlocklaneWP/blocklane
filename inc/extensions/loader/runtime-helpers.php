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

// The one CSS verdict the SVG sanitizer below reads (a core path, a runtime
// file like this one). Path-independent: inc/ from here, whichever copy of
// the plugin this is.
require_once dirname( __DIR__, 2 ) . '/svg-css-policy.php';

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
	 * Sanitizes SVG markup down to the small part of SVG an icon may keep.
	 *
	 * This is the security boundary: what it returns is stored and later
	 * printed into pages with no further filtering. The markup is refused
	 * outright when it is not a non-empty string, holds no `<svg`, or is
	 * outside the markup bound. Otherwise it is parsed as XML with network
	 * access off, no entity substitution and no DTD loaded or applied; the
	 * first element whose local name is `svg` becomes the root; every element
	 * under it that is not in the allowed set is removed with its subtree;
	 * text survives while comments, CDATA sections, processing instructions
	 * and entity references are removed; and every attribute left, the
	 * root's included, is judged by blocklane_pro_ext_sanitize_svg_keeps().
	 * The result is the root alone, serialized. Any failure anywhere is ''.
	 *
	 * @param mixed $svg The markup to sanitize.
	 * @return string The sanitized svg element, or '' when refused or when any step fails.
	 */
	function blocklane_pro_ext_sanitize_svg( mixed $svg ): string {
		if ( ! is_string( $svg ) || '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return '';
		}
		// Both policy functions must exist; without either, nothing is kept.
		if ( ! function_exists( 'blocklane_pro_svg_markup_bound' ) || ! function_exists( 'blocklane_pro_svg_css_verdict' ) ) {
			return '';
		}
		// The bound is judged on the input exactly as given, before parsing.
		if ( true !== blocklane_pro_svg_markup_bound( $svg ) ) {
			return '';
		}

		$doc  = new DOMDocument();
		$mode = libxml_use_internal_errors( true );
		try {
			// A fixed declaration goes first, so an input that brings its own fails to parse.
			if ( true !== $doc->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $svg, LIBXML_NONET | LIBXML_NOBLANKS ) ) {
				return '';
			}

			$root = null;
			foreach ( $doc->getElementsByTagName( '*' ) as $element ) {
				if ( $element instanceof DOMElement && 'svg' === $element->localName ) {
					$root = $element;
					break;
				}
			}
			if ( ! $root instanceof DOMElement ) {
				return '';
			}

			$rows = blocklane_pro_ext_sanitize_svg_rows();
			blocklane_pro_ext_sanitize_svg_judge( $root, $rows['svg'] );
			blocklane_pro_ext_sanitize_svg_walk( $root, $rows );

			$out = $doc->saveXML( $root );
			return is_string( $out ) ? $out : '';
		} catch ( \Throwable ) {
			return '';
		} finally {
			// Drop what the parser queued and put the caller's error mode back.
			libxml_clear_errors();
			libxml_use_internal_errors( $mode );
		}
	}

	/**
	 * The elements that may survive, each with the attribute names it may
	 * keep. Element names are lowercase and in alphabetical order, and so are
	 * the attribute names in each row.
	 *
	 * @return array<string, list<string>> Lowercase element name to its lowercase attribute names.
	 */
	function blocklane_pro_ext_sanitize_svg_rows(): array {
		// Geometry, paint and placement for the drawing elements.
		$shape = array( 'class', 'clip-rule', 'cx', 'cy', 'd', 'fill', 'fill-opacity', 'fill-rule', 'height', 'opacity', 'points', 'r', 'rx', 'ry', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'transform', 'width', 'x', 'x1', 'x2', 'y', 'y1', 'y2' );
		return array(
			'circle'   => $shape,
			'clippath' => array( 'clippathunits', 'id' ),
			'defs'     => array( 'id' ),
			'desc'     => array(),
			'ellipse'  => $shape,
			'g'        => array( 'class', 'clip-path', 'fill', 'id', 'opacity', 'stroke', 'stroke-width', 'transform' ),
			'line'     => $shape,
			'path'     => $shape,
			'polygon'  => $shape,
			'polyline' => $shape,
			'rect'     => $shape,
			'svg'      => array( 'class', 'fill', 'height', 'viewbox', 'width', 'xmlns', 'xmlns:xlink' ),
			'title'    => array(),
			'use'      => array( 'height', 'href', 'width', 'x', 'xlink:href', 'y' ),
		);
	}

	/**
	 * Walks an element's children: text nodes stay, an element in the set
	 * stays with its attributes judged and its own children walked, and
	 * every other node (an element outside the set with its whole subtree,
	 * a CDATA section, a comment, a processing instruction, an entity
	 * reference) is removed.
	 *
	 * @param DOMElement                  $parent The element whose children are walked.
	 * @param array<string, list<string>> $rows   The allowed set, from blocklane_pro_ext_sanitize_svg_rows().
	 */
	function blocklane_pro_ext_sanitize_svg_walk( DOMElement $parent, array $rows ): void {
		// Copy the children first, so removing one does not disturb the loop.
		$children = array();
		foreach ( $parent->childNodes as $child ) {
			$children[] = $child;
		}
		foreach ( $children as $child ) {
			// A CDATA section has its own node type, so it is not kept here.
			if ( XML_TEXT_NODE === $child->nodeType ) {
				continue;
			}
			if ( $child instanceof DOMElement ) {
				// The qualified name, so a prefixed name is never in the set.
				$name = strtolower( $child->nodeName );
				if ( isset( $rows[ $name ] ) ) {
					blocklane_pro_ext_sanitize_svg_judge( $child, $rows[ $name ] );
					blocklane_pro_ext_sanitize_svg_walk( $child, $rows );
					continue;
				}
			}
			$parent->removeChild( $child );
		}
	}

	/**
	 * Removes every attribute of an element that fails
	 * blocklane_pro_ext_sanitize_svg_keeps(). The failures are collected
	 * first and then removed as nodes, so of two attributes sharing a local
	 * name only the one that failed goes.
	 *
	 * @param DOMElement   $element The element whose attributes are judged.
	 * @param list<string> $allowed The lowercase attribute names its row allows.
	 */
	function blocklane_pro_ext_sanitize_svg_judge( DOMElement $element, array $allowed ): void {
		$failed = array();
		foreach ( $element->attributes as $attr ) {
			if ( $attr instanceof DOMAttr && ! blocklane_pro_ext_sanitize_svg_keeps( strtolower( $attr->nodeName ), $attr->value, $allowed ) ) {
				$failed[] = $attr;
			}
		}
		foreach ( $failed as $attr ) {
			$element->removeAttributeNode( $attr );
		}
	}

	/**
	 * Whether one attribute may stay. All of these must hold: its name is in
	 * the element's row; its name does not start with `on`; its value does
	 * not start with a javascript, data or vbscript scheme (blanks allowed
	 * around the scheme's name, ASCII case-insensitive); an href or
	 * xlink:href value starts with `#` after its leading blanks; and the CSS
	 * verdict answers exactly true for the value. Nothing is decoded: the
	 * value is judged as the parser handed it over. Blanks are space, tab,
	 * LF, CR, FF and VT.
	 *
	 * @param string       $name    The attribute's qualified name, lowercased.
	 * @param string       $value   The attribute's value, as parsed.
	 * @param list<string> $allowed The lowercase attribute names the element's row allows.
	 * @return bool Whether the attribute stays.
	 */
	function blocklane_pro_ext_sanitize_svg_keeps( string $name, string $value, array $allowed ): bool {
		if ( ! in_array( $name, $allowed, true ) || str_starts_with( $name, 'on' ) ) {
			return false;
		}
		if ( 1 === preg_match( '/^[\x20\t\n\r\f\x0B]*(?:javascript|data|vbscript)[\x20\t\n\r\f\x0B]*:/i', $value ) ) {
			return false;
		}
		if ( ( 'href' === $name || 'xlink:href' === $name ) && ! str_starts_with( ltrim( $value, "\x20\t\n\r\x0C\x0B" ), '#' ) ) {
			return false;
		}
		return true === blocklane_pro_svg_css_verdict( $value );
	}
}

if ( ! function_exists( 'blocklane_pro_ext_svg_policy_token' ) ) {

	/**
	 * Eight hex characters that change whenever the code of the SVG policy
	 * changes: md5() over md5_file() of each file, in order. The files ARE
	 * the policy (the verdict, and the sanitizer's element and attribute
	 * rules), so nothing else is hashed. '' when a file cannot be read, so a
	 * caller that names mirrors by it names none (fail closed).
	 *
	 * A parameter rather than two fixed paths so the review battery can hand
	 * it byte copies (WI14); blocklane_pro_ext_svg_policy_token() is its one
	 * production caller.
	 *
	 * @param list<string> $files The files that hold the verdict's code.
	 */
	function blocklane_pro_ext_svg_policy_fingerprint( array $files ): string {
		$hashes = '';
		foreach ( $files as $file ) {
			$hash = is_file( $file ) ? md5_file( $file ) : false;
			if ( false === $hash ) {
				return '';
			}
			$hashes .= $hash . "\n";
		}
		return substr( md5( $hashes ), 0, 8 );
	}

	/**
	 * The SVG policy's token: the CSS policy id, a dot, and the fingerprint
	 * of the two files that decide what the clean-room sanitizer keeps
	 * (inc/svg-css-policy.php, the verdict, and this file, its element and
	 * attribute rules), computed once per request. The stored-icon reader
	 * names a saved icon's uploads mirror by it (spec 2026-10-07-free-
	 * security-followup §18, S18-F1): a change to either file is a new
	 * token, and every mirror judged under the old code stops being read at
	 * once, with nobody having to remember to bump an id.
	 *
	 * The cost is accepted: a plugin update that touches either file sends
	 * every saved icon to the in-memory path (judged on each request) until
	 * the Pro mirror filler's first run, on an Author+ admin page.
	 *
	 * Outside the guarantee: the token reads the files' bytes on disk, while
	 * the code that judges is whatever PHP has compiled. With
	 * opcache.validate_timestamps=0 and a deploy that skips
	 * wp_opcache_invalidate() (a WordPress update calls it; a file copied
	 * into place by hand or by a host's sync does not), the old compiled
	 * sanitizer can run under the new token and mint a mirror the new code
	 * never judged. A deploy on such a host resets opcache.
	 *
	 * @return string '<css id>.<8 hex>', or '' when a file cannot be read.
	 */
	function blocklane_pro_ext_svg_policy_token(): string {
		static $token = null;
		if ( null === $token ) {
			$fingerprint = function_exists( 'blocklane_pro_svg_css_policy_id' )
				? blocklane_pro_ext_svg_policy_fingerprint( array( dirname( __DIR__, 2 ) . '/svg-css-policy.php', __FILE__ ) )
				: '';
			$token       = '' === $fingerprint ? '' : blocklane_pro_svg_css_policy_id() . '.' . $fingerprint;
		}
		return $token;
	}
}

if ( ! function_exists( 'blocklane_pro_ext_safe_css_color' ) ) {

	/**
	 * Whether a raw CSS color passes core's own rule: safecss_filter_attr()
	 * keeps `color: <value>` unchanged (it refuses url(), expression(), any
	 * function but var()/calc()/min()/max()/clamp(), backslashes, `&`, `=`
	 * and comments), and it carries no `;`, `"`, `'`, `{` or `}` (which that
	 * filter judges per declaration, so a second declaration would pass) and
	 * no `<` or `>` (Hover Colors' HC11: no stored value ends the
	 * attribute).
	 * The rule core's own style engine applies to a block's custom colors.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return string The value, or '' when unsafe or not a string.
	 */
	function blocklane_pro_ext_safe_css_color( mixed $value ): string {
		if ( ! is_string( $value ) || '' === $value || preg_match( '/[;"\'{}<>]/', $value ) ) {
			return '';
		}
		return 'color: ' . $value === safecss_filter_attr( 'color: ' . $value ) ? $value : '';
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
		return blocklane_pro_ext_safe_css_color( $stored );
	}
}

if ( ! function_exists( 'blocklane_pro_ext_require_unit' ) ) {

	/**
	 * Require a unit's front-end files, all of them or none (security read
	 * F5): a unit's door declares functions its runtime calls, so a torn
	 * deploy that lost one file must not load the others into a fatal.
	 * Every file is checked before any is required; when one is missing the
	 * unit is skipped and logged once, naming each missing file.
	 *
	 * @param string       $base The loader directory.
	 * @param list<string> $rels The unit's files, in load order, relative to it.
	 * @return true|\WP_Error True once every file is required (PHP 8.1 has no `true` type).
	 */
	function blocklane_pro_ext_require_unit( string $base, array $rels ): bool|\WP_Error {
		$missing = array_values( array_filter( $rels, static fn( string $rel ): bool => ! is_file( $base . '/' . $rel ) ) );
		if ( array() !== $missing ) {
			// The edition says this unit is here and the disk disagrees: a
			// torn deploy. Say so in the log, WP_DEBUG or not, rather than
			// rendering silently wrong (or fataling) forever.
			blocklane_pro_log_failure( 'Blocklane: extension runtime inc/extensions/loader/' . implode( ', inc/extensions/loader/', $missing ) . ' is missing, so its unit is off. Reinstall the plugin.' );
			return new \WP_Error( 'blocklane_pro_unit_torn', 'Blocklane: a file of this unit is missing.', $missing );
		}
		foreach ( $rels as $rel ) {
			require_once $base . '/' . $rel;
		}
		return true;
	}
}

if ( ! function_exists( 'blocklane_pro_ext_register_module' ) ) {

	/** An extensions build entry name (inc/extensions/webpack.config.js). */
	define( 'BLOCKLANE_PRO_EXT_ENTRY', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/' );

	/**
	 * The asset file of a build entry the extensions build emitted as a
	 * script module (build/<entry>.asset.php says `'type' => 'module'`; the
	 * build makes a *-frontend entry a module when its source is an
	 * Interactivity API store), or null: no such entry, not built, or a
	 * classic script.
	 *
	 * @param string $entry The build entry, `<unit>-frontend`.
	 * @return array{dependencies: list<string|array{id: string, import?: string}>, version: string}|null
	 */
	function blocklane_pro_ext_module_asset( string $entry ): ?array {
		$build = blocklane_pro_ext_base_path() . '/build/' . $entry;
		if ( 1 !== preg_match( BLOCKLANE_PRO_EXT_ENTRY, $entry ) || ! is_readable( $build . '.js' ) || ! is_readable( $build . '.asset.php' ) ) {
			return null;
		}
		$asset = require $build . '.asset.php';
		if ( ! is_array( $asset ) || 'module' !== ( $asset['type'] ?? null ) || ! is_array( $asset['dependencies'] ?? null ) ) {
			return null;
		}
		return array(
			'dependencies' => array_values( $asset['dependencies'] ),
			'version'      => is_string( $asset['version'] ?? null ) ? $asset['version'] : blocklane_pro_ext_version(),
		);
	}

	/**
	 * Register a unit's Interactivity API store as the script module the
	 * extensions build emitted (blocklane_pro_ext_module_asset()), with the
	 * dependencies and version its asset file names, as core's own block
	 * modules take theirs. A unit door calls it on `init`; it knows no unit.
	 * An entry that is not a built module gets a typed refusal and nothing
	 * is registered, so the unit keeps what it delivers today.
	 *
	 * @param string $id    The module id.
	 * @param string $entry The build entry, `<unit>-frontend`.
	 * @return true|\WP_Error True once registered (PHP 8.1, Pro's floor, has no `true` type).
	 */
	function blocklane_pro_ext_register_module( string $id, string $entry ): bool|\WP_Error {
		if ( 1 !== preg_match( BLOCKLANE_PRO_EXT_ENTRY, $entry ) ) {
			return new \WP_Error( 'blocklane_pro_module_entry', 'Blocklane: a build entry is a lowercase slug.' );
		}
		$asset = blocklane_pro_ext_module_asset( $entry );
		if ( null === $asset ) {
			return new \WP_Error( 'blocklane_pro_module_unbuilt', 'Blocklane: this entry is not built as a script module.' );
		}
		wp_register_script_module( $id, blocklane_pro_ext_base_url() . '/build/' . $entry . '.js', $asset['dependencies'], $asset['version'] );
		return true;
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

if ( ! function_exists( 'blocklane_pro_pro_seen' ) ) {

	/**
	 * Whether the Pro build has run on this site: the one reader of the
	 * blocklane_pro_pro_seen row.
	 *
	 * Pro's writer (inc/pro-seen/lifecycle.php) sets the row on every Pro
	 * request where it is not already set, to array( 'first' => the edition
	 * stamp, 'at' => a timestamp ). This accepts that shape only: an array
	 * whose `first` is a non-empty string. An absent row, the empty array the
	 * seed writes, and any other value (a string, true, an empty `first`, an
	 * array without one) read as unset, and the next Pro request overwrites
	 * them.
	 *
	 * It decides one thing: whether the attribute registrar
	 * (inc/extensions/loader/attribute-schema.php) keeps attributes that only
	 * the Pro build gives meaning to, as data. Nothing else may read it, no
	 * capability, render or UI (PHPStan blocklane.chokepointCall admits the
	 * registrar and the writer), because any options writer or database clone
	 * can set the row (spec 2026-10-17-pro-downgrade-inert-attributes, S6).
	 *
	 * No static cache: get_option() is cached already, and the batteries
	 * force both states within one request.
	 */
	function blocklane_pro_pro_seen(): bool {
		$row = get_option( 'blocklane_pro_pro_seen', array() );
		return is_array( $row ) && isset( $row['first'] ) && is_string( $row['first'] ) && '' !== $row['first'];
	}
}
