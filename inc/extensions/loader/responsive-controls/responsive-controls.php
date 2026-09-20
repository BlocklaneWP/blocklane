<?php
/**
 * Responsive Controls
 *
 * Since core 7.1, per-breakpoint styling is CORE's job: overrides live in the
 * block's style['@tablet'] / style['@mobile'] states (rendered by the states,
 * layout, and visibility block supports against theme.json's
 * settings.viewport). The render_block_data shim below converts the legacy
 * blocklaneProResponsive bag into those core formats at render time, so old
 * content is painted by core without a resave (the editor performs the same
 * migration at parse time, persisting it on save).
 *
 * What stays Blocklane-rendered — via render_block vars/classes + the static
 * media-query stylesheet — are the extras core has no property for: per-
 * breakpoint display `order` and `maxWidth`.
 *
 * @package blocklane_pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The server-side attribute registration (blocklaneProResponsive with its stdClass
// default) that used to live here is now the generated attribute schema
// (inc/extensions/attribute-schema.php, from this control's attributes.json)
// registered by inc/extensions/loader/attribute-schema.php in BOTH editions (#858).

/**
 * Convert a WordPress preset shorthand value to a CSS custom property.
 *
 * e.g. "var:preset|spacing|60" → "var(--wp--preset--spacing--60)"
 * Plain CSS values like "1rem" or "20px" pass through unchanged.
 *
 * @param string $value Raw value from block attributes.
 * @return string CSS-safe value.
 */
function blocklane_pro_responsive_resolve_preset_value( $value ) {
	if ( ! is_string( $value ) || strpos( $value, 'var:preset|' ) !== 0 ) {
		return $value;
	}

	// "var:preset|spacing|60" → "--wp--preset--spacing--60"
	$path = substr( $value, 4 ); // Remove "var:"
	$path = str_replace( '|', '--', $path );

	return 'var(--wp--' . $path . ')';
}

/**
 * Resolve a tablet → mobile cascade: mobile inherits the tablet value unless it
 * has its own. Returns only the breakpoints that end up with a value, keyed
 * 'tablet' / 'mobile', in that order.
 *
 * @param mixed $data Per-breakpoint data ( array with 'tablet' / 'mobile' keys ).
 * @return array<string,mixed>
 */
function blocklane_pro_responsive_cascade( $data ) {
	if ( ! is_array( $data ) ) {
		return array();
	}

	$tablet = ( isset( $data['tablet'] ) && '' !== $data['tablet'] ) ? $data['tablet'] : null;
	$mobile = ( isset( $data['mobile'] ) && '' !== $data['mobile'] ) ? $data['mobile'] : $tablet;

	$out = array();
	if ( null !== $tablet ) {
		$out['tablet'] = $tablet;
	}
	if ( null !== $mobile ) {
		$out['mobile'] = $mobile;
	}
	return $out;
}

/**
 * Convert the legacy blocklaneProResponsive bag into core 7.1 formats before
 * the block renders, so CORE's block supports (states, layout, visibility)
 * paint unmigrated content:
 *
 *  - fontSize / textAlign         → style['@bp'].typography.*
 *  - padding / margin / blockGap  → style['@bp'].spacing.*
 *  - minHeight                    → style['@bp'].dimensions.minHeight
 *  - justifyContent / orientation → style['@bp'].layout.* (core vocabulary)
 *  - hidden                       → metadata.blockVisibility.viewport.* = false
 *
 * The legacy tablet→mobile cascade is materialized (core's @tablet band is
 * exclusive: mobile < width <= tablet), existing core-format values are never
 * clobbered, and migrated keys are stripped from the bag so the legacy
 * render_block filter below only ever emits the Blocklane extras
 * (order/maxWidth) — exactly one engine renders each property.
 *
 * @param array<string,mixed> $parsed_block The parsed block.
 * @return array<string,mixed> The parsed block, migrated.
 */
