<?php
/**
 * Popups integration: the plugin-side half of the module.
 *
 * Everything the feature DOES lives in the self-contained runtime
 * (runtime.php + view.js + style.css), loaded in-process by the plugin. This
 * class carries only what belongs to the plugin lifecycle: the editor sidebar
 * bundle for the popup CPT.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Popups_Integration implements Bootable {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

}
