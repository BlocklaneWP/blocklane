<?php
/**
 * Render one choice row. The group's type/name/required arrive via block
 * context; checkbox groups submit as `{name}[]`, radio groups as `{name}`.
 * A required radio group marks every radio required (native semantics: any
 * checked satisfies); required checkbox groups are enforced server-side only.
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

// Inside a dropdown the options are read as DATA by the select's render
// callback — WordPress still runs this file per child just to build the
// discarded $content. No form-group ancestor means no group context
// (fieldType always arrives from a group — the attribute has a default)
// and no valid row to draw: render nothing, which also keeps any future
// side effect added here from firing inside selects.
if ( ! isset( $block->context['blocklane/fieldType'] ) ) {
	return;
}

$blocklane_opt_type    = 'checkbox' === $block->context['blocklane/fieldType'] ? 'checkbox' : 'radio';
$blocklane_opt_form_id = isset( $block->context['blocklane/formId'] ) ? (string) $block->context['blocklane/formId'] : '';

$blocklane_opt_group_name = blocklane_pro_forms_field_name(
	array(
		'name'  => isset( $block->context['blocklane/fieldName'] ) ? $block->context['blocklane/fieldName'] : '',
		'label' => isset( $block->context['blocklane/fieldLegend'] ) ? $block->context['blocklane/fieldLegend'] : '',
	),
	'choices'
);

$blocklane_opt_label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
// The shared value rule (label fallback + sanitize_text_field parity) —
// schema derivation resolves through the same helper.
$blocklane_opt_value = blocklane_pro_forms_option_value( $attributes );

$blocklane_opt_name = 'checkbox' === $blocklane_opt_type
	? $blocklane_opt_group_name . '[]'
	: $blocklane_opt_group_name;

$blocklane_opt_required = 'radio' === $blocklane_opt_type
	&& ! empty( $block->context['blocklane/fieldRequired'] );
?>
<label <?php echo get_block_wrapper_attributes( array( 'class' => 'blocklane-form__option' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<input
		type="<?php echo esc_attr( $blocklane_opt_type ); ?>"
		name="<?php echo esc_attr( $blocklane_opt_name ); ?>"
		value="<?php echo esc_attr( $blocklane_opt_value ); ?>"
		<?php echo $blocklane_opt_required ? 'required ' : ''; ?>
	/>
	<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_opt_label ); ?></span>
</label>
