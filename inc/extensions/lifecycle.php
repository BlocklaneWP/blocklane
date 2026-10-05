<?php
/**
 * The `extensions` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle().
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the version-resync migration is currently materializing extension
 * toggle rows. The eviction listener below must ignore the migration's own
 * writes (the seed add_option and the default re-align update_option), or the
 * ledger would empty itself the moment it was written.
 *
 * @param bool|null $set When given, sets the flag; null just reads it.
 * @return bool
 */
function blocklane_pro_ext_seeding( $set = null ): bool {
	static $seeding = false;
	if ( null !== $set ) {
		$seeding = (bool) $set;
	}
	return $seeding;
}

/**
 * Evict a toggle row from the seeded-defaults ledger the moment any writer
 * OTHER than the resync migration touches it — Extensions screen save, REST,
 * WP-CLI, a row deletion — regardless of the value it lands on.
 *
 * The ledger's job is to mark rows that are materialized shipped defaults,
 * not choices, so a later release that flips a default can still reach them.
 * The resync's value comparison cannot distinguish "untouched since seeded"
 * from "toggled away and back to the seeded value", and in the second case a
 * default flip would silently override an explicit user choice. Recording
 * the touch itself — this listener — closes that: any user write, round trip
 * included, permanently hands the row to the user.
 *
 * TRADE-OFF (product decision not yet ruled on — the safer side is taken
 * deliberately): with eviction-on-touch, a site whose owner ever saved a
 * toggle through the UI keeps that value forever, even if they only clicked
 * away and back and a future release ships a better default; they simply
 * never receive later default changes for that row — the same deal every
 * site with a genuine choice gets. The alternative (follow the default while
 * the value still matches the seed) reaches more sites but silently
 * overrides real choices. Silently changing behavior a user explicitly set
 * is the worse failure, so unsure rows are left alone. Reversing the call
 * means deleting this listener and its two hook-ups below.
 *
 * Fires on added/updated/deleted_option, so it costs one strpos per option
 * write site-wide (the Extensions handler's dirty-flag listener already pays
 * the same). The prefix and ledger option name are literals, mirroring
 * Extensions_Handler::OPTION_PREFIX — the class is not loaded on every
 * request this listener must cover.
 *
 * @param string $option Option name that was written or deleted.
 */
function blocklane_pro_ext_seeded_ledger_evict( $option ): void {
	$option = (string) $option;
	if ( 'blocklane_pro_ext_seeded_defaults' === $option
		|| 0 !== strpos( $option, 'blocklane_pro_ext_' )
		|| blocklane_pro_ext_seeding() ) {
		return;
	}

	$ledger = get_option( 'blocklane_pro_ext_seeded_defaults', array() );
	if ( ! is_array( $ledger ) ) {
		return;
	}

	$slug = substr( $option, strlen( 'blocklane_pro_ext_' ) );
	if ( ! array_key_exists( $slug, $ledger ) ) {
		return;
	}

	unset( $ledger[ $slug ] );
	update_option( 'blocklane_pro_ext_seeded_defaults', $ledger, true );
}
// Registered at file scope — not inside blocklane_pro_run_plugin — so a
// direct toggle write (WP-CLI, say) is recorded even while the companion
// theme is inactive and the plugin's UI never boots.
add_action( 'added_option', 'blocklane_pro_ext_seeded_ledger_evict' );
add_action( 'updated_option', 'blocklane_pro_ext_seeded_ledger_evict' );
add_action( 'deleted_option', 'blocklane_pro_ext_seeded_ledger_evict' );

/**
 * Materialize the per-extension toggle rows once per version.
 *
 * @return void
 */