function blocklane_pro_responsive_migrate_parsed_block( $parsed_block ) {
	$bag = $parsed_block['attrs']['blocklaneProResponsive'] ?? null;
	if ( ! is_array( $bag ) || empty( $bag ) ) {
		return $parsed_block;
	}

	$migrated_keys = array( 'fontSize', 'textAlign', 'padding', 'margin', 'blockGap', 'minHeight', 'justifyContent', 'orientation', 'hidden' );
	if ( ! array_intersect( $migrated_keys, array_keys( $bag ) ) ) {
		return $parsed_block;
	}

	$style = $parsed_block['attrs']['style'] ?? array();
	$style = is_array( $style ) ? $style : array();

	// Set a deep path only when the leaf is absent — authored core values win.
	$set_if_absent = static function ( &$target, $path, $value ) {
		$node = &$target;
		foreach ( array_slice( $path, 0, -1 ) as $key ) {
			if ( ! isset( $node[ $key ] ) || ! is_array( $node[ $key ] ) ) {
				$node[ $key ] = array();
			}
			$node = &$node[ $key ];
		}
		$leaf = $path[ count( $path ) - 1 ];
		if ( ! isset( $node[ $leaf ] ) ) {
			$node[ $leaf ] = $value;
		}
	};

	$state = static function ( $bp ) {
		return '@' . $bp;
	};

	foreach ( blocklane_pro_responsive_cascade( $bag['fontSize'] ?? null ) as $bp => $v ) {
		$set_if_absent( $style, array( $state( $bp ), 'typography', 'fontSize' ), $v );
	}
	foreach ( blocklane_pro_responsive_cascade( $bag['textAlign'] ?? null ) as $bp => $v ) {
		if ( in_array( $v, array( 'left', 'center', 'right' ), true ) ) {
			$set_if_absent( $style, array( $state( $bp ), 'typography', 'textAlign' ), $v );
		}
	}
	foreach ( array( 'padding', 'margin' ) as $prop ) {
		$data = $bag[ $prop ] ?? null;
		if ( ! is_array( $data ) ) {
			continue;
		}
		$tablet_data = is_array( $data['tablet'] ?? null ) ? $data['tablet'] : array();
		$mobile_data = is_array( $data['mobile'] ?? null ) ? $data['mobile'] : array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$pair = blocklane_pro_responsive_cascade(
				array(
					'tablet' => $tablet_data[ $side ] ?? '',
					'mobile' => $mobile_data[ $side ] ?? '',
				)
			);
			foreach ( $pair as $bp => $v ) {
				$set_if_absent( $style, array( $state( $bp ), 'spacing', $prop, $side ), $v );
			}
		}
	}
	foreach ( blocklane_pro_responsive_cascade( $bag['blockGap'] ?? null ) as $bp => $v ) {
		$set_if_absent( $style, array( $state( $bp ), 'spacing', 'blockGap' ), $v );
	}
	foreach ( blocklane_pro_responsive_cascade( $bag['minHeight'] ?? null ) as $bp => $v ) {
		$set_if_absent( $style, array( $state( $bp ), 'dimensions', 'minHeight' ), $v );
	}
	// Blocklane stored CSS justify values; core layout uses its own vocabulary.
	$justify_map = array(
		'flex-start'    => 'left',
		'center'        => 'center',
		'flex-end'      => 'right',
		'space-between' => 'space-between',
		'stretch'       => 'stretch',
	);
	foreach ( blocklane_pro_responsive_cascade( $bag['justifyContent'] ?? null ) as $bp => $v ) {
		if ( isset( $justify_map[ $v ] ) ) {
			$set_if_absent( $style, array( $state( $bp ), 'layout', 'justifyContent' ), $justify_map[ $v ] );
		}
	}
	foreach ( blocklane_pro_responsive_cascade( $bag['orientation'] ?? null ) as $bp => $v ) {
		if ( in_array( $v, array( 'horizontal', 'vertical' ), true ) ) {
			$set_if_absent( $style, array( $state( $bp ), 'layout', 'orientation' ), $v );
		}
	}

	// Visibility: independent per-device booleans (no cascade) → core's
	// metadata.blockVisibility.viewport (false = hidden). Never touch the
	// boolean blockVisibility === false "hidden everywhere" form.
	$hidden = $bag['hidden'] ?? null;
	if ( is_array( $hidden ) && ! empty( $hidden ) ) {
		$metadata         = $parsed_block['attrs']['metadata'] ?? array();
		$block_visibility = is_array( $metadata ) ? ( $metadata['blockVisibility'] ?? null ) : null;
		if ( is_array( $metadata ) && ( null === $block_visibility || is_array( $block_visibility ) ) ) {
			$viewport = is_array( $block_visibility['viewport'] ?? null ) ? $block_visibility['viewport'] : array();
			foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
				if ( ! empty( $hidden[ $device ] ) && ! isset( $viewport[ $device ] ) ) {
					$viewport[ $device ] = false;
				}
			}
			if ( ! empty( $viewport ) ) {
				$block_visibility             = is_array( $block_visibility ) ? $block_visibility : array();
				$block_visibility['viewport'] = $viewport;
				$metadata['blockVisibility']  = $block_visibility;

				$parsed_block['attrs']['metadata'] = $metadata;
			}
		}
	}

	foreach ( $migrated_keys as $key ) {
		unset( $bag[ $key ] );
	}

	if ( ! empty( $style ) ) {
		$parsed_block['attrs']['style'] = $style;
	}
	$parsed_block['attrs']['blocklaneProResponsive'] = $bag;

	return $parsed_block;
}
add_filter( 'render_block_data', 'blocklane_pro_responsive_migrate_parsed_block' );

