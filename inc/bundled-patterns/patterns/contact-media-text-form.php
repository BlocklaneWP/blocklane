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
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:media-text {"align":"wide","mediaType":"image","mediaWidth":45,"imageFill":true} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile is-image-fill-element" style="grid-template-columns:45% auto"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-960x1200.webp" alt="<?php esc_attr_e( 'Your team or storefront photo', 'blocklane' ); ?>" style="object-position:50% 50%"/></figure><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50","padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-xx-large-font-size"><?php esc_html_e( 'Come say hello', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-text-subtitle"} -->
<p class="is-style-text-subtitle"><?php esc_html_e( 'Questions, ideas or a project that needs a home on the web? Drop us a note and a real person will write back within one business day.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:blocklane/form -->
<!-- wp:blocklane/form-input {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Name', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"name"} /-->

<!-- wp:blocklane/form-input {"type":"email","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Email', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"email","isReplyTo":true} /-->

<!-- wp:blocklane/form-textarea {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Message', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true} /-->

<!-- wp:blocklane/form-submit-button {"text":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Send message', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->

<!-- wp:blocklane/form-notification -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Thanks! Your message is on its way. We’ll be in touch soon.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->

<!-- wp:blocklane/form-notification {"type":"error"} -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Something went wrong and your message was not sent. Please check the highlighted fields and try again.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->
<!-- /wp:blocklane/form --></div>
<!-- /wp:group --></div></div>
<!-- /wp:media-text --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Media & Text: Photo beside a contact form', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A tall team or storefront photo beside a friendly hello and a short contact form. On phones the photo sits on top.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'contact' ),
	'keywords'      => array( 'Contact' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button', 'blocklane/form-textarea' ),
);
