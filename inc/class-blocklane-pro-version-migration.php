<?php
/**
 * Version-change signal: fires once per plugin version, on the first request
 * to notice the code on disk changed.
 *
 * Background/CLI updates and deploy pipelines replace the plugin without an
 * admin_init, so anything that must run once per version cannot hang off an
 * admin hook. This is a cheap init-time version stamp that catches those:
 * compare the stamp to the code, fire `blocklane_pro_version_changed`, stamp.
 *
 * Listeners live in each unit's lifecycle file (Modules::lifecycle()). Every
 * listener must be idempotent — the stamp advances even if a listener fails,
 * deliberately, so a site that cannot complete one never re-runs the whole
 * pass on every request forever.
 *
 * THE LISTENER RULE under two editions (Spec C). Both editions share one
 * stamp and the hook fires on EVERY edition switch (pro/0.12.0 → free/0.11.4
 * → pro/0.12.0), in whichever edition runs, with only that edition's units'
 * listeners registered. So a listener must be idempotent, edition-agnostic,
 * and unable to undo a newer edition's transform: a one-way migration is
 * guarded by its OWN marker (the row it creates, the file it deletes), never
 * by a version comparison. Audited 2026-09-19: every listener passes — the
 * seeds are add-if-null, Scripts::migrate_legacy_file() and the class-manager
 * rename delete their source, and the reaper, the stale-forks scan, the
 * share-image resync and the toggle seeding are re-runnable.
 *
 * THE STAMP RECORDS WHAT IT SKIPPED (#839). A unit whose lifecycle file did
 * not load (Modules::misses()['lifecycle']) never registered its listeners,
 * so the pass that fired ran without them. Stamping a bare version would
 * declare that pass complete; skipping the stamp would re-run the whole pass
 * — the reaper, the fork scan, the seeds — on every request while torn. So
 * the stored value is `pro/0.12.0#module:seo,module:site-lock`: head = the
 * stamp, tail = the units that were missing. While those units are still
 * missing the head comparison alone decides (no per-request cost); the
 * moment one of them is back — by reinstall, upload-overwrite, rsync, any
 * path — the pass re-runs whole and the suffix is recomputed. Any external
 * reader of the option splits on `#` (none exists today).
 *
 * This was previously Bake_Health, which also owned the generated mu-runtimes'
 * disclosure and failure notices. Those went with the bakes; the version-stamp
 * mechanism and its lock did not, because the perf migration still needs them.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Version_Migration {

	/** Last plugin version the once-per-version listeners ran against. */
	const VERSION_OPTION = 'blocklane_pro_migrated_version';

	/** Held for the duration of one pass; see claim_lock(). */
	const LOCK_OPTION = 'blocklane_pro_migration_lock';

	/** Seconds after which a lock is considered abandoned. */
	const LOCK_TIMEOUT = 120;

	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// init runs on every request (front end included), so a stale deploy is
		// caught by the first visitor, not the next admin.
		add_action( 'init', array( __CLASS__, 'maybe_run_after_update' ), 5 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_version_stamp_after_upgrade' ), 10, 2 );
	}

	/**
	 * Is the stored stamp current — nothing to run?
	 *
	 * Pure, so the battery can drive it. `$stored` is the option's value
	 * (`pro/0.12.0`, or `pro/0.12.0#module:a,module:b` when the pass that
	 * wrote it ran with those units' lifecycle files missing); `$current` is
	 * Edition::stamp(); `$lifecycle_misses` is Modules::misses()['lifecycle']
	 * now, keyed by unit id.
	 *
	 * A pending unit that is NO LONGER missing means its listeners never ran:
	 * not current, whatever the head says. Otherwise the head decides — a
	 * miss that appears mid-version does not re-run a pass the stamp says ran
	 * (its file was there when the listeners fired).
	 *
	 * @param array<string, mixed> $lifecycle_misses
	 */
	public static function stored_stamp_is_current( string $stored, string $current, array $lifecycle_misses ): bool {
		$parts   = explode( '#', $stored, 2 );
		$head    = $parts[0];
		$pending = isset( $parts[1] ) && '' !== $parts[1] ? explode( ',', $parts[1] ) : array();
		foreach ( $pending as $unit ) {
			if ( ! isset( $lifecycle_misses[ $unit ] ) ) {
				return false; // the file is back: the pass must re-run.
			}
		}
		return $head === $current;
	}

	/**
	 * The value to store after a pass: the stamp, plus `#` and the sorted ids
	 * of the units whose lifecycle files were missing when it fired.
	 *
	 * @param array<string, mixed> $lifecycle_misses
	 */
	public static function stamp_with_pending( string $current, array $lifecycle_misses ): string {
		$units = array_map( 'strval', array_keys( $lifecycle_misses ) );
		sort( $units );
		return array() === $units ? $current : $current . '#' . implode( ',', $units );
	}

	/** Fire the once-per-version listeners when the code on disk has changed. */
	public static function maybe_run_after_update() {
		// Edition::stamp() is 'pro/0.12.0', not '0.12.0'. With both editions
		// installed — the normal steady state — removing Pro hands the site to
		// the free build mid-version, and a bare version would read as
		// unchanged: the new edition's seeds and migrations would never run.
		// Every pre-0.12.0 stamp also differs from this form, so the pass fires
		// exactly once on the first boot after this change, which is what the
		// listeners want anyway. Modules::misses() is final here: the
		// lifecycle boot ran at file scope, and this is init 5.
		$misses = Modules::misses()['lifecycle'];
		if ( self::stored_stamp_is_current( (string) get_option( self::VERSION_OPTION, '' ), Edition::stamp(), $misses ) ) {
			return;
		}

		// This hook is init on EVERY request, and the version stamp is only
		// written once the listeners finish. On a site with traffic, every
		// request arriving inside that window would otherwise run the whole
		// pass again, in ordinary visitors' page loads. One request does the
		// work; the rest skip and serve the page.
		if ( ! self::claim_lock() ) {
			return;
		}

		try {
			/**
			 * Fires once when the plugin version on disk has changed —
			 * including on updates that never touch wp-admin (auto-update,
			 * WP-CLI, deploy). Listeners must be idempotent.
			 */
			do_action( 'blocklane_pro_version_changed' );

			// The stamp advances even when a listener failed, deliberately: a
			// site that cannot complete the pass must not re-run it on every
			// request forever. It records the units whose listeners could not
			// have run, so the pass re-runs once when their files are back.
			update_option( self::VERSION_OPTION, self::stamp_with_pending( Edition::stamp(), $misses ) );
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Take the lock, or report that another request holds it.
	 *
	 * add_option() LOOKS like a mutex (option_name is UNIQUE) but is not one:
	 * core implements it as INSERT ... ON DUPLICATE KEY UPDATE, so a
	 * concurrent duplicate becomes an UPDATE with truthy affected-rows and
	 * both racers "win". That matters here because one listener — the
	 * seeded-defaults ledger migration — is a whole-array read-modify-write,
	 * not idempotent under interleaving. INSERT IGNORE is the real
	 * single-winner primitive (the same one core's WP_Upgrader::create_lock()
	 * uses; the SQLite integration translates it to INSERT OR IGNORE), and
	 * going through $wpdb also sidesteps the options caches, which can answer
	 * "absent" from a stale snapshot mid-race. A lock left behind by a fatal
	 * is stolen once it ages past the timeout, so a crash during the pass
	 * costs one window, not a permanently skipped migration.
	 *
	 * @return bool Whether this request owns the pass.
	 */
	private static function claim_lock() {
		if ( self::insert_lock_row() ) {
			return true;
		}

		// Raw read for the same reason as the raw insert: this must see the
		// row we just lost the INSERT to, not what the options cache holds.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reading the live lock row; any cache defeats the point.
		$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) );
		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Stale: delete the abandoned row, then race for a fresh claim. Both
		// steps hold up under concurrency — deletes are idempotent, and the
		// INSERT IGNORE picks exactly one winner among simultaneous
		// reclaimers. (The old read-then-update reclaim admitted all of them.)
		delete_option( self::LOCK_OPTION );
		return self::insert_lock_row();
	}

	/**
	 * Atomically create the lock row; false when it already exists.
	 *
	 * @return bool Whether the row was created by this call.
	 */
	private static function insert_lock_row() {
		global $wpdb;
		// Never autoloaded: it exists for seconds, on one request in a deploy.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the row IS the mutex; see claim_lock().
		return (bool) $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'off')",
				self::LOCK_OPTION,
				(string) time()
			)
		);
	}

	/**
	 * After the upgrader replaces this plugin, invalidate the version stamp.
	 * This hook still runs the OLD code, so it only clears the stamp; the next
	 * request's init pass (above) runs the listeners under the new code.
	 *
	 * @param object $upgrader   Upgrader instance; read for the folder an "install" wrote.
	 * @param array  $hook_extra Info about the completed process.
	 */
	public static function clear_version_stamp_after_upgrade( $upgrader, $hook_extra ) {
		if ( ! is_array( $hook_extra ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
			return;
		}

		// An UPDATE names the plugin; an INSTALL (a re-upload of the same zip
		// over a torn deploy — the remedy the module notices ask for) sets no
		// 'plugin' key, only the folder the upgrader wrote (#839). Match that
		// too, or the stamp survives the reinstall and the skipped pass never
		// re-runs.
		$installed_here = 'install' === ( $hook_extra['action'] ?? '' )
			&& $upgrader instanceof \WP_Upgrader
			&& is_array( $upgrader->result )
			&& dirname( BLOCKLANE_PRO_BASENAME ) === (string) $upgrader->result['destination_name'];

		$ours = $installed_here
			|| in_array( BLOCKLANE_PRO_BASENAME, (array) ( $hook_extra['plugins'] ?? array() ), true )
			|| BLOCKLANE_PRO_BASENAME === ( $hook_extra['plugin'] ?? '' );

		if ( $ours ) {
			delete_option( self::VERSION_OPTION );
		}
	}
}
