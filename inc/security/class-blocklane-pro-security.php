<?php
/**
 * Security hardening: a small set of opt-in, genuinely-effective toggles that
 * live on the Security screen.
 *
 * All default OFF, so nothing changes until the user opts in — and each one is a
 * no-op (or harmless) where a managed host already covers it:
 *   - disable-xmlrpc         Turn off /xmlrpc.php (brute-force amplification via
 *                            system.multicall + pingback DDoS/SSRF).
 *   - disable-file-editing   Define DISALLOW_FILE_EDIT so the dashboard's theme/
 *                            plugin code editors can't be used to run PHP.
 *   - block-user-enumeration Stop ?author=N probes and the unauthenticated
 *                            /wp/v2/users REST list from leaking usernames.
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

class Security implements Bootable {

	const OPTION = 'blocklane_pro_security';

	/** Login throttle: lock out a client after this many recent failed logins. */
	const LOGIN_FAIL_PREFIX  = 'blocklane_pro_login_fail_';
	const LOGIN_MAX_ATTEMPTS = 5;
	const LOGIN_LOCK_CODE    = 'blocklane_pro_login_locked';

	/** Canonical toggles + defaults (all off). Source of truth for normalize/REST. */
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
	 * The normalized settings (every known toggle as a bool).
	 *
	 * @return array<string,bool>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$out = array();
		foreach ( self::DEFAULTS as $slug => $default ) {
			$out[ $slug ] = isset( $stored[ $slug ] ) ? (bool) $stored[ $slug ] : (bool) $default;
		}
		return $out;
	}

	/**
	 * Persist from a request payload. Unknown keys in the PAYLOAD are ignored;
	 * unknown keys in the STORED row are preserved (Helper::with_foreign_keys —
	 * the other edition, or a newer version of this one, may own them). Omitted
	 * keys keep their CURRENTLY STORED value (self::get()) — never the shipped
	 * default: the REST route accepts partial maps, and filling absences from
	 * DEFAULTS let any partial payload silently reset the other toggles.
	 *
	 * @param array $settings { slug => bool }.
	 * @return array<string,bool>|\WP_Error The normalized settings as stored, or the helper's typed refusal (nothing written).
	 */
	public static function save( array $settings ): array|\WP_Error {
		$current = self::get();

		$next = array();
		foreach ( self::DEFAULTS as $slug => $default ) {
			$next[ $slug ] = isset( $settings[ $slug ] ) ? (bool) $settings[ $slug ] : $current[ $slug ];
		}
		$merged = Helper::with_foreign_keys( self::OPTION, $next, self::DEFAULTS );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		update_option( self::OPTION, $merged );

		return self::get();
	}

	public static function is_on( $slug ) {
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
	public static function forced() {
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
	 */
	private function apply() {
		// Auto-updates are the single highest-impact real-world protection here:
		// outdated plugins/themes are the most common compromise vector.
		if ( self::is_on( 'auto-update-plugins' ) ) {
			add_filter( 'auto_update_plugin', '__return_true' );
		}
		if ( self::is_on( 'auto-update-themes' ) ) {
			add_filter( 'auto_update_theme', '__return_true' );
		}

		if ( self::is_on( 'disable-file-editing' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		if ( self::is_on( 'disable-xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', array( $this, 'strip_pingback_methods' ) );
			add_filter( 'wp_headers', array( $this, 'strip_pingback_header' ) );
		}

		if ( self::is_on( 'block-user-enumeration' ) ) {
			// Priority 0 beats redirect_canonical (which would expose the slug).
			add_action( 'template_redirect', array( $this, 'block_author_archives' ), 0 );
			add_filter( 'rest_endpoints', array( $this, 'restrict_user_endpoints' ) );
			// Drop the users sitemap (wp-sitemap-users.xml) — another enum vector.
			add_filter( 'wp_sitemaps_add_provider', array( $this, 'remove_users_sitemap' ), 10, 2 );
		}

		if ( self::is_on( 'limit-login-attempts' ) ) {
			// Priority 30 runs after core's credential checks, so a locked client
			// is refused even with the right password.
			add_filter( 'authenticate', array( $this, 'block_locked_login' ), 30, 3 );
			add_action( 'wp_login_failed', array( $this, 'record_login_failure' ), 10, 2 );
			add_action( 'wp_login', array( $this, 'clear_login_failures' ), 10, 2 );
		}
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
	 * Per-client login throttle key — keyed on REMOTE_ADDR (hashed with the auth
	 * salt). X-Forwarded-For is intentionally not trusted; behind a reverse proxy
	 * this keys on the proxy address.
	 */
	private function login_throttle_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client IP used for the login throttle. REMOTE_ADDR is the safe
		 * default (X-Forwarded-For is spoofable); trusted-proxy / CDN setups can
		 * supply the real client IP here so the whole edge doesn't share one bucket.
		 *
		 * @param string $ip The REMOTE_ADDR value.
		 */
		$ip = (string) apply_filters( 'blocklane_pro_login_client_ip', $ip );
		$ip = self::normalize_ip_bucket( $ip );

		return self::LOGIN_FAIL_PREFIX . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Collapse an IPv6 address to its /64 network prefix (IPv4 returned unchanged),
	 * so the per-IP throttle can't be sidestepped by rotating addresses within a
	 * routed prefix.
	 *
	 * @param string $ip
	 * @return string
	 */
	private static function normalize_ip_bucket( $ip ) {
		$packed = function_exists( 'inet_pton' ) ? inet_pton( $ip ) : false;
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip; // Not a parseable IPv6 address — use as-is (incl. IPv4).
		}
		$prefix = substr( $packed, 0, 8 ) . str_repeat( "\0", 8 );
		$back   = inet_ntop( $prefix );
		return false !== $back ? $back . '/64' : $ip;
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
