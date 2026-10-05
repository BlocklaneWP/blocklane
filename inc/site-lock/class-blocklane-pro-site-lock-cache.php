<?php
/**
 * Site Lock cache integration: keeps the gate and shared page caches from
 * fighting each other.
 *
 * Host-level page caches (Varnish on Cloudways, WP Engine's EverCache, nginx
 * FastCGI caches) key responses on the URL and ignore cookies they don't
 * recognize. Left alone, that breaks the gate in both directions: the stored
 * Coming Soon splash keeps being served after a visitor unlocks (the "stuck
 * until you clear the cache" symptom), and a page rendered for an unlocked
 * visitor can be stored and served to locked ones — a content leak. Two
 * defenses, plus the unlock cookie's wp-postpass_ name (see Site_Lock_Gate):
 *
 * - send_no_cache_headers(): the strongest do-not-store response headers and
 *   the DONOTCACHEPAGE constant, sent on EVERY front-end and REST response
 *   while the lock is gating — not just on the splash, because locked and
 *   unlocked visitors share one cache key. Where headers are honored, nothing
 *   is ever stored, so nothing stale can be served.
 * - purge_page_caches(): best-effort flush of the known host and plugin page
 *   caches, fired when the lock turns on/off or swaps splashes (and on
 *   deactivation), so entries stored under the OLD state can't outlive it.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Lock_Cache {

	/**
	 * Mark the current response as uncacheable at every layer we can reach:
	 * the DONOTCACHEPAGE constant for page-cache plugins, core's nocache
	 * headers, and the stronger no-store/private + X-Accel-Expires forms that
	 * shared proxies (Varnish, nginx) and stricter host caches require —
	 * nocache_headers() alone stops at no-cache/must-revalidate, which some
	 * host caches don't honor.
	 *
	 * @return void
	 */
	public static function send_no_cache_headers() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// Honored by WP Rocket, W3TC, WP Super Cache, LiteSpeed, SG
			// Optimizer, Breeze, Hummingbird, WP Fastest Cache, WP-Optimize.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page-cache convention those plugins read; a prefixed name would tell them nothing.
			define( 'DONOTCACHEPAGE', true );
		}
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
		header( 'X-Accel-Expires: 0', true );
	}

	/**
	 * Best-effort purge of every known host / plugin page cache. Each entry
	 * is guarded (do_action fires nothing when no one listens; direct entry
	 * points run only when callable), so unknown environments are a no-op.
	 * External caches only — no plugin or WordPress state is touched.
	 *
	 * @return void
	 */
	public static function purge_page_caches() {
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- other plugins' purge hooks, fired on purpose so THEIR listeners run; the names are theirs.
		do_action( 'litespeed_purge_all' );                // LiteSpeed Cache.
		do_action( 'breeze_clear_all_cache' );             // Breeze (Cloudways).
		do_action( 'cache_enabler_clear_complete_cache' ); // Cache Enabler.
		do_action( 'wphb_clear_page_cache' );              // Hummingbird.
		do_action( 'rt_nginx_helper_purge_all' );          // Nginx Helper.
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		// Direct purge entry points, kept as callable strings rather than bare
		// symbols so no third-party plugin needs to exist for this file to load
		// or pass static analysis.
		$purgers = array(
			'rocket_clean_domain',                         // WP Rocket.
			'w3tc_pgcache_flush',                          // W3 Total Cache.
			'wp_cache_clear_cache',                        // WP Super Cache.
			'wpfc_clear_all_cache',                        // WP Fastest Cache.
			'wpo_cache_flush',                             // WP-Optimize.
			'sg_cachepress_purge_cache',                   // SiteGround Optimizer.
			'pantheon_wp_clear_edge_all',                  // Pantheon.
			'spinupwp_purge_site',                         // SpinupWP.
			array( '\WpeCommon', 'purge_varnish_cache' ),  // WP Engine.
		);
		foreach ( $purgers as $purger ) {
			if ( is_callable( $purger ) ) {
				call_user_func( $purger );
			}
		}

		// Kinsta's must-use plugin exposes a purge object rather than a function.
		$kinsta = isset( $GLOBALS['kinsta_cache'] ) && is_object( $GLOBALS['kinsta_cache'] )
			? $GLOBALS['kinsta_cache']
			: null;
		if ( $kinsta && isset( $kinsta->kinsta_cache_purge )
			&& is_callable( array( $kinsta->kinsta_cache_purge, 'purge_complete_caches' ) ) ) {
			$kinsta->kinsta_cache_purge->purge_complete_caches();
		}
	}
}