function blocklane_pro_ext_seed_toggle_rows(): void {
	// The per-extension toggle rows: a never-toggled site pays one miss
	// query per KNOWN_SLUGS entry, every request. Seed each missing row
	// with its RESOLVED value — blocklane_pro_ext_enabled walks
	// option → baked snapshot → shipped default — so the materialized
	// row answers exactly what the resolution answers today.
	//
	// "Today" is the catch. Elsewhere in this tree a stored row means a
	// deliberate user choice, so materializing defaults would freeze
	// them: a later release that
	// flips a shipped default could never reach a migrated site.
	//
	// Both properties are kept by recording WHICH rows this migration
	// seeded and what the shipped default was at the time. A row still
	// carrying its seeded value is a materialized default, not a
	// choice, so it is re-aligned when the shipped default moves. The
	// moment its value diverges the user has spoken, and it is dropped
	// from the ledger and never touched again.
	//
	// A value comparison alone cannot see a round trip — toggled away
	// and back to the seeded value between releases reads identical to
	// "never touched", and a later default flip would then silently
	// override that explicit choice. So the ledger is also evicted the
	// moment ANY writer outside this migration touches a toggle row,
	// whatever value it lands on (blocklane_pro_ext_seeded_ledger_evict
	// below).
	//
	// runtime-helpers.php is loaded directly when the resolver is
	// absent: on an unlicensed install nothing else defines it, and
	// because the version stamp is written in this same
	// request the seeding would otherwise be skipped and never retried
	// for this version — leaving exactly the per-request miss queries
	// this migration exists to remove. The file declares functions
	// only, with no side effects.
	if ( ! function_exists( 'blocklane_pro_ext_enabled' ) ) {
		require_once BLOCKLANE_PRO_PATH . '/inc/extensions/loader/runtime-helpers.php';
	}
	if ( function_exists( 'blocklane_pro_ext_enabled' ) ) {

		// This migration's own toggle-row writes must not evict the
		// very entries it is recording — flag them for the listener.
		blocklane_pro_ext_seeding( true );

		$blocklane_pro_seeded = get_option( 'blocklane_pro_ext_seeded_defaults', array() );
		$blocklane_pro_seeded = is_array( $blocklane_pro_seeded ) ? $blocklane_pro_seeded : array();

		foreach ( blocklane_pro\Extensions_Handler::known_slugs() as $blocklane_pro_ext_slug => $blocklane_pro_ext_default ) {
			$blocklane_pro_ext_row = blocklane_pro\Extensions_Handler::OPTION_PREFIX . $blocklane_pro_ext_slug;
			$blocklane_pro_stored  = get_option( $blocklane_pro_ext_row, null );

			if ( null === $blocklane_pro_stored ) {
				$blocklane_pro_resolved = blocklane_pro_ext_enabled( $blocklane_pro_ext_slug, $blocklane_pro_ext_default );
				add_option( $blocklane_pro_ext_row, $blocklane_pro_resolved ? '1' : '0', '', true );
				if ( $blocklane_pro_resolved === (bool) $blocklane_pro_ext_default ) {
					$blocklane_pro_seeded[ $blocklane_pro_ext_slug ] = (bool) $blocklane_pro_ext_default;
				}
				continue;
			}

			if ( ! array_key_exists( $blocklane_pro_ext_slug, $blocklane_pro_seeded ) ) {
				continue; // A real choice, or a row this migration never wrote.
			}

			$blocklane_pro_seeded_as = (bool) $blocklane_pro_seeded[ $blocklane_pro_ext_slug ];
			if ( (bool) $blocklane_pro_stored !== $blocklane_pro_seeded_as ) {
				// The user has since decided. Hands off from here.
				unset( $blocklane_pro_seeded[ $blocklane_pro_ext_slug ] );
			} elseif ( $blocklane_pro_seeded_as !== (bool) $blocklane_pro_ext_default ) {
				// Still the value we materialized, and the shipped
				// default has moved: follow it, as an absent row would.
				update_option( $blocklane_pro_ext_row, $blocklane_pro_ext_default ? '1' : '0' );
				$blocklane_pro_seeded[ $blocklane_pro_ext_slug ] = (bool) $blocklane_pro_ext_default;
			}
		}

		update_option( 'blocklane_pro_ext_seeded_defaults', $blocklane_pro_seeded, true );
		blocklane_pro_ext_seeding( false );
		unset( $blocklane_pro_seeded, $blocklane_pro_seeded_as, $blocklane_pro_stored, $blocklane_pro_resolved );
	}
	if ( function_exists( 'blocklane_pro_ext_breakpoints' ) && null === get_option( 'blocklane_pro_breakpoints', null ) ) {
		add_option( 'blocklane_pro_breakpoints', blocklane_pro_ext_breakpoints(), '', true );
	}

}
add_action( 'blocklane_pro_version_changed', 'blocklane_pro_ext_seed_toggle_rows' );
