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
 * media-query stylesheet — is whatever the shim did not convert. Where
 * render_block_data runs (do_blocks, render_block(), every inner block of a
 * block core renders whole) that is only the extras core has no property
 * for: per-breakpoint display `order` and `maxWidth`. But core also renders
 * blocks through WP_Block::render() with no render_block_data pass — a
 * core/navigation block's children read from its wp_navigation post, and the
 * top-level blocks of its overlay template part — and there the legacy bag
 * reaches render_block whole. So the render path below keeps a branch for
 * every legacy property; edition-battery row E46d pins that it is reachable.
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
 * clobbered, and migrated keys are stripped from the bag, so wherever this
 * filter runs the legacy render_block filter below emits only the Blocklane
 * extras (order/maxWidth). Where core skips this filter (the file header
 * names the paths) the legacy filter renders the whole bag and core paints
 * none of it — either way exactly one engine renders each property.
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
 * render_block: write the bag's custom properties and marker classes onto the block's first tag.
 *
 * Every legacy property is handled here, not only order/maxWidth: where core
 * renders a block without a render_block_data pass (see the file header) the
 * whole bag arrives. Each emitted value becomes a custom property
 * `--blocklane-pro-{infix}-{bp}` plus a `has-blocklane-pro-{infix}-{bp}`
 * class — the names responsive-controls.css reads. The added declarations go
 * IN FRONT of the tag's own style, which is kept verbatim so the author's
 * inline style still has the last word. Only the first tag is touched.
 *
 * A non-string $block_content (an earlier filter's doing) passes through.
 *
 * @param mixed                $block_content The block content.
 * @param array<string, mixed> $block         The parsed block.
 * @return mixed The content with the additions, or $block_content unchanged.
 */
function blocklane_pro_responsive_render_block( mixed $block_content, array $block ): mixed {
	if ( ! is_string( $block_content ) ) {
		return $block_content;
	}

	$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
	$bag   = is_array( $attrs['blocklaneProResponsive'] ?? null ) ? $attrs['blocklaneProResponsive'] : array();

	$paint   = blocklane_pro_responsive_paint( $bag );
	$classes = array_merge( $paint['classes'], blocklane_pro_responsive_order_markers( $block ) );

	if ( array() === $paint['declarations'] && array() === $classes ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag() ) {
		return $block_content;
	}

	if ( array() !== $paint['declarations'] ) {
		$own = $tags->get_attribute( 'style' );
		$tags->set_attribute( 'style', implode( '', $paint['declarations'] ) . ( is_string( $own ) ? $own : '' ) );
	}
	foreach ( $classes as $class ) {
		$tags->add_class( $class );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block', 'blocklane_pro_responsive_render_block', 10, 2 );

/**
 * What a responsive bag paints: the custom-property declarations (each
 * ending in `;`) and the classes.
 *
 * @param array<mixed> $bag The blocklaneProResponsive attribute.
 * @return array{declarations: list<string>, classes: list<string>}
 */
function blocklane_pro_responsive_paint( array $bag ): array {
	$out = array(
		'declarations' => array(),
		'classes'      => array(),
	);

	$emit = static function ( string $infix, string $bp, ?string $css ) use ( &$out ): void {
		if ( null === $css ) {
			return;
		}
		$out['declarations'][] = '--blocklane-pro-' . $infix . '-' . $bp . ':' . $css . ';';
		$out['classes'][]      = 'has-blocklane-pro-' . $infix . '-' . $bp;
	};

	// Single-value properties: bag key => [ infix, judge ].
	$single = array(
		'fontSize'       => array( 'fs', 'blocklane_pro_responsive_length' ),
		'blockGap'       => array( 'gap', 'blocklane_pro_responsive_length' ),
		'minHeight'      => array( 'mh', 'blocklane_pro_responsive_length' ),
		'maxWidth'       => array( 'mw', 'blocklane_pro_responsive_length' ),
		'textAlign'      => array( 'ta', 'blocklane_pro_responsive_text_align' ),
		'justifyContent' => array( 'jc', 'blocklane_pro_responsive_justify' ),
		'order'          => array( 'order', 'blocklane_pro_responsive_order' ),
	);
	foreach ( $single as $key => list( $infix, $judge ) ) {
		foreach ( blocklane_pro_responsive_cascade( $bag[ $key ] ?? null ) as $bp => $raw ) {
			$emit( $infix, (string) $bp, $judge( $raw ) );
		}
	}

	foreach ( array( 'padding', 'margin' ) as $key ) {
		$spacing = $bag[ $key ] ?? null;
		if ( ! is_array( $spacing ) ) {
			continue;
		}
		$by_bp = array(
			'tablet' => is_array( $spacing['tablet'] ?? null ) ? $spacing['tablet'] : array(),
			'mobile' => is_array( $spacing['mobile'] ?? null ) ? $spacing['mobile'] : array(),
		);
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$pair = array(
				'tablet' => $by_bp['tablet'][ $side ] ?? '',
				'mobile' => $by_bp['mobile'][ $side ] ?? '',
			);
			foreach ( blocklane_pro_responsive_cascade( $pair ) as $bp => $raw ) {
				$emit( $key . '-' . $side, (string) $bp, blocklane_pro_responsive_length( $raw ) );
			}
		}
	}

	$directions = array(
		'horizontal' => 'row',
		'vertical'   => 'column',
	);
	foreach ( blocklane_pro_responsive_cascade( $bag['orientation'] ?? null ) as $bp => $raw ) {
		if ( is_string( $raw ) && isset( $directions[ $raw ] ) ) {
			$emit( 'ori', (string) $bp, $directions[ $raw ] );
			$out['classes'][] = 'blocklane-pro-ori-' . $bp . '-' . $directions[ $raw ];
		}
	}

	// Visibility is per device and does not cascade; it adds a class only.
	$hidden = $bag['hidden'] ?? null;
	if ( is_array( $hidden ) ) {
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( ! empty( $hidden[ $device ] ) ) {
				$out['classes'][] = 'has-blocklane-pro-hide-' . $device;
			}
		}
	}

	return $out;
}

/**
 * A length as it may appear inside an inline style, or null when refused.
 *
 * A `var:preset|…` reference becomes the custom property core defines for
 * it. Anything that is not a string is refused, and so is any string that
 * could end the declaration, open or close a rule, or leave the attribute
 * (; { } < > " '). esc_attr() and the HTML API stop attribute breakout, not
 * CSS syntax, so this check is the guard.
 *
 * @param mixed $raw The stored value.
 */
function blocklane_pro_responsive_length( mixed $raw ): ?string {
	if ( ! is_string( $raw ) ) {
		return null;
	}
	if ( str_starts_with( $raw, 'var:preset|' ) ) {
		$raw = 'var(--wp--' . str_replace( '|', '--', substr( $raw, strlen( 'var:' ) ) ) . ')';
	}
	if ( strpbrk( $raw, ';{}<>"\'' ) !== false ) {
		return null;
	}
	return esc_attr( $raw );
}

/**
 * A text-align keyword, exactly as listed, or null.
 *
 * @param mixed $raw The stored value.
 */
function blocklane_pro_responsive_text_align( mixed $raw ): ?string {
	return in_array( $raw, array( 'left', 'center', 'right' ), true ) ? $raw : null;
}

/**
 * A justify-content keyword, exactly as listed, or null.
 *
 * @param mixed $raw The stored value.
 */
function blocklane_pro_responsive_justify( mixed $raw ): ?string {
	return in_array( $raw, array( 'flex-start', 'center', 'flex-end', 'space-between', 'stretch' ), true ) ? $raw : null;
}

/**
 * A display order from 1 to 50 as a decimal string, or null.
 *
 * Any scalar is read the way PHP's (int) cast reads it ("12abc" → 12,
 * 2.9 → 2, true → 1). A float that is not finite or that no int can hold is
 * refused before the cast, since it cannot land in range anyway.
 *
 * @param mixed $raw The stored value.
 */
function blocklane_pro_responsive_order( mixed $raw ): ?string {
	if ( ! is_scalar( $raw ) ) {
		return null;
	}
	if ( is_float( $raw ) && ( ! is_finite( $raw ) || abs( $raw ) >= PHP_INT_MAX ) ) {
		return null;
	}
	$order = (int) $raw;
	return ( $order >= 1 && $order <= 50 ) ? (string) $order : null;
}

/**
 * The child-order marker classes of a flex container: which breakpoints at
 * least one DIRECT child carries an order at. The values are not judged —
 * the stylesheet only needs to know ordered children exist.
 *
 * A block is a flex container when its layout type is `flex`, or when it is
 * core/columns (whose default flex layout is never serialized).
 *
 * @param array<string, mixed> $block The parsed block.
 * @return list<string>
 */
function blocklane_pro_responsive_order_markers( array $block ): array {
	$attrs  = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
	$layout = $attrs['layout'] ?? null;
	$flex   = ( is_array( $layout ) && 'flex' === ( $layout['type'] ?? null ) ) || 'core/columns' === ( $block['blockName'] ?? null );
	if ( ! $flex || ! is_array( $block['innerBlocks'] ?? null ) ) {
		return array();
	}

	$marked = array();
	foreach ( $block['innerBlocks'] as $child ) {
		$order = is_array( $child ) ? ( $child['attrs']['blocklaneProResponsive']['order'] ?? null ) : null;
		if ( ! is_array( $order ) ) {
			continue;
		}
		foreach ( array_keys( blocklane_pro_responsive_cascade( $order ) ) as $bp ) {
			$marked[ $bp ] = 'blocklane-pro-child-order-' . $bp;
		}
	}

	return array_values( $marked );
}

/**
 * The global responsive breakpoints. Read via the runtime helpers: the
 * option first, the shipped default when there is none.
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
