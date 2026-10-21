<?php
/**
 * The server render of the blocklane/carousel-controls block.
 *
 * Prints one row of carousel controls by its type: the previous and next
 * arrows, an empty pagination row the store fills with dots or bars, a
 * scrollbar, or the pause button (hidden until the store shows it). The gap
 * and the padding, which core does not print for this block, are applied
 * here.
 *
 * @package blocklane_pro
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_read_int = static function ( mixed $value, int $min, int $max, int $fallback ): int {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	return max( $min, min( $max, intval( $value ) ) );
};

$blocklane_type = $attributes['navType'] ?? 'arrows';
if ( ! in_array( $blocklane_type, array( 'arrows', 'dots', 'bars', 'scrollbar', 'autoplay' ), true ) ) {
	$blocklane_type = 'arrows';
}

$blocklane_justify = $attributes['justifyContent'] ?? 'left';
if ( ! in_array( $blocklane_justify, array( 'left', 'center', 'right', 'stretch' ), true ) ) {
	$blocklane_justify = 'left';
}
$blocklane_justify_class = 'is-content-justification-' . $blocklane_justify;

$blocklane_style   = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) ) ? $attributes['style'] : array();
$blocklane_spacing = ( isset( $blocklane_style['spacing'] ) && is_array( $blocklane_style['spacing'] ) ) ? $blocklane_style['spacing'] : array();

// The gap between the controls.
$blocklane_gap_raw = array_key_exists( 'blockGap', $blocklane_spacing ) ? $blocklane_spacing['blockGap'] : '8px';
if ( is_array( $blocklane_gap_raw ) ) {
	$blocklane_gap_raw = $blocklane_gap_raw['left'] ?? ( $blocklane_gap_raw['top'] ?? '8px' );
}
$blocklane_gap = blocklane_pro_carousel_spacing_to_css( $blocklane_gap_raw );
if ( '' === $blocklane_gap ) {
	$blocklane_gap = '8px';
}

// The padding.
$blocklane_padding     = array();
$blocklane_padding_raw = $blocklane_spacing['padding'] ?? null;
if ( is_array( $blocklane_padding_raw ) ) {
	foreach ( array( 'top', 'right', 'bottom', 'left' ) as $blocklane_side ) {
		$blocklane_side_value = blocklane_pro_carousel_spacing_to_css( $blocklane_padding_raw[ $blocklane_side ] ?? '' );
		if ( '' !== $blocklane_side_value ) {
			$blocklane_padding[] = 'padding-' . $blocklane_side . ':' . $blocklane_side_value;
		}
	}
} elseif ( is_string( $blocklane_padding_raw ) ) {
	$blocklane_padding_value = blocklane_pro_carousel_spacing_to_css( $blocklane_padding_raw );
	if ( '' !== $blocklane_padding_value ) {
		$blocklane_padding[] = 'padding:' . $blocklane_padding_value;
	}
}

$blocklane_dot_color = blocklane_pro_carousel_sanitize_color( $attributes['dotColor'] ?? '' );
$blocklane_bar_size  = $blocklane_read_int( $attributes['barSize'] ?? null, 2, 24, 4 ) . 'px';

$blocklane_declarations = array( '--blocklane-carousel-nav-gap:' . $blocklane_gap );
$blocklane_add          = static function ( array $list, string $property, string $value ): array {
	if ( '' !== $value ) {
		$list[] = $property . ':' . $value;
	}
	return $list;
};

if ( 'autoplay' === $blocklane_type ) {
	$blocklane_i18n = blocklane_pro_carousel_view_i18n();

	// The button's own declarations.
	$blocklane_button = array();
	$blocklane_text   = blocklane_pro_carousel_sanitize_color( $attributes['buttonTextColor'] ?? '' );
	if ( $blocklane_text ) {
		$blocklane_button[] = 'color:' . $blocklane_text;
	}
	$blocklane_fill = blocklane_pro_carousel_sanitize_color( $attributes['buttonBackgroundColor'] ?? '' );
	if ( $blocklane_fill ) {
		$blocklane_button[] = 'background-color:' . $blocklane_fill;
	}
	$blocklane_font_raw = $attributes['buttonFontSize'] ?? null;
	if ( is_numeric( $blocklane_font_raw ) && (float) $blocklane_font_raw > 0 ) {
		$blocklane_font = $blocklane_read_int( $blocklane_font_raw, 10, 40, 10 ) . 'px';
	} elseif ( is_string( $blocklane_font_raw ) ) {
		$blocklane_font = trim( $blocklane_font_raw );
	} else {
		$blocklane_font = '';
	}
	if ( '' !== $blocklane_font && ! blocklane_pro_carousel_is_css_length( $blocklane_font, 1 ) ) {
		$blocklane_font = '';
	}
	if ( $blocklane_font ) {
		$blocklane_button[] = 'font-size:' . $blocklane_font;
	}

	$blocklane_border = ( isset( $blocklane_style['border'] ) && is_array( $blocklane_style['border'] ) ) ? $blocklane_style['border'] : array();
	$blocklane_radius = $blocklane_border['radius'] ?? null;
	$blocklane_corner = static function ( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$prefix = 'var:preset|border-radius|';
		if ( str_starts_with( $value, $prefix ) ) {
			return 1 === preg_match( '/^[a-z0-9-]+$/', substr( $value, strlen( $prefix ) ) ) ? 'var(--wp--preset--border-radius--' . substr( $value, strlen( $prefix ) ) . ')' : '';
		}
		$value = trim( $value );
		return ( '' !== $value && blocklane_pro_carousel_is_css_length( $value, 4 ) ) ? $value : '';
	};
	if ( $blocklane_radius ) {
		if ( is_string( $blocklane_radius ) ) {
			$blocklane_button = $blocklane_add( $blocklane_button, 'border-radius', $blocklane_corner( $blocklane_radius ) );
		} elseif ( is_array( $blocklane_radius ) ) {
			$blocklane_corners = array(
				'topLeft'     => 'border-top-left-radius',
				'topRight'    => 'border-top-right-radius',
				'bottomLeft'  => 'border-bottom-left-radius',
				'bottomRight' => 'border-bottom-right-radius',
			);
			foreach ( $blocklane_radius as $blocklane_key => $blocklane_value ) {
				if ( isset( $blocklane_corners[ $blocklane_key ] ) ) {
					$blocklane_button = $blocklane_add( $blocklane_button, $blocklane_corners[ $blocklane_key ], $blocklane_corner( $blocklane_value ) );
				}
			}
		}
	}
	$blocklane_button = array_merge( $blocklane_button, $blocklane_padding );

	$blocklane_wrapper = get_block_wrapper_attributes(
		array(
			'class'                => 'blocklane-carousel-nav blocklane-carousel-nav--autoplay ' . $blocklane_justify_class,
			'style'                => implode( ';', $blocklane_declarations ),
			'data-wp-bind--hidden' => 'state.noAutoplay',
		)
	);

	$blocklane_button_style = array() === $blocklane_button ? '' : ' style="' . esc_attr( implode( ';', $blocklane_button ) ) . '"';
	$blocklane_context      = wp_interactivity_data_wp_context(
		array(
			'pause' => $blocklane_i18n['pause'],
			'play'  => $blocklane_i18n['play'],
		)
	);

	// The wrapper attributes and the context attribute come escaped from core.
	echo '<div ' . $blocklane_wrapper . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '<div class="wp-block-button" hidden data-wp-bind--hidden="state.toggleHidden">'
		. '<button type="button" class="wp-block-button__link wp-element-button blocklane-carousel-autoplay-toggle"' . $blocklane_button_style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. ' ' . $blocklane_context // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. ' data-wp-on--click="actions.toggleAutoplay" data-wp-text="state.toggleText">'
		. esc_html__( 'Pause autoplay', 'blocklane' )
		. '</button></div></div>';
	return;
}

if ( 'arrows' === $blocklane_type ) {
	$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-arrow-size', $blocklane_read_int( $attributes['arrowSize'] ?? null, 16, 96, 36 ) . 'px' );
	if ( ! empty( $block->context['blocklane/carouselCenterNav'] ) ) {
		$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-arrow-offset', $blocklane_read_int( $attributes['arrowOffset'] ?? null, -100, 100, 10 ) . 'px' );
	}
	$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-arrow-color', blocklane_pro_carousel_sanitize_color( $attributes['arrowColor'] ?? '' ) );
	$blocklane_declarations = array_merge( $blocklane_declarations, $blocklane_padding );

	$blocklane_icon   = ( isset( $attributes['arrowIcon'] ) && is_string( $attributes['arrowIcon'] ) ) ? $attributes['arrowIcon'] : 'arrow-circle';
	$blocklane_weight = $attributes['arrowIconWeight'] ?? 'regular';
	if ( ! in_array( $blocklane_weight, array( 'regular', 'bold', 'fill' ), true ) ) {
		$blocklane_weight = 'regular';
	}
	$blocklane_paste = ( isset( $attributes['customArrowSvg'] ) && is_string( $attributes['customArrowSvg'] ) ) ? $attributes['customArrowSvg'] : '';
	$blocklane_svg   = blocklane_pro_carousel_arrow_svg( $blocklane_icon, $blocklane_weight, $blocklane_paste );

	$blocklane_wrapper = get_block_wrapper_attributes(
		array(
			'class'      => 'blocklane-carousel-nav ' . $blocklane_justify_class,
			'style'      => implode( ';', $blocklane_declarations ),
			'role'       => 'group',
			'aria-label' => __( 'Slide controls', 'blocklane' ),
		)
	);

	// The wrapper attributes come escaped from core; the icon markup is the runtime's own, built from escaped or filtered parts.
	echo '<div ' . $blocklane_wrapper . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '<button type="button" class="blocklane-carousel-prev" aria-label="' . esc_attr__( 'Previous slide', 'blocklane' ) . '" data-wp-on--click="actions.prev" data-wp-bind--aria-disabled="state.prevOff">'
		. $blocklane_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '</button>'
		. '<button type="button" class="blocklane-carousel-next" aria-label="' . esc_attr__( 'Next slide', 'blocklane' ) . '" data-wp-on--click="actions.next" data-wp-bind--aria-disabled="state.nextOff">'
		. $blocklane_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '</button></div>';
	return;
}

$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-dot-color', $blocklane_dot_color );

if ( 'scrollbar' === $blocklane_type ) {
	$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-bar-height', $blocklane_bar_size );
	$blocklane_declarations = array_merge( $blocklane_declarations, $blocklane_padding );
	$blocklane_i18n         = blocklane_pro_carousel_view_i18n();

	$blocklane_wrapper = get_block_wrapper_attributes(
		array(
			'class'                      => 'blocklane-carousel-scrollbar ' . $blocklane_justify_class,
			'style'                      => implode( ';', $blocklane_declarations ),
			'data-wp-class--is-hidden'   => 'state.oneSnap',
			'data-wp-class--is-dragging' => 'context.scrubbing',
			'data-wp-watch---thumb'      => 'callbacks.thumb',
		)
	);

	// The wrapper attributes come escaped from core.
	echo '<div ' . $blocklane_wrapper . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		. '<div class="blocklane-carousel-scrollbar__track" data-wp-on--pointerdown="actions.scrubStart" data-wp-on--pointermove="actions.scrubMove" data-wp-on--pointerup="actions.scrubEnd" data-wp-on--pointercancel="actions.scrubEnd">'
		. '<div class="blocklane-carousel-scrollbar__thumb" role="slider" aria-orientation="horizontal" aria-label="' . esc_attr( $blocklane_i18n['scrollbar'] ) . '" aria-valuemin="1" tabindex="0" data-wp-bind--aria-valuemax="state.snapCount" data-wp-bind--aria-valuenow="state.snapNumber" data-wp-bind--aria-controls="state.rowId" data-wp-on--keydown="actions.thumbKey"></div>'
		. '</div></div>';
	return;
}

// Dots or bars: an empty row the store fills.
$blocklane_classes = array( 'blocklane-carousel-pagination' );
if ( 'bars' === $blocklane_type ) {
	$blocklane_fill_bars    = ! empty( $attributes['barFill'] );
	$blocklane_classes[]    = 'blocklane-carousel-pagination--bars';
	$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-bar-height', $blocklane_bar_size );
	if ( $blocklane_fill_bars ) {
		$blocklane_classes[] = 'blocklane-carousel-pagination--bars-fill';
	} else {
		$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-bar-width', $blocklane_read_int( $attributes['barWidth'] ?? null, 8, 120, 32 ) . 'px' );
	}
} else {
	$blocklane_declarations = $blocklane_add( $blocklane_declarations, '--blocklane-carousel-dot-size', $blocklane_read_int( $attributes['dotSize'] ?? null, 2, 24, 8 ) . 'px' );
}
$blocklane_classes[]    = $blocklane_justify_class;
$blocklane_declarations = array_merge( $blocklane_declarations, $blocklane_padding );

$blocklane_wrapper = get_block_wrapper_attributes(
	array(
		'class'                => implode( ' ', $blocklane_classes ),
		'style'                => implode( ';', $blocklane_declarations ),
		'role'                 => 'group',
		'aria-label'           => __( 'Slide navigation', 'blocklane' ),
		'data-wp-watch---dots' => 'callbacks.dots',
		'data-wp-on--click'    => 'actions.dot',
		'data-wp-on--keydown'  => 'actions.dotKey',
	)
);

// The wrapper attributes come escaped from core.
echo '<div ' . $blocklane_wrapper . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
