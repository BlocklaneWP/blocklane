<?php
/**
 * Blocklane Pro uninstall cleanup.
 *
 * Runs ONLY on full deletion of the plugin (Plugins -> Delete), never on
 * deactivation.
 *
 * The rule: uninstall removes PLUGIN STATE, never USER CONTENT. Blocklane Pro
 * is a builder, and what someone builds with it is theirs.
 *
 * The plugin generates no code files outside its own folder. The cleanup is
 * almost entirely a database one. It is not quite "no files": Forms keeps
 * uploaded attachments under uploads/blocklane-forms, and the child-theme
 * generator writes a theme. Both are user content by any reading, are left
 * alone on both paths, and are the user's to remove. Files that pre-2026-08
 * Pro builds generated outside the plugin are a Pro-only unit's teardown
 * (service:bake-leftovers, inc/bake-leftovers/uninstall.php), run through the
 * fragments below like every other unit's: the wordpress.org build carries
 * none of that code, because it never wrote those files.
 *
 * PLUGIN STATE (settings, stamps, caches, license session) is swept
 * unconditionally.
 *
 * CONTENT RECORDS are never swept on either path: posts, content-type entries,
 * taxonomy terms and the Forms submissions table survive even the opt-in. The
 * definitions go, the records stay.
 *
 * Between those two sit the rows that are authored content living in an
 * option — the uploaded SVG icons and the Custom Scripts code. They are kept
 * on the default path and removed only under the explicit Clean Uninstall
 * opt-in, alongside this install's license-seat identity.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * THE PLAN — what this uninstall may sweep, decided from what is ON DISK.
 *
 * Blocklane ships as two plugins from one tree (blocklane-pro and the
 * wordpress.org blocklane build), and they deliberately read and write the SAME
 * blocklane_pro_* rows so that a site's configuration survives moving between
 * them. Both being installed together is the normal steady state, not an edge
 * case. That makes an unguarded uninstall destructive in a way nothing warns
 * about: deleting one plugin would sweep the OTHER one's live settings out from
 * under it.
 *
 * So a row is swept when NO OTHER BUILD OF THIS TREE remains to read it —
 * never "because the other edition's folder is not there", never "because
 * this copy ran first". blocklane_pro_uninstall_plan() (inc/uninstall-
 * fragments.php) reads every other directory under plugins/ by CONTENT — its
 * own rendered inc/edition.php, through inc/edition-identity.php — and
 * answers two questions: may this edition's own unit fragments run (yes,
 * unless a twin of the same rank remains and still reads them), and may the
 * complete sweep below the guard run (yes, only when nothing else of this
 * tree remains, readable or not). Written from NO edition's viewpoint: this
 * file is the same bytes in both editions, and a comment written from one
 * edition's viewpoint is exactly how Pro's state got swept by a free-only
 * delete.
 *
 * ORDER, and why. (1) The lent files are required behind function_exists /
 * class_exists: deleting both editions at once runs both uninstall.php files
 * in ONE process, so the second copy finds the first copy's declarations
 * already bound and must not compile them again — an unconditional require
 * of a declaring file is a redeclare fatal halfway through the second
 * uninstall (#855; bin/generate-edition.php rule 10 refuses one). (2) The
 * plan. (3) This edition's own fragments. (4) The guard: refuse the shared
 * sweep and say what was FOUND. (5) The sweep — everything of this tree,
 * shared and Pro-only alike, so a fragment that never ran (Pro removed over
 * SFTP) is still caught the day the last build leaves.
 *
 * NOTHING ABOVE THE GUARD DELETES. Every delete, clear and unschedule sits
 * below it; the uninstall battery's U15 holds the file to that, because a
 * transient dropped above the guard ran in every uninstall, sibling or not
 * (#872).
 *
 * MIRROR CONTRACT with blocklane_pro\Edition — this file runs with no
 * autoloader, so it reads the generated data file directly; null when the
 * file is missing, and the plan then fails closed.
 */
