<?php
/**
 * Render one form step: a plain section the view module reveals one at a
 * time (front) with its own Back/Next controls. Steps are UX ONLY — the
 * server validates the FULL schema at submit exactly as for a flat form,
 * so nothing about stepping is trusted.
 *
 * @package blocklane_pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_step_label = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'blocklane-form__step' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>
	data-bl-step="<?php echo esc_attr( $blocklane_step_label ); ?>"
>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered inner blocks. ?>
	<div class="blocklane-form__step-nav">
		<button type="button" class="blocklane-form__step-back wp-block-button__link wp-element-button" data-bl-step-back hidden>
			<?php esc_html_e( 'Back', 'blocklane' ); ?>
		</button>
		<button type="button" class="blocklane-form__step-next wp-block-button__link wp-element-button" data-bl-step-next hidden>
			<?php esc_html_e( 'Next', 'blocklane' ); ?>
		</button>
	</div>
</div>
