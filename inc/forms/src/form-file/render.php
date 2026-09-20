<?php
/**
 * Render a file upload field: label + file input + constraints hint. The
 * effective accept/size/count constraints come from the SAME helper schema
 * derivation uses (blocklane_pro_forms_file_constraints) — render↔schema
 * agreement is the module's load-bearing invariant.
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

$blocklane_file             = blocklane_pro_forms_field_render_base( $attributes, $block, 'is-type-file' );
$blocklane_file_constraints = blocklane_pro_forms_file_constraints( $attributes );

$blocklane_file_hint = sprintf(
	/* translators: 1: extension list, 2: maximum file size (e.g. "8 MB"). */
	__( 'Accepted: %1$s · Max %2$s', 'blocklane' ),
	str_replace( array( '.', ',' ), array( '', ', ' ), $blocklane_file_constraints['accept'] ),
	size_format( $blocklane_file_constraints['max_size'] )
);
if ( $blocklane_file_constraints['multiple'] ) {
	$blocklane_file_hint .= ' · ' . sprintf(
		/* translators: %d: maximum number of files. */
		_n( 'Up to %d file', 'Up to %d files', $blocklane_file_constraints['max_files'], 'blocklane' ),
		$blocklane_file_constraints['max_files']
	);
}
?>
<div <?php echo $blocklane_file['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<label class="blocklane-form__label" for="<?php echo esc_attr( $blocklane_file['id'] ); ?>">
		<span class="blocklane-form__label-text"><?php echo wp_kses_post( $blocklane_file['label'] ); ?></span>
		<?php echo $blocklane_file['required_mark']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
	</label>
	<input
		class="blocklane-form__control is-file"
		type="file"
		id="<?php echo esc_attr( $blocklane_file['id'] ); ?>"
		name="<?php echo esc_attr( $blocklane_file['name'] ); ?><?php echo $blocklane_file_constraints['multiple'] ? '[]' : ''; ?>"
		<?php if ( '' !== $blocklane_file_constraints['accept'] ) : ?>
			accept="<?php echo esc_attr( $blocklane_file_constraints['accept'] ); ?>"
		<?php endif; ?>
		<?php echo $blocklane_file_constraints['multiple'] ? 'multiple ' : ''; ?>
		<?php echo $blocklane_file['required'] ? 'required ' : ''; ?>
		data-max-size="<?php echo esc_attr( (string) $blocklane_file_constraints['max_size'] ); ?>"
		data-max-files="<?php echo esc_attr( (string) $blocklane_file_constraints['max_files'] ); ?>"
		data-bl-error-size="<?php echo esc_attr( sprintf( /* translators: %s: maximum file size, e.g. "8 MB". */ __( 'Each file must be smaller than %s.', 'blocklane' ), size_format( $blocklane_file_constraints['max_size'] ) ) ); ?>"
		data-bl-error-count="<?php echo esc_attr( sprintf( /* translators: %d: maximum number of files. */ _n( 'Please attach at most %d file.', 'Please attach at most %d files.', $blocklane_file_constraints['max_files'], 'blocklane' ), $blocklane_file_constraints['max_files'] ) ); ?>"
		data-bl-error-empty="<?php echo esc_attr( __( 'An attached file is empty. Please choose a different file.', 'blocklane' ) ); ?>"
		<?php echo $blocklane_file['style_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute-escaped in the helper. ?>
	/>
	<p class="blocklane-form__hint"><?php echo esc_html( $blocklane_file_hint ); ?></p>
</div>