// Each probe names the NEWEST function of the file it guards: an older
// lender's copy declares the older names and not this one, so probing an
// old name would skip our own require and leave the new function undefined
// (the uninstall battery's U19 holds each probe to the file's last
// declaration). Every function in those files is function_exists-wrapped,
// so requiring ours beside a lender's copy adds only what is missing.
if ( ! function_exists( 'blocklane_pro_log_failure' ) ) {
	require_once __DIR__ . '/inc/log.php';
}
if ( ! function_exists( 'blocklane_pro_edition_outranking' ) ) {
	require_once __DIR__ . '/inc/edition-identity.php';
}
if ( ! function_exists( 'blocklane_pro_uninstall_plan' ) ) {
	require_once __DIR__ . '/inc/uninstall-fragments.php';
}
$blocklane_pro_edition_data = is_file( __DIR__ . '/inc/edition.php' ) ? (array) require __DIR__ . '/inc/edition.php' : null;

$blocklane_pro_plan = blocklane_pro_uninstall_plan( $blocklane_pro_edition_data, __DIR__, WP_PLUGIN_DIR );

// This edition's own unit fragments (the seat release, the updater's option
// and cron, Pro's bake-era leftovers), from this edition's own list — unless
// a twin of the same rank remains on disk and still reads them.
if ( $blocklane_pro_plan['fragments'] ) {
	blocklane_pro_uninstall_fragments( (array) $blocklane_pro_edition_data, __DIR__ );
}

if ( ! $blocklane_pro_plan['shared'] ) {
	// Stop here. Everything past this point is state another build of this
	// tree may still be reading. The log names what was FOUND — a build at
	// whatever folder, an unreadable one, or no data of our own — never a
	// guess about a folder name. Nothing in this block sweeps anything; the
	// uninstall battery's U12 holds it to that.
	//
	// What a Pro delete beside a surviving free edition TAKES: its own
	// fragments ran above — the license key and seat, the updater's state,
	// and the three Pro-only units' definitions (Animation Designer presets,
	// the CSS Class Manager catalog, the Content Types builder's bookkeeping).
	// Those are definitions the surviving free edition has no screen to read
	// or edit; the CONTENT that used them — blocks carrying the class names
	// and animation attributes, the registered types and their entries —
	// stays, as it does for every definition this file removes ("the
	// definitions go, the records stay"). A reinstalled Pro starts without
	// them, exactly as after a Pro-only delete.
	// What a Pro delete beside a surviving free edition therefore LEAVES,
	// bounded and accepted (Spec B D-B2): the Pro-only extensions' toggle
	// rows (blocklane_pro_ext_*, shared-unit ledger state), the canvas grid
	// tools switch, the Menu Designer preview-token meta, and AI MCP's
	// day-TTL transients — all swept the day the last build leaves. The
	// units whose state autoloads or recurs (license, updates, class
	// manager, animation designer, content types) sweep theirs in their own
	// fragments above, so a surviving free site carries no daily no-op cron
	// and no autoloaded Pro row.
	// The one trace that a delete deliberately swept nothing, written in
	// production too: nothing else will ever say why the settings stayed.
	blocklane_pro_log_failure(
		'Blocklane: uninstall kept every shared setting: ' . $blocklane_pro_plan['reason']
		. ( array() === $blocklane_pro_plan['others'] ? '' : ' [' . implode( ', ', $blocklane_pro_plan['others'] ) . ']' )
	);
	return;
}

/*
 * Options — every option the plugin persists (settings, auth session, and each
 * module's state). Leaving these behind would be sloppy: the site-lock option
 * holds the encrypted password + preview key, and the auth option holds the
 * Supabase session tokens, so a full uninstall must clear them. Nothing of
 * ours is left running to need them: the plugin writes no code outside its
 * own folder, so deleting it stops every Blocklane surface.
 *
 * What this list must NOT contain is user content. Custom Scripts moved out of
 * a generated file and into blocklane_pro_scripts in the same release that
 * removed the writers, and the row is now the only copy of that code — it is
 * kept below with the custom icons, not swept here.
 *
 * Single-site scope: on a network WordPress runs uninstall once, so per-site
 * options elsewhere in a multisite would need a site loop — out of scope for this
 * plugin's single-site model.
 */
