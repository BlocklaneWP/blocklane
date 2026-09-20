<?php
/**
 * Render the submit button — a real <button type="submit"> carrying the
 * theme's button element classes (site-password precedent).
 *
 * @package blocklane_pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (unused).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_btn_text = isset( $attributes['text'] ) && '' !== $attributes['text']
	? $attributes['text']
	: __( 'Submit', 'blocklane' );
$blocklane_btn_busy = isset( $attributes['busyText'] ) && '' !== $attributes['busyText']
	? $attributes['busyText']
	: __( 'Sending…', 'blocklane' );

// Width is the core dimensions support (style.dimensions.width, the same
// control core/button uses) — the style engine serializes it onto the
// wrapper, and the wrapper here IS the button element.
$blocklane_btn_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'blocklane-form__submit wp-block-button__link wp-element-button',
	)
);

// Turnstile renders at the FORM level (form/render.php), not here: as a
// sibling of the button it became a flex child in inline layouts (the
// signup-row patterns) and sat beside the fields instead of below them.
?>
<button
	<?php echo $blocklane_btn_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>
	type="submit"
	data-busy-text="<?php echo esc_attr( wp_strip_all_tags( $blocklane_btn_busy ) ); ?>"
><?php echo wp_kses_post( $blocklane_btn_text ); ?></button>
