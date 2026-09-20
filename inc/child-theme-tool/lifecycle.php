<?php
/**
 * The `child-theme` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle().
 *
 * WHY THIS IS NOT IN inc/child-theme/: that directory is the child-theme
 * TEMPLATE, copied file-for-file into every theme the generator produces
 * (Helper::generate_child_theme, and the dist gate floors it for exactly that
 * reason). A lifecycle file there would be copied into people's themes.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The generator's REST routes register only while the Advanced "Child Theme
// Generator" toggle is on — turning the tool off removes the filesystem-writing
// endpoints entirely, not just the menu item.
add_action(
	'rest_api_init',
	static function () {
		if ( blocklane_pro\Settings::child_theme_tool_on() ) {
			( new blocklane_pro\Child_Theme_Controller() )->register_routes();
		}
	}
);
