<?php
/**
 * The server render of the blocklane/carousel-track block.
 *
 * Prints the carousel's row (the viewport) with every behavior setting the
 * front-end store reads, the container inside it, and the slides: the inner
 * blocks as given, or one slide per post for a dynamic track. Each real slide
 * is named for assistive technology and bound to the store; a looping row
 * also gets inert copies of its end slides on both sides.
 *
 * @package blocklane_pro
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Rendered inner blocks.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Readers for the stored numbers.
 */
$blocklane_read_int = static function ( mixed $value, int $min, int $max, int $fallback ): int {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	return max( $min, min( $max, intval( $value ) ) );
};

$blocklane_read_tenths = static function ( mixed $value, ?float $fallback ): ?float {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	return max( 1.0, min( 10.0, round( (float) $value * 10 ) / 10 ) );
};

$blocklane_number_text = static function ( float $number ): string {
	return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
};

$blocklane_json_number = static function ( float $number ): int|float {
	return floor( $number ) === $number ? (int) $number : $number;
};

$blocklane_present = static function ( string $key ) use ( $attributes ): bool {
	return array_key_exists( $key, $attributes ) && null !== $attributes[ $key ];
};

/*
 * The settings.
 */
$blocklane_per_view      = (float) $blocklane_read_tenths( $attributes['slidesPerView'] ?? null, 3.0 );
$blocklane_per_group     = $blocklane_read_int( $attributes['slidesPerGroup'] ?? null, 1, 10, 1 );
$blocklane_speed         = $blocklane_read_int( $attributes['speed'] ?? null, 0, 1000, 300 );
$blocklane_delay         = $blocklane_read_int( $attributes['autoplaySpeed'] ?? null, 500, 10000, 3000 );
$blocklane_autoplay_kind = ( isset( $attributes['autoplayStyle'] ) && 'scroll' === $attributes['autoplayStyle'] ) ? 'scroll' : 'slide';
$blocklane_scroll_speed  = is_numeric( $attributes['scrollSpeed'] ?? null ) ? max( 0.25, min( 10.0, (float) $attributes['scrollSpeed'] ) ) : 2.0;

$blocklane_max_width = is_numeric( $attributes['slideMaxWidth'] ?? null ) ? intval( $attributes['slideMaxWidth'] ) : 0;
$blocklane_max_width = $blocklane_max_width > 0 ? max( 100, min( 1200, $blocklane_max_width ) ) : 0;
$blocklane_min_width = is_numeric( $attributes['slideMinWidth'] ?? null ) ? intval( $attributes['slideMinWidth'] ) : 0;
$blocklane_min_width = $blocklane_min_width > 0 ? max( 50, min( 800, $blocklane_min_width ) ) : 0;

$blocklane_loop        = ! empty( $attributes['loop'] );
$blocklane_autoplay    = ! empty( $attributes['autoplay'] );
$blocklane_hover_pause = ! empty( $attributes['pauseOnMouseEnter'] );
$blocklane_fade        = ! empty( $attributes['fade'] );
$blocklane_overflow    = ! empty( $attributes['overflowVisible'] );
$blocklane_fade_out    = ! empty( $attributes['fadeOut'] );
$blocklane_drag_cursor = ! empty( $attributes['dragCursor'] );
$blocklane_wheel       = ! empty( $attributes['wheelGestures'] );
$blocklane_edges       = ( isset( $attributes['fadeOutEdges'] ) && 'end' === $attributes['fadeOutEdges'] ) ? 'end' : 'both';
$blocklane_rtl         = $blocklane_present( 'rtl' ) ? ! empty( $attributes['rtl'] ) : (bool) is_rtl();

$blocklane_tablet_view  = $blocklane_present( 'tabletSlidesPerView' ) ? $blocklane_read_tenths( $attributes['tabletSlidesPerView'], null ) : null;
$blocklane_mobile_view  = $blocklane_present( 'mobileSlidesPerView' ) ? $blocklane_read_tenths( $attributes['mobileSlidesPerView'], null ) : null;
$blocklane_tablet_group = null;
if ( $blocklane_present( 'tabletSlidesPerGroup' ) && is_numeric( $attributes['tabletSlidesPerGroup'] ) ) {
	$blocklane_tablet_group = $blocklane_read_int( $attributes['tabletSlidesPerGroup'], 1, 10, 1 );
}
$blocklane_mobile_group = null;
if ( $blocklane_present( 'mobileSlidesPerGroup' ) && is_numeric( $attributes['mobileSlidesPerGroup'] ) ) {
	$blocklane_mobile_group = $blocklane_read_int( $attributes['mobileSlidesPerGroup'], 1, 10, 1 );
}

