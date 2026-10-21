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
<!-- wp:group {"align":"full","className":"is-style-section-5","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-5" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:site-logo {"width":300} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php esc_html_e( 'New layouts and WordPress tips, once a week.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:blocklane/form {"showRequiredNotice":false} -->
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
<!-- /wp:blocklane/form --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"small"} -->
<h2 class="wp-block-heading has-small-font-size"><?php esc_html_e( 'Services', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Services', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Websites', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Online stores', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Landing pages', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Site care', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"small"} -->
<h2 class="wp-block-heading has-small-font-size"><?php esc_html_e( 'Work', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Work', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Case studies', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Client stories', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Before and after', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"small"} -->
<h2 class="wp-block-heading has-small-font-size"><?php esc_html_e( 'Studio', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Studio', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'About', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Team', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Careers', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"small"} -->
<h2 class="wp-block-heading has-small-font-size"><?php esc_html_e( 'Resources', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Resources', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Blog', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Guides', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Newsletter archive', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"small"} -->
<h2 class="wp-block-heading has-small-font-size"><?php esc_html_e( 'Help', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Help', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'FAQ', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Contact', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Start a project', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:separator {"align":"wide","className":"is-style-wide"} -->
<hr class="wp-block-separator alignwide has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Built with WordPress and plenty of good coffee.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"fontSize":"small","layout":{"type":"flex"},"ariaLabel":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Legal', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>} -->
<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Privacy', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->

<!-- wp:navigation-link {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Terms', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"url":"#"} /-->
<!-- /wp:navigation --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Columns: Site map with newsletter signup', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Your logo beside an email sign-up row, then five menu columns and a legal row on a dark background.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'footer' ),
	'keywords'      => array( 'Newsletter', 'Site-map' ),
	'blockTypes'    => array( 'core/template-part/footer' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/form', 'blocklane/form-input', 'blocklane/form-notification', 'blocklane/form-submit-button' ),
);
