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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Gallery', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-xx-large-font-size"><?php esc_html_e( 'Take a closer look', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color"><?php esc_html_e( 'Snapshots from launch week, from first sketch to the big reveal. Use the arrows to browse, and click any photo to see it up close.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel {"centerNav":true,"align":"wide"} -->
<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Photo gallery', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"loop":true,"tabletSlidesPerView":2,"mobileSlidesPerView":1.2,"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Your photo, first in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-960x1200.webp" alt="<?php esc_attr_e( 'Your photo, second in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Your photo, third in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Your photo, fourth in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-960x1200.webp" alt="<?php esc_attr_e( 'Your photo, fifth in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Your photo, sixth in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Your photo, seventh in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|medium"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-960x1200.webp" alt="<?php esc_attr_e( 'Your photo, eighth in the set', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--medium);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->

<!-- wp:blocklane/carousel-controls {"arrowIconWeight":"fill","arrowSize":44} /-->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Gallery: Square photos with side arrows and lightbox', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Square photos three at a time with arrows on the sides, and each photo opens larger with a click, for showing off a set of images.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'gallery' ),
	'keywords'      => array( 'Gallery' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
