<?php
/**
 * The server render of the blocklane/carousel block: the carousel's outer
 * wrapper and the store's interactive root.
 *
 * Prints the shell around its inner blocks and marks every controls block it
 * owns (any controls block inside it that is not inside a nested carousel or
 * a slide copy) with the store's live class and the shell's layout marks.
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

$blocklane_center = ! empty( $attributes['centerNav'] );
$blocklane_full   = isset( $attributes['align'] ) && 'full' === $attributes['align'];

// The direction of the shell's own track: its stored setting, else the site's.
$blocklane_rtl   = null;
$blocklane_inner = ( isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] ) ) ? $block->parsed_block['innerBlocks'] : array();
foreach ( $blocklane_inner as $blocklane_inner_block ) {
	if ( is_array( $blocklane_inner_block ) && 'blocklane/carousel-track' === ( $blocklane_inner_block['blockName'] ?? null ) ) {
		$blocklane_track_attrs = ( isset( $blocklane_inner_block['attrs'] ) && is_array( $blocklane_inner_block['attrs'] ) ) ? $blocklane_inner_block['attrs'] : array();
		if ( array_key_exists( 'rtl', $blocklane_track_attrs ) && null !== $blocklane_track_attrs['rtl'] ) {
			$blocklane_rtl = ! empty( $blocklane_track_attrs['rtl'] );
		}
		break;
	}
}
if ( null === $blocklane_rtl ) {
	$blocklane_rtl = (bool) is_rtl();
}

// Mark the controls blocks this shell owns.
$blocklane_marked     = $content;
$blocklane_pagination = false;
$blocklane_processor  = WP_HTML_Processor::create_fragment( $content );
if ( $blocklane_processor instanceof WP_HTML_Processor ) {
	$blocklane_skip = null;
	while ( $blocklane_processor->next_token() ) {
		/*
		 * A skipped element's descendants, and the closers of its children,
		 * sit at its own depth or deeper; its own closer is the first token
		 * back above it.
		 */
		$blocklane_depth = $blocklane_processor->get_current_depth();
		if ( null !== $blocklane_skip ) {
			if ( $blocklane_depth >= $blocklane_skip ) {
				continue;
			}
			$blocklane_skip = null;
		}
		if ( '#tag' !== $blocklane_processor->get_token_type() || $blocklane_processor->is_tag_closer() ) {
			continue;
		}
		if (
			true === $blocklane_processor->has_class( 'wp-block-blocklane-carousel' )
			|| true === $blocklane_processor->has_class( 'blocklane-carousel-clone' )
		) {
			if ( $blocklane_processor->expects_closer() ) {
				$blocklane_skip = $blocklane_depth;
			}
			continue;
		}
		if ( true !== $blocklane_processor->has_class( 'wp-block-blocklane-carousel-controls' ) ) {
			continue;
		}
		if ( true === $blocklane_processor->has_class( 'blocklane-carousel-pagination' ) ) {
			$blocklane_pagination = true;
		}
		$blocklane_processor->set_attribute( 'data-wp-class--blocklane-carousel-live', 'context.live' );
		if ( $blocklane_center ) {
			$blocklane_processor->add_class( 'blocklane-carousel-controls--centered' );
		}
		if ( $blocklane_full ) {
			$blocklane_processor->add_class( 'blocklane-carousel-controls--full' );
		}
		if ( $blocklane_rtl ) {
			$blocklane_processor->add_class( 'blocklane-carousel-controls--rtl' );
		}
	}
	if ( null === $blocklane_processor->get_last_error() ) {
		$blocklane_marked = $blocklane_processor->get_updated_html();
	}
}
unset( $blocklane_processor );

$blocklane_classes = array();
if ( $blocklane_center ) {
	$blocklane_classes[] = 'blocklane-carousel-center-nav';
}
if ( $blocklane_pagination ) {
	$blocklane_classes[] = 'blocklane-carousel-has-pagination';
}

$blocklane_wrapper = get_block_wrapper_attributes(
	array(
		'class'                                  => implode( ' ', $blocklane_classes ),
		'data-wp-interactive'                    => 'blocklane/carousel',
		'data-wp-init---carousel'                => 'callbacks.start',
		'data-wp-class--blocklane-carousel-live' => 'context.live',
	)
);
$blocklane_context = wp_interactivity_data_wp_context(
	array(
		'live'      => false,
		'moving'    => false,
		'dragging'  => false,
		'scrubbing' => false,
		'snap'      => 0,
		'snaps'     => 1,
		'paused'    => false,
	)
);

// The wrapper and context attributes come escaped from core; the inner blocks are rendered block markup.
echo '<div ' . $blocklane_wrapper . ' ' . $blocklane_context . '>' . $blocklane_marked . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