/*
 * Deliberately kept: blocklane_pro_custom_icons — the user's uploaded SVG
 * icons, which render icons already placed in saved content. Icons are
 * content, not plugin state; deleting the row would blank them. Their
 * uploads mirror (uploads/blocklane-icons/) is kept for the same reason:
 * reinstalling EITHER edition registers those icons again from the row and
 * the mirror. Nothing else registers the collection — the companion theme
 * stopped in its 1.0.0, when the registrar moved into this plugin. Both go
 * only with the clean-uninstall opt-in below.
 *
 * Deliberately kept for the same reason: blocklane_pro_scripts — the header,
 * body and footer code the USER wrote. Since 2026-08 this row is the ONLY
 * copy: Scripts::migrate_legacy_file() writes it and then deletes the old
 * generated file, so there is no longer anything on disk behind it. Deleting
 * the row on the default path would destroy authored content — the same line
 * Pro's bake-leftovers fragment refuses to cross for the pre-2026-08 file form
 * of this exact data, which it moves rather than deletes. The row carries the
 * feature's toggles too; that is a fair price for not eating someone's
 * analytics and verification tags on a routine reinstall.
 *
 * Also deliberately kept: blocklane_pro_install_id — this install's seat
 * identity at the license gateway. It is read below to free the seat, then
 * left in place so a REINSTALL on the same site reuses the same seat instead
 * of burning a new activation slot.
 *
 * Short-TTL abilities transients are not swept here — WordPress's
 * expired-transient purge clears them: the preview/confirm tokens
 * (blocklane_pro_abilities_preview_*, blocklane_pro_abilities_confirm_*)
 * and the pattern store's search results (blocklane_pro_cs_<md5>, 300 s).
 * The store's per-slug entries (blocklane_pro_ps_<sha1>), the per-pattern
 * markup transients (blocklane_pro_pc_<sha256>) and the retired slug map
 * live a day and are swept below by name/prefix.
 */
delete_transient( 'blocklane_pro_abilities_pattern_cache' );
// The sibling installer's throttle/outcome record. Below the guard: dropped
// above it, a free-only delete wiped Pro's record while Pro was still
// installed (#872).
delete_transient( 'blocklane_pro_sibling_ensure' );
delete_site_transient( 'blocklane_pro_sibling_ensure' );

/*
 * THE SAFETY NET. Every row, cron and meta of this tree is below this line,
 * Pro-only state INCLUDED, even though the Pro-only units sweep their own
 * state in their fragments (inc/license/uninstall.php,
 * inc/updates/uninstall.php, and the three under the extensions loader and
 * inc/content-types/). A fragment runs only when ITS plugin is deleted
 * through WordPress; a Pro removed over SFTP never runs one, and the day the
 * surviving free edition is deleted this sweep is the last chance the
 * license key, the auth tokens and the updater's cron get (#825). Each line
 * is an idempotent no-op when its fragment already ran. No gateway host, no
 * URL, no code — names only, which the directory build may carry.
 *
 * bin/uninstall-battery.php keeps both halves honest: U13′ — every Pro-only
 * string named here sits BELOW the guard, so a free-only delete beside Pro
 * touches none of it (#820); U14 — every row a fragment sweeps is named here
 * too (fragments ⊆ safety net).
 *
 * NOT released here: the license SEAT. Freeing it needs the gateway, which
 * lives only in the license fragment (guideline 7 keeps the host out of this
 * file); a seat whose Pro left without its uninstall is reaped by the
 * gateway's own liveness rule (#735) once the site stops checking in.
 */
