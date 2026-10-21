<?php
/**
 * Rest_Registrable — the contract every REST controller fulfills: the
 * manifest's `controller` entries, which Modules::register_rest()
 * instantiates and calls register_routes() on during rest_api_init, and the
 * four service controllers Settings::register_rest_routes() wires by hand
 * (license, scripts, child-theme, abilities). The manifest types its entries
 * as class-string<Rest_Registrable>, so PHPStan proves the wired class exists
 * and carries the method; the hand-wired four declare the same contract so
 * a controller that is neither is a name without a role (#505).
 *
 * No native return type on purpose — see Bootable's docblock; the same
 * exemption applies here.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Rest_Registrable {

	/**
	 * Register the controller's REST routes. Called on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes();
}
