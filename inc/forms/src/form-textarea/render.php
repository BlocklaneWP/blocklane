<?php
/**
 * Render a textarea field: label + control, name/id wired for accessibility.
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

$blocklane_ta       = blocklane_pro_forms_field_render_base( $attributes, $block, 'is-type-textarea' );
$blocklane_ta_rows  = isset( $attributes['rows'] ) ? max( 2, absint( $attributes['rows'] ) ) : 4;

// A hidden label folds into the control (same rule as form-input) — into a
// LOCAL, never back into core's injected $attributes.
$blocklane_ta_placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
if ( '' === $blocklane_ta_placeholder && ! empty( $attributes['hideLabel'] ) ) {
	$blocklane_ta_placeholder = wp_strip_all_tags( $blocklane_ta['label'] );
}
?>
<div <?php echo get_block_wrapper_attributes( $blocklane_ta['wrapper_args'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<label class="<?php echo esc_attr( $blocklane_ta['label_class'] ); ?>" for="<?php echo esc_attr( $blocklane_ta['id'] ); ?>">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_ta['label'] ); ?></span>
		<?php if ( $blocklane_ta['required'] ) : ?><span class="blocklane-form__required" aria-hidden="true">*</span><?php endif; ?>
	</label>
	<textarea
		class="blocklane-form__control"
		id="<?php echo esc_attr( $blocklane_ta['id'] ); ?>"
		name="<?php echo esc_attr( $blocklane_ta['name'] ); ?>"
		rows="<?php echo esc_attr( (string) $blocklane_ta_rows ); ?>"
		<?php echo $blocklane_ta['required'] ? 'required ' : ''; ?>
		<?php if ( '' !== $blocklane_ta_placeholder ) : ?>
			placeholder="<?php echo esc_attr( $blocklane_ta_placeholder ); ?>"
		<?php endif; ?>
		<?php if ( ! empty( $attributes['maxlength'] ) ) : ?>
			maxlength="<?php echo absint( $attributes['maxlength'] ); ?>"
		<?php endif; ?>
		<?php if ( '' !== $blocklane_ta['control_css'] ) : ?> style="<?php echo esc_attr( $blocklane_ta['control_css'] ); ?>"<?php endif; ?>
	><?php echo esc_textarea( isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '' ); ?></textarea>
</div>
