<?php
/**
 * Security hardening: a small set of opt-in, genuinely-effective toggles that
 * live on the Advanced screen (its Updates & Performance and Security &
 * Hardening tabs; the Security screen merged in there in 2026-07).
 *
 * All default OFF, so nothing changes until the user opts in — and each one is a
 * no-op (or harmless) where a managed host already covers it. The four this
 * class enforces itself:
 *   - limit-login-attempts   Lock a client out after repeated failed logins,
 *                            even with the right password (the throttle below).
 *   - disable-xmlrpc         Turn off /xmlrpc.php (brute-force amplification via
 *                            system.multicall + pingback DDoS/SSRF).
 *   - disable-file-editing   Define DISALLOW_FILE_EDIT so the dashboard's theme/
 *                            plugin code editors can't be used to run PHP.
 *   - block-user-enumeration Stop ?author=N probes and the unauthenticated
 *                            /wp/v2/users REST list from leaking usernames.
 *
 * THE OTHER TWO KEYS ARE CONTRIBUTED. auto-update-plugins and
 * auto-update-themes are keys of this store (DEFAULTS) whose ENFORCEMENT
 * belongs to two contributing units, security:auto-update-plugins and
 * security:auto-update-themes (edition-manifest rule 6, the `security.setting`
 * vocabulary): forced auto-updates are Pro. Each unit's runtime hooks the core
 * auto-update filters from inside its callback on the seam below and nowhere
 * else. This file quotes neither core filter name — not here, not in a
 * comment: Plugin Check's update_modification_detected keys on the string, the
 * free zip carries this file whole, and editions.free.forbidden_strings fails
 * that zip on it.
 *
 * ONE VIEW, FIVE DOORS. DEFAULTS is the shipped schema; what THIS build owns is
 * known() — every door (get(), save()'s refusal, its reads, its writer and the
 * carry-through) reads that one view, the mirror of Advanced::known(). Keys a
 * contributing unit adds to the `security.setting` vocabulary (edition-manifest
 * rule 6) leave the view in an edition without the unit, or with its runtime
 * torn out of the deploy.
 *
 * ONE SEAM. apply() binds the view once, enforces what it owns, and fires
 * blocklane_pro_security_enforce with that same map: the one door through which
 * a contributing unit enforces its own key (spec 2026-09-28).
 *
 * Deliberately NOT included (security theatre or host/CDN territory): hiding the
 * WP version, renaming wp-login, changing the table prefix, and HTTP security
 * headers (those overlap with hosts/CDNs and duplicate headers can backfire).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Security implements Bootable {

	const OPTION = 'blocklane_pro_security';

	/** Login throttle: lock out a client after this many recent failed logins. */
	const LOGIN_FAIL_PREFIX  = 'blocklane_pro_login_fail_';
	const LOGIN_MAX_ATTEMPTS = 5;
	const LOGIN_LOCK_CODE    = 'blocklane_pro_login_locked';

	/**
	 * THE SHIPPED TABLE, whole in both editions: the schema Pro reads a
	 * free-written row through (a key free never authored falls to Pro's own
	 * default, #857) and the vocabulary `security.setting` the manifest's
	 * contributing units add to. Not what this build OWNS — that is known().
	 * Read only by known() and absent(): a read anywhere else is a PHPStan
	 * error, blocklane.chokepointMember (tools/phpstan-rules/rules.neon).
	 */
	const DEFAULTS = array(
		'auto-update-plugins'    => false,
		'auto-update-themes'     => false,
		'limit-login-attempts'   => false,
		'disable-xmlrpc'         => false,
		'disable-file-editing'   => false,
		'block-user-enumeration' => false,
	);

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->apply();
	}

	/**
	 * The normalized settings — every key this EDITION owns, as a bool. A key
	 * the stored row holds for a unit this build does not carry is not
	 * returned: a reader never sees, edits or echoes a key it cannot write
	 * (the dashboard PUTs this object back whole), and it survives in the row
	 * untouched (save()).
	 *
	 * @return array<string, bool>
	 */
	public static function get(): array {
		return self::normalized( self::known() );
	}

	/**
	 * get()'s read, over a known() view the caller already derived: the stored
	 * row normalized to exactly the keys of $known. save() reads the row twice
	 * (the current values, then what it stored) through the ONE view it bound,
	 * instead of deriving the view again for each read (#1005).
	 *
	 * @param array<string, bool> $known The known() view.
	 * @return array<string, bool>
	 */
	private static function normalized( array $known ): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$out = array();
		foreach ( $known as $slug => $default ) {
			$out[ $slug ] = isset( $stored[ $slug ] ) ? (bool) $stored[ $slug ] : $default;
		}
		return $out;
	}

	/**
	 * The keys this EDITION owns — the one definition of "known" for this
	 * store: DEFAULTS minus every key contributed (manifest rule 6, the
	 * `security.setting` vocabulary) by a unit this edition does not carry
	 * OR whose runtime did not load (Modules::content_missed — a torn deploy
	 * is a key with no code behind it, the #844 hazard, and the store refuses
	 * to persist an ON it cannot honor).
	 *
	 * Readers: get() (through normalized()) and save(), which binds it once:
	 * the refusal takes its complement (absent()), both reads of the row
	 * normalize to it, the writer iterates it, and with_foreign_keys() takes
	 * it as $known — so a Pro-stored value is FOREIGN under free by
	 * construction and rides through raw. A read of the DEFAULTS constant or
	 * a call of Edition::contributor() anywhere else in this class is a
	 * PHPStan error (blocklane.chokepointMember).
	 *
	 * MIRROR of Advanced::known() — same doors; a vocabulary resolver instead
	 * of the toggle map, plus the loader consult. Change one, read the other.
	 * A third store with edition-split keys lifts both into one helper (spec
	 * 2026-09-28 D5).
	 *
	 * Outside the mechanism, and stated so nobody documents it wider: a RAW
	 * reader of the row (get_option( 'blocklane_pro_security' )) that honored
	 * a contributed key would bypass this view. Today the raw readers are
	 * Helper::with_foreign_keys() (generic: writes what it is handed),
	 * inc/security/lifecycle.php (a seed of an empty row), uninstall.php (a
	 * sweep by name) and the batteries; none honors a key.
	 *
	 * Not memoized: Edition::data() already is, and a per-request cache is one
	 * more state a battery could leave stale.
	 *
	 * @return array<string, bool> Key => shipped default, this edition's subset.
	 */
	private static function known(): array {
		$known = array();
		foreach ( self::DEFAULTS as $slug => $default ) {
			$unit = Edition::contributor( 'security.setting', (string) $slug );
			if ( null !== $unit && ( ! Edition::has( $unit ) || Modules::content_missed( $unit ) ) ) {
				continue;
			}
			$known[ $slug ] = $default;
		}
		return $known;
	}

	/**
	 * The complement of the known() view it is handed: shipped keys whose unit
	 * this edition does not carry, or whose runtime did not load. Empty in a
	 * whole Pro deploy. Read by save()'s refusal and nothing else.
	 *
	 * @param array<string, bool> $known The known() view save() bound.
	 * @return array<string, bool>
	 */
	private static function absent( array $known ): array {
		return array_diff_key( self::DEFAULTS, $known );
	}

	/**
	 * Persist from a request payload. Unknown keys in the PAYLOAD are ignored;
	 * keys in the STORED row this edition does not own — another edition's, or
	 * a newer build's — are preserved verbatim (Helper::with_foreign_keys,
	 * handed known() as its template, so an absent unit's key is foreign by
	 * construction). Omitted keys keep their CURRENTLY STORED value (normalized
	 * to the view, as get() reads it) — never the shipped default: the REST
	 * route accepts partial maps, and filling absences from DEFAULTS let any
	 * partial payload silently reset the other toggles.
	 *
	 * @param array<string, mixed> $settings { slug => bool }.
	 * @return array<string, bool>|\WP_Error The normalized settings as stored, or the typed refusal (nothing written).
	 */
	public static function save( array $settings ): array|\WP_Error {
		// The view, once: every step below reads this binding (#1005).
		$known = self::known();

		// A key this edition does not own is refused before anything is
		// written (#844, the Advanced posture): get() never hands one out, so
		// the only clients that send one are a stale Pro tab after Pro left,
		// a torn Pro deploy's live row, and a hand-built request. Accepting
		// it would store an ON with no code behind it — honored, without
		// anyone opting in, the day the code arrives. The whole request is
		// refused rather than the key ignored, so a stale tab reverts instead
		// of believing it saved. Same code and data shape as Advanced's, so
		// the dashboard has one error path; the sentence covers both reasons.
		foreach ( array_keys( self::absent( $known ) ) as $slug ) {
			if ( array_key_exists( $slug, $settings ) ) {
				return new \WP_Error(
					'blocklane_pro_absent_toggle',
					sprintf(
						/* translators: %s: the setting key. */
						__( 'The "%s" setting belongs to a part of Blocklane that is not in this build or did not load.', 'blocklane' ),
						$slug
					),
					array(
						'status' => 400,
						'toggle' => $slug,
					)
				);
			}
		}

		$current = self::normalized( $known );

		// The writer authors exactly the keys this edition owns: a key for a
		// unit this build does not carry is never authored here, so on a site
		// that never ran the other edition it stays ABSENT, and on one that
		// did, the stored value rides through untouched.
		$next = array();
		foreach ( $known as $slug => $default ) {
			$next[ $slug ] = isset( $settings[ $slug ] ) ? (bool) $settings[ $slug ] : $current[ $slug ];
		}

		// Carry through every stored key this EDITION does not own. The
		// template is known(), never the shipped table: merge_foreign() keeps
		// a raw key only when the template lacks it, so handing it DEFAULTS
		// would make the contributed keys "known" and erase Pro's stored
		// values on every free save (#968).
		$merged = Helper::with_foreign_keys( self::OPTION, $next, $known );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		update_option( self::OPTION, $merged );

		return self::normalized( $known );
	}

	/**
	 * Whether a toggle this edition owns is on. A key outside the view — a
	 * contributed key under an edition without its unit — answers false.
	 */
	public static function is_on( string $slug ): bool {
		$settings = self::get();
		return ! empty( $settings[ $slug ] );
	}

	/**
	 * Toggles whose effective state is forced ON by the environment (a wp-config
	 * constant), independent of the stored option. The dashboard renders these as
	 * locked-on so the user isn't misled into thinking they can turn them off — e.g.
	 * a managed host that already defines DISALLOW_FILE_MODS.
	 *
	 * File editing is off outside our control when DISALLOW_FILE_MODS is set (it also
	 * removes the file editor), or when DISALLOW_FILE_EDIT is defined by the
	 * environment rather than by our own toggle — detected as "the constant is on but
	 * the stored toggle isn't," which is what our apply() keys off too.
	 *
	 * @return array<string,bool> slug => true when forced on by the environment.
	 */
	public static function forced(): array {
		$forced = array();
		$stored = self::get();

		$file_mods_locked   = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;
		$file_edit_external = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT
			&& empty( $stored['disable-file-editing'] );
		if ( $file_mods_locked || $file_edit_external ) {
			$forced['disable-file-editing'] = true;
		}

		return $forced;
	}

	/* ---- enforcement ------------------------------------------------------- */

	/**
	 * Register the hooks for whichever toggles are on. Runs at bootstrap; the
	 * individual hooks fire only in their own context (xmlrpc/admin/front/REST).
	 * The view is bound ONCE: every block below and the action read the same
	 * map, where each is_on() used to re-read and re-derive it.
	 */
	private function apply(): void {
		$settings = self::get();

		if ( ! empty( $settings['disable-file-editing'] ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant: defining it is the documented way to turn the file editors off.
			define( 'DISALLOW_FILE_EDIT', true );
		}

		if ( ! empty( $settings['disable-xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', array( $this, 'strip_pingback_methods' ) );
			add_filter( 'wp_headers', array( $this, 'strip_pingback_header' ) );
		}

		if ( ! empty( $settings['block-user-enumeration'] ) ) {
			// Priority 0 beats redirect_canonical (which would expose the slug).
			add_action( 'template_redirect', array( $this, 'block_author_archives' ), 0 );
			add_filter( 'rest_endpoints', array( $this, 'restrict_user_endpoints' ) );
			// Drop the users sitemap (wp-sitemap-users.xml) — another enum vector.
			add_filter( 'wp_sitemaps_add_provider', array( $this, 'remove_users_sitemap' ), 10, 2 );
		}

		if ( ! empty( $settings['limit-login-attempts'] ) ) {
			// Priority 30 runs after core's credential checks, so a locked client
			// is refused even with the right password.
			add_filter( 'authenticate', array( $this, 'block_locked_login' ), 30, 3 );
			add_action( 'wp_login_failed', array( $this, 'record_login_failure' ), 10, 2 );
			add_action( 'wp_login', array( $this, 'clear_login_failures' ), 10, 2 );
		}

		/**
		 * Fires once at boot with the normalized settings this edition OWNS,
		 * after the toggles above are applied. THE ONE DOOR a contributing unit
		 * (edition-manifest rule 6, the `security.setting` vocabulary) enforces
		 * its setting through: the contributor reads ITS key from this map and
		 * hooks whatever core filter enforces it, inside its callback and never
		 * at file scope (bin/contributor-scope-check.php, wiring check 7). A key
		 * is in the map only when its unit is present in this edition AND its
		 * runtime loaded (known()), so an absent or torn contributor never
		 * enforces, and a build that lacks the contributor never quotes the core
		 * filter it hooks.
		 *
		 * @param array<string, bool> $settings Security::get(), bound once.
		 */
		do_action( 'blocklane_pro_security_enforce', $settings );
	}

	public function strip_pingback_methods( $methods ) {
		if ( is_array( $methods ) ) {
			unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		}
		return $methods;
	}

	public function strip_pingback_header( $headers ) {
		if ( is_array( $headers ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Send ?author=N / author-archive probes from logged-out visitors back home,
	 * before the canonical redirect can reveal the author slug.
	 */
	public function block_author_archives() {
		if ( is_author() && ! is_user_logged_in() ) {
			// 302 (not 301): the feature is toggleable, so the redirect must not be
			// permanently cached by browsers — disabling it should restore archives.
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	/**
	 * Remove the users sitemap provider (wp-sitemap-users.xml) so it can't be used
	 * to enumerate author accounts.
	 *
	 * @param \WP_Sitemaps_Provider|null $provider The provider instance.
	 * @param string                     $name     The provider name.
	 * @return \WP_Sitemaps_Provider|null|false
	 */
	public function remove_users_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Hide the user-listing REST endpoints from unauthenticated requests. Logged-in
	 * users keep them so the block editor's author controls keep working.
	 *
	 * @param array $endpoints
	 * @return array
	 */
	public function restrict_user_endpoints( $endpoints ) {
		// Editors keep these (the block editor's author controls need them); anon
		// visitors AND low-privileged roles do not — otherwise a subscriber on an
		// open-registration store (WooCommerce/membership) could enumerate users.
		if ( current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/* ---- login-attempt limiting -------------------------------------------- */

	/**
	 * Per-client login throttle key, through the one derivation every public
	 * throttle shares (Helper::client_key(): REMOTE_ADDR, the
	 * `blocklane_pro_login_client_ip` filter for trusted-proxy and CDN setups,
	 * IPv6 collapsed to its /64, HMAC with the auth salt). A request with no
	 * address shares one bucket, so the lockout still engages (F3).
	 */
	private function login_throttle_key(): string {
		return (string) Helper::client_key( self::LOGIN_FAIL_PREFIX, 'blocklane_pro_login_client_ip', true );
	}

	private function is_login_locked() {
		return (int) get_transient( $this->login_throttle_key() ) >= self::LOGIN_MAX_ATTEMPTS;
	}

	/**
	 * Refuse authentication while the client is locked out — even with valid
	 * credentials — so a brute-forcer can't proceed.
	 *
	 * @param \WP_User|\WP_Error|null $user
	 * @param string                  $username
	 * @param string                  $password
	 * @return \WP_User|\WP_Error|null
	 */
	public function block_locked_login( $user, $username, $password ) {
		unset( $username, $password );
		if ( $this->is_login_locked() ) {
			return new \WP_Error(
				self::LOGIN_LOCK_CODE,
				__( 'Too many failed login attempts. Please try again in a few minutes.', 'blocklane' )
			);
		}
		return $user;
	}

	/**
	 * Count a failed login — but not our own lockout rejection (which would keep
	 * extending the window). Uses the shared atomic counter: the old
	 * read-modify-write transient pair let parallel requests exceed the
	 * 5-attempt limit (both read N, both stored N+1).
	 *
	 * @param string             $username
	 * @param \WP_Error|string|null $error
	 */
	public function record_login_failure( $username, $error = null ) {
		unset( $username );
		if ( $error instanceof \WP_Error && self::LOGIN_LOCK_CODE === $error->get_error_code() ) {
			return;
		}
		Helper::increment_throttle_counter( $this->login_throttle_key(), 15 * MINUTE_IN_SECONDS );
	}

	public function clear_login_failures( $username = '', $user = null ) {
		unset( $username, $user );
		delete_transient( $this->login_throttle_key() );
	}
}
