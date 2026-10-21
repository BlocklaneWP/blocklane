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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Header', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"backgroundColor":"base","textColor":"contrast","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-contrast-color has-base-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"blocklaneProResponsive":{"order":{"mobile":1}},"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"@mobile":{"layout":{"selfStretch":"fill"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":300} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"overlay":"navigation-overlay-stack-divided-search","blocklaneProResponsive":{"order":{"mobile":3}},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","justifyContent":"center"}} /-->

<!-- wp:group {"blocklaneProResponsive":{"order":{"mobile":2}},"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"@mobile":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"max-content"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:search {"label":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Search', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"showLabel":false,"placeholder":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Search the site', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"buttonText":<?php /* translators: do not use < or > in this string; it is also used as a form label. */ echo wp_json_encode( __( 'Search', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"buttonPosition":"button-only","buttonUseIcon":true,"metadata":{"blockVisibility":{"viewport":{"mobile":false}}},"style":{"border":{"radius":"var:preset|border-radius|full"}},"backgroundColor":"base","textColor":"contrast"} /-->

<!-- wp:buttons {"style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"max-content"}}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Let’s build', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Header: Centered menu, button stays on phones', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Your brand on the left, a centered menu, and search plus one call to action on the right. On phones the button stays in view beside the menu icon, and search moves into the menu.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'header' ),
	'blockTypes'    => array( 'core/template-part/header' ),
	'viewportWidth' => 1400,
	'requires'      => array(),
);