// The `license` unit (inc/license/uninstall.php).
wp_clear_scheduled_hook( 'blocklane_pro_daily_license_check' );
delete_option( 'blocklane_pro_license' );
delete_option( 'blocklane_pro_auth' );
delete_option( 'blocklane_pro_licensed' );
delete_option( 'blocklane_pro_license_until' );
delete_metadata( 'user', 0, 'blocklane_pro_lapse_notice_dismissed', '', true );
// The `updates` unit (inc/updates/uninstall.php): the update checker's
// persisted state and its cron, in both scopes.
delete_option( 'external_updates-blocklane-pro' );
delete_site_option( 'external_updates-blocklane-pro' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-blocklane-pro' );

/*
 * One filesystem connection for every File_Ops operation below.
 *
 * On the wp-admin Delete path delete_plugins() has already opened this with
 * whatever credentials the host required — FTP and SSH included — and bails
 * before uninstall_plugin() if it could not. So routing through it does not
 * just satisfy a checker: on a non-direct host the old raw rename() was being
 * attempted by a web user with no write access there, and silently failed.
 *
 * A WP_Error means one context only: a third-party caller of uninstall_plugin()
 * on a non-direct host, constants or not — WP_Filesystem() with no arguments
 * never reads FTP_*; only request_filesystem_credentials() does, and nothing
 * here may call that. There, the FILE work is skipped
 * and named in the log, and the database sweep still completes in full — the
 * secrets (site-lock password, auth tokens, SEO identity) are options, and they
 * go regardless. Skipping file work leaves the user's uploads where they
 * already are on every non-opt-in site, which is a known state; deleting them
 * halfway would not be.
 */
// class_exists-guarded like the fragments function, and under the same
// cross-version contract: its name and method signatures are frozen, because
// in a bulk delete the SECOND edition runs the FIRST edition's copy.
if ( ! class_exists( '\blocklane_pro\File_Ops', false ) ) {
	require_once __DIR__ . '/inc/class-blocklane-pro-file-ops.php';
}
$blocklane_pro_fs   = \blocklane_pro\File_Ops::filesystem();
$blocklane_pro_left = array();

// Read the Advanced toggles BEFORE the loop below deletes the row — the
// clean-uninstall opt-in at the bottom of this file is the last thing that
// runs and needs to know whether the user asked for it.
$blocklane_pro_advanced_final = get_option( 'blocklane_pro_advanced' );

/*
 * Submission data survives uninstall by default — client leads are the
 * site owner's, not ours ("what you build is yours"). The v3
 * delete_on_uninstall setting is the explicit opt-in that flips this:
 * only then do the submissions table and stored uploads go with us.
 */
$blocklane_pro_forms_settings_final = get_option( 'blocklane_pro_forms' );
if ( is_array( $blocklane_pro_forms_settings_final ) && ! empty( $blocklane_pro_forms_settings_final['delete_on_uninstall'] ) ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- opt-in teardown of the plugin's own table; core has no API for dropping one.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'blocklane_form_submissions' ) );

	$blocklane_pro_forms_uploads = wp_get_upload_dir();
	$blocklane_pro_forms_uploads = $blocklane_pro_forms_uploads['basedir'] . '/blocklane-forms';
	if ( is_wp_error( $blocklane_pro_fs ) ) {
		$blocklane_pro_left[] = $blocklane_pro_forms_uploads;
	} else {
		$blocklane_pro_forms_gone = \blocklane_pro\File_Ops::remove_tree( $blocklane_pro_fs, $blocklane_pro_forms_uploads );
		if ( is_wp_error( $blocklane_pro_forms_gone ) ) {
			$blocklane_pro_left[] = $blocklane_pro_forms_uploads . ' (' . $blocklane_pro_forms_gone->get_error_message() . ')';
		}
		unset( $blocklane_pro_forms_gone );
	}
	unset( $blocklane_pro_forms_uploads );
}

$blocklane_pro_options = array(
	'blocklane_pro',                   // main settings / plugin state
	'blocklane_pro_content_types',     // Content Types definitions
	'blocklane_pro_taxonomies',        // Content Types taxonomy definitions
	'blocklane_pro_content_types_rev', // Content Types definitions version (save concurrency)
	'blocklane_pro_content_types_lock', // pre-2026-08 Content Types save mutex (removed; may linger from old builds)
	'blocklane_pro_ct_pending_wipes',  // pre-fix3 ARRAY form of the pending-wipe ledger (now one row per entry, swept by prefix below); may linger from old builds
	'blocklane_pro_ct_rewrite_hash',   // Content Types rewrite-flush marker
	'blocklane_pro_ct_stale_template_forks', // stale-fork scan state (wrapped {state,findings} or legacy list)
	'blocklane_pro_ct_stale_forks_rescan',   // theme-switch rescan flag for the scan above
	'blocklane_pro_security',          // Security toggles
	'blocklane_pro_advanced',          // Advanced toggles + settings
	// SEO settings: org identity, street address, phone, GPS coordinates,
	// verification tokens, IndexNow key — the most personally-identifying row
	// the plugin stores. Like the site-lock and auth rows it belongs on this
	// DEFAULT path, not only behind the clean-uninstall opt-in.
	'blocklane_pro_seo',
	'blocklane_pro_site_lock',         // Site Privacy (encrypted password + preview key)
	'blocklane_pro_dynamic_values',    // Dynamic Values tokens
	'blocklane_pro_breakpoints',       // Responsive breakpoints
	'blocklane_pro_grid_canvas_tools', // Canvas grid tools switch (advanced-grid)
	'blocklane_pro_animation_presets', // Animation Designer presets
	'blocklane_pro_css_classes',       // CSS Class Manager classes
	'blocklane_pro_css_class_usage',   // CSS Class Manager usage tracking (split from the blob 2026-07)
	'blocklane_pro_pro_css_classes',   // …pre-2026-07 slug, in case the rename migration never ran
	'blocklane_pro_migrated_version',  // Once-per-version migration stamp (Version_Migration)
	'blocklane_pro_migration_lock',    // Once-per-version migration lock (Version_Migration)
	// The licensed flag + lease expiry. Every reader of these lives inside the
	// plugin (inc/*/runtime.php), so once it is deleted nothing is left to
	// consult them. They were kept while generated mu-runtimes outlived the
	// plugin and gated on them; that is no longer true.
	// Bake-era rows, swept for sites upgrading from a version that wrote them.
	'blocklane_pro_bake_version',
	'blocklane_pro_bake_resync_lock',
	'blocklane_pro_bake_resync_outcome',
	'blocklane_pro_bake_files_hash',
	'blocklane_pro_md_bake_files_hash',
	'blocklane_pro_popups_bake_files_hash',
	'blocklane_pro_forms_bake_files_hash',
	'blocklane_pro_ext_seeded_defaults', // Which toggle rows the migration materialized from shipped defaults
	'blocklane_pro_forms',             // Forms settings (purge days, hint state)
	'blocklane_pro_forms_db_version',  // Forms submissions-table schema stamp
	// The submissions TABLE is user content and is kept unless the v3
	// delete_on_uninstall opt-in is set (handled near the top of this file),
	// following the Content Types precedent: definitions removed, records
	// preserved. The forms OPTIONS above go unconditionally, like every other
	// plugin setting — only the records are protected.
);
foreach ( $blocklane_pro_options as $blocklane_pro_option ) {
	delete_option( $blocklane_pro_option );
}

global $wpdb;

/*
 * Per-extension enable toggles (blocklane_pro_ext_<slug>) and the login / unlock
 * throttle transients have dynamic names. No core API deletes options or
 * transients by prefix, so a direct, prepared LIKE delete is the right tool.
 */
$blocklane_pro_prefixes = array(
	'blocklane_pro_ext_',
	// Content Types: the pending-wipe ledger, one dynamically-named row per
	// once-validated wipe that a failed save skipped. NOTE: this prefix does
	// NOT match the legacy `blocklane_pro_ct_pending_wipes` array row — that
	// one stays in the explicit list above; both entries are required.
	'blocklane_pro_ct_pending_wipe_',
	// Content Types: background wipe-job rows (Wipe_Runner). Uninstall
	// abandons in-flight wipes DELIBERATELY: it removes plugin state, never
	// finishes destructive work unsupervised — the remaining records are
	// ordinary WordPress rows governed by uninstall's content-tier rules.
	'blocklane_pro_ct_wipe_job_',
	'_transient_blocklane_pro_login_fail_',
	'_transient_timeout_blocklane_pro_login_fail_',
	'_transient_blocklane_pro_unlock_fail_',
	'_transient_timeout_blocklane_pro_unlock_fail_',
	// Forms: the unique-entry mutex. Normally released at shutdown, but a
	// request killed mid-flight leaves one behind, and nothing else sweeps
	// them — they are the only dynamically-named forms option not covered
	// here, so without this line a fatal could strand rows forever.
	'blocklane_pro_forms_ulock_',
	// Forms: per-form location caches + per-IP submission rate counters.
	'_transient_blocklane_pro_forms_loc_',
	'_transient_timeout_blocklane_pro_forms_loc_',
	'_transient_blocklane_pro_forms_rl_',
	'_transient_timeout_blocklane_pro_forms_rl_',
	// AI tools' pattern store: one transient per remembered slug (ps_) and
	// one per fetched pattern's markup keyed by content sha256 (pc_), a day
	// each; the pre-schema-2 slug map is the explicit key above.
	'_transient_blocklane_pro_ps_',
	'_transient_timeout_blocklane_pro_ps_',
	'_transient_blocklane_pro_pc_',
	'_transient_timeout_blocklane_pro_pc_',
	// The failure-level log door's throttle (inc/log.php): one transient per
	// distinct message, a day each, named by the message's hash.
	'_transient_blocklane_pro_log_',
	'_transient_timeout_blocklane_pro_log_',
);
foreach ( $blocklane_pro_prefixes as $blocklane_pro_prefix ) {
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup; no core API deletes by prefix.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $blocklane_pro_prefix ) . '%'
		)
	);
}

