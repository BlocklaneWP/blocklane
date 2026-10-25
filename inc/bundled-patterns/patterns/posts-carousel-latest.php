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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Posts', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-1" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:blocklane/carousel {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"align":"wide"} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Fresh from the blog', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color"><?php esc_html_e( 'Tips, tutorials and news to help you build better WordPress sites in less time.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'See all posts →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph -->

<!-- wp:blocklane/carousel-controls {"arrowSize":40} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Latest posts', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"sourceType":"dynamic","query":{"postType":"post","perPage":6,"offset":0,"order":"desc","orderBy":"date","sticky":"","taxQuery":[],"author":"","search":"","excludeCurrent":true},"tabletSlidesPerView":2,"mobileSlidesPerView":1.1,"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"},"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","style":{"border":{"radius":"var:preset|border-radius|medium"}}} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:post-terms {"term":"category","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"var:preset|border-radius|full"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"backgroundColor":"accent-5","textColor":"contrast","fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"large"} /-->

<!-- wp:post-excerpt {"excerptLength":20,"style":{"layout":{"selfStretch":"fill","flexSize":null}}} /-->

<!-- wp:post-date {"datetime":"2026-10-17T23:32:09.081Z","textColor":"accent-4","fontSize":"small"} /--></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Posts: Latest posts as sliding cards', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Your newest blog posts as cards with an image, category, title and excerpt, sliding three at a time, with a link to see every post.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'posts' ),
	'keywords'      => array( 'Posts' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
