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
<!-- wp:cover {"url":"<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp","dimRatio":90,"overlayColor":"accent-5","isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"vh","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|40"}},"textColor":"contrast","layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)"}} -->
<div class="wp-block-cover alignfull has-contrast-color has-text-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);min-height:100vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-accent-5-background-color has-background-dim-90 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:site-logo {"width":300,"align":"center"} /-->

<!-- wp:heading {"level":1,"className":"is-style-text-display","style":{"typography":{"textAlign":"center"}}} -->
<h1 class="wp-block-heading has-text-align-center is-style-text-display"><?php esc_html_e( 'Something fun is on the way', 'blocklane' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size"><?php esc_html_e( 'We’re putting the finishing touches on our new site. Leave your email and we’ll tell you the moment it’s live.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dsmall)"}} -->
<div class="wp-block-group"><!-- wp:blocklane/form {"showRequiredNotice":false} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:blocklane/form-input {"type":"email","label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Email address', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"required":true,"autocomplete":"email","isReplyTo":true,"style":{"layout":{"selfStretch":"fill"},"border":{"radius":"var:preset|border-radius|full"},"typography":{"lineHeight":"1.3"}},"backgroundColor":"base","textColor":"contrast"} /-->

<!-- wp:blocklane/form-submit-button {"text":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Notify me', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"style":{"border":{"radius":"var:preset|border-radius|full"}},"fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:blocklane/form-notification -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'You’re on the list! We’ll email you the moment we open the doors.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->

<!-- wp:blocklane/form-notification {"type":"error"} -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'That didn’t go through. Check your email address and try again.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:blocklane/form-notification -->
<!-- /wp:blocklane/form --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"},"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--50)"><?php esc_html_e( 'Have the password? Come on in.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dsmall)"}} -->
<div class="wp-block-group"><!-- wp:blocklane-pro/site-password /--></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
<?php
return array(
	'title'         => _x( 'Cover: Launch signup with password', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A full-screen photo under a soft, light tint with your site name, a big headline, an email signup for launch news and the password form for early guests. Signups keep working while the site is locked.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'blocklane-site-lock' ),
	'keywords'      => array( 'Announcement', 'Newsletter' ),
	'templateTypes' => array( 'coming-soon' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane-pro/site-password', 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button' ),
);