// Last-login user meta (Advanced » Last login column), across all users.
delete_metadata( 'user', 0, 'blocklane_pro_last_login', '', true );

// Menu Designer per-user preview tokens (hashed) + their expiry, across all users.
delete_metadata( 'user', 0, 'blocklane_pro_md_preview_token', '', true );
delete_metadata( 'user', 0, 'blocklane_pro_md_preview_token_exp', '', true );

// Pattern favorites user meta — pre-extraction leftover (the Pattern Library
// moved to the blocklane-patterns plugin in July 2026, which uses its own
// meta key; this clears state from installs that predate the split).
delete_metadata( 'user', 0, 'blocklane_pro_pattern_favorites', '', true );

/*
 * Render caches. Derived rows with nothing left installed to read or ever
 * delete them once the plugin is gone. Both autoload while small, so an
 * orphan is a permanent per-request tax on a site that no longer runs this
 * plugin.
 *
 * The transient names are the pre-0.3.2 storage for the same caches; delete
 * both spellings so sites upgrading through that change leave nothing behind.
 */
delete_option( 'blocklane_pro_preset_css' );
delete_transient( 'blocklane_pro_preset_css' );
delete_option( 'blocklane_pro_popup_rows' );
delete_transient( 'blocklane_pro_popup_rows' );
// Companions to the caches above, added with the flush-generation token and
// the share-image probe. Both are derived and autoloaded, so an orphan is a
// per-request tax on a site that no longer runs this plugin.
delete_option( 'blocklane_pro_popup_rows_gen' );
delete_option( 'blocklane_pro_preset_css_gen' );
delete_option( 'blocklane_pro_seo_og_probe' );

