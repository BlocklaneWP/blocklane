<?php
/**
 * Site Lock gate: the front-end enforcement for the Coming Soon / Maintenance
 * setting (see the Site_Lock store).
 *
 * On a front-end request, when the lock is on and the visitor is neither an
 * editor (logged-in, can edit) nor holding a valid unlock cookie, we render the
 * appropriate editable block template and exit — Coming Soon as 200 with a
 * password form, Maintenance as 503 with no form. The splash gate runs on
 * template_redirect, so wp-admin, wp-login, and cron are never affected; the
 * other logged-out entry points are gated separately — anonymous REST reads
 * via rest_authentication_errors, comments/pingbacks via comments_open/
 * pings_open, nopriv admin-ajax on admin_init, and anonymous XML-RPC methods
 * via xmlrpc_methods (logged-in users pass all of them). Because it lives in
 * the active plugin, deactivating Blocklane Pro makes the site public again (no
 * possible lock-out).
 *
 * Shared page caches (Varnish, WP Engine, nginx) are handled in three parts:
 * while gating, every front-end and REST response is marked no-store (see
 * Site_Lock_Cache and the send_headers wiring), the unlock cookie's
 * wp-postpass_ name rides the hosts' built-in cache-bypass rules, and lock
 * state transitions purge the known caches (Site_Lock::save + deactivation).
 *
 * PUBLIC CONTRACT: render_gate() fires `blocklane_pro_site_lock_splash` (with
 * the mode) as its first statement, before any splash output. The splash
 * reuses wp_head()/wp_footer() to inherit theme styling, which makes it a
 * shared execution surface — every footer tenant runs on it. The action is
 * how a tenant (the popups runtime today) knows it is inside the takeover;
 * did_action() is a precise splash predicate because render_gate() is only
 * reached when the splash actually renders.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Lock_Gate implements Bootable {

	/**
	 * The unlock cookie. Deliberately named with core's post-password prefix:
	 * every major host page cache (WP Engine, Cloudways Varnish, Kinsta,
	 * SiteGround) and page-cache plugin ships a bypass rule matching
	 * "wp-postpass" in the Cookie header, so an unlocked visitor's requests
	 * skip the shared cache with no per-host configuration. A custom cookie
	 * name gets ignored by those caches instead — they keep serving the
	 * stored splash after a successful unlock until someone flushes them.
	 * Core itself only reads its exact 'wp-postpass_' . COOKIEHASH key (an
	 * md5 suffix), so this name can never collide with it.
	 */
	const COOKIE                = 'wp-postpass_blocklane_pro';
	/** The cookie's pre-rename name, still honored so existing unlocks survive the upgrade. */
	const COOKIE_LEGACY         = 'blocklane_pro_site_unlock';
	const NONCE_ACTION          = 'blocklane_pro_site_unlock';
	const FIELD                 = 'blocklane_pro_site_password';
	const PREVIEW_PARAM         = 'blocklane_pro_preview';
	/** The password-form block: namespace and slug are spelled once, the registered name derives from them (#513). */
	const BLOCK_NS              = 'blocklane-pro';
	const BLOCK_SLUG            = 'site-password';
	const BLOCK                 = self::BLOCK_NS . '/' . self::BLOCK_SLUG;
	const TEMPLATE_COMING_SOON  = 'blocklane-pro//coming-soon';
	const TEMPLATE_MAINTENANCE  = 'blocklane-pro//maintenance';

	/** Brute-force throttle: max wrong guesses per client before a short cooldown. */
	const THROTTLE_PREFIX = 'blocklane_pro_unlock_fail_';
	const THROTTLE_MAX    = 5;

	private static $instance = null;

	/** '' | 'expired' (nonce failed) | 'incorrect' (wrong password) — drives the form message. */
	private $unlock_error = '';

	/** @var int When the last successful is_unlocked() verified the pre-rename cookie name, that cookie's expiry (else 0). Drives maybe_gate()'s re-issue. */
	private $legacy_unlock_expires = 0;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_templates' ) );
		add_action( 'init', array( $this, 'register_password_block' ) );
		// Priority 0: gate before anything else renders.
		add_action( 'template_redirect', array( $this, 'maybe_gate' ), 0 );
		// While the lock is gating, EVERY front-end response — the splash and
		// the pages served to unlocked visitors alike — must stay out of shared
		// page caches: both visitor classes share one URL-keyed cache entry, so
		// whichever response is stored first would be served to both. Fires for
		// all front-end requests, before any output.
		add_action( 'send_headers', array( $this, 'send_no_cache_headers' ) );
		// Anonymous REST reads must not bypass the gate — published content is
		// otherwise readable at /wp-json (posts, media, oembed) while "locked".
		add_filter( 'rest_authentication_errors', array( $this, 'gate_rest' ), 99 );
		// A locked site must also refuse comments and pingbacks — those POST
		// directly to wp-comments-post.php / the trackback handler and so never
		// pass through maybe_gate().
		add_filter( 'comments_open', array( $this, 'gate_comments' ), 99 );
		add_filter( 'pings_open', array( $this, 'gate_comments' ), 99 );
		// Anonymous admin-ajax (nopriv) actions are another logged-out entry point
		// that never reaches template_redirect — refuse them while locked.
		add_action( 'admin_init', array( $this, 'gate_ajax' ), 0 );
		// XML-RPC: strip the anonymous-callable methods (pingback.*/demo.*) while
		// locked. Credentialed methods stay — the lock gates visitors, not the
		// owner's publishing tools; full XML-RPC shutdown remains the Security
		// module's disable-xmlrpc option.
		add_filter( 'xmlrpc_methods', array( $this, 'gate_xmlrpc_methods' ), 99 );
		// A persistent Coming Soon / Maintenance badge in the toolbar, so an editor
		// (who bypasses the gate and sees the live site) always knows it's private.
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 100 );
		add_action( 'wp_head', array( $this, 'print_admin_bar_styles' ) );
		add_action( 'admin_head', array( $this, 'print_admin_bar_styles' ) );
		// Activating a classic theme takes this whole gate offline. Warn on the
		// way in, while the plugin is still running to be able to.
		Site_Lock_Theme_Switch::init();
	}

	/* ---- registration ------------------------------------------------------ */

	/**
	 * Register the editable splash templates (Site-Editor customizable).
	 */
	public function register_templates() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		register_block_template(
			self::TEMPLATE_COMING_SOON,
			array(
				'title'       => __( 'Coming Soon', 'blocklane' ),
				'description' => __( 'Shown to visitors while the site is password-protected. Edit it in the Site Editor.', 'blocklane' ),
				'content'     => self::default_template_content( true ),
			)
		);

		register_block_template(
			self::TEMPLATE_MAINTENANCE,
			array(
				'title'       => __( 'Maintenance', 'blocklane' ),
				'description' => __( 'Shown to visitors while the site is in maintenance mode. Edit it in the Site Editor.', 'blocklane' ),
				'content'     => self::default_template_content( false ),
			)
		);
	}

	/**
	 * The password-form block. Server-rendered; insertable in the Site Editor so
	 * authors can place it within the Coming Soon template.
	 */
	public function register_password_block(): void {
		// Through the one registrar: registry-idempotent (init may fire more
		// than once), and a missing build is announced to administrators the
		// way every other block suite announces it — it used to be a
		// WP_DEBUG-only log line, and the splash silently rendered without
		// its form. The log line stays for the debug trail. The block's
		// identity comes from the class constants, so the name the registrar
		// registers, the name the gate renders and the name block.json
		// declares can never be spelled apart (#513).
		$build_dir = BLOCKLANE_PRO_PATH . '/inc/site-lock/build';
		$missing   = Block_Suite::register(
			'site-lock',
			self::BLOCK_NS,
			$build_dir,
			array( self::BLOCK_SLUG ),
			'npm run build:site-password',
			__( 'Site Visibility', 'blocklane' ),
			__( 'The Coming Soon page renders without its password form, so visitors with the password cannot unlock the site, until the assets are rebuilt.', 'blocklane' ),
			array( 'render_callback' => array( __CLASS__, 'render_password_block' ) )
		);
		if ( $missing ) {
			blocklane_pro_log( 'Blocklane: site-password block build missing at ' . $build_dir . '/' . self::BLOCK_SLUG );
		}
	}

	/* ---- the gate ---------------------------------------------------------- */

	public function maybe_gate() {
		if ( ! Site_Lock::is_enabled() || $this->should_bypass() ) {
			return;
		}

		// Let robots.txt and favicon.ico fall through to core — otherwise crawlers
		// receive the HTML splash instead of real robots directives.
		if ( is_robots() || is_favicon() ) {
			return;
		}

		$mode = Site_Lock::mode();

		// A valid preview link unlocks either mode (sets the cookie + redirects).
		$this->maybe_handle_preview();

		if ( Site_Lock::MODE_COMING_SOON === $mode ) {
			// A Coming Soon gate with no password can't be entered by anyone, so
			// it isn't a real gate — stay open rather than lock everyone out.
			if ( ! Site_Lock::has_password() ) {
				return;
			}
			$this->maybe_handle_unlock();
		}

		// Both modes: a valid unlock/preview cookie bypasses the gate. (Maintenance
		// has no password form, but a preview link still lets you in.)
		if ( $this->is_unlocked() ) {
			// Migrate a pre-rename unlock to the current wp-postpass_ name so
			// the visitor gets the host-cache bypass the rename exists for —
			// carrying the ORIGINAL expiry forward, so migration never extends
			// the grant (the legacy cookie itself just dies at that same
			// expiry). Only here: template_redirect priority 0 is guaranteed
			// pre-output, unlike the comments_open path that also reaches
			// is_unlocked() mid-render (where setcookie() would fail on sent
			// headers).
			if ( $this->legacy_unlock_expires > 0 && ! headers_sent() ) {
				$this->set_unlock_cookie( $this->legacy_unlock_expires );
			}
			return;
		}

		$this->render_gate( $mode );
	}

	/**
	 * Editors (and admins) always see the live site so they can work/preview.
	 */
	private function should_bypass() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Mark every front-end response uncacheable while the lock is gating (see
	 * the constructor's send_headers wiring for why this covers all visitors,
	 * not just locked ones).
	 *
	 * @return void
	 */
	public function send_no_cache_headers() {
		if ( Site_Lock::is_gating() ) {
			Site_Lock_Cache::send_no_cache_headers();
		}
	}

	/**
	 * Refuse anonymous REST requests while the site is locked, so published content
	 * (posts/pages/media/oEmbed) isn't readable via /wp-json. Logged-in users and
	 * holders of a valid unlock/preview cookie are unaffected, so the block editor,
	 * dashboard, and authenticated app flows keep working.
	 *
	 * @param mixed $result Existing auth result (WP_Error, true, or null).
	 * @return mixed
	 */
	public function gate_rest( $result ) {
		// While gating, no REST response may enter a shared cache either —
		// send_headers doesn't fire for REST, and an unlocked visitor's
		// anonymous read would otherwise be stored under a cookie-less cache
		// key and served to locked visitors. Applies whatever the outcome.
		if ( Site_Lock::is_gating() ) {
			Site_Lock_Cache::send_no_cache_headers();
		}
		// If another handler already produced a result/error, respect it.
		if ( ! empty( $result ) || is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! Site_Lock::is_enabled() || is_user_logged_in() || $this->is_unlocked() ) {
			return $result;
		}
		// Coming Soon with no password isn't a real gate (no one could enter it).
		if ( Site_Lock::MODE_COMING_SOON === Site_Lock::mode() && ! Site_Lock::has_password() ) {
			return $result;
		}
		$status = ( Site_Lock::MODE_MAINTENANCE === Site_Lock::mode() ) ? 503 : 403;
		return new \WP_Error(
			'blocklane_pro_site_locked',
			__( 'This site is not currently available.', 'blocklane' ),
			array( 'status' => $status )
		);
	}

	/**
	 * Whether the current request is sealed by the lock: it's on with a real gate
	 * and the visitor is neither an editor nor holding a valid unlock/preview cookie
	 * — i.e. exactly the visitors maybe_gate() would show the splash to. Used to also
	 * close comments/pingbacks for them.
	 *
	 * @return bool
	 */
	private function is_locked_out() {
		if ( ! Site_Lock::is_enabled() || $this->should_bypass() || $this->is_unlocked() ) {
			return false;
		}
		// Coming Soon with no password isn't a real gate (no one could enter it).
		if ( Site_Lock::MODE_COMING_SOON === Site_Lock::mode() && ! Site_Lock::has_password() ) {
			return false;
		}
		return true;
	}

	/**
	 * Close comments and pingbacks for locked-out visitors. Shared by the
	 * `comments_open` and `pings_open` filters — both pass ( bool $open, $post_id ).
	 *
	 * @param bool $open Whether comments/pings are currently open.
	 * @return bool
	 */
	public function gate_comments( $open ) {
		return $this->is_locked_out() ? false : $open;
	}

	/**
	 * Refuse anonymous (nopriv) admin-ajax requests while locked. Logged-in AJAX
	 * and unlock-cookie holders pass; runs at admin_init priority 0 so no nopriv
	 * handler fires first.
	 */
	public function gate_ajax() {
		if ( ! wp_doing_ajax() || is_user_logged_in() ) {
			return;
		}
		// Same shared-cache hygiene as gate_rest(): while gating, neither the
		// locked-out 403 nor an unlocked visitor's real nopriv response may be
		// stored. Core's admin-ajax bootstrap sends only no-cache, which some
		// host caches don't honor (see Site_Lock_Cache).
		if ( Site_Lock::is_gating() ) {
			Site_Lock_Cache::send_no_cache_headers();
		}
		if ( ! $this->is_locked_out() ) {
			return;
		}
		wp_die( '-1', '', array( 'response' => 403 ) );
	}

	/**
	 * Strip the anonymous-callable XML-RPC methods (pingback.*, demo.*) for
	 * locked-out visitors. Everything else on the endpoint authenticates with
	 * credentials per call, which the lock does not gate.
	 *
	 * @param array $methods Method name => handler map.
	 * @return array
	 */
	public function gate_xmlrpc_methods( $methods ) {
		if ( ! $this->is_locked_out() ) {
			return $methods;
		}
		foreach ( array_keys( $methods ) as $method ) {
			if ( 0 === strpos( $method, 'pingback.' ) || 0 === strpos( $method, 'demo.' ) ) {
				unset( $methods[ $method ] );
			}
		}
		return $methods;
	}

	/* ---- admin-bar badge --------------------------------------------------- */

	/**
	 * Whether to surface the Coming Soon / Maintenance badge in the toolbar: the
	 * lock is on and actually gating, the toolbar is showing, and the current user
	 * is one who bypasses the gate (editor+) — so they see the live site and should
	 * be reminded it's private.
	 *
	 * @return bool
	 */
	private function should_show_admin_bar_notice() {
		if ( ! is_admin_bar_showing() || ! Site_Lock::is_enabled() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		// A passwordless Coming Soon isn't a real gate (matches maybe_gate()).
		if ( Site_Lock::MODE_COMING_SOON === Site_Lock::mode() && ! Site_Lock::has_password() ) {
			return false;
		}
		return true;
	}

	/**
	 * Add the Coming Soon / Maintenance badge to the toolbar. Links to the Site
	 * Privacy screen for admins; a plain badge for editors (who can't manage it).
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar_notice( $wp_admin_bar ) {
		if ( ! $this->should_show_admin_bar_notice() ) {
			return;
		}

		$coming_soon = Site_Lock::MODE_COMING_SOON === Site_Lock::mode();
		$label       = $coming_soon
			? __( 'Coming Soon', 'blocklane' )
			: __( 'Maintenance Mode', 'blocklane' );
		$tip         = $coming_soon
			? __( 'Your site is private — visitors see the Coming Soon page.', 'blocklane' )
			: __( 'Your site is in maintenance mode — visitors see the maintenance page.', 'blocklane' );

		$href = current_user_can( 'manage_options' )
			? admin_url( 'admin.php?page=' . Branding::MENU_SLUG . '&screen=site-privacy' )
			: false;

		$wp_admin_bar->add_node(
			array(
				'id'    => 'blocklane-pro-site-lock',
				// A small status dot (not a background) so the item sits natively among
				// the other toolbar tools, like the dashboard's status indicators. The
				// dot is colored by mode to match the dashboard's status pill, and the
				// label names the active mode so it's clear what's running.
				'title' => '<span class="blocklane-pro-site-lock-dot '
					. ( $coming_soon ? 'is-coming-soon' : 'is-maintenance' )
					. '" aria-hidden="true"></span>'
					. esc_html( $label ),
				'href'  => $href ? esc_url( $href ) : false,
				'meta'  => array( 'title' => $tip ),
			)
		);
	}

	/**
	 * Style the badge's status dot (a mode-colored indicator, no background — the
	 * item stays native to the rest of the toolbar). Printed in both the front-end
	 * and admin document heads, since the toolbar shows in both. The dot colors
	 * mirror the dashboard's Site Visibility status pill: Coming Soon uses the
	 * "locked" gold (#f5b301), Maintenance the warning orange (#b45309).
	 */
	public function print_admin_bar_styles() {
		if ( ! $this->should_show_admin_bar_notice() ) {
			return;
		}
		?>
<style id="blocklane-pro-site-lock-adminbar">
	#wpadminbar #wp-admin-bar-blocklane-pro-site-lock > .ab-item {
		display: flex;
		align-items: center;
	}
	#wpadminbar #wp-admin-bar-blocklane-pro-site-lock .blocklane-pro-site-lock-dot {
		width: 8px;
		height: 8px;
		margin-right: 7px;
		border-radius: 50%;
		background: #dba617;
	}
	#wpadminbar #wp-admin-bar-blocklane-pro-site-lock .blocklane-pro-site-lock-dot.is-coming-soon {
		background: #f5b301;
	}
	#wpadminbar #wp-admin-bar-blocklane-pro-site-lock .blocklane-pro-site-lock-dot.is-maintenance {
		background: #b45309;
	}
