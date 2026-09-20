<?php
/**
 * Render a dropdown field: label + select. Options are form-option inner
 * blocks; the options attribute is the legacy fallback for content saved
 * before the inner-block conversion. Both feed through
 * blocklane_pro_forms_select_options() — the SAME helper schema derivation
 * uses — so the rendered options and the validated options cannot diverge.
 *
 * @package blocklane_pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (unused — the option
 *                           children are read as data, not markup).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_sel       = blocklane_pro_forms_field_render_base( $attributes, $block, 'is-type-select' );
$blocklane_sel_empty = isset( $attributes['emptyLabel'] ) ? (string) $attributes['emptyLabel'] : '';

$blocklane_sel_option_attrs = array();
foreach ( $block->inner_blocks as $blocklane_sel_child ) {
	if ( 'blocklane/form-option' === $blocklane_sel_child->name ) {
		$blocklane_sel_option_attrs[] = $blocklane_sel_child->attributes;
	}
}
if ( ! $blocklane_sel_option_attrs && isset( $attributes['options'] ) && is_array( $attributes['options'] ) ) {
	$blocklane_sel_option_attrs = $attributes['options'];
}
$blocklane_sel_options = blocklane_pro_forms_select_options( $blocklane_sel_option_attrs );
?>
<div <?php echo $blocklane_sel['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<label class="<?php echo esc_attr( $blocklane_sel['label_class'] ); ?>" for="<?php echo esc_attr( $blocklane_sel['id'] ); ?>">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_sel['label'] ); ?></span>
		<?php echo $blocklane_sel['required_mark']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
	</label>
	<select
		class="blocklane-form__control"
		id="<?php echo esc_attr( $blocklane_sel['id'] ); ?>"
		name="<?php echo esc_attr( $blocklane_sel['name'] ); ?>"
		<?php echo $blocklane_sel['required'] ? 'required ' : ''; ?>
		<?php echo $blocklane_sel['style_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute-escaped in the helper. ?>
	>
		<option value=""><?php echo esc_html( '' !== $blocklane_sel_empty ? $blocklane_sel_empty : '—' ); ?></option>
		<?php foreach ( $blocklane_sel_options as $blocklane_sel_option ) : ?>
			<option value="<?php echo esc_attr( $blocklane_sel_option['value'] ); ?>"><?php echo esc_html( $blocklane_sel_option['label'] ); ?></option>
		<?php endforeach; ?>
	</select>
</div>
