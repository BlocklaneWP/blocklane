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
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"verticalAlignment":"center","width":"40%","className":"is-style-section-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"},"border":{"radius":"var:preset|border-radius|large"},"@mobile":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}}},"layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-column is-vertically-aligned-center is-style-section-2" style="border-radius:var(--wp--preset--border-radius--large);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);flex-basis:40%"><!-- wp:icon {"icon":"core/quote","style":{"dimensions":{"width":"2rem"}},"textColor":"accent-1"} /-->

<!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'We sent one short message on a Friday and had a plan for our new site by Monday. The whole process felt easy and, honestly, fun.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Maya Torres</strong><br>Owner, Little Fern Studio', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"60%","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size"><?php esc_html_e( 'Tell us about your project', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color"><?php esc_html_e( 'Share a few details and we’ll send back honest next steps. No pressure, no jargon.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:blocklane/form -->
<!-- wp:blocklane/form-input {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Name', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"name"} /-->

<!-- wp:blocklane/form-input {"type":"email","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Email', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"email","isReplyTo":true} /-->

<!-- wp:blocklane/form-textarea {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Project details', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true} /-->

<!-- wp:blocklane/form-submit-button {"text":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Send my details', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} /-->

<!-- wp:blocklane/form-notification -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Thanks! Your details are in. We’ll reply within one business day.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->

<!-- wp:blocklane/form-notification {"type":"error"} -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Something went wrong and your details were not sent. Please check the highlighted fields and try again.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->
<!-- /wp:blocklane/form --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Columns: Contact form with a customer quote', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A happy customer’s quote, photo and name in a tinted panel beside your contact form, for inquiries where a little reassurance helps.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'contact' ),
	'keywords'      => array( 'Contact', 'Testimonials' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button', 'blocklane/form-textarea' ),
);
