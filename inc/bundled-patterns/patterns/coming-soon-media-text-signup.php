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
<!-- wp:group {"align":"full","style":{"dimensions":{"minHeight":"100vh"},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="min-height:100vh;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:media-text {"align":"wide","mediaType":"image","mediaWidth":45,"verticalAlignment":"center"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile is-vertically-aligned-center" style="grid-template-columns:45% auto"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-960x1200.webp" alt="<?php esc_attr_e( 'A sneak peek of what’s coming', 'blocklane' ); ?>"/></figure><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"},"@mobile":{"spacing":{"padding":{"top":"var:preset|spacing|50"}}}}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":300} /-->

<!-- wp:heading {"level":1,"className":"is-style-text-display"} -->
<h1 class="wp-block-heading is-style-text-display"><?php esc_html_e( 'Opening soon', 'blocklane' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-text-subtitle"} -->
<p class="is-style-text-subtitle"><?php esc_html_e( 'We’re building something we think you’ll love. Join the list for a heads-up on launch day, and maybe a little something extra.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:blocklane/form {"showRequiredNotice":false} -->
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
<!-- /wp:blocklane/form -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Have the password? Come on in.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dsmall)","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:blocklane-pro/site-password /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div></div>
<!-- /wp:media-text --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Media & Text: Launch signup beside a photo', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A tall photo beside your site name, a friendly “opening soon,” an email signup for launch day and the password form for early guests. Signups keep working while the site is locked.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'blocklane-site-lock' ),
	'keywords'      => array( 'Announcement', 'Newsletter' ),
	'templateTypes' => array( 'coming-soon' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane-pro/site-password', 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button' ),
);
