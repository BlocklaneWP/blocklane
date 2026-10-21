<?php
/**
 * The server render of the blocklane/slide block: one slide, a wrapper with
 * the slide class around whatever blocks the slide holds.
 *
 * @package blocklane_pro
 *
 * @var string               $content    Rendered inner blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_slide_wrapper = get_block_wrapper_attributes( array( 'class' => 'blocklane-carousel-slide' ) );

// The wrapper attributes come escaped from core; $content is the inner blocks' rendered output.
echo '<div ' . $blocklane_slide_wrapper . '>' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
