<?php
/**
 * Scripts: custom code injected into wp_head / wp_body_open / wp_footer.
 *
 * Settings live in one option (`blocklane_pro_scripts`) and the code is emitted
 * in-process by boot(). Nothing is written to disk.
 *
 * Until 2026-08 this feature generated a must-use plugin and treated that FILE
 * as its source of truth, with the settings base64-encoded inside it. That was
 * the last thing in Blocklane writing to wp-content/mu-plugins, and it carried
 * the whole cost of being there: it could not run on multisite (the file would
 * execute on every site in the network), it refused to save under
 * DISALLOW_FILE_MODS or on an unwritable host, and the code kept running after
 * the plugin was deleted with nothing left to manage it. migrate_legacy_file()
 * below moves any surviving file into the option and removes it.
 *
 * Output is verbatim by design — the point is to emit <script>/<meta>/pixels —
 * so there is no output sanitization. The entire safety model is the permission
 * gate on saving: manage_options AND unfiltered_html (see the controller).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scripts {

	/** Settings store: { enabled, header, body, footer }. */
	const OPTION = 'blocklane_pro_scripts';

	/** The must-use plugin this feature generated before 2026-08. */
	const LEGACY_MU_FILENAME = 'blocklane-pro-scripts.php';

	private static $booted = false;

	/**
	 * Register the front-end emitters.
	 *
	 * License-ungated on purpose: the code here is the user's content, like
	 * content types and dynamic values, and content never depends on license
	 * state. Safe mode silences it with every other Blocklane surface —
	 * injected third-party code is the likeliest breakage culprit, so it must
	 * go quiet when someone is bisecting a broken site.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
			return;
		}

		// One read, and only on the front end — wp-admin never emits these.
		if ( is_admin() ) {
			return;
		}

		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			return;
		}

		// Read through normalize() so every stored shape is understood the same
		// way the import gate and the dashboard understand it. A raw read is
		// what made the pre-per-section rows — each section a flat string, not
		// a { enabled, code } array — silently emit nothing while has_code()
		// counted them as code.
		$settings = self::normalize( $stored );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// A pre-2026-08 generated file that could not be removed is still
		// emitting on its own (mu-plugins load first). Stand down rather than
		// output everything twice. This rides INSIDE the settings row on
		// purpose: a separate flag option would be absent on every healthy
		// site, and an absent autoloaded row costs a miss query on every
		// front-end request.
		if ( ! empty( $settings['legacy_file'] ) ) {
			return;
		}

		// Nothing to emit in any section: leave the hooks unregistered rather
		// than attach three closures that would each decide to print nothing.
		// This is also what makes an empty row cost exactly what an absent one
		// used to — the row exists only so the read above is a cache hit.
		if ( self::is_empty( $settings ) ) {
			return;
		}

		$emit = static function ( $key ) use ( $settings ) {
			$section = isset( $settings[ $key ] ) ? $settings[ $key ] : null;
			if ( is_array( $section ) && ! empty( $section['enabled'] ) && ! empty( $section['code'] ) ) {
				echo $section['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- verbatim by design; gated on unfiltered_html at save.
			}
		};

		add_action(
			'wp_head',
			static function () use ( $emit ) {
				$emit( 'header' );
			},
			100
		);
		add_action(
			'wp_body_open',
			static function () use ( $emit ) {
				$emit( 'body' );
			}
		);
		add_action(
			'wp_footer',
			static function () use ( $emit ) {
				$emit( 'footer' );
			},
			100
		);
	}

	/** Default settings shape. */
	public static function defaults() {
		return array(
			'enabled' => true,
			'header'  => self::normalize_section( '' ),
			'body'    => self::normalize_section( '' ),
			'footer'  => self::normalize_section( '' ),
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array{enabled:bool,header:array,body:array,footer:array}
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION, null );

		return is_array( $stored ) ? self::normalize( $stored ) : self::defaults();
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings { enabled, header, body, footer }.
	 * @return true|\WP_Error
	 */
	public static function save( array $settings ) {
		$stored = get_option( self::OPTION, null );

		$blocklane_legacy_path = trailingslashit( WPMU_PLUGIN_DIR ) . self::LEGACY_MU_FILENAME;
		$legacy_stuck          = false;

		// The stand-down marker describes the filesystem, not the settings, and
		// the REST controller builds its payload from request params alone, so
		// it cannot know about it. Reconcile it against the disk on EVERY save:
		// a save is the one moment we know the user is watching, and both
		// stale states are silent failures otherwise — a marker left behind
		// after the file was removed by hand means boot() stands down forever,
		// and a marker that is still true means the code they just typed will
		// not appear no matter how many times they press save.
		if ( is_array( $stored ) && ! empty( $stored['legacy_file'] ) ) {
			$legacy_stuck = file_exists( $blocklane_legacy_path );

			if ( $legacy_stuck ) {
				$settings['legacy_file'] = true;
			}
		}

		$data  = self::normalize( $settings );
		$empty = self::is_empty( $data );

		// Clearing every box is a request for nothing to be emitted, and while
		// the marker is set the FILE is the emitter — no option write silences
		// it. Retire it out of the load path, which stops the emission without
		// destroying the code inside it.
		//
		// Retire rather than delete, even though migrate_legacy_file() deletes
		// in the equivalent spot: by the time we get here we cannot tell
		// whether that file's contents were ever imported. It may be the
		// unparseable case, in which case this row never held its code and
		// deleting would destroy the only copy — the same line the original
		// uninstall bug crossed.
		if ( $empty && $legacy_stuck ) {
			$legacy_stuck = ! self::retire_legacy_file( $blocklane_legacy_path );

			if ( ! $legacy_stuck ) {
				unset( $data['legacy_file'] );
			}
		}

		// Both editions write this row and are allowed to differ in version, so
		// a key this build does not know (a newer edition's per-page entries,
		// say) is carried through, never dropped — the same rule as every
		// other store (Helper::with_foreign_keys, Spec C; #857's last writer).
		// A row that is not an array is refused untouched.
		$merged = Helper::with_foreign_keys( self::OPTION, $data, self::normalize( array() ) + array( 'legacy_file' => true ) );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		// The row is stored even when every section is empty, rather than
		// dropped. boot() reads it on every front-end request, and an absent
		// row costs a cache-miss query on each one — the same reason the
		// version migration seeds the other every-request rows. It costs
		// nothing else: boot()'s is_empty() check returns before registering
		// any hook, so an empty row behaves exactly like an absent one.
		$autoload = Helper::autoload_ok( $merged );

		if ( null === $stored ) {
			add_option( self::OPTION, $merged, '', $autoload );
		} else {
			update_option( self::OPTION, $merged, $autoload );
		}

		// Settings are persisted either way — the failure is about the file, so
		// losing the user's edit on top of it would help nobody. But report it
		// rather than answer "saved" while their old code is still running.
		if ( $legacy_stuck ) {
			return new \WP_Error(
				'blocklane_pro_scripts_legacy_stuck',
				__( 'Your settings were saved, but code from a file generated by an older version is still running and could not be removed. Delete wp-content/mu-plugins/blocklane-pro-scripts.php by hand to stop it.', 'blocklane' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Move a pre-2026-08 generated mu-plugin into the option and remove it.
	 *
	 * Runs once per plugin version via blocklane_pro_version_changed, and is a
	 * no-op on every site that never had the file.
	 *
	 * Order matters: the option is written first, so a failure to delete leaves
	 * the settings safe rather than losing the user's code. That ordering can
	 * leave the file emitting alongside the option for one request — hence the
	 * explicit stand-down below rather than a silent double-emit.
	 *
	 * @return void
	 */
	public static function migrate_legacy_file() {
		$path   = trailingslashit( WPMU_PLUGIN_DIR ) . self::LEGACY_MU_FILENAME;
		$stored = get_option( self::OPTION, null );

		// No file to migrate. An absent or unreadable file cannot be emitting
		// anything, so a marker left by an earlier run is stale by definition
		// — clear it here, BEFORE the early return, or a site whose file was
		// removed by hand would stand down forever and never emit again.
		if ( ! is_readable( $path ) ) {
			if ( is_array( $stored ) && isset( $stored['legacy_file'] ) ) {
				unset( $stored['legacy_file'] );
				update_option( self::OPTION, $stored, Helper::autoload_ok( $stored ) );
			}

			return;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading our own generated file during migration.
		$decoded  = null;

		if ( false !== $contents && preg_match( "/base64_decode\(\s*'([A-Za-z0-9+\/=]+)'\s*\)/", $contents, $m ) ) {
			$payload = json_decode( base64_decode( $m[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- our own encoding, see the pre-2026-08 template.
			if ( is_array( $payload ) ) {
				$decoded = $payload;
			}
		}

		// Could not read the payload: hand-edited, truncated, or not ours at
		// all. The file is then the ONLY copy of whatever code it holds, so
		// deleting it would destroy authored content — the line uninstall
		// refuses to cross for this same file. Leave it in place, still the
		// emitter, and stand boot() down so nothing is output twice.
		if ( null === $decoded ) {
			self::mark_legacy_file( true );

			return;
		}

		// Import only while the file is still the acknowledged source of truth:
		// no stored code yet, or a previous run could not remove the file and
		// said so. A stored row that HAS code and no marker means the user has
		// edited through the dashboard since, and the older file must not
		// silently revert that.
		//
		// The test is "has code", not "row exists": the version migration
		// seeds an empty row for the front end's benefit, and an empty row
		// must not be mistaken for a deliberate choice — otherwise seeding
		// would quietly cancel this import depending on which listener ran
		// first.
		if ( ! self::has_code( $stored ) || ! empty( $stored['legacy_file'] ) ) {
			self::save( $decoded );
		}

		// Contents are safely in the option now (or deliberately superseded by
		// a newer one), so the file can go. Leaving it would emit everything
		// twice. wp_delete_file() is the WP-filtered unlink.
		wp_delete_file( $path );

		// Could not remove it — record that so boot() stands down and the file
		// stays the only emitter, rather than doubling output.
		self::mark_legacy_file( file_exists( $path ) );
	}

	/**
	 * Whether a stored row actually carries code in any section.
	 *
	 * Distinguishes "the user has scripts" from "a row exists" — the version
	 * migration seeds an empty row, so existence alone means nothing.
	 *
	 * @param mixed $stored Raw option value.
	 * @return bool
	 */
	private static function has_code( $stored ) {
		if ( ! is_array( $stored ) ) {
			return false;
		}

		// Through normalize() rather than reading the raw row: a pre-per-section
		// row stores each section as a flat string, and indexing ['code'] into a
		// string would both miss real code and raise on PHP 8. boot() normalizes
		// for the same reason, so the gate and the emitter agree on what counts.
		return ! self::is_empty( self::normalize( $stored ) );
	}

	/**
	 * Whether a NORMALIZED settings array has no code in any section.
	 *
	 * @param array $data Normalized settings.
	 * @return bool
	 */
	private static function is_empty( array $data ) {
		return '' === $data['header']['code']
			&& '' === $data['body']['code']
			&& '' === $data['footer']['code'];
	}

	/**
	 * Move a pre-2026-08 generated file out of the mu-plugins load path.
	 *
	 * ONE BODY, in File_Ops, because uninstall.php makes exactly the same move
	 * and two hand-rolled copies of "never delete the user's code" is one copy
	 * too many. See that class for why the file keeps its .php extension and
	 * why it moves into a subdirectory rather than being renamed.
	 *
	 * Going through WP_Filesystem also fixes a real failure: the old raw
	 * rename() was performed by the web user, which on an FTP- or SSH-method
	 * host cannot write to mu-plugins at all — so the move silently failed and
	 * the file kept executing. When there is no connection at all the answer is
	 * false, and the caller's existing `legacy_stuck` response already tells the
	 * user to remove the file by hand; that message is now true on those hosts
	 * instead of merely plausible.
	 *
	 * @param string $path Absolute path to the legacy file.
	 * @return bool Whether it is no longer in the load path.
	 */
	private static function retire_legacy_file( string $path ): bool {
		$fs = File_Ops::filesystem();
		if ( is_wp_error( $fs ) ) {
			return ! file_exists( $path );
		}

		return true === File_Ops::retire_legacy_scripts(
			$fs,
			(string) WPMU_PLUGIN_DIR,
			self::LEGACY_MU_FILENAME
		);
	}

	/**
	 * Set or clear the stand-down marker inside the settings row.
	 *
	 * The marker rides INSIDE that row on purpose: as its own option it would
	 * be absent on every healthy site, and an absent row read on the front end
	 * costs a cache-miss query on every request.
	 *
	 * @param bool $stuck Whether a legacy file is still on disk and emitting.
	 * @return void
	 */
	private static function mark_legacy_file( $stuck ) {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			// Nothing stored and a file still emitting: keep a marker-only row
			// so boot() stands down on later requests too.
			if ( $stuck ) {
				$stored = self::normalize( array( 'legacy_file' => true ) );
				add_option( self::OPTION, $stored, '', Helper::autoload_ok( $stored ) );
			}

			return;
		}

		if ( $stuck === ! empty( $stored['legacy_file'] ) ) {
			return;
		}

		if ( $stuck ) {
			$stored['legacy_file'] = true;
		} else {
			unset( $stored['legacy_file'] );
		}

		update_option( self::OPTION, $stored, Helper::autoload_ok( $stored ) );
	}

	private static function normalize( array $data ) {
		$out = array(
			'enabled' => ! isset( $data['enabled'] ) || ! empty( $data['enabled'] ),
			'header'  => self::normalize_section( isset( $data['header'] ) ? $data['header'] : '' ),
			'body'    => self::normalize_section( isset( $data['body'] ) ? $data['body'] : '' ),
			'footer'  => self::normalize_section( isset( $data['footer'] ) ? $data['footer'] : '' ),
		);

		// Survives a re-save: it describes the filesystem, not the settings, so
		// it is never taken from caller input. Only the two places that have
		// actually looked at the disk may clear it — migrate_legacy_file(), and
		// save(), which re-checks the file on every call.
		if ( ! empty( $data['legacy_file'] ) ) {
			$out['legacy_file'] = true;
		}

		return $out;
	}

	/**
	 * Coerce a section into { enabled:bool, code:string }. Accepts the nested
	 * shape and the legacy flat string (pre-per-section saves), which is treated
	 * as an enabled section so existing scripts keep running after upgrade.
	 *
	 * @param mixed $value Nested array or legacy code string.
	 */
	private static function normalize_section( $value ) {
		if ( is_array( $value ) ) {
			return array(
				'enabled' => ! isset( $value['enabled'] ) || ! empty( $value['enabled'] ),
				'code'    => isset( $value['code'] ) ? (string) $value['code'] : '',
			);
		}

		return array(
			'enabled' => true,
			'code'    => (string) $value,
		);
	}
}
