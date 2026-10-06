<?php
/**
 * A notification's SHELL: hidden until the submission runtime reveals it
 * (Phase 2), with the slot where the authored message goes
 * (Block_Suite::render_shell()). Success announces politely
 * (role="status"); errors assertively (role="alert").
 *
 * Hidden by the HTML `hidden` attribute, not only by the block stylesheet:
 * on a production site an optimizer that strips "unused" CSS, rewrites
 * combined block styles, or a page cache pointing at stale asset URLs left
 * the stylesheet unapplied — and both messages rendered visible for every
 * visitor, then got cached that way (#743). The runtime clears `hidden`
 * when it reveals a message (view.js setNotification) and restores it when
 * it hides one, so a MISSING stylesheet cannot expose a message: the UA's
 * own `[hidden]` rule hides it. What the attribute does not survive is an
 * author rule setting `display` on this element (author origin beats the
 * UA rule at any specificity) — the FORM block's stylesheet (this block
 * ships none of its own) is the only one that does, and it is the styled
 * half of the same toggle.
 *
 * @package blocklane_pro
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocklane_msg_type = isset( $attributes['type'] ) && 'error' === $attributes['type'] ? 'error' : 'success';
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'blocklane-form__notification is-' . $blocklane_msg_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>
	role="<?php echo 'error' === $blocklane_msg_type ? 'alert' : 'status'; ?>"
	data-bl-notification="<?php echo esc_attr( $blocklane_msg_type ); ?>"
	tabindex="-1"
	hidden
>
	<!--blocklane:inner-blocks--></div>