$blocklane_label = ( isset( $attributes['label'] ) && is_string( $attributes['label'] ) ) ? trim( $attributes['label'] ) : '';
if ( '' === $blocklane_label ) {
	$blocklane_label = __( 'Carousel', 'blocklane' );
}

// Fade shows one slide at a time, so it holds only at one slide per view everywhere.
$blocklane_fade_on = $blocklane_fade
	&& 1.0 === $blocklane_per_view
	&& ( null === $blocklane_tablet_view || 1.0 === $blocklane_tablet_view )
	&& ( null === $blocklane_mobile_view || 1.0 === $blocklane_mobile_view );

// The breakpoints the front end reads.
$blocklane_widths      = blocklane_pro_carousel_breakpoints();
$blocklane_breakpoints = array();
if ( null !== $blocklane_tablet_view || null !== $blocklane_tablet_group ) {
	$blocklane_breakpoints[] = array(
		'width'          => $blocklane_widths['tablet'],
		'slidesPerView'  => $blocklane_json_number( $blocklane_tablet_view ?? $blocklane_per_view ),
		'slidesPerGroup' => $blocklane_tablet_group ?? $blocklane_per_group,
	);
}
$blocklane_breakpoints[] = array(
	'width'          => $blocklane_widths['mobile'],
	'slidesPerView'  => $blocklane_json_number( $blocklane_mobile_view ?? 1.0 ),
	'slidesPerGroup' => $blocklane_mobile_group ?? 1,
);

// The gap between slides.
$blocklane_style   = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) ) ? $attributes['style'] : array();
$blocklane_spacing = ( isset( $blocklane_style['spacing'] ) && is_array( $blocklane_style['spacing'] ) ) ? $blocklane_style['spacing'] : array();
$blocklane_gap_raw = $blocklane_spacing['blockGap'] ?? null;
if ( is_array( $blocklane_gap_raw ) ) {
	$blocklane_gap_raw = $blocklane_gap_raw['left'] ?? ( $blocklane_gap_raw['top'] ?? null );
}
$blocklane_gap = blocklane_pro_carousel_spacing_to_css( $blocklane_gap_raw );

$blocklane_i18n = blocklane_pro_carousel_view_i18n();

/*
 * The slides.
 */
$blocklane_slides = '';
if ( isset( $attributes['sourceType'] ) && 'dynamic' === $attributes['sourceType'] ) {
	$blocklane_template = null;
	$blocklane_inner    = ( isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] ) ) ? $block->parsed_block['innerBlocks'] : array();
	foreach ( $blocklane_inner as $blocklane_inner_block ) {
		if ( is_array( $blocklane_inner_block ) && 'blocklane/slide' === ( $blocklane_inner_block['blockName'] ?? null ) ) {
			$blocklane_template = $blocklane_inner_block;
			break;
		}
	}

	$blocklane_depth = blocklane_pro_carousel_render_depth( 1 );
	try {
		if ( null === $blocklane_template ) {
			$blocklane_slides = '<!-- blocklane/carousel-track: dynamic mode with no blocklane/slide template; nothing to repeat -->';
		} elseif ( $blocklane_depth > 2 ) {
			$blocklane_slides = '<!-- blocklane/carousel-track: nesting depth exceeded; dynamic slides suppressed -->';
		} else {
			$blocklane_current_id = is_singular() ? (int) get_queried_object_id() : 0;
			$blocklane_args       = blocklane_pro_carousel_build_query_args( $attributes['query'] ?? array(), $blocklane_current_id );
			$blocklane_filtered   = apply_filters( 'blocklane_pro_carousel_query_args', $blocklane_args, $block );
			if ( is_array( $blocklane_filtered ) ) {
				$blocklane_args = $blocklane_filtered;
			}

			global $post;
			$blocklane_previous_post = $post;
			try {
				$blocklane_query = new WP_Query( $blocklane_args );
				while ( $blocklane_query->have_posts() ) {
					$blocklane_query->the_post();
					$blocklane_slide   = new WP_Block(
						$blocklane_template,
						array(
							'postId'   => get_the_ID(),
							'postType' => get_post_type(),
						)
					);
					$blocklane_slides .= $blocklane_slide->render();
				}
			} finally {
				if ( $blocklane_previous_post instanceof WP_Post ) {
					$post = $blocklane_previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restores the post that was current before the loop.
					setup_postdata( $blocklane_previous_post );
				} else {
					wp_reset_postdata();
				}
			}
		}
	} finally {
		blocklane_pro_carousel_render_depth( -1 );
	}
} else {
	$blocklane_slides = $content;
}

