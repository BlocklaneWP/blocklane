<?php
/**
 * The plugin's one door to the PHP error log: two functions, one file.
 *
 * A production site's log carries a line from this plugin only when that
 * line may be the only trace of a real problem anyone ever sees. So there
 * are two levels, each a function, and the caller picks by that criterion:
 *
 *   blocklane_pro_log()          — debug. Written only while WP_DEBUG is
 *                                  true, when a site owner (or a reviewer)
 *                                  has asked to see what plugins have to say.
 *                                  The default for every line.
 *   blocklane_pro_log_failure()  — written whatever WP_DEBUG says, ONCE
 *                                  per distinct message per day. Only for a
 *                                  failure the log may be the only place
 *                                  anyone sees: the sibling installer that
 *                                  could not install the free edition or
 *                                  found its slug held by someone else
 *                                  (#817), what uninstall left on disk and
 *                                  that it deliberately swept nothing
 *                                  (#818), a skipped uninstall fragment, an
 *                                  extension runtime missing from a torn
 *                                  deploy, a module that cannot load
 *                                  (Module_Miss, which also hooks a wp-admin
 *                                  notice that a site nobody logs in to
 *                                  never shows). The last two run on every
 *                                  request, a visitor's included, so the
 *                                  door throttles ITSELF rather than trust
 *                                  each caller to: a transient keyed on the
 *                                  message's hash, written before the line,
 *                                  so a torn deploy on a busy site writes
 *                                  one line a day per missing file, not one
 *                                  per page view (#1835). Uninstall is the
 *                                  exception: it runs once, from an admin's
 *                                  delete, and must leave no row behind
 *                                  (it sweeps this transient family), so
 *                                  under WP_UNINSTALL_PLUGIN every line is
 *                                  written and nothing is stored. The
 *                                  callers are pinned in both directions by
 *                                  the uninstall battery's U20d: a new
 *                                  failure-level line fails there by name.
 *
 * Every other file routes through here; PHPStan's blocklane.chokepointCall
 * rule fails an error_log() call anywhere else in the shipped plugin, and
 * fails the whole run if this file is missing (tools/phpstan-rules/
 * rules.neon, `required: true`).
 *
 * A LENT FILE. Required by inc/bootstrap.php before anything else, and by
 * uninstall.php (where the main file never runs) behind a function_exists
 * probe on the NEWEST function here: deleting both editions at once runs
 * both uninstall.php files in one process, so the second may reach the
 * first one's copy. Each function is declared conditionally, in file order
 * oldest to newest, and its signature is pinned in the uninstall battery
 * (U16); a changed contract is a new name.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_log' ) ) {

	/**
	 * Write one line to the PHP error log, only while WP_DEBUG is on.
	 *
	 * @param string $message The line, prefixed by the caller ("Blocklane: …").
	 */
	function blocklane_pro_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one door: debug-only by construction.
		}
	}
}

if ( ! function_exists( 'blocklane_pro_log_failure' ) ) {

	/**
	 * Write one line to the PHP error log whatever WP_DEBUG says, at most
	 * once per distinct message per day (see the file header for what
	 * qualifies, and why the throttle lives here and not at the callers).
	 *
	 * The transient is written BEFORE the line, as the sibling installer's
	 * throttle is: a request that dies after logging must not log again on
	 * the next one. Under WP_UNINSTALL_PLUGIN nothing is read or stored —
	 * uninstall runs once and sweeps the `blocklane_pro_log_` family, so a
	 * row written there would outlive the plugin.
	 *
	 * @param string $message The line, prefixed by the caller ("Blocklane: …").
	 */
	function blocklane_pro_log_failure( string $message ): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			$throttle = 'blocklane_pro_log_' . md5( $message );
			if ( false !== get_transient( $throttle ) ) {
				return;
			}
			set_transient( $throttle, 1, DAY_IN_SECONDS );
		}
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one door: a failure with no other trace.
	}
}
