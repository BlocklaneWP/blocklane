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
 * Paints a block's legacy per-breakpoint settings onto its first tag.
 *
 * The bag in `attrs.blocklaneProResponsive` normally reaches core already
 * converted, but blocks rendered without a `render_block_data` pass (the
 * children of a navigation block, the top-level blocks of a navigation overlay
 * template part) still carry it here. Each painted value becomes one custom
 * property on the tag's inline style and one marker class; visibility and the
 * child-order markers of flex containers are classes only.
 *
 * Every tablet and mobile resolution goes through
 * blocklane_pro_responsive_cascade(). Values are judged after the cascade:
 * a length must be a string free of the characters that could end a CSS
 * declaration or the attribute, a keyword must be one of its property's exact
 * values, and an order must convert to an integer from 1 to 50.
 *
 * @param mixed                $block_content The rendered block markup.
 * @param array<string, mixed> $block         The parsed block.
 * @return mixed The markup with the first tag painted, or the content unchanged.
 */
function blocklane_pro_responsive_render_block( mixed $block_content, array $block ): mixed {
	if ( ! is_string( $block_content ) ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$bag   = isset( $attrs['blocklaneProResponsive'] ) && is_array( $attrs['blocklaneProResponsive'] ) ? $attrs['blocklaneProResponsive'] : array();

	$declarations = array();
	$classes      = array();

	blocklane_pro_responsive_render_single_values( $bag, $declarations, $classes );
	blocklane_pro_responsive_render_spacing( $bag, $declarations, $classes );
	blocklane_pro_responsive_render_orientation( $bag, $declarations, $classes );
	blocklane_pro_responsive_render_visibility( $bag, $classes );
	blocklane_pro_responsive_render_child_order( $block, $classes );

	if ( array() === $declarations && array() === $classes ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag() ) {
		return $block_content;
	}

	if ( array() !== $declarations ) {
		$own = $tags->get_attribute( 'style' );
		$tags->set_attribute( 'style', implode( '', $declarations ) . ( is_string( $own ) ? $own : '' ) );
	}
	foreach ( $classes as $class ) {
		$tags->add_class( $class );
	}

	return $tags->get_updated_html();
}

/**
 * The properties that hold one value per breakpoint, with the infix each one
 * paints under and how its values are judged: `length`, `order`, or the list
 * of keywords the property accepts.
 *
 * @return array<string, array{infix: string, kind: string|list<string>}>
 */
function blocklane_pro_responsive_render_single_properties(): array {
	return array(
		'blockGap'       => array(
			'infix' => 'gap',
			'kind'  => 'length',
		),
		'fontSize'       => array(
			'infix' => 'fs',
			'kind'  => 'length',
		),
		'justifyContent' => array(
			'infix' => 'jc',
			'kind'  => array( 'center', 'flex-end', 'flex-start', 'space-between', 'stretch' ),
		),
		'maxWidth'       => array(
			'infix' => 'mw',
			'kind'  => 'length',
		),
		'minHeight'      => array(
			'infix' => 'mh',
			'kind'  => 'length',
		),
		'order'          => array(
			'infix' => 'order',
			'kind'  => 'order',
		),
		'textAlign'      => array(
			'infix' => 'ta',
			'kind'  => array( 'center', 'left', 'right' ),
		),
	);
}

/**
 * Paints every single-value property of the bag.
 *
 * @param array<mixed, mixed> $bag          The responsive bag.
 * @param list<string>        $declarations Declarations gathered so far; added to.
 * @param list<string>        $classes      Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_single_values( array $bag, array &$declarations, array &$classes ): void {
	foreach ( blocklane_pro_responsive_render_single_properties() as $key => $property ) {
		if ( ! isset( $bag[ $key ] ) || ! is_array( $bag[ $key ] ) ) {
			continue;
		}
		foreach ( blocklane_pro_responsive_render_resolve( $bag[ $key ] ) as $breakpoint => $value ) {
			$kind = $property['kind'];
			if ( is_array( $kind ) ) {
				$css = blocklane_pro_responsive_render_keyword( $value, $kind );
			} elseif ( 'order' === $kind ) {
				$css = blocklane_pro_responsive_render_order( $value );
			} else {
				$css = blocklane_pro_responsive_render_length( $value );
			}
			if ( null !== $css ) {
				blocklane_pro_responsive_render_paint( $property['infix'], $breakpoint, $css, $declarations, $classes );
			}
		}
	}
}

/**
 * Paints padding and margin, each side cascading on its own.
 *
 * @param array<mixed, mixed> $bag          The responsive bag.
 * @param list<string>        $declarations Declarations gathered so far; added to.
 * @param list<string>        $classes      Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_spacing( array $bag, array &$declarations, array &$classes ): void {
	foreach ( array( 'padding', 'margin' ) as $key ) {
		if ( ! isset( $bag[ $key ] ) || ! is_array( $bag[ $key ] ) ) {
			continue;
		}
		$data   = $bag[ $key ];
		$tablet = isset( $data['tablet'] ) && is_array( $data['tablet'] ) ? $data['tablet'] : array();
		$mobile = isset( $data['mobile'] ) && is_array( $data['mobile'] ) ? $data['mobile'] : array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$map = array(
				'tablet' => $tablet[ $side ] ?? '',
				'mobile' => $mobile[ $side ] ?? '',
			);
			foreach ( blocklane_pro_responsive_render_resolve( $map ) as $breakpoint => $value ) {
				$css = blocklane_pro_responsive_render_length( $value );
				if ( null !== $css ) {
					blocklane_pro_responsive_render_paint( $key . '-' . $side, $breakpoint, $css, $declarations, $classes );
				}
			}
		}
	}
}

/**
 * Paints orientation: a direction declaration, its marker class, and a class
 * naming the direction.
 *
 * @param array<mixed, mixed> $bag          The responsive bag.
 * @param list<string>        $declarations Declarations gathered so far; added to.
 * @param list<string>        $classes      Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_orientation( array $bag, array &$declarations, array &$classes ): void {
	if ( ! isset( $bag['orientation'] ) || ! is_array( $bag['orientation'] ) ) {
		return;
	}
	$directions = array(
		'horizontal' => 'row',
		'vertical'   => 'column',
	);
	foreach ( blocklane_pro_responsive_render_resolve( $bag['orientation'] ) as $breakpoint => $value ) {
		if ( ! is_string( $value ) || ! isset( $directions[ $value ] ) ) {
			continue;
		}
		$direction = $directions[ $value ];
		blocklane_pro_responsive_render_paint( 'ori', $breakpoint, $direction, $declarations, $classes );
		$classes[] = 'blocklane-pro-ori-' . $breakpoint . '-' . $direction;
	}
}

/**
 * Adds a hide class for each device whose visibility value is truthy. There
 * is no cascade and no declaration.
 *
 * @param array<mixed, mixed> $bag     The responsive bag.
 * @param list<string>        $classes Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_visibility( array $bag, array &$classes ): void {
	if ( ! isset( $bag['hidden'] ) || ! is_array( $bag['hidden'] ) ) {
		return;
	}
	foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
		if ( ! empty( $bag['hidden'][ $device ] ) ) {
			$classes[] = 'has-blocklane-pro-hide-' . $device;
		}
	}
}

/**
 * Marks a flex container whose direct children carry an order at tablet or
 * mobile. The children's order values are resolved but not judged.
 *
 * @param array<string, mixed> $block   The parsed block.
 * @param list<string>         $classes Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_child_order( array $block, array &$classes ): void {
	if ( ! blocklane_pro_responsive_render_is_flex( $block ) ) {
		return;
	}
	if ( ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
		return;
	}
	$marked = array();
	foreach ( $block['innerBlocks'] as $child ) {
		if ( ! is_array( $child ) || ! isset( $child['attrs'] ) || ! is_array( $child['attrs'] ) ) {
			continue;
		}
		$bag = $child['attrs']['blocklaneProResponsive'] ?? null;
		if ( ! is_array( $bag ) || ! isset( $bag['order'] ) || ! is_array( $bag['order'] ) ) {
			continue;
		}
		foreach ( blocklane_pro_responsive_render_resolve( $bag['order'] ) as $breakpoint => $value ) {
			$marked[ $breakpoint ] = true;
		}
	}
	foreach ( array( 'tablet', 'mobile' ) as $breakpoint ) {
		if ( isset( $marked[ $breakpoint ] ) ) {
			$classes[] = 'blocklane-pro-child-order-' . $breakpoint;
		}
	}
}

/**
 * Whether the block lays its children out as a flex container: a flex layout
 * in its attributes, or a columns block (whose flex layout core never writes).
 *
 * @param array<string, mixed> $block The parsed block.
 */
function blocklane_pro_responsive_render_is_flex( array $block ): bool {
	if ( 'core/columns' === ( $block['blockName'] ?? null ) ) {
		return true;
	}
	$layout = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? ( $block['attrs']['layout'] ?? null ) : null;
	return is_array( $layout ) && 'flex' === ( $layout['type'] ?? null );
}

/**
 * Resolves a map of breakpoint values through the plugin's cascade, keeping
 * only the two breakpoints this filter paints.
 *
 * @param array<mixed, mixed> $map A property's map from breakpoint to value.
 * @return array<string, mixed> The kept values, keyed `tablet` and/or `mobile`.
 */
function blocklane_pro_responsive_render_resolve( array $map ): array {
	$kept = array();
	foreach ( blocklane_pro_responsive_cascade( $map ) as $breakpoint => $value ) {
		if ( 'tablet' === $breakpoint || 'mobile' === $breakpoint ) {
			$kept[ $breakpoint ] = $value;
		}
	}
	return $kept;
}

/**
 * Adds one declaration and its marker class.
 *
 * @param string       $infix        The property's infix, such as `fs` or `padding-top`.
 * @param string       $breakpoint   `tablet` or `mobile`.
 * @param string       $css          The judged CSS value.
 * @param list<string> $declarations Declarations gathered so far; added to.
 * @param list<string> $classes      Classes gathered so far; added to.
 */
function blocklane_pro_responsive_render_paint( string $infix, string $breakpoint, string $css, array &$declarations, array &$classes ): void {
	$declarations[] = '--blocklane-pro-' . $infix . '-' . $breakpoint . ':' . $css . ';';
	$classes[]      = 'has-blocklane-pro-' . $infix . '-' . $breakpoint;
}

/**
 * Judges a length. A `var:preset|…` reference becomes the custom property
 * core defines for that preset; any other string stays as written. Anything
 * that is not a string, or a string holding a character that could end the
 * declaration or the attribute, is refused.
 *
 * @param mixed $value The value the cascade kept.
 * @return string|null The value to paint, or null when it is refused.
 */
function blocklane_pro_responsive_render_length( mixed $value ): ?string {
	if ( ! is_string( $value ) ) {
		return null;
	}
	if ( str_starts_with( $value, 'var:preset|' ) ) {
		$value = 'var(--wp--' . str_replace( '|', '--', substr( $value, 4 ) ) . ')';
	}
	if ( false !== strpbrk( $value, ';{}<>"\'' ) ) {
		return null;
	}
	return esc_attr( $value );
}

/**
 * Judges a keyword: it must be identical to one of the allowed values.
 *
 * @param mixed        $value   The value the cascade kept.
 * @param list<string> $allowed The property's keywords.
 * @return string|null The keyword, or null when it is refused.
 */
function blocklane_pro_responsive_render_keyword( mixed $value, array $allowed ): ?string {
	return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
}

/**
 * Judges an order. Scalars convert the way PHP's integer cast converts them
 * and are accepted from 1 to 50. A float (or a numeric string read as one)
 * that is not finite or that no integer can hold is refused before any
 * conversion, so the cast never warns.
 *
 * @param mixed $value The value the cascade kept.
 * @return string|null The order in decimal, or null when it is refused.
 */
function blocklane_pro_responsive_render_order( mixed $value ): ?string {
	if ( ! is_scalar( $value ) ) {
		return null;
	}
	if ( is_float( $value ) || is_string( $value ) ) {
		$number = (float) $value;
		if ( ! is_finite( $number ) || abs( $number ) >= (float) PHP_INT_MAX ) {
			return null;
		}
	}
	$order = (int) $value;
	if ( $order < 1 || $order > 50 ) {
		return null;
	}
	return (string) $order;
}

add_filter( 'render_block', 'blocklane_pro_responsive_render_block', 10, 2 );

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
