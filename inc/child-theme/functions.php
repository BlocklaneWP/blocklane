<?php
/**
 * Generated child theme functions.
 *
 * This file is a TEMPLATE: the child-theme tool copies it verbatim into the
 * theme it generates, so the guard below protects both copies — the one
 * shipped inside the plugin and the one living in wp-content/themes. WordPress
 * has defined ABSPATH long before it loads any theme's functions.php, so it
 * costs the generated theme nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'blocklane_child_enqueue_styles' );

/**
 * Enqueue the child theme's stylesheet.
 *
 * The parent is a block theme — its styles load via theme.json / global
 * styles, so there is no parent style handle to depend on. This enqueues the
 * child's own style.css for any custom CSS you add to it.
 *
 * @return void
 */
function blocklane_child_enqueue_styles(): void {
	wp_enqueue_style( 'blocklane-child-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}