/*
 * The real slides: the top-level elements of the slides' markup that carry
 * the slide class. First their markup as the slide render gave it (the
 * copies start from it), then the same markup with each one named and bound.
 */
$blocklane_originals = array();
$blocklane_readable  = false;
$blocklane_reader    = WP_HTML_Processor::create_fragment( $blocklane_slides );
if ( $blocklane_reader instanceof WP_HTML_Processor ) {
	$blocklane_top     = $blocklane_reader->get_current_depth() + 1;
	$blocklane_capture = -1;
	while ( $blocklane_reader->next_token() ) {
		$blocklane_token_depth = $blocklane_reader->get_current_depth();
		$blocklane_token_type  = $blocklane_reader->get_token_type();
		$blocklane_is_closer   = '#tag' === $blocklane_token_type && $blocklane_reader->is_tag_closer();
		if ( $blocklane_capture >= 0 ) {
			$blocklane_originals[ $blocklane_capture ] .= $blocklane_reader->serialize_token();
			if ( $blocklane_is_closer && $blocklane_token_depth < $blocklane_top ) {
				$blocklane_capture = -1;
			}
			continue;
		}
		if (
			'#tag' === $blocklane_token_type
			&& ! $blocklane_is_closer
			&& $blocklane_token_depth === $blocklane_top
			&& true === $blocklane_reader->has_class( 'blocklane-carousel-slide' )
		) {
			$blocklane_index                         = count( $blocklane_originals );
			$blocklane_originals[ $blocklane_index ] = $blocklane_reader->serialize_token();
			if ( $blocklane_reader->expects_closer() ) {
				$blocklane_capture = $blocklane_index;
			}
		}
	}
	$blocklane_readable = null === $blocklane_reader->get_last_error();
}
unset( $blocklane_reader );

$blocklane_count = $blocklane_readable ? count( $blocklane_originals ) : 0;
$blocklane_real  = $blocklane_slides;
if ( $blocklane_count > 0 ) {
	$blocklane_marker = WP_HTML_Processor::create_fragment( $blocklane_slides );
	if ( $blocklane_marker instanceof WP_HTML_Processor ) {
		$blocklane_top    = $blocklane_marker->get_current_depth() + 1;
		$blocklane_number = 0;
		while ( $blocklane_marker->next_token() ) {
			if (
				'#tag' !== $blocklane_marker->get_token_type()
				|| $blocklane_marker->is_tag_closer()
				|| $blocklane_marker->get_current_depth() !== $blocklane_top
				|| true !== $blocklane_marker->has_class( 'blocklane-carousel-slide' )
			) {
				continue;
			}
			++$blocklane_number;
			try {
				$blocklane_slide_label = sprintf( $blocklane_i18n['slideLabel'], $blocklane_number, $blocklane_count );
			} catch ( ValueError | ArgumentCountError ) {
				$blocklane_slide_label = $blocklane_number . ' / ' . $blocklane_count;
			}
			$blocklane_marker->set_attribute( 'role', 'group' );
			$blocklane_marker->set_attribute( 'aria-roledescription', $blocklane_i18n['slideRole'] );
			$blocklane_marker->set_attribute( 'aria-label', $blocklane_slide_label );
			$blocklane_marker->set_attribute( 'data-wp-context', (string) wp_json_encode( array( 'slide' => $blocklane_number - 1 ) ) );
			$blocklane_marker->set_attribute( 'data-wp-bind--aria-hidden', 'state.slideHidden' );
			$blocklane_marker->set_attribute( 'data-wp-watch---inert', 'callbacks.slideInert' );
		}
		if ( null === $blocklane_marker->get_last_error() ) {
			$blocklane_real = $blocklane_marker->get_updated_html();
		}
	}
	unset( $blocklane_marker );
}

