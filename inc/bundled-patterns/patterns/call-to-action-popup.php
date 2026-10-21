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
<!-- wp:group {"className":"is-style-card","style":{"shadow":"var:preset|shadow|x-large","spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50);box-shadow:var(--wp--preset--shadow--x-large)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-text-annotation","style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center is-style-text-annotation"><?php esc_html_e( 'Free starter kit', 'blocklane' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-xx-large-font-size"><?php esc_html_e( 'Build your next site in an afternoon', 'blocklane' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center"><?php esc_html_e( 'Grab ready-made sections and a launch-day checklist, then go from blank page to live site before your coffee gets cold.', 'blocklane' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-buttons"><!-- wp:button {"style":{"dimensions":{"width":"100%"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Get the free kit', 'blocklane' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline","style":{"dimensions":{"width":"100%"}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'See what’s inside', 'blocklane' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<?php
return array(
	'title'         => _x( 'Group: Popup offer card with stacked buttons', 'Pattern title', 'blocklane' ),
	'description'   => _x( 'A rounded offer card made to be a popup’s content: a short label, a bold headline, one line of copy and a full-width button above a quieter second choice. Set when and where it opens in the popup’s settings.', 'Pattern description', 'blocklane' ),
	'categories'    => array( 'call-to-action' ),
	'keywords'      => array( 'Offer', 'Call-to-action' ),
	'blockTypes'    => array( 'core/post-content' ),
	'postTypes'     => array( 'blocklane_popup' ),
	'viewportWidth' => 1400,
	'requires'      => array(),
);
