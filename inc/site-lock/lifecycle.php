<?php
/**
 * The `site-lock` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle(). Everything here
 * runs OUTSIDE the block-theme gate on purpose: a classic theme takes the lock
 * gate offline entirely, and the consequences of that — stale page caches, and
 * a site that believes it is private while serving publicly — are exactly what
 * these callbacks exist to handle.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purge the known page caches when the site lock is enabled — reading the
 * option DIRECTLY, because both callers run in states the module loader may
 * never have reached: deactivation and theme switches can happen on a classic
 * theme (or in safe mode), where blocklane_pro_run_plugin() returns before
 * Modules::boot() and a class_exists() probe would silently skip the purge.
 * The cache class resolves through the classmap whatever the module state —
 * Site_Lock_Cache references no other module class, so purging here loads
 * nothing a probe reads.
 *
 * @return void
 */
function blocklane_pro_site_lock_purge_if_enabled(): void {
	// after_switch_theme fires pre-init (from check_theme_switched on
	// after_setup_theme), where the do_action-based purgers in the fan-out
	// (LiteSpeed, Hummingbird, …) may not have registered listeners yet —
	// purging there is a silent no-op on exactly the hosts it targets. Defer
	// to late init in that case; the deactivation caller (and WP-CLI) runs
	// post-init and purges immediately.
	if ( ! did_action( 'init' ) ) {
		add_action( 'init', __FUNCTION__, 99 );
		return;
	}
	// Purge only when the lock is actually GATING. An inert passwordless
	// Coming Soon never changed cacheability, so flushing a correctly-cached
	// public site for it would be pure churn.
	if ( '' === blocklane_pro_site_lock_gating_mode() ) {
		return;
	}
	\blocklane_pro\Site_Lock_Cache::purge_page_caches();
}

/**
 * The site lock's mode when it is actually gating someone, '' when it isn't —
 * read from the option DIRECTLY, mirroring Site_Lock::gates(), because every
 * caller runs in the degraded path where that class was never loaded: a
 * deactivation, a theme switch, or the classic-theme early return in
 * blocklane_pro_run_plugin(). A class_exists() probe would answer "not
 * gating" there, which is precisely the wrong answer.
 *
 * Gating means enabled AND (maintenance mode OR some password) — a
 * passwordless Coming Soon lets everyone through untouched.
 *
 * @return string 'maintenance', 'coming-soon', or '' when not gating.
 */
function blocklane_pro_site_lock_gating_mode(): string {
	$lock = get_option( 'blocklane_pro_site_lock', array() );
	if ( ! is_array( $lock ) || empty( $lock['enabled'] ) ) {
		return '';
	}
	$has_password = ( isset( $lock['password_enc'] ) && is_string( $lock['password_enc'] ) && '' !== $lock['password_enc'] )
		|| ( isset( $lock['password_hash'] ) && is_string( $lock['password_hash'] ) && '' !== $lock['password_hash'] );
	if ( 'maintenance' === ( $lock['mode'] ?? '' ) ) {
		return 'maintenance';
	}
	return $has_password ? 'coming-soon' : '';
}

// A theme switch can take the whole lock gate offline (classic themes skip
// Modules::boot() entirely) and back online later, with page caches storing
// the wrong world in between — and the lock option never changes, so
// Site_Lock::save()'s transition purge can't see it. Purge on every switch
// while the lock is enabled, in both directions: leaving a block theme (a
// stored splash must not linger over the now-ungated site) and returning to
// one (public pages stored during the excursion must not serve to locked
// visitors). Registered unconditionally, outside the block-theme check, for
// exactly that reason.
add_action( 'after_switch_theme', 'blocklane_pro_site_lock_purge_if_enabled' );

/**
 * Purge the external page caches when THIS plugin deactivates, in case the
 * lock goes with it. Both editions carry site-lock and read the same row, so
 * a locked site stays locked when one edition deactivates and the other
 * takes over on the next request — then this purge is harmless churn. It
 * matters in the other case: no other edition present or active, the gate
 * gone, and a splash a host page cache stored lingering in front of the
 * now-public site. Best-effort and external-caches-only — no plugin state is
 * touched: deactivating is not uninstalling, and a reactivation must find the
 * site exactly as it was. (In the free copy of this file "this plugin" is the
 * free plugin; the sentence holds in both, #851.)
 *
 * Registered by the unit that owns the consequence, so an edition without
 * site-lock has no aggregator calling into code that is not there.
 *
 * @return void
 */
function blocklane_pro_site_lock_deactivate(): void {
	blocklane_pro_site_lock_purge_if_enabled();
}
register_deactivation_hook( BLOCKLANE_PRO_FILE, 'blocklane_pro_site_lock_deactivate' );

// An absent row read on every request costs a miss query. Seed an empty
// autoloaded one; the site-lock store normalizes an empty array to its
// defaults exactly like an absent row. The one seeder body is in
// inc/bootstrap.php (#850); this unit reads the row, so this unit seeds it.
blocklane_pro_seed_on_version_change( 'blocklane_pro_site_lock' );

/**
 * Say so when the classic theme has taken a LIVE Coming Soon / Maintenance
 * gate offline: the setting still reads "enabled" on the Site Privacy screen,
 * but nothing is enforcing it and the site is public.
 *
 * Rides the requires-a-block-theme notice because it is the same condition,
 * and it is the only surface that catches the doors the pre-switch dialog
 * cannot reach — WP-CLI, the network admin, the theme installer, a Customizer
 * "Activate & Publish". Not dismissible: it describes a live state, so it
 * belongs on screen exactly as long as that state lasts.
 *
 * @return void
 */
function blocklane_pro_admin_notice_site_lock_not_enforced(): void {
	$mode = blocklane_pro_site_lock_gating_mode();
	if ( '' === $mode ) {
		return;
	}

	$mode_label = 'maintenance' === $mode
		? __( 'Maintenance Mode', 'blocklane' )
		: __( 'Coming Soon', 'blocklane' );

	$message = sprintf(
		/* translators: 1: the site visibility mode, e.g. "Coming Soon". */
		__( 'Your site is publicly visible. %1$s is still switched on, but it needs a block theme to be enforced — activate one and it starts working again. Nothing was lost: your settings and password are still saved.', 'blocklane' ),
		'<strong>' . esc_html( $mode_label ) . '</strong>'
	);

	echo wp_kses_post( '<div class="notice notice-warning"><p>' . $message . '</p></div>' );
}


// The notice rides the same condition as the requires-a-block-theme notice but
// registers ITSELF, rather than being called from that notice's body: the
// generic notice belongs to the bootstrap and must not reach into a unit an
// edition may not carry.
add_action(
	is_multisite() ? 'network_admin_notices' : 'admin_notices',
	static function () {
		if ( ! wp_is_block_theme() ) {
			blocklane_pro_admin_notice_site_lock_not_enforced();
		}
	}
);
