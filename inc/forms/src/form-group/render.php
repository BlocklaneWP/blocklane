<?php
/**
 * Render a choice group: fieldset + legend wrapping the option inner blocks.
 * The options read the group's type/name/required through block context.
 *
 * @package blocklane_pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (the options).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_grp_type     = isset( $attributes['type'] ) && 'checkbox' === $attributes['type'] ? 'checkbox' : 'radio';
$blocklane_grp_legend   = isset( $attributes['legend'] ) ? (string) $attributes['legend'] : '';
$blocklane_grp_required = ! empty( $attributes['required'] );

// Through the shared base builder, NOT a hand-rolled wrapper. It is the single
// place that emits data-bl-cond + data-bl-name, so building the fieldset's
// attributes here meant a conditional rule on a radio or checkbox group was
// enforced by the server and completely invisible to view.js: the group stayed
// on screen, the visitor answered it, and the server then resolved it hidden
// and discarded the answer — with a success response.
//
// The class string is unchanged: the base prefixes 'blocklane-form__field ' and
// appends ' is-required', so the fieldset classes ride in as the type class.
// The name must be resolved the way the SCHEMA resolves it, before the base
// sees it. A group's human name is its LEGEND and its fallback is 'choices',
// but the base reads name|label with fallback 'field' — so passing $attributes
// straight through stamped data-bl-name="field" on every legend-named group
// while the server keyed its hidden set by the legend-derived name. That is
// the same one-sided divergence this whole fix exists to remove, and it also
// made two unnamed groups collide on one bogus name.
$blocklane_grp_name    = blocklane_pro_forms_field_name(
	array(
		'name'  => isset( $attributes['name'] ) ? $attributes['name'] : '',
		'label' => isset( $attributes['legend'] ) ? $attributes['legend'] : '',
	),
	'choices'
);
$blocklane_grp_base    = blocklane_pro_forms_field_render_base(
	array_merge( $attributes, array( 'name' => $blocklane_grp_name ) ),
	$block,
	'blocklane-form__fieldset is-type-' . $blocklane_grp_type
);
$blocklane_grp_wrapper = $blocklane_grp_base['wrapper'];
?>
<fieldset <?php echo $blocklane_grp_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<legend class="blocklane-form__legend">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_grp_legend ); ?></span>
		<?php if ( $blocklane_grp_required ) : ?>
			<span class="blocklane-form__required" aria-hidden="true">*</span>
		<?php endif; ?>
	</legend>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered inner blocks. ?>
</fieldset>
