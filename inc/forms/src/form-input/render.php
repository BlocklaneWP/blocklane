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
	if ( '' !== $blocklane_field['cond_json'] ) {
		printf(
			'<input type="hidden" name="%s" value="%s" data-bl-cond="%s" data-bl-name="%s" />',
			esc_attr( $blocklane_field['name'] ),
			esc_attr( $blocklane_field_value ),
			esc_attr( $blocklane_field['cond_json'] ),
			esc_attr( $blocklane_field['name'] )
		);
	} else {
		printf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( $blocklane_field['name'] ),
			esc_attr( $blocklane_field_value )
		);
	}

	return;
}

// Consent checkbox: input first, label text after, one wrapping <label>.
if ( 'checkbox' === $blocklane_field_type ) {
	?>
	<div <?php echo get_block_wrapper_attributes( $blocklane_field['wrapper_args'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
		<label class="blocklane-form__checkbox-label" for="<?php echo esc_attr( $blocklane_field['id'] ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $blocklane_field['id'] ); ?>"
				name="<?php echo esc_attr( $blocklane_field['name'] ); ?>"
				value="1"
				<?php echo $blocklane_field['required'] ? 'required ' : ''; ?>
			/>
			<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_field['label'] ); ?></span>
			<?php if ( $blocklane_field['required'] ) : ?><span class="blocklane-form__required" aria-hidden="true">*</span><?php endif; ?>
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
// second author of a value every other consumer reads.
$blocklane_field_placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
if ( '' === $blocklane_field_placeholder && ! empty( $attributes['hideLabel'] ) ) {
	$blocklane_field_placeholder = wp_strip_all_tags( $blocklane_field['label'] );
}

// The optional constraints, each printed at its own sink below with a
// literal attribute name and esc_attr() — never a name taken from a map.
$blocklane_field_autocomplete = isset( $attributes['autocomplete'] ) ? (string) $attributes['autocomplete'] : '';
$blocklane_field_min          = isset( $attributes['min'] ) ? (string) $attributes['min'] : '';
$blocklane_field_max          = isset( $attributes['max'] ) ? (string) $attributes['max'] : '';
$blocklane_field_step         = isset( $attributes['step'] ) ? (string) $attributes['step'] : '';
?>
<div <?php echo get_block_wrapper_attributes( $blocklane_field['wrapper_args'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<label class="<?php echo esc_attr( $blocklane_field['label_class'] ); ?>" for="<?php echo esc_attr( $blocklane_field['id'] ); ?>">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_field['label'] ); ?></span>
		<?php if ( $blocklane_field['required'] ) : ?><span class="blocklane-form__required" aria-hidden="true">*</span><?php endif; ?>
	</label>
	<input
		class="blocklane-form__control"
		type="<?php echo esc_attr( $blocklane_field_type ); ?>"
		id="<?php echo esc_attr( $blocklane_field['id'] ); ?>"
		name="<?php echo esc_attr( $blocklane_field['name'] ); ?>"
		value="<?php echo esc_attr( $blocklane_field_value ); ?>"
		<?php echo $blocklane_field['required'] ? 'required ' : ''; ?>
		<?php if ( '' !== $blocklane_field_placeholder ) : ?> placeholder="<?php echo esc_attr( $blocklane_field_placeholder ); ?>"<?php endif; ?>
<?php if ( '' !== $blocklane_field_autocomplete ) : ?> autocomplete="<?php echo esc_attr( $blocklane_field_autocomplete ); ?>"<?php endif; ?>
<?php if ( '' !== $blocklane_field_min ) : ?> min="<?php echo esc_attr( $blocklane_field_min ); ?>"<?php endif; ?>
<?php if ( '' !== $blocklane_field_max ) : ?> max="<?php echo esc_attr( $blocklane_field_max ); ?>"<?php endif; ?>
<?php if ( '' !== $blocklane_field_step ) : ?> step="<?php echo esc_attr( $blocklane_field_step ); ?>"<?php endif; ?>
<?php if ( ! empty( $attributes['maxlength'] ) ) : ?> maxlength="<?php echo absint( $attributes['maxlength'] ); ?>"<?php endif; ?>
		<?php if ( '' !== $blocklane_field['control_css'] ) : ?> style="<?php echo esc_attr( $blocklane_field['control_css'] ); ?>"<?php endif; ?>
	/>
</div>