/*
 * The copies, for a row that loops.
 */
$blocklane_before = '';
$blocklane_after  = '';
if ( $blocklane_loop && ! $blocklane_fade_on && $blocklane_count >= 2 ) {
	$blocklane_widest = (int) max(
		ceil( $blocklane_per_view ),
		ceil( $blocklane_tablet_view ?? $blocklane_per_view ),
		ceil( $blocklane_mobile_view ?? 1.0 )
	);
	$blocklane_group  = max(
		$blocklane_per_group,
		$blocklane_tablet_group ?? $blocklane_per_group,
		$blocklane_mobile_group ?? 1
	);
	$blocklane_k      = min( $blocklane_count, $blocklane_widest + $blocklane_group - 1 );

	$blocklane_copy = static function ( string $markup, int $number ): string {
		$tags  = new WP_HTML_Tag_Processor( $markup );
		$first = true;
		while ( $tags->next_tag() ) {
			$data_names = $tags->get_attribute_names_with_prefix( 'data-wp-' );
			foreach ( (array) $data_names as $data_name ) {
				$tags->remove_attribute( $data_name );
			}

			$tag_name = (string) $tags->get_tag();
			$id       = $tags->get_attribute( 'id' );
			if ( $first ) {
				$tags->add_class( 'blocklane-carousel-clone' );
				$tags->set_attribute( 'inert', true );
				$tags->set_attribute( 'aria-hidden', 'true' );
				$tags->remove_attribute( 'id' );
			} elseif ( is_string( $id ) && '' !== $id ) {
				$tags->set_attribute( 'id', $id . '-copy-' . $number );
			}

			$href            = $tags->get_attribute( 'href' );
			$type            = $tags->get_attribute( 'type' );
			$controls        = $tags->get_attribute( 'controls' );
			$tabindex        = $tags->get_attribute( 'tabindex' );
			$contenteditable = $tags->get_attribute( 'contenteditable' );
			$focusable       = ( in_array( $tag_name, array( 'A', 'AREA' ), true ) && null !== $href )
				|| in_array( $tag_name, array( 'BUTTON', 'SELECT', 'TEXTAREA', 'IFRAME', 'SUMMARY' ), true )
				|| ( 'INPUT' === $tag_name && ! ( is_string( $type ) && 'hidden' === strtolower( trim( $type ) ) ) )
				|| ( in_array( $tag_name, array( 'AUDIO', 'VIDEO' ), true ) && null !== $controls )
				|| null !== $tabindex
				|| ( null !== $contenteditable && ! ( is_string( $contenteditable ) && 'false' === strtolower( trim( $contenteditable ) ) ) );
			if ( $focusable ) {
				$tags->set_attribute( 'tabindex', '-1' );
			}
			$first = false;
		}
		return $tags->get_updated_html();
	};

	$blocklane_number = 0;
	for ( $blocklane_i = $blocklane_count - $blocklane_k; $blocklane_i < $blocklane_count; $blocklane_i++ ) {
		++$blocklane_number;
		$blocklane_before .= $blocklane_copy( $blocklane_originals[ $blocklane_i ], $blocklane_number );
	}
	for ( $blocklane_i = 0; $blocklane_i < $blocklane_k; $blocklane_i++ ) {
		++$blocklane_number;
		$blocklane_after .= $blocklane_copy( $blocklane_originals[ $blocklane_i ], $blocklane_number );
	}
}

/*
 * The viewport.
 */
$blocklane_classes = array( 'blocklane-carousel-viewport' );
if ( $blocklane_overflow ) {
	$blocklane_classes[] = 'blocklane-carousel-overflow-visible';
}
if ( $blocklane_fade_out ) {
	$blocklane_classes[] = 'blocklane-carousel-fade-out';
	if ( 'end' === $blocklane_edges ) {
		$blocklane_classes[] = 'blocklane-carousel-fade-out--end';
	}
}
if ( $blocklane_drag_cursor ) {
	$blocklane_classes[] = 'blocklane-carousel-drag-cursor';
}
if ( $blocklane_rtl ) {
	$blocklane_classes[] = 'blocklane-carousel-rtl';
}

