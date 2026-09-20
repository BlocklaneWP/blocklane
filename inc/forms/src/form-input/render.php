<?php
/**
 * Render an input field: label + control, name/id wired for accessibility.
 * Hidden fields render bare; the consent checkbox renders input-then-label.
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

$blocklane_field_type = isset( $attributes['type'] ) && in_array( $attributes['type'], BLOCKLANE_PRO_FORMS_INPUT_TYPES, true )
	? $attributes['type']
	: 'text';
$blocklane_field_value = isset( $attributes['defaultValue'] ) ? (string) $attributes['defaultValue'] : '';

// Shared preamble (name/id/label/required + skip-serialized control style,
// which lands border/color on the control not the wrapper). The checkbox has
// no styleable text control, so its style targets the row wrapper instead.
$blocklane_field = blocklane_pro_forms_field_render_base(
	$attributes,
	$block,
	'is-type-' . $blocklane_field_type,
	'checkbox' === $blocklane_field_type
);

// Hidden fields: a bare input, no visible wrapper, never required.
if ( 'hidden' === $blocklane_field_type ) {
	// The rule rides on the input itself — there is no wrapper here, and
	// without it view.js could not see the rule at all while the server still
	// enforced it. view.js disables a control that carries its own rule.
	printf(
		'<input type="hidden" name="%s" value="%s"%s />',
		esc_attr( $blocklane_field['name'] ),
		esc_attr( $blocklane_field_value ),
		$blocklane_field['cond_attr'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr() in the base.
	);

	return;
}

// Consent checkbox: input first, label text after, one wrapping <label>.
if ( 'checkbox' === $blocklane_field_type ) {
	?>
	<div <?php echo $blocklane_field['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
		<label class="blocklane-form__checkbox-label" for="<?php echo esc_attr( $blocklane_field['id'] ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $blocklane_field['id'] ); ?>"
				name="<?php echo esc_attr( $blocklane_field['name'] ); ?>"
				value="1"
				<?php echo $blocklane_field['required'] ? 'required ' : ''; ?>
			/>
			<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_field['label'] ); ?></span>
			<?php echo $blocklane_field['required_mark']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
		</label>
	</div>
	<?php
	return;
}

// A hidden label folds into the control: with no authored placeholder the
// label text becomes the placeholder, so the field still reads at a glance.
//
// A LOCAL, never $attributes. That array is core's, injected into this
// template by the block renderer; writing back into it makes this file a
// second author of a value every other consumer reads, and it is what the
// prefix sniff is objecting to. Emitted before the loop below so the rendered
// attribute order is unchanged.
$blocklane_field_placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
if ( '' === $blocklane_field_placeholder && ! empty( $attributes['hideLabel'] ) ) {
	$blocklane_field_placeholder = wp_strip_all_tags( $blocklane_field['label'] );
}

$blocklane_field_extra = '';
if ( '' !== $blocklane_field_placeholder ) {
	$blocklane_field_extra .= sprintf( ' placeholder="%s"', esc_attr( $blocklane_field_placeholder ) );
}
foreach ( array(
	'autocomplete' => 'autocomplete',
	'min'          => 'min',
	'max'          => 'max',
	'step'         => 'step',
) as $blocklane_attr_key => $blocklane_html_attr ) {
	if ( isset( $attributes[ $blocklane_attr_key ] ) && '' !== $attributes[ $blocklane_attr_key ] ) {
		$blocklane_field_extra .= sprintf( ' %s="%s"', $blocklane_html_attr, esc_attr( (string) $attributes[ $blocklane_attr_key ] ) );
	}
}
if ( ! empty( $attributes['maxlength'] ) ) {
	$blocklane_field_extra .= sprintf( ' maxlength="%d"', absint( $attributes['maxlength'] ) );
}
?>
<div <?php echo $blocklane_field['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<label class="<?php echo esc_attr( $blocklane_field['label_class'] ); ?>" for="<?php echo esc_attr( $blocklane_field['id'] ); ?>">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_field['label'] ); ?></span>
		<?php echo $blocklane_field['required_mark']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
	</label>
	<input
		class="blocklane-form__control"
		type="<?php echo esc_attr( $blocklane_field_type ); ?>"
		id="<?php echo esc_attr( $blocklane_field['id'] ); ?>"
		name="<?php echo esc_attr( $blocklane_field['name'] ); ?>"
		value="<?php echo esc_attr( $blocklane_field_value ); ?>"
		<?php echo $blocklane_field['required'] ? 'required ' : ''; ?>
		<?php echo $blocklane_field_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute-escaped above. ?>
		<?php echo $blocklane_field['style_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute-escaped in the helper. ?>
	/>
</div>
