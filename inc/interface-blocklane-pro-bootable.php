<?php
/**
 * Bootable — the contract a module's boot class fulfills.
 *
 * Modules::boot() runs get_instance() on every enabled module's `boot`
 * entry. The manifest types those entries as class-string<Bootable>, so
 * PHPStan proves at CI time that the wired class exists and carries the
 * method — wiring that used to be plain strings the analysis could not see.
 *
 * No native return type on purpose: the implementations predate this
 * interface and declare none, and adding one here would fatal them. This is
 * the one surface deliberately exempt from the native-types standing rule.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Bootable {

	/**
	 * The singleton accessor Modules::boot() calls on module load.
	 *
	 * @return static
	 */
	public static function get_instance();
}
