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
<!-- wp:group {"metadata":{"name":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Testimonials', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>},"align":"full","className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)"}} -->
<div class="wp-block-group alignfull is-style-section-1" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:icon {"icon":"core/quote","align":"center","style":{"dimensions":{"width":"2.5rem"}},"textColor":"accent-1"} /-->

<!-- wp:blocklane/carousel {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
<!-- wp:blocklane/carousel-track {"label":<?php /* translators: do not use < or > in this string. */ echo wp_json_encode( __( 'Customer quotes', 'blocklane' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); ?>,"slidesPerView":1,"speed":400,"loop":true,"autoplay":true,"autoplaySpeed":7000,"pauseOnMouseEnter":true} -->
<!-- wp:blocklane/slide -->
<!-- wp:quote {"textAlign":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-text-align-center has-small-font-size"><!-- wp:paragraph {"fontSize":"x-large"} -->
<p class="has-x-large-font-size"><?php esc_html_e( 'Launch day was actually fun. The site looks great on every phone we tried, and it loads in a blink.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","align":"center","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image aligncenter is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Priya Shah</strong><br>Founder, Little Lantern Candles', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:quote {"textAlign":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-text-align-center has-small-font-size"><!-- wp:paragraph {"fontSize":"x-large"} -->
<p class="has-x-large-font-size"><?php esc_html_e( 'We finally have a site we’re proud to share. Adding a new page takes minutes, not a support ticket.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","align":"center","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image aligncenter is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Marcus Lee</strong><br>Director, Northfield Youth Soccer', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:quote {"textAlign":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-text-align-center has-small-font-size"><!-- wp:paragraph {"fontSize":"x-large"} -->
<p class="has-x-large-font-size"><?php esc_html_e( 'Clear timeline, friendly check-ins and zero jargon. We would happily hire them again tomorrow.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","align":"center","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image aligncenter is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Elena Garcia</strong><br>Co-owner, Copper Kettle Coffee', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote -->
<!-- /wp:blocklane/slide -->

<!-- wp:blocklane/slide -->
<!-- wp:quote {"textAlign":"center","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"fontSize":"small"} -->
<blockquote class="wp-block-quote has-text-align-center has-small-font-size"><!-- wp:paragraph {"fontSize":"x-large"} -->
<p class="has-x-large-font-size"><?php esc_html_e( 'Bookings doubled in the first month. Customers tell us the new site is the easiest one in town to use.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"3rem","aspectRatio":"1","scale":"cover","linkDestination":"none","align":"center","style":{"border":{"radius":"var:preset|border-radius|full"}}} -->
<figure class="wp-block-image aligncenter is-resized has-custom-border"><img src="<?php echo esc_url( blocklane_pro_bundled_patterns_url() ); ?>/placeholder-600x600.webp" alt="<?php esc_attr_e( 'Customer photo', 'blocklane' ); ?>" style="border-radius:var(--wp--preset--border-radius--full);aspect-ratio:1;object-fit:cover;width:3rem;height:auto"/></figure>
<!-- /wp:image --><cite><?php echo wp_kses_post( __( '<strong>Daniel Brooks</strong><br>Marketing Lead, Harbor Lane Dental', 'blocklane' ) ); ?></cite></blockquote>
<!-- /wp:quote -->
<!-- /wp:blocklane/slide -->
<!-- /wp:blocklane/carousel-track -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:blocklane/carousel-controls {"navType":"dots","justifyContent":"center"} /-->

<!-- wp:blocklane/carousel-controls {"navType":"autoplay","justifyContent":"center","buttonTextColor":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dcontrast)","buttonBackgroundColor":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dbase)","buttonFontSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dfont-size\u002d\u002dsmall)"} /--></div>
<!-- /wp:group -->
<!-- /wp:blocklane/carousel --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Testimonials: One centered quote at a time', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'One large centered quote at a time that moves on by itself, with dots and a pause button, for letting a few strong reviews take the stage.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'testimonials' ),
	'keywords'      => array( 'Testimonials' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane/carousel', 'blocklane/carousel-controls', 'blocklane/carousel-track', 'blocklane/slide' ),
);
