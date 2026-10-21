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
<!-- wp:group {"blocklaneProTransparentHeader":true,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"backgroundColor":"base","textColor":"contrast","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-contrast-color has-base-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":300,"blocklaneProTransparentLogo":"white"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"},"@mobile":{"spacing":{"blockGap":"var:preset|spacing|30"},"layout":{"selfStretch":"fixedNoShrink","flexSize":"max-content"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:navigation {"overlay":"navigation-overlay-cover-brand-contact","blocklaneProResponsive":{"order":{"mobile":2}},"blocklaneProTransparentTextColor":"var:preset|color|base","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","justifyContent":"right"}} /-->

<!-- wp:buttons {"blocklaneProResponsive":{"order":{"mobile":1}},"style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"max-content"}}} -->
<div class="wp-block-buttons"><!-- wp:button {"blocklaneProTransparentTextColor":"var:preset|color|base","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Let’s talk', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Row: Transparent over a cover banner', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'For pages that open with a cover: the header floats see-through over your banner, then turns solid as visitors scroll. Logo on the left, the menu and one outline button on the right.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'header' ),
	'blockTypes'    => array( 'core/template-part/header' ),
	'viewportWidth' => 1400,
	'requires'      => array(),
);