// Pattern-library category-counts cache (pre-extraction leftover, as above).
delete_transient( 'blocklane_pro_pattern_categories' );

// The Content Types wipe machinery is ALSO in inc/content-types/uninstall.php
// (its fragment); here for the last build out, like every Pro-only row above.
wp_clear_scheduled_hook( 'blocklane_pro_forms_purge' );
// Wipe continuation events carry per-job args, so wp_unschedule_hook (all
// events for the hook, any args) is the right sweep. The daily watchdog is
// argless — wp_clear_scheduled_hook suffices.
wp_unschedule_hook( 'blocklane_pro_ct_wipe' );
wp_clear_scheduled_hook( 'blocklane_pro_ct_wipe_watchdog' );


/*
 * Clean Uninstall (Advanced toggle, ships OFF).
 * When the user opted in, "Delete" means delete: the options the default path
 * deliberately keeps (the install id, custom icons, the Custom Scripts row) go
 * too. Runs LAST — the gateway seat release and the standard cleanup above
 * already happened. (Pro's bake-leftovers fragment, above the guard, retires
 * a pre-2026-08 Custom Scripts file under the same opt-in and the same plan.)
 * Content records (posts, the submissions table) are still kept: files and
 * settings are ours to remove, content is not.
 * $blocklane_pro_advanced_final was read above, before its option was
 * deleted.
 */
