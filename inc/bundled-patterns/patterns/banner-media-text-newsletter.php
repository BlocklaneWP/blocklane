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
<div class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:media-text {"align":"wide","mediaPosition":"right","mediaType":"image","verticalAlignment":"center"} -->
<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center"><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"},"@mobile":{"spacing":{"padding":{"top":"var:preset|spacing|50"}}}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group"><!-- wp:heading {"className":"is-style-text-display"} -->
<h2 class="wp-block-heading is-style-text-display"><?php esc_html_e( 'Fresh layouts, every Friday', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-text-subtitle"} -->
<p class="is-style-text-subtitle"><?php esc_html_e( 'Join our friendly weekly email for new section ideas, quick WordPress tips and the odd shortcut that makes building sites feel like play.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-group"><!-- wp:blocklane/form {"showRequiredNotice":false} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:blocklane/form-input {"type":"email","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Email address', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"email","isReplyTo":true,"style":{"layout":{"selfStretch":"fill"},"border":{"radius":"var:preset|border-radius|full"},"typography":{"lineHeight":"1.3"}},"backgroundColor":"base","textColor":"contrast"} /-->

<!-- wp:blocklane/form-submit-button {"text":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Sign me up', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"style":{"border":{"radius":"var:preset|border-radius|full"}},"fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:blocklane/form-notification -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'You’re in! Keep an eye on your inbox for the next one.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->

<!-- wp:blocklane/form-notification {"type":"error"} -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'That didn’t go through. Check your email address and try again.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->
<!-- /wp:blocklane/form -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'One email a week. Unsubscribe anytime.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'A peek at what lands in your inbox', 'blocklane' ); ?>"/></figure></div>
<!-- /wp:media-text --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Media & Text: Newsletter signup', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A big headline and one friendly line beside your photo, with an email field and a sign-up button in one row. Made for launches and newsletters where the only ask is an email.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'banner' ),
	'keywords'      => array( 'Newsletter', 'Hero' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button' ),
);