/**
 * Filter block content to add responsive CSS custom properties
 * and marker classes on the frontend.
 *
 * Each property emits, per breakpoint, a `--blocklane-pro-*` custom property and a
 * matching `has-blocklane-pro-*` marker class; the static stylesheet's media queries
 * do the rest. Tablet values cascade to mobile (see the cascade helper).
 *
 * @param string $block_content The block content.
 * @param array  $block         The block data.
 * @return string Modified block content.
 */
function blocklane_pro_responsive_render_block( $block_content, $block ) {
	$responsive = $block['attrs']['blocklaneProResponsive'] ?? null;
	$responsive = is_array( $responsive ) ? $responsive : array();

	// A flex container whose children carry order overrides gets a marker so the
	// stylesheet can push its un-ordered children last (scoped per container, so
	// it never touches the columns reorder or other flex rows).
	$child_order_classes = blocklane_pro_responsive_child_order_classes( $block );

	if ( empty( $responsive ) && empty( $child_order_classes ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag() ) {
		return $block_content;
	}

	$style_additions = '';
	$classes         = array();

	/**
	 * Emit a var + marker class for each cascaded breakpoint of a property.
	 *
	 * @param string   $key    Var/class infix (e.g. 'fs', 'gap').
	 * @param mixed    $data   Per-breakpoint data.
	 * @param callable $sanitize Maps a raw value to its CSS value, or null to skip.
	 */
	$emit = function ( $key, $data, $sanitize ) use ( &$style_additions, &$classes ) {
		foreach ( blocklane_pro_responsive_cascade( $data ) as $breakpoint => $raw ) {
			$value = $sanitize( $raw );
			if ( null === $value ) {
				continue;
			}
			$style_additions .= "--blocklane-pro-{$key}-{$breakpoint}:{$value};";
			$classes[]        = "has-blocklane-pro-{$key}-{$breakpoint}";
		}
	};

	// A length/preset value (font size, gap, min height, spacing sides). Reject
	// anything that could inject extra declarations into the inline style — the
	// value is author-controlled and esc_attr only guards attribute breakout,
	// not CSS syntax. Keeps var()/calc()/clamp()/<number><unit>; drops ; { } < >
	// and quotes.
	$as_length = function ( $raw ) {
		$value = blocklane_pro_responsive_resolve_preset_value( $raw );
		if ( ! is_string( $value ) || preg_match( '/[;{}<>"\']/', $value ) ) {
			return null;
		}
		return esc_attr( $value );
	};
	// A value constrained to an allow-list (alignment, justification).
	$as_keyword = function ( array $allowed ) {
		return function ( $raw ) use ( $allowed ) {
			return in_array( $raw, $allowed, true ) ? esc_attr( $raw ) : null;
		};
	};
	// A small positive integer (flex order, 1..50).
	$as_order = function ( $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$n = (int) $raw;
		return ( $n >= 1 && $n <= 50 ) ? (string) $n : null;
	};

	$emit( 'fs', $responsive['fontSize'] ?? null, $as_length );
	$emit( 'gap', $responsive['blockGap'] ?? null, $as_length );
	$emit( 'mh', $responsive['minHeight'] ?? null, $as_length );
	$emit( 'mw', $responsive['maxWidth'] ?? null, $as_length );
	$emit( 'ta', $responsive['textAlign'] ?? null, $as_keyword( array( 'left', 'center', 'right' ) ) );
	$emit( 'jc', $responsive['justifyContent'] ?? null, $as_keyword( array( 'flex-start', 'center', 'flex-end', 'space-between', 'stretch' ) ) );
	$emit( 'order', $responsive['order'] ?? null, $as_order );

	// Per-side spacing (padding, margin): cascade each side independently.
	foreach ( array( 'padding', 'margin' ) as $prop ) {
		$prop_data = $responsive[ $prop ] ?? null;
		if ( ! is_array( $prop_data ) ) {
			continue;
		}
		$tablet_data = is_array( $prop_data['tablet'] ?? null ) ? $prop_data['tablet'] : array();
		$mobile_data = is_array( $prop_data['mobile'] ?? null ) ? $prop_data['mobile'] : array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$emit(
				"{$prop}-{$side}",
				array(
					'tablet' => $tablet_data[ $side ] ?? '',
					'mobile' => $mobile_data[ $side ] ?? '',
				),
				$as_length
			);
		}
	}

	// Orientation maps horizontal/vertical → row/column and adds a direction class
	// the stylesheet keys justification off, so it gets a dedicated pass.
	foreach ( blocklane_pro_responsive_cascade( $responsive['orientation'] ?? null ) as $breakpoint => $raw ) {
		if ( ! in_array( $raw, array( 'horizontal', 'vertical' ), true ) ) {
			continue;
		}
		$dir              = 'vertical' === $raw ? 'column' : 'row';
		$style_additions .= "--blocklane-pro-ori-{$breakpoint}:{$dir};";
		$classes[]        = "has-blocklane-pro-ori-{$breakpoint}";
		$classes[]        = "blocklane-pro-ori-{$breakpoint}-{$dir}";
	}

	// Visibility — independent per-device booleans (no cascade): each true value
	// hides the block within that device's width range (see the stylesheet).
	$hidden = $responsive['hidden'] ?? null;
	if ( is_array( $hidden ) ) {
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( ! empty( $hidden[ $device ] ) ) {
				$classes[] = "has-blocklane-pro-hide-{$device}";
			}
		}
	}

	// Apply if we have anything.
	if ( ! empty( $style_additions ) ) {
		$existing = $processor->get_attribute( 'style' ) ?? '';
		$processor->set_attribute( 'style', $style_additions . $existing );
	}

	foreach ( array_merge( $classes, $child_order_classes ) as $cls ) {
		$processor->add_class( $cls );
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'blocklane_pro_responsive_render_block', 10, 2 );

