<?php
/**
 * The Form's SHELL: the real <form>, the required-fields notice, the
 * anti-spam fields, and the slot where the rendered inner blocks go. The
 * canvas renders the same structure (edit.js) — 1:1 is the contract.
 *
 * A container shell (Block_Suite::render_shell()): it never sees the inner
 * blocks, it prints the slot comment once where they belong, and the block
 * renderer splices them in. A closed form prints no slot: its message is the
 * whole render.
 *
 * @package blocklane_pro
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_form_id    = isset( $attributes['formId'] ) && '' !== $attributes['formId']
	? sanitize_title( $attributes['formId'] )
	: wp_unique_id( 'blf-' );
$blocklane_form_name  = isset( $attributes['formName'] ) ? (string) $attributes['formName'] : '';
$blocklane_form_style = blocklane_pro_forms_input_style_vars( isset( $attributes['inputStyles'] ) ? $attributes['inputStyles'] : array() );

$blocklane_form_wrapper_args = array(
	'class' => 'blocklane-form blf-' . $blocklane_form_id,
);
if ( '' !== $blocklane_form_style ) {
	$blocklane_form_wrapper_args['style'] = $blocklane_form_style;
}

$blocklane_form_inner = isset( $block->parsed_block['innerBlocks'] ) ? $block->parsed_block['innerBlocks'] : array();

// Multi-step (v3): step children flip the form into stepped mode — the view
// module reveals one at a time. Steps are UX ONLY; the server validates the
// full schema at submit regardless, so nothing here is a trust boundary.
$blocklane_form_steps      = array();
// A step is a step only in an edition that REGISTERS the block (rule 6):
// under free a Pro-authored stepped form renders flat — every field, no
// progress list, no stepped attribute — and the view module stays inert.
$blocklane_form_step_known = in_array( 'form-step', blocklane_pro_forms_known_blocks(), true );
foreach ( $blocklane_form_inner as $blocklane_form_child ) {
	if ( $blocklane_form_step_known && is_array( $blocklane_form_child ) && isset( $blocklane_form_child['blockName'] ) && 'blocklane/form-step' === $blocklane_form_child['blockName'] ) {
		$blocklane_form_steps[] = isset( $blocklane_form_child['attrs']['label'] ) ? trim( (string) $blocklane_form_child['attrs']['label'] ) : '';
	}
}
$blocklane_form_stepped = count( $blocklane_form_steps ) > 1;
$blocklane_show_progress = ! isset( $attributes['showProgress'] ) || false !== $attributes['showProgress'];

// Availability (v3): a closed form never puts its fields in the DOM — the
// closed message is the whole render. The submission handler re-enforces
// (a cached page may show a stale open form; the server gate is what
// counts), so this is presentation, not the security boundary.
$blocklane_form_availability = blocklane_pro_forms_availability(
	blocklane_pro_forms_attrs_with_defaults( 'blocklane/form', $attributes )
);
if ( ! $blocklane_form_availability['open'] ) {
	?>
	<div <?php echo get_block_wrapper_attributes( $blocklane_form_wrapper_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
		<p class="blocklane-form__closed" role="status"><?php echo esc_html( $blocklane_form_availability['message'] ); ?></p>
	</div>
	<?php
	return;
}
?>
<form <?php echo get_block_wrapper_attributes( $blocklane_form_wrapper_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>
	method="post"
	novalidate
	data-wp-interactive="blocklane-pro/forms"
	data-wp-init="callbacks.init"
	data-wp-on--submit="actions.submit"
	data-bl-endpoint="<?php echo esc_url( rest_url( 'blocklane/v1/forms/submissions' ) ); ?>"
	data-bl-arm-delay="<?php echo esc_attr( (string) blocklane_pro_forms_time_trap_delay() ); ?>"
	<?php if ( $blocklane_form_stepped ) : ?>
		data-bl-stepped="1"
		<?php // The live-region template, translated here because view.js has no i18n runtime. ?>
		<?php /* translators: 1: current step number, 2: total number of steps. Both placeholders are literal tokens replaced by script — keep each exactly once. */ ?>
		data-bl-step-status="<?php echo esc_attr__( 'Step %1$s of %2$s', 'blocklane' ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $attributes['requireLogin'] ) ) : ?>
		<?php // Same reason: view.js cannot translate, so the expired-nonce copy ships resolved. ?>
		data-bl-expired-message="<?php echo esc_attr__( 'Your session expired. Please reload the page and try again.', 'blocklane' ); ?>"
	<?php endif; ?>
	<?php if ( '' !== $blocklane_form_name ) : ?>
		aria-label="<?php echo esc_attr( $blocklane_form_name ); ?>"
	<?php endif; ?>
