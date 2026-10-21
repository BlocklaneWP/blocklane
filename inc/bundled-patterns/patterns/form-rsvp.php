<?php
/**
 * A bundled pattern (spec 2026-10-15-bundled-patterns), written by the theme
 * repo's capture door, tools/patterns/capture.mjs --target=plugin. Never edit
 * it by hand: bin/bundled-patterns-check.php refuses any other shape.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:blocklane/form -->
<!-- wp:blocklane/form-input {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Name', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"name"} /-->

<!-- wp:blocklane/form-input {"type":"email","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Email', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"email","isReplyTo":true} /-->

<!-- wp:blocklane/form-group {"legend":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Will you attend?', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true} -->
<!-- wp:blocklane/form-option {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Yes, I will be there', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->

<!-- wp:blocklane/form-option {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Sorry, I can’t make it', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->
<!-- /wp:blocklane/form-group -->

<!-- wp:blocklane/form-input {"type":"number","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Number of guests', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"min":"0","max":"10"} /-->

<!-- wp:blocklane/form-textarea {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Anything we should know?', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->

<!-- wp:blocklane/form-submit-button {"text":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Send RSVP', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->

<!-- wp:blocklane/form-notification -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Thanks! Your message has been sent.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->

<!-- wp:blocklane/form-notification {"type":"error"} -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Something went wrong and your message was not sent. Please check the highlighted fields and try again.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->
<!-- /wp:blocklane/form -->
<?php
return array(
	'title'         => _x( 'Form: RSVP', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Attendance, guest count, and a note.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'blocklane-forms' ),
	'blockTypes'    => array( 'blocklane/form' ),
	'viewportWidth' => 800,
	'requires'      => array( 'blocklane/form', 'blocklane/form-group', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-option', 'blocklane/form-submit-button', 'blocklane/form-textarea' ),
);