/**
 * Marker classes for a flex container whose direct children carry responsive
 * order overrides. Lets the stylesheet push that container's un-ordered children
 * last, scoped so it never affects other flex rows (or the columns reorder).
 *
 * @param array $block The block data.
 * @return string[] Class names ('blocklane-pro-child-order-tablet' / '-mobile').
 */
function blocklane_pro_responsive_child_order_classes( $block ) {
	$layout = $block['attrs']['layout'] ?? null;
	$is_flex = ( is_array( $layout ) && ( $layout['type'] ?? '' ) === 'flex' )
		// core/columns is always a flex container but its default flex layout
		// isn't serialized to the layout attribute.
		|| ( $block['blockName'] ?? '' ) === 'core/columns';

	if ( ! $is_flex ) {
		return array();
	}

	$inner = $block['innerBlocks'] ?? array();
	if ( empty( $inner ) || ! is_array( $inner ) ) {
		return array();
	}

	$has = array(
		'tablet' => false,
		'mobile' => false,
	);

	foreach ( $inner as $child ) {
		$order = $child['attrs']['blocklaneProResponsive']['order'] ?? null;
		if ( ! is_array( $order ) ) {
			continue;
		}
		foreach ( array_keys( blocklane_pro_responsive_cascade( $order ) ) as $breakpoint ) {
			$has[ $breakpoint ] = true;
		}
	}

	$classes = array();
	if ( $has['tablet'] ) {
		$classes[] = 'blocklane-pro-child-order-tablet';
	}
	if ( $has['mobile'] ) {
		$classes[] = 'blocklane-pro-child-order-mobile';
	}
	return $classes;
}