>
	<input type="hidden" name="_bl_form_id" value="<?php echo esc_attr( $blocklane_form_id ); ?>" />
	<?php if ( ! empty( $attributes['requireLogin'] ) ) : ?>
		<?php // Cookie auth on the REST route needs a nonce or WP treats the visitor as anonymous — without this, the server's login re-check would reject the very users the gate admits. Login-required pages are per-user, so the page-cache staleness concern that keeps nonces out of the public form doesn't apply here. ?>
		<input type="hidden" name="_bl_rest" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" />
	<?php endif; ?>
	<?php // Singular views only: on archives the queried object id is a TERM or USER id, which the provenance capture would misread as a post id (term/post id spaces overlap). ?>
	<input type="hidden" name="_bl_source" value="<?php echo esc_attr( (string) ( is_singular() ? get_queried_object_id() : 0 ) ); ?>" />
	<input type="hidden" name="_bl_time" value="" />
	<div class="blocklane-form__hp" aria-hidden="true">
		<label>
			<?php esc_html_e( 'Leave this field empty', 'blocklane' ); ?>
			<input type="text" name="<?php echo esc_attr( blocklane_pro_forms_honeypot_name() ); ?>" value="" tabindex="-1" autocomplete="new-password" />
		</label>
	</div>
	<?php if ( ( ! isset( $attributes['showRequiredNotice'] ) || false !== $attributes['showRequiredNotice'] ) && blocklane_pro_forms_has_required( $blocklane_form_inner ) ) : ?>
		<p class="blocklane-form__required-notice">
			<?php esc_html_e( 'Required fields are marked with an asterisk (*).', 'blocklane' ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $blocklane_form_stepped && $blocklane_show_progress ) : ?>
		<ol class="blocklane-form__progress" data-bl-progress aria-hidden="true">
			<?php foreach ( $blocklane_form_steps as $blocklane_step_i => $blocklane_step_label ) : ?>
				<li><?php echo esc_html( '' !== $blocklane_step_label ? $blocklane_step_label : sprintf( /* translators: %d: step number. */ __( 'Step %d', 'blocklane' ), $blocklane_step_i + 1 ) ); ?></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
	<!--blocklane:inner-blocks-->	<?php
	// Turnstile is the form's LAST child, always its own block-level row —
	// never a flex sibling of the button, where inline layouts (signup rows)
	// pulled it beside the fields. Rendering here keeps the script enqueue
	// conditional by construction: only pages that render a form load
	// Cloudflare's api.js (no wp_enqueue_scripts block-scanning).
	$blocklane_form_turnstile = blocklane_pro_forms_turnstile();
	if ( $blocklane_form_turnstile['active'] ) {
		// Cloudflare Turnstile is an opt-in, documented service (readme FAQ):
		// api.js is requested only on pages that render a form AND only when
		// the owner has enabled Turnstile with their own site key. Guideline 8
		// permits loading a documented service's script; the version is null
		// because Cloudflare versions the file at its own URL.
		// phpcs:disable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent, WordPress.WP.EnqueuedResourceParameters.MissingVersion -- documented opt-in service, see the readme FAQ.
		wp_enqueue_script(
			'blocklane-pro-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		// phpcs:enable PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent, WordPress.WP.EnqueuedResourceParameters.MissingVersion
		?>
		<div
			class="cf-turnstile blocklane-form__turnstile"
			data-sitekey="<?php echo esc_attr( $blocklane_form_turnstile['site_key'] ); ?>"
			data-theme="<?php echo esc_attr( $blocklane_form_turnstile['theme'] ); ?>"
			data-appearance="<?php echo esc_attr( $blocklane_form_turnstile['appearance'] ); ?>"
			data-size="<?php echo esc_attr( $blocklane_form_turnstile['size'] ); ?>"
			data-language="<?php echo esc_attr( $blocklane_form_turnstile['language'] ); ?>"
			data-action="<?php echo esc_attr( blocklane_pro_forms_turnstile_action( $blocklane_form_id ) ); ?>"
		></div>
	<?php } ?>
</form>
