<?php
/**
 * The Carousel's server runtime.
 *
 * Everything the four carousel blocks need from the server besides their own
 * render files: the grammars that decide which CSS values may reach a style
 * attribute, the query a carousel of posts runs, the breakpoints and strings
 * the front end reads, the check that admits a pasted SVG arrow, the arrow
 * icons' markup, the theme presets the bundled patterns rely on, and the
 * hooks that register the blocks, their category and their pattern category.
 *
 * Nothing here is declared or hooked while the plugin runs in safe mode, while
 * the Carousel module is switched off, or when this file was already loaded.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	( ! defined( 'BLOCKLANE_PRO_SAFE_MODE' ) || ! constant( 'BLOCKLANE_PRO_SAFE_MODE' ) )
	&& class_exists( '\blocklane_pro\Content_Toggle', false )
	&& \blocklane_pro\Content_Toggle::on( 'carousel' )
	&& ! defined( 'BLOCKLANE_PRO_CAROUSEL_RUNTIME_LOADED' )
) {
	define( 'BLOCKLANE_PRO_CAROUSEL_RUNTIME_LOADED', true );

	/**
	 * Whether a string may be placed in a style attribute as a CSS length,
	 * exactly as given. The string is judged, never rewritten.
	 *
	 * Admitted: a list of plain lengths separated by spaces (at most
	 * $max_terms of them), or one function value built only from var(),
	 * calc(), min(), max() and clamp().
	 *
	 * @param string $value     The candidate value.
	 * @param int    $max_terms The longest list of lengths admitted (below 1 counts as 1).
	 * @return bool
	 */
	function blocklane_pro_carousel_is_css_length( string $value, int $max_terms = 1 ): bool {
		if ( '' === $value ) {
			return false;
		}
		if ( blocklane_pro_carousel_is_length_list( $value, max( 1, $max_terms ) ) ) {
			return true;
		}
		return blocklane_pro_carousel_is_function_length( $value );
	}

	/**
	 * Whether a string is a list of 1 to $max_terms plain lengths, separated
	 * by one or more U+0020 spaces.
	 *
	 * @param string $value     The candidate value.
	 * @param int    $max_terms The most terms admitted (at least 1).
	 * @return bool
	 */
	function blocklane_pro_carousel_is_length_list( string $value, int $max_terms ): bool {
		$terms = preg_split( '/ +/', $value );
		if ( ! is_array( $terms ) || count( $terms ) > $max_terms ) {
			return false;
		}
		$units = '%|cap|ch|cm|cqb|cqh|cqi|cqmax|cqmin|cqw|dvh|dvw|em|ex|ic|in|lh|lvh|lvw|mm|pc|pt|px|q|rem|rlh|svh|svw|vh|vmax|vmin|vw';
		$term  = '/\A(?:0|-?(?:\d+|\d*\.\d+)(?:' . $units . '))\z/i';
		foreach ( $terms as $one ) {
			if ( 1 !== preg_match( $term, $one ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a string is an admitted function value: the shape of var(--name)
	 * with an optional fallback, or of clamp(), min(), max() or calc(); only
	 * safe characters; balanced parentheses; and no function name other than
	 * var, calc, min, max and clamp anywhere in it.
	 *
	 * @param string $value The candidate value.
	 * @return bool
	 */
	function blocklane_pro_carousel_is_function_length( string $value ): bool {
		$shape = '/\A(?:var\(--[A-Za-z0-9_-]+(?:\s*,\s*\S.*)?\)|(?:clamp|min|max|calc)\(.+\))\z/is';
		if ( 1 !== preg_match( $shape, $value ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/\A[A-Za-z0-9\s()+*\/.,%-]+\z/', $value ) ) {
			return false;
		}
		$open   = 0;
		$length = strlen( $value );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( '(' === $value[ $i ] ) {
				++$open;
			} elseif ( ')' === $value[ $i ] ) {
				--$open;
				if ( $open < 0 ) {
					return false;
				}
			}
		}
		if ( 0 !== $open ) {
			return false;
		}
		if ( false === preg_match_all( '/([A-Za-z][A-Za-z0-9_-]*)\(/', $value, $names ) ) {
			return false;
		}
		$allowed = array( 'var', 'calc', 'min', 'max', 'clamp' );
		foreach ( $names[1] as $name ) {
			if ( ! in_array( strtolower( $name ), $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A color as given when it is a hex color, a plain custom property
	 * reference or a lowercase rgb(), rgba(), hsl() or hsla() value; otherwise
	 * the empty string.
	 *
	 * @param mixed $value The candidate color.
	 * @return string
	 */
	function blocklane_pro_carousel_sanitize_color( mixed $value ): string {
		if ( ! is_string( $value ) || ! $value ) {
			return '';
		}
		$patterns = array(
			'/\A#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})\z/',
			'/\Avar\(--[A-Za-z0-9-]+\)\z/',
			'/\A(?:rgba?|hsla?)\([0-9.,%\s]+\)\z/',
		);
		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * A block's spacing value as CSS: a spacing preset reference becomes the
	 * preset's custom property, and anything else must be one length.
	 *
	 * @param mixed $value The stored spacing value.
	 * @return string The CSS value, or the empty string.
	 */
	function blocklane_pro_carousel_spacing_to_css( mixed $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		if ( str_contains( $value, 'var:preset|spacing|' ) ) {
			$cut  = strrpos( $value, '|' );
			$slug = false === $cut ? '' : substr( $value, $cut + 1 );
			$slug = (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $slug );
			$slug = (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $slug );
			$slug = trim( strtolower( $slug ), '-' );
			return 'var(--wp--preset--spacing--' . $slug . ')';
		}
		$trimmed = trim( $value );
		return blocklane_pro_carousel_is_css_length( $trimmed, 1 ) ? $trimmed : '';
	}

	/**
	 * A float rounded to the nearest integer, or null when it cannot be one.
	 *
	 * @param float $number The number.
	 * @return int|null
	 */
	function blocklane_pro_carousel_round_px( float $number ): ?int {
		$rounded = round( $number );
		if ( ! is_finite( $rounded ) || abs( $rounded ) >= 9.0e18 ) {
			return null;
		}
		return (int) $rounded;
	}

	/**
	 * One theme viewport width in pixels: a number, or a number followed
	 * directly by px, em or rem (em and rem read against 16px).
	 *
	 * @param mixed $value The viewport setting.
	 * @return int|null The width, or null when the value is not one.
	 */
	function blocklane_pro_carousel_viewport_px( mixed $value ): ?int {
		if ( is_int( $value ) || is_float( $value ) ) {
			return blocklane_pro_carousel_round_px( (float) $value );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$trimmed = trim( $value );
		if ( is_numeric( $trimmed ) ) {
			return blocklane_pro_carousel_round_px( (float) $trimmed );
		}
		if ( 1 !== preg_match( '/\A(\d*\.\d+|\d+)(px|em|rem)\z/', $trimmed, $parts ) ) {
			return null;
		}
		$number = (float) $parts[1];
		return blocklane_pro_carousel_round_px( 'px' === $parts[2] ? $number : $number * 16 );
	}

	/**
	 * The tablet and mobile widths the track's render emits, from the theme's
	 * viewport settings; each falls back to its default on its own when it is
	 * missing, unreadable or outside 320 to 3840 pixels.
	 *
	 * @return array{tablet: int, mobile: int}
	 */
	function blocklane_pro_carousel_breakpoints(): array {
		$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'viewport' ) ) : array();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$widths = array(
			'tablet' => 768,
			'mobile' => 480,
		);
		foreach ( array_keys( $widths ) as $slot ) {
			$px = blocklane_pro_carousel_viewport_px( $settings[ $slot ] ?? null );
			if ( null !== $px && $px >= 320 && $px <= 3840 ) {
				$widths[ $slot ] = $px;
			}
		}
		return $widths;
	}

	/**
	 * The translated strings the front end reads.
	 *
	 * @return array{slideLabel: string, goToSlide: string, pause: string, play: string, scrollbar: string, slideRole: string}
	 */
	function blocklane_pro_carousel_view_i18n(): array {
		return array(
			/* translators: 1: the slide's number, 2: the number of slides. */
			'slideLabel' => __( '%1$d of %2$d', 'blocklane' ),
			/* translators: 1: the number of the first slide the dot shows, 2: the number of slides. */
			'goToSlide'  => __( 'Go to slide %1$d of %2$d', 'blocklane' ),
			'pause'      => __( 'Pause autoplay', 'blocklane' ),
			'play'       => __( 'Play autoplay', 'blocklane' ),
			'scrollbar'  => __( 'Carousel scroll bar', 'blocklane' ),
			'slideRole'  => _x( 'slide', 'aria role description announced by screen readers', 'blocklane' ),
		);
	}

	/**
	 * The post types a dynamic carousel may query: core's built-in types,
	 * through the blocklane_pro_carousel_query_post_types filter, keeping each
	 * name once when it exists, is viewable and is not attachment.
	 *
	 * @return list<string>
	 */
	function blocklane_pro_carousel_query_post_types(): array {
		$builtin = array_values( (array) get_post_types( array( '_builtin' => true ), 'names' ) );
		$types   = apply_filters( 'blocklane_pro_carousel_query_post_types', $builtin );
		if ( ! is_array( $types ) ) {
			return array();
		}
		$kept = array();
		foreach ( $types as $type ) {
			if ( ! is_string( $type ) || 'attachment' === $type || in_array( $type, $kept, true ) ) {
				continue;
			}
			if ( post_type_exists( $type ) && is_post_type_viewable( $type ) ) {
				$kept[] = $type;
			}
		}
		return $kept;
	}

	/**
	 * A numeric value as an integer clamped to a range, or a default when the
	 * value is not numeric.
	 *
	 * @param mixed $value    The candidate.
	 * @param int   $min      The lowest result.
	 * @param int   $max      The highest result.
	 * @param int   $fallback The result for a value that is not numeric.
	 * @return int
	 */
	function blocklane_pro_carousel_clamp_int( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		$number = (float) $value;
		if ( is_nan( $number ) ) {
			return $fallback;
		}
		return (int) max( (float) $min, min( (float) $max, $number ) );
	}

	/**
	 * A list of ids, each once, cut to its first 100: the items of a list, or
	 * of a comma- or space-separated string, that are numbers whose whole
	 * part is above 0. A negative or unreadable item is dropped, never made
	 * into another id (own-code amendment, U11d security read F4: core's
	 * wp_parse_id_list() takes the absolute value, so -2 named author 2, and
	 * the editor's preview could not mirror it).
	 *
	 * @param mixed $value A list, or a comma- or space-separated string.
	 * @return list<int>
	 */
	function blocklane_pro_carousel_id_list( mixed $value ): array {
		if ( ! is_scalar( $value ) && ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( wp_parse_list( $value ) as $item ) {
			if ( ! is_numeric( $item ) ) {
				continue;
			}
			$number = (float) $item;
			if ( is_nan( $number ) || $number < 1 || $number > PHP_INT_MAX ) {
				continue;
			}
			$id = (int) $number;
			if ( ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, 100 );
	}

	/**
	 * The site's sticky posts as integers (a single value counts as a
	 * one-item list).
	 *
	 * @return list<int>
	 */
	function blocklane_pro_carousel_sticky_ids(): array {
		$raw = get_option( 'sticky_posts', array() );
		if ( ! is_array( $raw ) ) {
			$raw = ( is_scalar( $raw ) && false !== $raw && '' !== $raw ) ? array( $raw ) : array();
		}
		$ids = array();
		foreach ( $raw as $id ) {
			if ( is_scalar( $id ) ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * The first characters of a string, counted as UTF-8 characters.
	 *
	 * @param string $text  The text.
	 * @param int    $count How many characters to keep.
	 * @return string
	 */
	function blocklane_pro_carousel_first_chars( string $text, int $count ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $count, 'UTF-8' );
		}
		if ( 1 === preg_match( '/\A.{0,' . $count . '}/su', $text, $match ) ) {
			return $match[0];
		}
		return substr( $text, 0, $count );
	}

	/**
	 * The query arguments for a dynamic track's stored query.
	 *
	 * @param mixed $query      The track's query attribute (anything but an array reads as empty).
	 * @param int   $current_id The current singular post's id, or 0 for none.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_carousel_build_query_args( mixed $query, int $current_id = 0 ): array {
		$q = is_array( $query ) ? $query : array();

		$post_type = 'post';
		if ( isset( $q['postType'] ) && is_string( $q['postType'] ) && '' !== $q['postType'] ) {
			$post_type = sanitize_key( $q['postType'] );
		}
		if ( ! in_array( $post_type, blocklane_pro_carousel_query_post_types(), true ) ) {
			$post_type = 'blocklane-carousel-none';
		}

		$order   = $q['order'] ?? null;
		$orderby = $q['orderBy'] ?? null;
		$args    = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => blocklane_pro_carousel_clamp_int( $q['perPage'] ?? null, 1, 24, 6 ),
			'offset'              => blocklane_pro_carousel_clamp_int( $q['offset'] ?? null, 0, 10000, 0 ),
			'orderby'             => ( 'date' === $orderby || 'title' === $orderby ) ? $orderby : 'date',
			'order'               => ( is_string( $order ) && 'asc' === strtolower( $order ) ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( ! empty( $q['search'] ) && is_string( $q['search'] ) ) {
			$args['s'] = blocklane_pro_carousel_first_chars( $q['search'], 256 );
		}

		if ( ! empty( $q['author'] ) ) {
			$authors = blocklane_pro_carousel_id_list( $q['author'] );
			if ( array() !== $authors ) {
				$args['author__in'] = $authors;
			}
		}

		$exclude_current = ! empty( $q['excludeCurrent'] ) && $current_id > 0;
		$excluded_sticky = array();
		if ( 'post' === $post_type && ! empty( $q['sticky'] ) ) {
			$sticky = blocklane_pro_carousel_sticky_ids();
			if ( 'only' === $q['sticky'] ) {
				$only = array();
				foreach ( $sticky as $id ) {
					if ( ! ( $exclude_current && $id === $current_id ) ) {
						$only[] = $id;
					}
				}
				$args['post__in'] = array() === $only ? array( 0 ) : $only;
				$exclude_current  = false;
			} elseif ( 'exclude' === $q['sticky'] && array() !== $sticky ) {
				$excluded_sticky = $sticky;
			}
		}

		$not_in = $excluded_sticky;
		if ( $exclude_current ) {
			$not_in[] = $current_id;
		}
		$not_in = array_values( array_unique( $not_in ) );
		if ( array() !== $not_in && ! isset( $args['post__in'] ) ) {
			$args['post__not_in'] = $not_in;
		}

		if ( ! empty( $q['taxQuery'] ) && is_array( $q['taxQuery'] ) ) {
			$clauses = array();
			foreach ( $q['taxQuery'] as $taxonomy => $terms ) {
				if ( count( $clauses ) >= 10 ) {
					break;
				}
				$taxonomy = (string) $taxonomy;
				$ids      = blocklane_pro_carousel_id_list( $terms );
				// Only a taxonomy attached to the queried type filters it (own-code
				// amendment, U11d security read F4; the editor's preview mirrors it).
				if ( array() !== $ids && taxonomy_exists( $taxonomy ) && is_taxonomy_viewable( $taxonomy ) && in_array( $taxonomy, get_object_taxonomies( $post_type ), true ) ) {
					$clauses[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $ids,
					);
				}
			}
			if ( array() !== $clauses ) {
				if ( count( $clauses ) >= 2 ) {
					$clauses['relation'] = 'AND';
				}
				$args['tax_query'] = $clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the editor's own taxonomy filter.
			}
		}

		return $args;
	}

	/**
	 * Whether this host can check pasted SVG (its DOM extension is loaded).
	 *
	 * @return bool
	 */
	function blocklane_pro_carousel_host_validates_svg(): bool {
		return class_exists( 'DOMDocument' );
	}

	/**
	 * Whether a pasted arrow is a well-formed SVG document. An entity
	 * declaration is refused on every host; a host that cannot parse XML
	 * answers an error; the document must parse with no error or warning and
	 * have an unprefixed svg root. Nothing external is ever fetched, and the
	 * parsed document is never output.
	 *
	 * @param string    $svg          The pasted markup.
	 * @param bool|null $can_validate Whether the host can validate (null: ask the host).
	 * @return bool|WP_Error
	 */
	function blocklane_pro_carousel_svg_well_formed( string $svg, ?bool $can_validate = null ): bool|WP_Error {
		if ( false !== stripos( $svg, '<!ENTITY' ) ) {
			return false;
		}
		// One paste limit in both doors (own-code amendment, U11d security
		// read F5): more than 64 KiB is refused before any parser, on every host.
		if ( strlen( $svg ) > 65536 ) {
			return false;
		}
		$can = null === $can_validate ? blocklane_pro_carousel_host_validates_svg() : $can_validate;
		if ( ! $can || ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error(
				'blocklane_carousel_no_svg_validator',
				__( 'This server cannot check pasted SVG because PHP\'s DOM extension is not loaded.', 'blocklane' )
			);
		}
		if ( '' === $svg ) {
			return false;
		}
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$document = new DOMDocument();
		try {
			$loaded = $document->loadXML( $svg, LIBXML_NONET );
		} catch ( ValueError $e ) {
			$loaded = false;
		}
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || array() !== $errors || null === $document->documentElement ) {
			return false;
		}
		return 'svg' === strtolower( $document->documentElement->nodeName );
	}

	/**
	 * The elements and attributes a pasted arrow may keep.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function blocklane_pro_carousel_svg_allowlist(): array {
		$shared = array(
			'fill'         => true,
			'opacity'      => true,
			'stroke'       => true,
			'stroke-width' => true,
			'transform'    => true,
		);
		$points = $shared + array( 'points' => true );
		return array(
			'circle'   => $shared + array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'ellipse'  => $shared + array(
				'cx' => true,
				'cy' => true,
				'rx' => true,
				'ry' => true,
			),
			'g'        => $shared + array( 'clip-path' => true ),
			'line'     => $shared + array(
				'x1' => true,
				'x2' => true,
				'y1' => true,
				'y2' => true,
			),
			'path'     => $shared + array(
				'clip-rule'       => true,
				'd'               => true,
				'fill-rule'       => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'polygon'  => $points,
			'polyline' => $points,
			'rect'     => $shared + array(
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'width'  => true,
				'x'      => true,
				'y'      => true,
			),
			'svg'      => array(
				'class'   => true,
				'fill'    => true,
				'height'  => true,
				'viewbox' => true,
				'width'   => true,
				'xmlns'   => true,
			),
		);
	}

	/**
	 * Whether an attribute value holds a url( that points anywhere but a
	 * fragment of the same document. Every url( in the value is judged.
	 * A value holding a backslash counts too: a CSS escape (`\75 rl(`) can
	 * spell url( in a presentation attribute, where no literal url( shows,
	 * and the editor's door drops such a value (U11d controls editor,
	 * ruling 1 at import).
	 *
	 * @param string $value The attribute's decoded value.
	 * @return bool
	 */
	function blocklane_pro_carousel_value_fetches( string $value ): bool {
		if ( str_contains( $value, '\\' ) ) {
			return true;
		}
		$lower  = strtolower( $value );
		$length = strlen( $value );
		$offset = 0;
		while ( false !== ( $at = strpos( $lower, 'url(', $offset ) ) ) {
			$i = $at + 4;
			$i += strspn( $value, " \t\n\r\f", $i );
			if ( $i < $length && ( "'" === $value[ $i ] || '"' === $value[ $i ] ) ) {
				++$i;
				$i += strspn( $value, " \t\n\r\f", $i );
			}
			if ( $i >= $length || '#' !== $value[ $i ] ) {
				return true;
			}
			$offset = $at + 4;
		}
		return false;
	}

	/**
	 * A pasted arrow's markup, cleaned and wrapped, or the empty string when
	 * it is not used (not well formed, the host cannot check it, or nothing
	 * in it draws).
	 *
	 * @param string $paste The pasted SVG.
	 * @return string
	 */
	function blocklane_pro_carousel_custom_arrow( string $paste ): string {
		static $logged = false;
		$verdict = blocklane_pro_carousel_svg_well_formed( $paste );
		if ( $verdict instanceof WP_Error ) {
			if ( ! $logged && function_exists( 'blocklane_pro_log' ) ) {
				$logged = true;
				blocklane_pro_log( 'Carousel: a pasted arrow was replaced by a catalog icon because ' . rtrim( $verdict->get_error_message(), '.' ) . '.' );
			}
			return '';
		}
		if ( true !== $verdict ) {
			return '';
		}
		$clean   = wp_kses( $paste, blocklane_pro_carousel_svg_allowlist() );
		$drawing = array( 'PATH', 'CIRCLE', 'RECT', 'LINE', 'POLYLINE', 'POLYGON', 'ELLIPSE' );
		$draws   = false;
		$tags    = new WP_HTML_Tag_Processor( $clean );
		while ( $tags->next_tag() ) {
			if ( in_array( $tags->get_tag(), $drawing, true ) ) {
				$draws = true;
			}
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$value = $tags->get_attribute( $name );
				if ( is_string( $value ) && blocklane_pro_carousel_value_fetches( $value ) ) {
					$tags->remove_attribute( $name );
				}
			}
		}
		if ( ! $draws ) {
			return '';
		}
		return '<span class="blocklane-carousel-custom-icon" aria-hidden="true">' . $tags->get_updated_html() . '</span>';
	}

	/**
	 * The arrow icon catalog beside this file, read once per request; an
	 * empty catalog when the file is missing, unreadable or invalid.
	 *
	 * @return list<array<string, mixed>>
	 */
	function blocklane_pro_carousel_arrow_catalog(): array {
		static $catalog = null;
		if ( is_array( $catalog ) ) {
			return $catalog;
		}
		$catalog = array();
		$file    = __DIR__ . '/arrow-icons.json';
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return $catalog;
		}
		$text = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file shipped with the plugin.
		if ( ! is_string( $text ) ) {
			return $catalog;
		}
		$decoded = json_decode( $text, true );
		if ( ! is_array( $decoded ) ) {
			return $catalog;
		}
		foreach ( $decoded as $entry ) {
			if ( is_array( $entry ) ) {
				$catalog[] = $entry;
			}
		}
		return $catalog;
	}

	/**
	 * The markup inside an arrow button: a pasted arrow when the icon is
	 * custom and the paste is admitted, else the catalog icon at the weight
	 * (falling back to regular, then to its first path), else a chevron.
	 *
	 * @param string $icon   The icon name.
	 * @param string $weight The icon weight.
	 * @param string $paste  A pasted SVG (used only for the custom icon).
	 * @return string
	 */
	function blocklane_pro_carousel_arrow_svg( string $icon, string $weight, string $paste ): string {
		if ( 'custom' === $icon && '' !== $paste ) {
			$custom = blocklane_pro_carousel_custom_arrow( $paste );
			if ( '' !== $custom ) {
				return $custom;
			}
		}

		$view_box = '0 0 256 256';
		$path     = 'M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z';

		foreach ( blocklane_pro_carousel_arrow_catalog() as $entry ) {
			if ( ! isset( $entry['name'] ) || $entry['name'] !== $icon ) {
				continue;
			}
			$paths = isset( $entry['paths'] ) && is_array( $entry['paths'] ) ? $entry['paths'] : array();
			$found = '';
			foreach ( array( $weight, 'regular' ) as $key ) {
				if ( isset( $paths[ $key ] ) && is_string( $paths[ $key ] ) && '' !== $paths[ $key ] ) {
					$found = $paths[ $key ];
					break;
				}
			}
			if ( '' === $found ) {
				foreach ( $paths as $candidate ) {
					if ( is_string( $candidate ) && '' !== $candidate ) {
						$found = $candidate;
						break;
					}
				}
			}
			$box = $entry['viewBox'] ?? '';
			if ( '' !== $found && is_string( $box ) && '' !== $box ) {
				$view_box = $box;
				$path     = $found;
			}
			break;
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $view_box ) . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
	}

	/**
	 * The six theme presets the bundled patterns use, as one root block in a
	 * cascade layer, so a theme's or core's own definition wins.
	 *
	 * @return string
	 */
	function blocklane_pro_carousel_preset_root_css(): string {
		$presets = array(
			'spacing--30'    => '1rem',
			'spacing--40'    => '1.5rem',
			'spacing--50'    => '2rem',
			'spacing--60'    => 'clamp(2.25rem, 2rem + 1.25vw, 3rem)',
			'spacing--70'    => 'clamp(2.75rem, 2.33rem + 2.08vw, 4rem)',
			'color--outline' => '#e5e7eb',
		);
		$body    = '';
		foreach ( $presets as $name => $value ) {
			$body .= '--wp--preset--' . $name . ': ' . $value . ';';
		}
		return '@layer blocklane-carousel-presets{:root{' . $body . '}}';
	}

	/**
	 * Register and enqueue the preset root block as a file-less style handle
	 * carrying it inline (once).
	 *
	 * @return void
	 */
	function blocklane_pro_carousel_enqueue_preset_root(): void {
		$handle  = 'blocklane-carousel-presets';
		$version = defined( 'BLOCKLANE_PRO_VERSION' ) ? (string) constant( 'BLOCKLANE_PRO_VERSION' ) : false;
		wp_register_style( $handle, false, array(), $version );
		wp_enqueue_style( $handle );
		$css   = blocklane_pro_carousel_preset_root_css();
		$after = wp_styles()->get_data( $handle, 'after' );
		if ( ! is_array( $after ) || ! in_array( $css, $after, true ) ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * The request's render depth counter: add the change, never fall below 0,
	 * and return the new depth.
	 *
	 * @param int $change The change (1 on entering a track's query, -1 on leaving).
	 * @return int
	 */
	function blocklane_pro_carousel_render_depth( int $change ): int {
		static $depth = 0;
		if ( $change > 0 && $depth > PHP_INT_MAX - $change ) {
			$depth = PHP_INT_MAX;
		} else {
			$depth = max( 0, $depth + $change );
		}
		return $depth;
	}

	/**
	 * Tell the block editor whether pasted SVG can be validated on this host
	 * and which post types a dynamic carousel may query, as one inline script
	 * before the controls block's editor script.
	 *
	 * @param mixed $validator A boolean states the host fact; anything else asks the host.
	 * @return void
	 */
	function blocklane_pro_carousel_editor_state( mixed $validator = null ): void {
		$handle = 'blocklane-carousel-controls-editor-script';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}
		$state = wp_json_encode(
			array(
				'svgValidator'   => is_bool( $validator ) ? $validator : blocklane_pro_carousel_host_validates_svg(),
				'queryPostTypes' => blocklane_pro_carousel_query_post_types(),
			)
		);
		if ( ! is_string( $state ) ) {
			return;
		}
		wp_add_inline_script(
			$handle,
			'window.blocklaneProCarousel = Object.assign( window.blocklaneProCarousel || {}, ' . $state . ' );',
			'before'
		);
	}

	/**
	 * Add the Carousel block category unless the editor already has it.
	 *
	 * @param array<int|string, mixed> $categories The editor's block categories.
	 * @return array<int|string, mixed>
	 */
	function blocklane_pro_carousel_block_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( is_array( $category ) && isset( $category['slug'] ) && 'blocklane-carousel' === $category['slug'] ) {
				return $categories;
			}
		}
		$categories[] = array(
			'slug'  => 'blocklane-carousel',
			'title' => __( 'Carousel', 'blocklane' ),
		);
		return $categories;
	}

	/**
	 * Register the four carousel blocks through the plugin's block suite
	 * registrar.
	 *
	 * @return void
	 */
	function blocklane_pro_carousel_register_blocks(): void {
		if ( ! class_exists( '\blocklane_pro\Block_Suite', false ) ) {
			return;
		}
		\blocklane_pro\Block_Suite::register(
			'carousel',
			'blocklane',
			__DIR__ . '/build',
			array( 'carousel', 'carousel-track', 'slide', 'carousel-controls' ),
			'npm run build:carousel',
			__( 'Carousel', 'blocklane' ),
			__( 'Saved carousels will not render until the assets are rebuilt.', 'blocklane' )
		);
	}

	/**
	 * Register the Carousels pattern category, only while every carousel
	 * block's manifest is present.
	 *
	 * @return void
	 */
	function blocklane_pro_carousel_register_pattern_category(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) || ! class_exists( '\blocklane_pro\Block_Suite', false ) ) {
			return;
		}
		if ( ! empty( \blocklane_pro\Block_Suite::missing( 'carousel' ) ) ) {
			return;
		}
		register_block_pattern_category(
			'blocklane-carousel',
			array( 'label' => __( 'Carousels', 'blocklane' ) )
		);
	}

	add_action( 'init', 'blocklane_pro_carousel_register_blocks' );
	add_action( 'init', 'blocklane_pro_carousel_register_pattern_category' );
	add_filter( 'block_categories_all', 'blocklane_pro_carousel_block_category' );
	add_action( 'enqueue_block_editor_assets', 'blocklane_pro_carousel_editor_state' );
	add_action( 'enqueue_block_assets', 'blocklane_pro_carousel_enqueue_preset_root' );
}