/**
 * The global responsive breakpoints. Read via the runtime helpers (option
 * first, baked snapshot after uninstall) so this loader works from either
 * home — the plugin or the generated mu-plugin.
 *
 * @return array{tablet:int,mobile:int}
 */
function blocklane_pro_responsive_get_breakpoints() {
	return blocklane_pro_ext_breakpoints();
}

/**
 * The responsive stylesheet with the configured breakpoints baked into its media
 * queries. The static file is the template (its 768px / 480px are the only
 * occurrences); media queries can't read CSS vars, so the px must be inlined.
 *
 * @return string
 */
function blocklane_pro_responsive_generate_css() {
	// Sibling file — resolves in both homes (plugin and baked mu copy).
	$template = __DIR__ . '/responsive-controls.css';
	$css      = file_exists( $template ) ? (string) file_get_contents( $template ) : '';

	if ( '' === $css ) {
		return '';
	}

	$bp = blocklane_pro_responsive_get_breakpoints();
	$t  = (int) $bp['tablet'];
	$m  = (int) $bp['mobile'];

	// The template's only 768/480 are the override media queries (max-width); the
	// 769/481 are the visibility ranges' min-width (breakpoint + 1). Use strtr() so
	// all four are replaced SIMULTANEOUSLY (each match once, replaced text not
	// re-scanned) — str_replace() applies each pair sequentially, so a legal
	// tablet=480 config would rewrite 768->480 then 480->mobile, corrupting every
	// override.
	return strtr(
		$css,
		array(
			'max-width: 768px' => "max-width: {$t}px",
			'max-width: 480px' => "max-width: {$m}px",
			'min-width: 769px' => 'min-width: ' . ( $t + 1 ) . 'px',
			'min-width: 481px' => 'min-width: ' . ( $m + 1 ) . 'px',
		)
	);
}

/**
 * Enqueue the responsive stylesheet on the frontend, generated inline so its
 * breakpoints reflect the configured option.
 */
function blocklane_pro_responsive_enqueue_styles() {
	wp_register_style( 'blocklane-pro-responsive', false, array( 'global-styles' ), blocklane_pro_ext_version() );
	wp_enqueue_style( 'blocklane-pro-responsive' );
	wp_add_inline_style( 'blocklane-pro-responsive', blocklane_pro_responsive_generate_css() );
}
add_action( 'wp_enqueue_scripts', 'blocklane_pro_responsive_enqueue_styles' );
