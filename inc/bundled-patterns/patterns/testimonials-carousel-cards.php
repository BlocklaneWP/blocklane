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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Testimonials', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-1" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:blocklane/carousel {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"align":"wide"} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size"><?php esc_html_e( 'Happy clients, in their own words', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color"><?php esc_html_e( 'Real notes from the small businesses we’ve helped launch, grow and refresh.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel-controls {"arrowSize":40} /--></div>
<!-- /wp:group -->

<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Client testimonials', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"tabletSlidesPerView":2,"mobileSlidesPerView":1.1,"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'Launch day was actually fun. The site looks great on every phone we tried, and it loads in a blink.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Priya Shah</strong><br>Founder, Little Lantern Candles', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'We finally have a site we’re proud to share. Adding a new page takes minutes, not a support ticket.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Marcus Lee</strong><br>Director, Northfield Youth Soccer', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'Clear timeline, friendly check-ins and zero jargon. We would happily hire them again tomorrow.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Elena Garcia</strong><br>Co-owner, Copper Kettle Coffee', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'Bookings doubled in the first month. Customers tell us the new site is the easiest one in town to use.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Daniel Brooks</strong><br>Marketing Lead, Harbor Lane Dental', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'They listened first and designed second. Every page sounds like us, only clearer.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Sofia Romano</strong><br>Owner, Romano Family Bakery', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:group {"className":"is-style-card","style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="min-height:100%"><!-- wp:quote {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-small-font-size"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php esc_html_e( 'Our volunteers update events themselves now. That alone saves us hours every single week.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Grace Okoye</strong><br>Coordinator, Riverside Food Share', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Testimonials: Quote cards sliding three at a time', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'Six short client quotes on cards that slide three at a time, with arrows beside the heading, for showing many happy customers in little space.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'testimonials' ),
	'keywords'      => array( 'Testimonials' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
