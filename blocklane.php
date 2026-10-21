<?php
/**
 * Plugin Name:       Blocklane
 * Plugin URI:        https://blocklanewp.com/
 * Description:       Everything a WordPress site should have out of the box: SEO, a contact form, popups, a coming soon page, and security hardening. In one plugin.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            Blocklane
 * Author URI:        https://profiles.wordpress.org/blocklane/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blocklane
 * Domain Path:       /languages
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * COEXISTENCE GUARD — the first statement, before a single define().
 *
 * Blocklane ships as two plugins built from this one tree: blocklane-pro
 * (every unit) and blocklane (the wordpress.org build, a subset). BOTH STAY
 * ACTIVE when both are installed. That is deliberate: an active free plugin
 * is the site's working fallback if Pro is ever removed, with nothing to
 * reactivate by hand.
 *
 * Pro is a strict superset, so exactly ONE of them may register anything;
 * two copies of the same hooks would double every emitter on the page. The
 * lower-RANKED edition returns here, inert but still activated, having
 * declared nothing. Neither ever deactivates the other.
 *
 * Who runs is decided by CONTENT, never by a name or by load order. Each
 * rendered inc/edition.php carries its edition's rank (the manifest: pro 2,
 * free 1) and whether it is the top edition. A copy that finds the constants
 * already defined yields. A copy that is not top scans the ACTIVE lists for
 * a higher-ranked build — reading each candidate's own inc/edition.php as
 * bytes through inc/edition-identity.php — and yields if one is there. So a
 * Pro folder renamed to sort after blocklane/ still wins (the free copy that
 * loaded first finds it and steps aside), and a Pro folder removed over SFTP
 * with its row still active does not hold the free copy hostage (the row's
 * directory carries no edition file, so it is not a build). Identical bytes
 * in both editions; the generator refuses a declaration in this file, and
 * the one file this guard may require declares nothing at its top level.
 *
 * A copy that yields while NOT ranking below the winner — a second copy of
 * the same edition, or a higher edition whose own file the winner could not
 * read — is abnormal, and it says so with one constant and no hook: the
 * winner prints the notice (inc/bootstrap.php). The normal free-under-Pro
 * case defines nothing and registers nothing (Amendment 12a).
 */
$blocklane_pro_boot_me = is_file( __DIR__ . '/inc/edition.php' ) ? (array) require __DIR__ . '/inc/edition.php' : array();
if ( defined( 'BLOCKLANE_PRO_FILE' ) ) {
	// Another build already holds the constants. Normal when it outranks this
	// one; abnormal otherwise. A winner without BLOCKLANE_PRO_RANK is an older
	// build that cannot print the notice anyway — leave nothing behind.
	if ( defined( 'BLOCKLANE_PRO_RANK' ) && ! defined( 'BLOCKLANE_PRO_SHADOWED' )
		&& (int) ( $blocklane_pro_boot_me['rank'] ?? PHP_INT_MAX ) >= BLOCKLANE_PRO_RANK ) {
		define( 'BLOCKLANE_PRO_SHADOWED', __FILE__ );
	}
	unset( $blocklane_pro_boot_me );
	return;
}
if ( array() !== $blocklane_pro_boot_me && empty( $blocklane_pro_boot_me['top'] ) ) {
	require_once __DIR__ . '/inc/edition-identity.php';
	$blocklane_pro_boot_above = blocklane_pro_edition_outranking(
		(int) ( $blocklane_pro_boot_me['rank'] ?? 0 ),
		(string) ( $blocklane_pro_boot_me['text_domain'] ?? '' ),
		WP_PLUGIN_DIR,
		blocklane_pro_edition_candidate_dirs(
			array( basename( __DIR__ ) ),
			(array) get_option( 'active_plugins', array() ),
			is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array()
		)
	);
	if ( array() !== $blocklane_pro_boot_above ) {
		// An ACTIVE, PRESENT, READABLE higher-ranked build exists: it will
		// declare everything this file would. Stand down before the first
		// define(), silently.
		unset( $blocklane_pro_boot_me, $blocklane_pro_boot_above );
		return;
	}
	unset( $blocklane_pro_boot_above );
}
// A torn own edition file (array() above) proceeds and fatals loudly in
// Edition::data() rather than silently running as the other edition.

define( 'BLOCKLANE_PRO_FILE', __FILE__ );
define( 'BLOCKLANE_PRO_RANK', (int) ( $blocklane_pro_boot_me['rank'] ?? 0 ) );
unset( $blocklane_pro_boot_me );
define( 'BLOCKLANE_PRO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'BLOCKLANE_PRO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'BLOCKLANE_PRO_VERSION', '1.0.0' );
define( 'BLOCKLANE_PRO_BASENAME', plugin_basename( __FILE__ ) );


/*
 * Everything else — the autoloader, the boot phases, the notices — lives in
 * inc/bootstrap.php and is REQUIRED, not declared here.
 *
 * That is not tidying. PHP binds a top-level `function` when it compiles the
 * file, before the first statement runs, so a `return` in the guard above
 * cannot stop a declaration further down THIS file — the loser would still
 * redeclare every one of them and fatal. A `require_once` is a statement, so
 * the guard's `return` does stop it. Keep this file free of declarations;
 * bin/generate-edition.php refuses one that is not.
 */
require_once __DIR__ . '/inc/bootstrap.php';
