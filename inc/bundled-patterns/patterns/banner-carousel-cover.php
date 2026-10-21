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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Hero', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","className":"is-style-section-5","style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|40"},"blockGap":"0"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-5" style="padding-top:0;padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:blocklane/carousel {"align":"full"} -->
<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Featured highlights', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"slidesPerView":1,"speed":400,"loop":true,"style":{"spacing":{"blockGap":"0"}}} -->
<!-- wp:blocklane/slide {"layout":{"type":"default"}} -->
<!-- wp:cover {"url":"<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp","dimRatio":80,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":70,"minHeightUnit":"vh","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);min-height:70vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-text-annotation"} -->
<p class="is-style-text-annotation"><?php esc_html_e( 'Built on WordPress', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-text-display"} -->
<h2 class="wp-block-heading is-style-text-display"><?php esc_html_e( 'Launch your site faster', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'Pick a layout, drop in your words and photos, and go live this week.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30","margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Get started', 'blocklane' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Browse layouts', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide {"layout":{"type":"default"}} -->
<!-- wp:cover {"url":"<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp","dimRatio":80,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":70,"minHeightUnit":"vh","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);min-height:70vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-text-annotation"} -->
<p class="is-style-text-annotation"><?php esc_html_e( 'New this season', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-text-display"} -->
<h2 class="wp-block-heading is-style-text-display"><?php esc_html_e( 'Book a free site review', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'Twenty minutes, one friendly call and a short list of fixes you can make today.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30","margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Book a review', 'blocklane' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'See how it works', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide {"layout":{"type":"default"}} -->
<!-- wp:cover {"url":"<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp","dimRatio":80,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":70,"minHeightUnit":"vh","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull has-base-color has-text-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);min-height:70vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-2100x900-blank.webp" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-text-annotation"} -->
<p class="is-style-text-annotation"><?php esc_html_e( 'For busy owners', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-text-display"} -->
<h2 class="wp-block-heading is-style-text-display"><?php esc_html_e( 'Update your site in minutes', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'Change prices, add photos and post news yourself, right from your phone.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30","margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'See the editor', 'blocklane' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Read the guide', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group alignwide"><!-- wp:blocklane/carousel-controls {"navType":"bars"} /-->

<!-- wp:blocklane/carousel-controls {"justifyContent":"right","arrowSize":40} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Hero: Full-width cover slides with bars', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Full-width cover slides with a big headline and two buttons, and progress bars and arrows on a dark strip below, for opening a page with several messages.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'banner' ),
	'keywords'      => array( 'Hero' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