if ( is_array( $blocklane_pro_advanced_final ) && ! empty( $blocklane_pro_advanced_final['clean-uninstall'] ) ) {
	// The custom icons' uploads mirror goes with its option row (same body as
	// the forms uploads above; a missing directory is a no-op).
	$blocklane_pro_icons_uploads = wp_get_upload_dir();
	$blocklane_pro_icons_uploads = $blocklane_pro_icons_uploads['basedir'] . '/blocklane-icons';
	if ( is_wp_error( $blocklane_pro_fs ) ) {
		$blocklane_pro_left[] = $blocklane_pro_icons_uploads;
	} else {
		$blocklane_pro_icons_gone = \blocklane_pro\File_Ops::remove_tree( $blocklane_pro_fs, $blocklane_pro_icons_uploads );
		if ( is_wp_error( $blocklane_pro_icons_gone ) ) {
			$blocklane_pro_left[] = $blocklane_pro_icons_uploads . ' (' . $blocklane_pro_icons_gone->get_error_message() . ')';
		}
		unset( $blocklane_pro_icons_gone );
	}
	unset( $blocklane_pro_icons_uploads );

	// The options the default path keeps on purpose (see the header notes).
	// The licensed flag and lease are NOT here: they moved to the standard
	// list above, which already ran on both paths.
	delete_option( 'blocklane_pro_install_id' );
	delete_option( 'blocklane_pro_custom_icons' );
	// The user's header/body/footer code. Only the explicit opt-in removes it —
	// clean-uninstall is exactly the "yes, remove everything" signal that
	// justifies dropping authored content.
	delete_option( 'blocklane_pro_scripts' );
	// blocklane_pro_seo is NOT deleted here: it moved to the standard options
	// list above, which already ran on both paths — nothing here retains it.

}

/*
 * Anything the filesystem would not let go of is NAMED, once. Uninstall
 * cannot ask the user anything and cannot be re-run, so a line in the log
 * is the only way a site owner (or we) can find out that a file is still
 * sitting there.
 *
 * AT FILE SCOPE, not inside the clean-uninstall opt-in: the forms uploads
 * removal is gated by the Forms delete_on_uninstall setting, which is a
 * different toggle, and its failures were appended here but never logged when
 * the Advanced opt-in was off — after DROP TABLE had already run (#818).
 */
if ( array() !== $blocklane_pro_left ) {
	// Uninstall has no other channel, so this is written whatever WP_DEBUG says.
	blocklane_pro_log_failure(
		'Blocklane: uninstall left these in place'
		. ( is_wp_error( $blocklane_pro_fs ) ? ' (' . $blocklane_pro_fs->get_error_message() . ')' : '' )
		. ': ' . implode( '; ', $blocklane_pro_left )
	);
}
