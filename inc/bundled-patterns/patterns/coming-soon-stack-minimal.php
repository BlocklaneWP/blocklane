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
<h1 class="wp-block-heading has-text-align-center is-style-text-display"><?php esc_html_e( 'Something fun is on the way', 'blocklane' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-text-subtitle","style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center is-style-text-subtitle"><?php esc_html_e( 'We’re putting the finishing touches on our new site. Have the password? Pop it in below for an early look.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002ddimension\u002d\u002dsmall)"}} -->
<div class="wp-block-group"><!-- wp:blocklane-pro/site-password /--></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="mailto:hello@example.com"><?php esc_html_e( 'Email us', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Stack: Minimal launch note with password', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A calm, centered note for your Coming soon page: your site name, a friendly headline, the password form for anyone with early access, and an email button. Nothing to distract from the big reveal.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'blocklane-site-lock' ),
	'keywords'      => array( 'Announcement' ),
	'templateTypes' => array( 'coming-soon' ),
	'viewportWidth' => 1400,
	'requires'      => array( 'blocklane-pro/site-password' ),
);