$blocklane_declarations = array( '--blocklane-carousel-per-view:' . $blocklane_number_text( $blocklane_per_view ) );
if ( '' !== $blocklane_gap ) {
	$blocklane_declarations[] = '--blocklane-carousel-gap:' . $blocklane_gap;
}
if ( $blocklane_max_width > 0 ) {
	$blocklane_declarations[] = '--blocklane-carousel-slide-max-width:' . $blocklane_max_width . 'px';
}
if ( $blocklane_min_width > 0 ) {
	$blocklane_declarations[] = '--blocklane-carousel-slide-min-width:' . $blocklane_min_width . 'px';
}

$blocklane_flag = static function ( bool $value ): string {
	return $value ? 'true' : 'false';
};

$blocklane_viewport = get_block_wrapper_attributes(
	array(
		'class'                                      => implode( ' ', $blocklane_classes ),
		'style'                                      => implode( ';', $blocklane_declarations ),
		'dir'                                        => $blocklane_rtl ? 'rtl' : 'ltr',
		'tabindex'                                   => '0',
		'role'                                       => 'group',
		'aria-roledescription'                       => _x( 'carousel', 'aria role description announced by screen readers', 'blocklane' ),
		'aria-label'                                 => $blocklane_label,
		'data-bl-carousel-per-view'                  => $blocklane_number_text( $blocklane_per_view ),
		'data-bl-carousel-min-slide-width'           => (string) $blocklane_min_width,
		'data-bl-carousel-per-group'                 => (string) $blocklane_per_group,
		'data-bl-carousel-speed'                     => (string) $blocklane_speed,
		'data-bl-carousel-loop'                      => $blocklane_flag( $blocklane_loop ),
		'data-bl-carousel-autoplay'                  => $blocklane_flag( $blocklane_autoplay ),
		'data-bl-carousel-pause-on-hover'            => $blocklane_flag( $blocklane_hover_pause ),
		'data-bl-carousel-wheel-gestures'            => $blocklane_flag( $blocklane_wheel ),
		'data-bl-carousel-fade'                      => $blocklane_flag( $blocklane_fade_on ),
		'data-bl-carousel-rtl'                       => $blocklane_flag( $blocklane_rtl ),
		'data-bl-carousel-autoplay-speed'            => (string) $blocklane_delay,
		'data-bl-carousel-autoplay-style'            => $blocklane_autoplay_kind,
		'data-bl-carousel-scroll-speed'              => $blocklane_number_text( $blocklane_scroll_speed ),
		'data-bl-carousel-breakpoints'               => (string) wp_json_encode( $blocklane_breakpoints ),
		'data-bl-carousel-i18n'                      => (string) wp_json_encode( $blocklane_i18n ),
		'data-wp-class--blocklane-carousel-live'     => 'context.live',
		'data-wp-class--blocklane-carousel-moving'   => 'context.moving',
		'data-wp-class--blocklane-carousel-dragging' => 'context.dragging',
		'data-wp-bind--tabindex'                     => 'state.rowTabindex',
		'data-wp-on--scroll'                         => 'actions.scrolled',
		'data-wp-on--scrollend'                      => 'actions.settled',
		'data-wp-on--pointerdown'                    => 'actions.dragStart',
		'data-wp-on--pointermove'                    => 'actions.dragMove',
		'data-wp-on--pointerup'                      => 'actions.dragEnd',
		'data-wp-on--pointercancel'                  => 'actions.dragEnd',
		'data-wp-on--wheel'                          => 'actions.wheel',
		'data-wp-on--mouseenter'                     => 'actions.hover',
		'data-wp-on--mouseleave'                     => 'actions.unhover',
		'data-wp-on--focusin'                        => 'actions.focusIn',
		'data-wp-on--focusout'                       => 'actions.focusOut',
	)
);

// The wrapper attributes come escaped from core; the slides are rendered block markup.
echo '<div ' . $blocklane_viewport . '><div class="blocklane-carousel-container">' . $blocklane_before . $blocklane_real . $blocklane_after . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