</style>
		<?php
	}

	/* ---- preview link ------------------------------------------------------ */

	/**
	 * A valid ?blocklane_pro_preview=<key> unlocks the gate (sets the same cookie a
	 * correct password would) and redirects to strip the key from the URL, so a
	 * shared preview link works without the password and doesn't linger in the
	 * address bar / history.
	 *
	 * No nonce, by design: the link is opened cold by a visitor who has no
	 * session, so there is nothing to bind a nonce to. Authorization is the
	 * secret key itself, compared in Site_Lock::check_preview_key() in
	 * constant time. It is NOT throttled: the password form's per-client
	 * throttle does not cover this path, and the key's defense is its size —
	 * 24 random alphanumerics (about 143 bits), which no request rate can
	 * guess. The parameter is read-only input that is sanitized before use
	 * and never echoed.
	 */
	private function maybe_handle_preview() {
		if ( ! isset( $_GET[ self::PREVIEW_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- capability-less preview link; the secret key checked below is the authorization.
			return;
		}
		$key = sanitize_text_field( wp_unslash( $_GET[ self::PREVIEW_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same: authorized by the key, not a nonce.
		if ( ! Site_Lock::check_preview_key( $key ) ) {
			return; // invalid key: fall through to the normal gate.
		}

		$this->set_unlock_cookie();
		wp_safe_redirect( remove_query_arg( self::PREVIEW_PARAM, $this->current_url() ) );
		exit;
	}

	/* ---- unlock + cookie --------------------------------------------------- */

	private function maybe_handle_unlock() {
		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE_ACTION . '_nonce' ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_ACTION . '_nonce' ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->unlock_error = 'expired';
			return;
		}

		// Brute-force throttle: after too many recent wrong guesses from this
		// client, refuse further attempts until the cooldown passes.
		if ( $this->is_throttled() ) {
			$this->unlock_error = 'throttled';
			return;
		}

		$password = wp_unslash( $_POST[ self::FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared against a hash, not stored/echoed.
		if ( ! is_string( $password ) || ! Site_Lock::check_password( $password ) ) {
			$this->record_failed_attempt();
			$this->unlock_error = 'incorrect';
			return;
		}

		$this->clear_failed_attempts();
		$this->set_unlock_cookie();
		wp_safe_redirect( $this->current_url() );
		exit;
	}

	/* ---- brute-force throttle ---------------------------------------------- */

	/**
	 * Per-client throttle key. Keyed on the remote address (hashed with the auth
	 * salt so the transient name isn't guessable). X-Forwarded-For is intentionally
	 * not trusted — it's spoofable.
	 */
	private function throttle_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return self::THROTTLE_PREFIX . md5( $ip . '|' . wp_salt( 'auth' ) );
	}

	private function is_throttled() {
		return (int) get_transient( $this->throttle_key() ) >= self::THROTTLE_MAX;
	}

	private function record_failed_attempt() {
		// Shared atomic counter — a get/set transient pair is a read-modify-write
		// that parallel guesses can use to exceed THROTTLE_MAX.
		Helper::increment_throttle_counter( $this->throttle_key(), 15 * MINUTE_IN_SECONDS );
	}

	private function clear_failed_attempts() {
		delete_transient( $this->throttle_key() );
	}

	/**
	 * The unlock cookie is "<expiry>|<hmac>", where the HMAC covers the expiry
	 * timestamp plus the current password token + preview key. So: rotating the
	 * password OR regenerating the preview link instantly invalidates every
	 * existing unlock; the value can't be forged without the site's auth salt;
	 * and a captured cookie stops working server-side when its expiry passes —
	 * the client-side `expires` alone would leave a stolen value valid forever.
	 * Including the preview key means the same cookie also gates Maintenance
	 * mode (which has no password), so a preview link works there too.
	 *
	 * @param int $expires Unix timestamp the token is valid until (HMAC-signed).
	 */
	private function unlock_token( $expires ) {
		$basis = Site_Lock::password_token() . '|' . Site_Lock::preview_key();
		return hash_hmac( 'sha256', 'blocklane-pro-site-unlock|' . (int) $expires, $basis . wp_salt( 'auth' ) );
	}

	private function is_unlocked() {
		$this->legacy_unlock_expires = 0;

		// The current name first; the pre-rename name still verifies (same token
		// format) so an already-unlocked visitor isn't re-locked by the upgrade —
		// though only the new name bypasses host page caches, so maybe_gate()
		// re-issues under it when the legacy branch is the one that verified.
		/*
		 * sanitize_text_field() on a value that is then hash_equals()'d looks
		 * redundant, and would be dangerous if it could MANGLE a valid token —
		 * this codebase has had a sanitizer make a whole grammar unreachable
		 * that way. It cannot here, and the reason is provable rather than
		 * lucky: every value hash_equals() can accept matches
		 * ^\d+\|[0-9a-f]{64}$ (see the issue() side), a string of digits, a
		 * pipe and lowercase hex. sanitize_text_field() strips tags, nulls and
		 * control characters and collapses whitespace — none of which occurs in
		 * that alphabet — so it is byte-for-byte the identity on every token
		 * that could verify, and only ever alters one that could not.
		 */
		$via_legacy = false;
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		} elseif ( ! empty( $_COOKIE[ self::COOKIE_LEGACY ] ) ) {
			$raw        = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_LEGACY ] ) );
			$via_legacy = true;
		} else {
			return false;
		}
		// Need a non-empty basis (a password and/or a preview key) for the token to
		// be site-specific; maybe_gate() only runs this when the lock is enabled,
		// at which point a preview key always exists.
		if ( ! Site_Lock::has_password() && '' === Site_Lock::preview_key() ) {
			return false;
		}
		$parts = explode( '|', $raw, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		$expires = (int) $parts[0];
		if ( $expires < time() ) {
			return false; // Server-side expiry: the visitor can't extend it by editing the cookie.
		}
		$ok = hash_equals( $this->unlock_token( $expires ), $parts[1] );

		$this->legacy_unlock_expires = ( $ok && $via_legacy ) ? $expires : 0;

		return $ok;
	}

	/**
	 * Issue the unlock cookie.
	 *
	 * @param int $expires Expiry to grant; 0 (a fresh unlock) mints the full
	 *                     14-day window. The legacy-cookie migration passes the
	 *                     original expiry through so migrating never extends it.
	 * @return void
	 */
	private function set_unlock_cookie( $expires = 0 ) {
		$expires = $expires > 0 ? (int) $expires : time() + 14 * DAY_IN_SECONDS;
		setcookie(
			self::COOKIE,
			$expires . '|' . $this->unlock_token( $expires ),
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	private function current_url() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return home_url( $path );
	}

	/* ---- rendering --------------------------------------------------------- */

	/**
	 * Resolve the effective template, preferring the author's Site-Editor edits.
	 *
	 * register_block_template() registers the default under the plugin namespace
	 * (blocklane-pro//slug), but when an author customizes it in the Site Editor the
	 * override is saved under the *active theme* (stylesheet//slug). Looking up the
	 * plugin id alone therefore always returns the un-edited default — so we check
	 * for the theme-namespaced customization first and fall back to the default.
	 *
	 * @param string $registered_id Plugin-namespaced id (self::TEMPLATE_*).
	 * @return \WP_Block_Template|null
	 */
	private static function resolve_template( $registered_id ) {
		if ( ! function_exists( 'get_block_template' ) ) {
			return null;
		}

		$slug = $registered_id;
		if ( false !== strpos( $registered_id, '//' ) ) {
			list( , $slug ) = explode( '//', $registered_id, 2 );
		}

		// Author customization lives under the active theme's namespace.
		$custom = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
		if ( $custom && ! empty( $custom->content ) ) {
			return $custom;
		}

		// Otherwise the plugin-registered default.
		return get_block_template( $registered_id, 'wp_template' );
	}

	private function render_gate( $mode ) {
		/**
		 * The splash is now the whole response: everything downstream — the
		 * template's do_blocks(), wp_head(), wp_footer() — runs inside the
		 * takeover. Fired first so any footer tenant (the popups runtime) can
		 * detect the splash via did_action() and suppress itself; see the file
		 * header's PUBLIC CONTRACT note.
		 *
		 * @param string $mode Site_Lock::MODE_COMING_SOON | MODE_MAINTENANCE.
		 */
		do_action( 'blocklane_pro_site_lock_splash', $mode );

		$coming_soon = Site_Lock::MODE_COMING_SOON === $mode;

		// Belt over the send_headers wiring: whatever path reached the splash,
		// it must never be stored by a page cache (a stored splash means a
		// stale nonce in its form, and being served it after unlocking).
		Site_Lock_Cache::send_no_cache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		header( 'X-Robots-Tag: noindex, nofollow', true );

		// The reveal toggle uses Dashicons (WP's native show/hide-password icons).
		wp_enqueue_style( 'dashicons' );

		if ( $coming_soon ) {
			status_header( 200 );
		} else {
			status_header( 503 );
			header( 'Retry-After: 3600' );
		}

		$template = $coming_soon ? self::TEMPLATE_COMING_SOON : self::TEMPLATE_MAINTENANCE;
		$tpl      = self::resolve_template( $template );
		$content  = ( $tpl && ! empty( $tpl->content ) ) ? do_blocks( $tpl->content ) : self::fallback_content( $coming_soon );

		// Safety net: a Coming Soon page must always offer a way in. If the author
		// removed the password block, append the form so the site can't be locked
		// beyond reach.
		if ( $coming_soon && false === strpos( $content, 'blocklane-pro-site-lock__form' ) ) {
			$content .= self::render_password_form();
		}

		// Don't leak the hidden page's identity through wp_head(): strip canonical,
		// feed, oEmbed/REST discovery, shortlink, and RSD links, and force a neutral
		// document title instead of the requested (hidden) post's title.
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );

		$neutral_title = $coming_soon
			/* translators: %s: site name. */
			? sprintf( __( '%s — Coming soon', 'blocklane' ), get_bloginfo( 'name' ) )
			/* translators: %s: site name. */
			: sprintf( __( '%s — Under maintenance', 'blocklane' ), get_bloginfo( 'name' ) );
		add_filter(
			'pre_get_document_title',
			static function () use ( $neutral_title ) {
				return $neutral_title;
			}
		);

		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
	<title><?php echo esc_html( $neutral_title ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
	<style>
		body.blocklane-pro-site-lock { margin: 0; }
		.blocklane-pro-site-lock__layout {
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 1rem;
			text-align: center;
			box-sizing: border-box;
		}
		.blocklane-pro-site-lock__form {
			display: flex;
			flex-direction: column;
			gap: 12px;
			width: 100%;
			text-align: left;
		}
		.blocklane-pro-site-lock__label {
			margin: 0;
			font-size: 0.75em;
			font-weight: 700;
			letter-spacing: 0.05em;
			text-transform: uppercase;
			color: inherit;
		}
		.blocklane-pro-site-lock__inputwrap {
			position: relative;
			display: block;
		}
		.blocklane-pro-site-lock__input {
			width: 100%;
			box-sizing: border-box;
			padding: 12px 44px 12px 14px;
			border: 1px solid var( --wp--preset--color--outline, rgba( 0, 0, 0, 0.15 ) );
			border-radius: 4px;
			background: #fff;
			color: #1e1e1e;
			font-size: 16px;
			font-family: inherit;
		}
		.blocklane-pro-site-lock__reveal {
			position: absolute;
			top: 50%;
			right: 6px;
			transform: translateY( -50% );
			display: inline-flex;
			padding: 4px;
			border: 0;
			background: none;
			color: #1e1e1e;
			opacity: 0.55;
			cursor: pointer;
		}
		.blocklane-pro-site-lock__reveal:hover { opacity: 1; }
		/* Submit reuses the theme's button element styles (wp-element-button); we
		   only force full width so color/radius/typography track Global Styles. */
		.blocklane-pro-site-lock__submit { width: 100%; }
		.blocklane-pro-site-lock__error {
			margin: 0;
			font-size: 0.875em;
			color: #cc1818;
		}
		.blocklane-pro-site-lock__hint { display: none; }
	</style>
</head>
<body <?php body_class( 'blocklane-pro-site-lock' ); ?>>
	<main class="blocklane-pro-site-lock__main">
	<?php
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block + form output is already escaped at the source.
	?>
	</main>
	<?php
	wp_footer();
	// The password field's show/hide toggle — the splash is a standalone
	// document (no theme template), so it prints its one script itself,
	// through core's inline-script printer.
	wp_print_inline_script_tag(
		'( function () {
		document.addEventListener( "click", function ( e ) {
			var btn = e.target.closest( ".blocklane-pro-site-lock__reveal" );
			if ( ! btn ) {
				return;
			}
			var input = btn.parentNode.querySelector( ".blocklane-pro-site-lock__input" );
			if ( ! input ) {
				return;
			}
			var show = input.type === "password";
			input.type = show ? "text" : "password";
			btn.setAttribute( "aria-pressed", show ? "true" : "false" );
			var icon = btn.querySelector( ".dashicons" );
			if ( icon ) {
				icon.classList.toggle( "dashicons-visibility", ! show );
				icon.classList.toggle( "dashicons-hidden", show );
			}
		} );
	} )();'
	);
	?>
</body>
</html>
		<?php
		exit;
	}

	/* ---- the password form (used by the block + the safety-net append) ----- */

	/**
	 * Block render callback. Renders the unlock form in Coming Soon mode; nothing
	 * in Maintenance (which has no password) or once unlocked.
	 *
	 * @return string
	 */
	public static function render_password_block( $attributes = array() ) {
		if ( Site_Lock::MODE_COMING_SOON !== Site_Lock::mode() ) {
			return '';
		}

		// Carry the block's container design controls (color, spacing, border,
		// typography) onto the form so author styling from the editor reaches the
		// front end.
		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'class' => 'blocklane-pro-site-lock__form' ) )
			: 'class="blocklane-pro-site-lock__form"';

		// Optional per-instance submit-button styling. Empty values fall through to
		// the theme button (wp-element-button).
		$button = array(
			'text'       => isset( $attributes['buttonText'] ) ? $attributes['buttonText'] : '',
			'background' => isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '',
			'color'      => isset( $attributes['buttonTextColor'] ) ? $attributes['buttonTextColor'] : '',
			'radius'     => isset( $attributes['buttonBorderRadius'] ) ? $attributes['buttonBorderRadius'] : '',
		);

		return self::render_password_form( $wrapper, $button );
	}

	/**
	 * Allow only safe CSS color values (hex, rgb/rgba, or a CSS custom property);
	 * reject anything else, so author-set inline colors can't smuggle in markup.
	 *
	 * @param string $value
	 * @return string Sanitized color or '' if not a recognized color.
	 */
	private static function sanitize_css_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value )
			// Real rgb()/rgba() component structure — the old loose character
			// class accepted garbage like rgb(,,,) or rgba(.....).
			|| preg_match( '/^rgba?\(\s*[0-9]+(?:\.[0-9]+)?%?(?:(?:\s*,\s*|\s+)[0-9]+(?:\.[0-9]+)?%?){2}(?:(?:\s*,\s*|\s*\/\s*)(?:0|1|0?\.[0-9]+)%?)?\s*\)$/i', $value )
			|| preg_match( '/^var\(\s*--[a-z0-9-]+\s*\)$/i', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Allow only a safe CSS length (number + px/em/rem/% or a bare number → px).
	 *
	 * @param string $value
	 * @return string Sanitized length or '' if not recognized.
	 */
	private static function sanitize_css_length( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(0|[0-9]+(\.[0-9]+)?(px|em|rem|%|vh|vw))$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) ) {
			return $value . 'px';
		}
		return '';
	}

	/**
	 * The unlock form markup — a self-contained, theme-styled form (modelled on
	 * wp-login.php's structure) so it drops into any design.
	 *
	 * @param string $wrapper_attributes Pre-built attributes for the <form> (from
	 *                                   block supports). Defaults to the bare class
	 *                                   when called outside a block (safety-net append).
	 * @param array  $button             Optional submit overrides: text, background,
	 *                                   color, radius. Empty → the theme button.
	 * @return string
	 */
	public static function render_password_form( $wrapper_attributes = '', $button = array() ) {
		if ( '' === $wrapper_attributes ) {
			$wrapper_attributes = 'class="blocklane-pro-site-lock__form"';
		}
		$error = self::get_instance()->unlock_error;

		$button_label = ( isset( $button['text'] ) && '' !== trim( (string) $button['text'] ) )
			? $button['text']
			: __( 'Log in', 'blocklane' );

		$button_style = '';
		$bg           = isset( $button['background'] ) ? self::sanitize_css_color( $button['background'] ) : '';
		$fg           = isset( $button['color'] ) ? self::sanitize_css_color( $button['color'] ) : '';
		$radius       = isset( $button['radius'] ) ? self::sanitize_css_length( $button['radius'] ) : '';
		if ( '' !== $bg ) {
			$button_style .= 'background-color:' . $bg . ';';
		}
		if ( '' !== $fg ) {
			$button_style .= 'color:' . $fg . ';';
		}
		if ( '' !== $radius ) {
			$button_style .= 'border-radius:' . $radius . ';';
		}

		ob_start();
		?>
		<form <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes; bare-class fallback is static. ?> method="post" action="<?php echo esc_url( self::get_instance()->current_url() ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_ACTION . '_nonce' ); ?>
			<label class="blocklane-pro-site-lock__label" for="blocklane-pro-site-lock-input"><?php esc_html_e( 'Password', 'blocklane' ); ?></label>
			<span class="blocklane-pro-site-lock__inputwrap">
				<input
					id="blocklane-pro-site-lock-input"
					class="blocklane-pro-site-lock__input"
					type="password"
					name="<?php echo esc_attr( self::FIELD ); ?>"
					autocomplete="current-password"
					required
				>
				<button type="button" class="blocklane-pro-site-lock__reveal" aria-pressed="false" aria-label="<?php esc_attr_e( 'Show password', 'blocklane' ); ?>">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				</button>
			</span>
			<?php if ( '' !== $error ) : ?>
				<p class="blocklane-pro-site-lock__error" role="alert">
					<?php
					if ( 'throttled' === $error ) {
						$message = __( 'Too many attempts. Please wait a few minutes and try again.', 'blocklane' );
					} elseif ( 'expired' === $error ) {
						$message = __( 'Your session expired — please refresh and try again.', 'blocklane' );
					} else {
						$message = __( 'Sorry, that password is incorrect.', 'blocklane' );
					}
					echo esc_html( $message );
					?>
				</p>
			<?php endif; ?>
			<button type="submit" class="blocklane-pro-site-lock__submit wp-block-button__link wp-element-button"<?php echo '' !== $button_style ? ' style="' . esc_attr( $button_style ) . '"' : ''; ?>>
				<?php echo wp_kses( $button_label, array() ); // RichText-sourced label: strip any tags, keep entities (esc_html would double-encode). ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ---- default template content + bare fallback -------------------------- */

	/**
	 * Seed markup for the editable templates. Standalone (no site header/footer)
	 * so a locked site doesn't leak navigation; uses theme presets so it inherits
	 * the brand.
	 *
	 * @param bool $with_password Include the password block (Coming Soon).
	 * @return string
	 */
	private static function default_template_content( $with_password ) {
		if ( $with_password ) {
			return self::coming_soon_template_content();
		}

		// Maintenance: a simple centered notice (no password).
		$inner  = '<!-- wp:site-logo {"width":120,"align":"center"} /-->';
		$inner .= '<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"heading-xxx-large"} --><h1 class="wp-block-heading has-text-align-center has-heading-xxx-large-font-size">' . esc_html__( 'We’ll be right back', 'blocklane' ) . '</h1><!-- /wp:heading -->';
		$inner .= '<!-- wp:paragraph {"align":"center","fontSize":"large"} --><p class="has-text-align-center has-large-font-size">' . esc_html__( 'The site is briefly down for maintenance. Please check back shortly.', 'blocklane' ) . '</p><!-- /wp:paragraph -->';

		// tagName is a plain div: render_gate() wraps all gate output in a single
		// <main> landmark, so the group must not emit its own (nested landmarks).
		return '<!-- wp:group {"tagName":"div","layout":{"type":"constrained","contentSize":"520px"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"},"dimensions":{"minHeight":"100vh"}},"className":"blocklane-pro-site-lock__layout"} -->'
			. '<div class="wp-block-group blocklane-pro-site-lock__layout" style="min-height:100vh">'
			. $inner
			. '</div><!-- /wp:group -->';
	}

	/**
	 * The Coming Soon splash: a two-column layout — a password card on a color
	 * panel (left) and a contact/support list (right). Core blocks + our
	 * site-password block; placeholder copy the author edits in the Site Editor.
	 *
	 * @return string
	 */
	private static function coming_soon_template_content() {
		// One contact row: an emoji "icon", a bold label, and a link.
		$row = static function ( $emoji, $label, $link ) {
			return '<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"12px"}}} -->'
				. '<div class="wp-block-group" style="gap:12px">'
				. '<!-- wp:paragraph {"fontSize":"large","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} --><p class="has-large-font-size" style="margin-top:0;margin-bottom:0">' . $emoji . '</p><!-- /wp:paragraph -->'
				. '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"2px"}}} --><div class="wp-block-group">'
				. '<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} --><p style="margin-top:0;margin-bottom:0;font-weight:600">' . $label . '</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} --><p style="margin-top:0;margin-bottom:0"><a href="#">' . $link . '</a></p><!-- /wp:paragraph -->'
				. '</div><!-- /wp:group -->'
				. '</div><!-- /wp:group -->';
		};

		$rows  = $row( '📞', esc_html__( 'Sales: (555) 123-4567', 'blocklane' ), esc_html__( 'Contact sales', 'blocklane' ) );
		$rows .= $row( '✉️', esc_html__( 'Support: (555) 765-4321', 'blocklane' ), esc_html__( 'Get help', 'blocklane' ) );
		$rows .= $row( '🌐', esc_html__( 'Guides &amp; training', 'blocklane' ), esc_html__( 'Learn more', 'blocklane' ) );
		$rows .= $row( '📍', esc_html__( 'Office', 'blocklane' ), esc_html__( '123 Example St, Suite 100, City, ST', 'blocklane' ) );

		// Left: the white password card on a color panel.
		$card = '<!-- wp:group {"style":{"color":{"background":"#ffffff"},"spacing":{"padding":{"top":"36px","bottom":"36px","left":"36px","right":"36px"},"blockGap":"20px"},"border":{"radius":"8px"}},"layout":{"type":"constrained"}} -->'
			. '<div class="wp-block-group has-background" style="border-radius:8px;background-color:#ffffff;padding-top:36px;padding-right:36px;padding-bottom:36px;padding-left:36px">'
			. '<!-- wp:site-logo {"width":180} /-->'
			. '<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"1.75rem"}}} --><h1 class="wp-block-heading" style="font-size:1.75rem">' . esc_html__( 'Coming soon', 'blocklane' ) . '</h1><!-- /wp:heading -->'
			. '<!-- wp:' . self::BLOCK . ' /-->'
			. '</div><!-- /wp:group -->';

		// Columns stretch to full height (no verticalAlignment, which would shrink
		// them to content); each column flex-centers its own content vertically.
		$left = '<!-- wp:column {"width":"58%","style":{"color":{"background":"#4a9fd4"},"spacing":{"padding":{"top":"56px","bottom":"56px","left":"40px","right":"40px"}}}} -->'
			. '<div class="wp-block-column has-background" style="background-color:#4a9fd4;padding-top:56px;padding-right:40px;padding-bottom:56px;padding-left:40px;flex-basis:58%;display:flex;flex-direction:column;justify-content:center">'
			. '<!-- wp:group {"layout":{"type":"constrained","contentSize":"420px"}} --><div class="wp-block-group">' . $card . '</div><!-- /wp:group -->'
			. '</div><!-- /wp:column -->';

		$right = '<!-- wp:column {"width":"42%","style":{"color":{"background":"#ffffff"},"spacing":{"padding":{"top":"56px","bottom":"56px","left":"40px","right":"40px"}}}} -->'
			. '<div class="wp-block-column has-background" style="background-color:#ffffff;padding-top:56px;padding-right:40px;padding-bottom:56px;padding-left:40px;flex-basis:42%;display:flex;flex-direction:column;justify-content:center">'
			. '<!-- wp:group {"layout":{"type":"constrained","contentSize":"340px"},"style":{"spacing":{"blockGap":"20px"}}} --><div class="wp-block-group">'
			. '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html__( 'Request website support', 'blocklane' ) . '</h2><!-- /wp:heading -->'
			. $rows
			. '</div><!-- /wp:group -->'
			. '</div><!-- /wp:column -->';

		return '<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"0"},"dimensions":{"minHeight":"100vh"}}} -->'
			. '<div class="wp-block-columns" style="min-height:100vh">'
			. $left . $right
			. '</div><!-- /wp:columns -->';
	}

	/**
	 * Bare HTML used only if the block template can't be resolved at all.
	 *
	 * @param bool $coming_soon
	 * @return string
	 */
	private static function fallback_content( $coming_soon ) {
		$html  = '<main class="blocklane-pro-site-lock__layout" style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;padding:2rem;text-align:center">';
		$html .= '<h1>' . ( $coming_soon ? esc_html__( 'Launching soon', 'blocklane' ) : esc_html__( 'We’ll be right back', 'blocklane' ) ) . '</h1>';
		if ( $coming_soon ) {
			$html .= self::render_password_form();
		}
		$html .= '</main>';

		return $html;
	}
}
