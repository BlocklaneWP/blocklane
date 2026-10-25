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
<!-- wp:group {"align":"full","style":{"dimensions":{"minHeight":"100vh"},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group alignfull has-base-background-color has-background" style="min-height:100vh;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)"><!-- wp:site-logo {"width":300} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dlarge)"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"className":"is-style-text-display","style":{"typography":{"textAlign":"center"}}} -->
<h1 class="wp-block-heading has-text-align-center is-style-text-display"><?php esc_html_e( 'We’ll be right back', 'blocklane' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-text-subtitle","style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center is-style-text-subtitle"><?php esc_html_e( 'We’re giving the site a quick tune-up. Thanks for your patience, and see you in a few minutes.', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"accent-4","fontSize":"small"} -->
<p class="has-text-align-center has-accent-4-color has-text-color has-small-font-size"><?php esc_html_e( 'Bookmark this page and check back soon.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Stack: Centered maintenance notice', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A simple centered notice for your Maintenance page: your site name, a big “we’ll be right back” and one reassuring line, so visitors know the break is short.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'blocklane-site-lock' ),
	'keywords'      => array( 'Notice' ),
	'templateTypes' => array( 'maintenance' ),
	'viewportWidth' => 1400,
	'requires'      => array(),
);
