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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Portfolio', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-1" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:blocklane/carousel {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"align":"wide"} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:heading {"fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-xx-large-font-size"><?php esc_html_e( 'Work we’re proud of', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color"><?php esc_html_e( 'Every project starts with a friendly chat and ends with a site you can’t wait to share.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel-controls {"arrowSize":40} /--></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Recent projects', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"slidesPerView":2.3,"tabletSlidesPerView":1.6,"mobileSlidesPerView":1.15,"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
<!-- wp:blocklane/slide -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|large"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Preview of the Tidewater Bakery project', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--large);aspect-ratio:4/3;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Tidewater Bakery', 'blocklane' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Brand refresh, website and photography', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A sunny new look and a menu the whole team can update from the counter.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'View project →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|large"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Preview of the Northstar Coaching project', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--large);aspect-ratio:4/3;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Northstar Coaching', 'blocklane' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Landing page, copywriting and email signup', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'One focused page that turns curious visitors into booked discovery calls.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'View project →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|large"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Preview of the Wildroot Gardens project', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--large);aspect-ratio:4/3;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Wildroot Gardens', 'blocklane' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Blog design, search setup and training', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A seasonal journal the owners love writing in, with tips readers can find.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'View project →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|large"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Preview of the Harbor Lane Dental project', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--large);aspect-ratio:4/3;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Harbor Lane Dental', 'blocklane' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Website, online booking and local search', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'New patients book in two taps, and the front desk answers fewer calls.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'View project →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|large"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-1200x900.webp" alt="<?php esc_attr_e( 'Preview of the Riverside Food Share project', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--large);aspect-ratio:4/3;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Riverside Food Share', 'blocklane' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Volunteer portal and events calendar', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Volunteers sign up for shifts themselves, so the team can focus on families.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600"><a href="#"><?php esc_html_e( 'View project →', 'blocklane' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->

<!-- wp:blocklane/carousel-controls {"navType":"bars","justifyContent":"stretch","barFill":true} /-->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Portfolio: Large project shots with a peek', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Big project images with a short summary and a link, a peek of the next one at the edge and a progress bar below, for showcasing your best work.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'portfolio' ),
	'keywords'      => array( 'Portfolio', 'Case-studies' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
