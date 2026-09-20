<?php
/**
 * Extensions Handler: loads enabled block-editor extensions and enqueues their assets.
 *
 * The activation model mirrors BlocklanePro Pro v2.5.4's Extensions_Handler 1:1 — the
 * slug list, the (non-1:1) slug -> loader map, the always-loaded set, and the
 * data localized to JS — so behavior matches BlocklanePro exactly. Storage/REST stay on
 * blocklane_pro's per-slug option scheme. BlocklanePro-specific toggles we don't ship
 * (menu-designer auto-install, pattern-library, abilities/AI) are omitted.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Extensions_Handler implements Bootable {

	/**
	 * Canonical extension slugs + defaults. Source of truth for REST validation
	 * and the localized enabled map. Mirrors BlocklanePro's get_enabled_extensions()
	 * defaults (class-manager OFF), minus BlocklanePro-only toggles. The hidden default-on
	 * one (advanced-paragraph) loads but is not surfaced in the admin toggle
	 * list, matching BlocklanePro.
	 */
	const KNOWN_SLUGS = array(
		'animation-designer'  => true,
		'hover-colors'        => true,
		'advanced-group'      => true,
		'advanced-paragraph'  => true,
		'button-icons'        => true,
		'advanced-grid'       => true,
		'class-manager'       => false,
		'smart-sync'          => true,
		'text-wrap'           => true,
		'transparent-header'  => true,
		'video-modal'         => true,
		'responsive-controls' => true,
		'background-url'      => true,
		'icon-library'        => true,
		'advanced-tabs'       => true,
	);

	const OPTION_PREFIX = 'blocklane_pro_ext_';

	/**
	 * The plugin-side halves — REST, editor styles, usage tracking — an
	 * extension loads IN ADDITION to its front-end runtime, keyed by toggle
	 * slug and relative to /inc.
	 *
	 * A table rather than three hand-written requires, so the edition filter
	 * and the torn-deploy check apply to all of them at once instead of to
	 * whichever one someone remembered.
	 *
	 * @var array<string, string>
	 */
	const ADMIN_RUNTIMES = array(
		// Animation preset CRUD (REST, edit_posts-gated).
		'animation-designer' => 'extensions/loader/animation/animation-presets-api.php',
		// Class Manager REST + editor styles + usage tracking; its front-end
		// CSS output is in class-manager-frontend.php, loaded by the runtime.
		'class-manager'      => 'extensions/loader/class-manager/class-manager.php',
		// Icon Library picker REST (append to /wp/v2/icons + custom-icon save).
		// Its front-end RENDER half loads unconditionally from
		// frontend-loader.php, so a switched-off picker never blanks icons
		// already placed in saved content.
		'icon-library'       => 'extensions/loader/icon-library/icon-library-api.php',
	);

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_extensions();

		// enqueue_block_assets is the one editor style hook: in the editor its
		// styles print in the parent page (the chrome — inspector controls
		// included) AND core injects them into the canvas iframe as the
		// sanctioned set. A second enqueue on enqueue_block_editor_assets
		// used to duplicate editor.css for the chrome; core clones that
		// hook's styles into the iframe with a console warning, and the
		// chrome already had the sheet — pure double-load (#15).
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * The slug => shipped-default table FILTERED to this edition.
	 *
	 * KNOWN_SLUGS stays whole — it is data, and both editions ship the same
	 * table so a row written by one is legible to the other. This is the view
	 * of it the running artifact actually carries: every consumer (the toggle
	 * list, the REST validator, the seeding pass, the localized editor map)
	 * reads THIS, so the free build never offers, validates, seeds or
	 * advertises a toggle whose code is not in the zip.
	 *
	 * @return array<string, bool>
	 */
	public static function known_slugs(): array {
		$out = array();
		foreach ( self::KNOWN_SLUGS as $slug => $default ) {
			$unit = 'extension:' . self::UNIT_OF[ $slug ];
			if ( Edition::has( $unit ) ) {
				$out[ $slug ] = $default;
			}
		}
		return $out;
	}

	/**
	 * Toggle slug => manifest unit suffix. The two differ for historical
	 * reasons (hover-colors/hover-color, animation-designer/animation), and a
	 * missing row here would throw from Edition::has() rather than silently
	 * hiding an extension — E6 in bin/edition-battery.php asserts the mapping.
	 */
	const UNIT_OF = array(
		'animation-designer'  => 'animation-designer',
		'hover-colors'        => 'hover-colors',
		'advanced-group'      => 'advanced-group',
		'advanced-paragraph'  => 'paragraph-hover-decoration',
		'button-icons'        => 'button-icons',
		'advanced-grid'       => 'advanced-grid',
		'class-manager'       => 'class-manager',
		'smart-sync'          => 'smart-sync',
		'text-wrap'           => 'text-wrap',
		'transparent-header'  => 'transparent-header',
		'video-modal'         => 'video-modal',
		'responsive-controls' => 'responsive-controls',
		'background-url'      => 'background-url',
		'icon-library'        => 'icon-library',
		'advanced-tabs'       => 'advanced-tabs',
	);

	public static function get_known_slugs() {
		return array_keys( self::known_slugs() );
	}

	public static function is_known_slug( $slug ) {
		return array_key_exists( $slug, self::known_slugs() );
	}

	/**
	 * Per-slug enabled state from individual wp_options rows (avoids the
	 * read-modify-write race a shared blob would have).
	 */
	public function get_enabled_extensions() {
		$enabled = array();
		// known_slugs(), not KNOWN_SLUGS: this map is what the REST list and
		// the localized editor map return, and the free build must offer only
		// the extensions its zip carries (#846).
		foreach ( self::known_slugs() as $slug => $default ) {
			$stored           = get_option( self::OPTION_PREFIX . $slug, null );
			$enabled[ $slug ] = ( null === $stored ) ? (bool) $default : (bool) $stored;
		}
		return $enabled;
	}

	/**
	 * Persist one toggle and prove it landed.
	 *
	 * update_option()'s own return is not a verdict: false means "the row was
	 * not written", which is also what it says for "already that value". So
	 * the row is read back after the write, and only a read-back that
	 * disagrees with the request is a failure — a typed one, so the REST door
	 * can refuse instead of answering 200 with a map the store never changed.
	 *
	 * @param string $slug    Extension slug.
	 * @param bool   $enabled Requested state.
	 * @return bool|\WP_Error True when the row reads back as requested;
	 *                       WP_Error `blocklane_pro_unknown_extension` (400)
	 *                       for a slug outside KNOWN_SLUGS, or
	 *                       `blocklane_pro_extension_write_failed` (500) when
	 *                       the row did not take the value.
	 */
	public function set_extension_enabled( string $slug, bool $enabled ): bool|\WP_Error {
		if ( ! self::is_known_slug( $slug ) ) {
			return new \WP_Error(
				'blocklane_pro_unknown_extension',
				/* translators: %s: extension slug */
				sprintf( __( 'Unknown extension: %s', 'blocklane' ), $slug ),
				array( 'status' => 400 )
			);
		}
		$option = self::OPTION_PREFIX . $slug;
		// A row that does not exist yet must be CREATED: update_option()
		// compares the new value with get_option()'s default (false), so a
		// request for false on a missing row is "unchanged" to it and no row
		// is ever written — while the map would keep reporting the shipped
		// default. (The version migration seeds every known row, so this is
		// the path for a hand-deleted row or a slug newer than the seed.)
		// Autoload: the frontend loader reads every toggle on every request,
		// so these tiny rows must ride the alloptions query. update_option
		// only applies the autoload arg when the value changes, so align
		// pre-2026-07 rows (written autoload=false) explicitly.
		if ( null === get_option( $option, null ) ) {
			add_option( $option, $enabled, '', true );
		} else {
			update_option( $option, $enabled, true );
		}
		wp_set_option_autoload( $option, true );

		// Read the row back with the SAME resolution the map uses (a missing
		// row is the shipped default), so the verdict here and the map the
		// door returns can never disagree.
		$stored = get_option( $option, null );
		$now    = ( null === $stored ) ? (bool) ( self::known_slugs()[ $slug ] ?? false ) : (bool) $stored;
		if ( $now !== $enabled ) {
			return new \WP_Error(
				'blocklane_pro_extension_write_failed',
				/* translators: %s: extension slug */
				sprintf( __( 'The setting for %s could not be saved.', 'blocklane' ), $slug ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/** Option holding the global responsive breakpoints (px). */
	const BREAKPOINTS_OPTION = 'blocklane_pro_breakpoints';

	/** Shipped defaults — must match the px in responsive-controls.css. */
	public static function default_breakpoints() {
		return array(
			'tablet' => 768,
			'mobile' => 480,
		);
	}

	/**
	 * The global responsive breakpoints. Since core 7.1 the source of truth is
	 * theme.json's settings.viewport (what core's responsive style states and
	 * block visibility media queries are built from); the option is the legacy
	 * fallback for themes without a viewport. The runtime helper owns the read
	 * chain so the plugin and the baked runtime always agree.
	 *
	 * @return array{tablet:int,mobile:int}
	 */
	public static function get_breakpoints() {
		if ( function_exists( 'blocklane_pro_ext_breakpoints' ) ) {
			return blocklane_pro_ext_breakpoints();
		}

		$defaults = self::default_breakpoints();
		$stored   = get_option( self::BREAKPOINTS_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();

		$tablet = isset( $stored['tablet'] ) ? (int) $stored['tablet'] : $defaults['tablet'];
		$mobile = isset( $stored['mobile'] ) ? (int) $stored['mobile'] : $defaults['mobile'];

		return self::clamp_breakpoints( $tablet, $mobile );
	}

	/**
	 * Clamp breakpoints to a sane range with mobile kept strictly below tablet.
	 *
	 * @param int $tablet Tablet breakpoint (px).
	 * @param int $mobile Mobile breakpoint (px).
	 * @return array{tablet:int,mobile:int}
	 */
	private static function clamp_breakpoints( $tablet, $mobile ) {
		$defaults = self::default_breakpoints();
		$tablet   = max( 360, min( 2000, (int) $tablet ) );
		$mobile   = max( 240, min( 1600, (int) $mobile ) );

		if ( $mobile >= $tablet ) {
			$mobile = $defaults['mobile'] < $tablet ? $defaults['mobile'] : max( 240, (int) round( $tablet * 0.6 ) );
		}

		return array(
			'tablet' => $tablet,
			'mobile' => $mobile,
		);
	}

	/**
	 * Persist the global breakpoints, clamped so the stored option is always
	 * within range (get_breakpoints() also clamps defensively on read).
	 *
	 * Both editions write this row (responsive-controls ships in both), so a
	 * key this build does not know is carried through, never dropped
	 * (Helper::with_foreign_keys, Spec C); a row that is not an array is
	 * refused untouched and the caller answers with the error.
	 *
	 * @param int $tablet Tablet breakpoint (px).
	 * @param int $mobile Mobile breakpoint (px).
	 * @return array{tablet:int,mobile:int}|\WP_Error The clamped, stored values.
	 */
	public static function set_breakpoints( $tablet, $mobile ) {
		$clamped = self::clamp_breakpoints( $tablet, $mobile );
		$merged  = Helper::with_foreign_keys( self::BREAKPOINTS_OPTION, $clamped, array( 'tablet' => 0, 'mobile' => 0 ) );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		// Autoload: read on every front-end request (frontend-loader inline
		// script + responsive CSS). wp_set_option_autoload aligns rows
		// written autoload=false before 2026-07 (update_option only applies
		// the arg when the value changes).
		update_option( self::BREAKPOINTS_OPTION, $merged, true );
		wp_set_option_autoload( self::BREAKPOINTS_OPTION, true );

		// Write through to settings.viewport in the user Global Styles: core
		// 7.1's responsive states / block visibility read wp_get_global_settings,
		// which is the source of truth get_breakpoints() now follows — a save
		// that only touched our option would silently diverge from core's
		// media queries. The option stays as the baked-runtime fallback.
		self::write_viewport_global_setting( $clamped );

		return $clamped;
	}

	/** Option holding the canvas grid tools switch (advanced-grid sub-setting). */
	const GRID_CANVAS_TOOLS_OPTION = 'blocklane_pro_grid_canvas_tools';

	/**
	 * Whether the editor's canvas grid tools are switched on. OFF by default —
	 * see blocklane_pro_ext_grid_canvas_tools() for the reasoning; the flag
	 * itself is printed by loader/advanced-grid/advanced-grid.php.
	 *
	 * @return bool
	 */
	public static function get_grid_canvas_tools() {
		return (bool) get_option( self::GRID_CANVAS_TOOLS_OPTION, false );
	}

	/**
	 * Persist the canvas grid tools switch.
	 *
	 * NOT autoloaded, unlike its siblings above: this row is read only inside
	 * enqueue_block_editor_assets (editor requests), never on the front end, so
	 * autoloading it would tax every request with a value they never read.
	 *
	 * @param bool $enabled Whether to switch the canvas tools on.
	 * @return bool|\WP_Error True when the row reads back as requested; WP_Error
	 *                       `blocklane_pro_grid_tools_write_failed` (500) when it
	 *                       does not — update_option()'s own return is not usable
	 *                       as a verdict (false also means "already that value").
	 */
	public static function set_grid_canvas_tools( bool $enabled ): bool|\WP_Error {
		update_option( self::GRID_CANVAS_TOOLS_OPTION, $enabled, false );
		wp_set_option_autoload( self::GRID_CANVAS_TOOLS_OPTION, false );

		// Read back the way get_grid_canvas_tools() reads (a missing row is
		// off), so a write the store did not take is refused, never echoed.
		if ( self::get_grid_canvas_tools() !== $enabled ) {
			return new \WP_Error(
				'blocklane_pro_grid_tools_write_failed',
				__( 'The canvas grid tools setting could not be saved.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Persist tablet/mobile into settings.viewport of the user Global Styles
	 * post so wp_get_global_settings (core's and our own source of truth)
	 * reflects the change immediately.
	 *
	 * @param array{tablet:int,mobile:int} $breakpoints Clamped breakpoints.
	 * @return void
	 */
	private static function write_viewport_global_setting( $breakpoints ) {
		if ( ! class_exists( '\WP_Theme_JSON_Resolver' ) || ! method_exists( '\WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id' ) ) {
			return;
		}

		$post_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		if ( ! $post_id ) {
			return;
		}

		$post     = get_post( $post_id );
		$existing = $post ? (string) $post->post_content : '';
		$config   = json_decode( $existing, true );

		// NEVER overwrite content we can't parse: this holds the user's whole
		// Global Styles customization. A decode failure here means someone
		// else's data (or corruption) — bail rather than clobber. Only a truly
		// empty post gets the fresh skeleton.
		if ( ! is_array( $config ) ) {
			if ( '' !== trim( $existing ) ) {
				return;
			}
			$config = array(
				'version'                     => \WP_Theme_JSON::LATEST_SCHEMA,
				'isGlobalStylesUserThemeJSON' => true,
			);
		}

		$viewport = array(
			'mobile' => $breakpoints['mobile'] . 'px',
			'tablet' => $breakpoints['tablet'] . 'px',
		);
		if ( ( $config['settings']['viewport'] ?? null ) === $viewport ) {
			return;
		}
		$config['settings']             = is_array( $config['settings'] ?? null ) ? $config['settings'] : array();
		$config['settings']['viewport'] = $viewport;

		// wp_update_post expects SLASHED data (it wp_unslash()es, and the
		// global-styles content_save_pre filter unslashes again) — pass the
		// JSON through wp_slash() like core's own Global Styles REST
		// controller does, or the stored bytes come out mangled.
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => wp_json_encode( $config ),
				)
			)
		);

		// Round-trip guard: if the save filters still mangled the JSON,
		// restore the previous bytes verbatim rather than leave the user's
		// Global Styles unreadable (an unreadable record reads as "no user
		// styles" site-wide).
		clean_post_cache( $post_id );
		$stored = get_post( $post_id );
		if ( $stored && null === json_decode( (string) $stored->post_content, true ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- byte-exact restore must bypass save filters.
			$wpdb->update( $wpdb->posts, array( 'post_content' => $existing ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
		}

		// Global-styles caches are keyed off the post; drop them so reads in
		// this same request (the REST response echoes get_breakpoints()) see
		// the new viewport. clean_cached_data() resets the resolver statics,
		// but wp_get_global_settings() ALSO memoizes per-request in the
		// theme_json cache group — flush that too or the fresh value only
		// appears on the next request.
		\WP_Theme_JSON_Resolver::clean_cached_data();
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'theme_json' );
		}
	}

	public function is_extension_enabled( $slug ) {
		$enabled = $this->get_enabled_extensions();
		return ! empty( $enabled[ $slug ] );
	}

	/**
	 * Load the extensions' PHP. The FRONT-END half (render filters, attribute
	 * registration, front-end enqueues) lives in loader/frontend-loader.php,
	 * the canonical runtime; the require below is guarded because that file is
	 * also required directly during bootstrap. The plugin-only halves (REST /
	 * editor) load here for enabled extensions.
	 */
	public function load_extensions() {
		if ( ! defined( 'BLOCKLANE_PRO_EXT_RUNTIME_LOADED' ) ) {
			require_once BLOCKLANE_PRO_PATH . '/inc/extensions/loader/frontend-loader.php';
		}

		// Gate the plugin-only halves with the SAME read the runtime uses for
		// their front-end halves (option -> shipped default; runtime-helpers.php
		// is loaded by the frontend-loader require above), so the editor half and
		// the render half can never disagree about whether an extension is on.
		// Gating on get_enabled_extensions() instead could diverge before
		// import_missing() runs — loading a REST half whose runtime half (and
		// its functions) never loaded.

		$defaults = self::known_slugs();
		foreach ( self::ADMIN_RUNTIMES as $slug => $rel ) {
			if ( ! isset( $defaults[ $slug ] ) || ! blocklane_pro_ext_enabled( $slug, $defaults[ $slug ] ) ) {
				continue;
			}
			if ( ! is_file( BLOCKLANE_PRO_PATH . '/inc/' . $rel ) ) {
				// Present per the edition but missing on disk: a torn deploy,
				// and the same typed miss the modules get rather than a fatal
				// or a silent skip.
				Modules::record(
					new Module_Miss( 'extension:' . self::UNIT_OF[ $slug ], Module_Miss::PHASE_CONTENT, Module_Miss::RUNTIME_MISSING, $rel )
				);
				continue;
			}
			require_once BLOCKLANE_PRO_PATH . '/inc/' . $rel;
		}
	}

	/**
	 * Whether any header-area template part on this site is set to overlay.
	 *
	 * Feeds the per-page override control, which names what "Default" resolves
	 * to ("Default – Transparent") so the choice is not a guess. A site with
	 * several header parts set differently gets the answer for whichever one
	 * uses the overlay; the alternative is resolving this page's template to
	 * its header part on every editor load, which costs more than the hint is
	 * worth. get_block_templates() merges theme-provided and customized parts,
	 * and this only runs on editor loads.
	 *
	 * @return bool
	 */
	private function transparent_header_in_use() {
		// enqueue_block_assets fires twice per editor load (admin_enqueue_scripts,
		// then again inside the iframe's asset resolution) — cache per request
		// like get_asset_file() below so the second call doesn't repeat the query
		// and re-walk every header-area part's content.
		static $in_use = null;
		if ( null !== $in_use ) {
			return $in_use;
		}

		// Ask the loader's resolver rather than scanning raw part content. A
		// strpos over $part->content only sees attributes written literally in
		// that part — but this theme's header IS a single wp:pattern reference,
		// so the attribute lives behind it and a raw scan reports false. The
		// resolver expands pattern, synced-pattern and nested-part references,
		// and rides the same cache. function_exists covers the extension being
		// off, where the answer is moot anyway.
		if ( ! function_exists( 'blocklane_pro_transparent_header_default_on' ) ) {
			return false;
		}

		$in_use = (bool) blocklane_pro_transparent_header_default_on();

		return $in_use;
	}

	public function get_asset_file() {
		static $asset_file = null;
		if ( null === $asset_file ) {
			$asset_file_path = BLOCKLANE_PRO_PATH . '/inc/extensions/build/index.asset.php';
			$asset_file      = file_exists( $asset_file_path ) ? include $asset_file_path : false;
		}
		return $asset_file;
	}

	public function enqueue_block_editor_assets() {
		if ( ! is_admin() ) {
			return;
		}

		$enabled = $this->get_enabled_extensions();
		// AI Tools ships its editor UI in this bundle but is gated by its own
		// Advanced toggle, so the bundle must load when it's on even if no
		// block extension is. ONE source for that answer: the unit's own
		// config function, which is present exactly when the unit is. Asking
		// the same question twice — once here and once where the route is
		// built — is how the two drift apart.
		$ai_tools_config = function_exists( 'blocklane_pro_ai_tools_editor_config' )
			? blocklane_pro_ai_tools_editor_config()
			: array( 'available' => false, 'runPath' => '' );
		$ai_tools        = (bool) $ai_tools_config['available'];
		// Popup Bindings (popup-bindings.md) also ship their editor UI in this
		// bundle but belong to the Popups module: gate on the runtime's own
		// outcome — the CPT exists only when the full popups gate chain
		// (safe mode, then the Advanced toggle; the license gates nothing at
		// runtime in this plugin) passed.
		$popups_on = post_type_exists( 'blocklane_popup' );
		if ( ! in_array( true, $enabled, true ) && ! $ai_tools && ! $popups_on ) {
			return;
		}

		$asset_file = $this->get_asset_file();
		if ( ! $asset_file ) {
			return;
		}

		$handle    = 'blocklane-pro-extensions-editor';
		$build_url = BLOCKLANE_PRO_URL . '/inc/extensions/build/';

		// CodeMirror for the CSS Class Manager editor.
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		wp_enqueue_script(
			$handle,
			$build_url . 'index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_localize_script(
			$handle,
			'blocklaneProExtensions',
			array(
				'enabled'          => $enabled,
				'buildUrl'         => $build_url,
				'termImagePreview' => $build_url . 'images/preview.webp',
				'wp7'              => version_compare( get_bloginfo( 'version' ), '7.0-alpha', '>=' ),
				// For the Responsive Editing hint + the advanced-grid collision guard.
				'breakpoints'      => self::get_breakpoints(),
				// Nullable CORE viewport (no option fallback) — the advanced-grid
				// migration's gate. Must match the PHP shim's gate exactly, or
				// the editor and front-end migrations disagree.
				'coreViewport'     => function_exists( 'blocklane_pro_ext_core_viewport_px' )
					? blocklane_pro_ext_core_viewport_px()
					: null,
				// Whether core's canvas grid tools are switched on (the flag is
				// printed by the advanced-grid loader). The editor bundle reads
				// this to know whether the tools can be on screen at all.
				'gridCanvasTools'  => self::get_grid_canvas_tools(),
				'settingsUrl'      => admin_url( 'admin.php?page=' . \blocklane_pro\Branding::MENU_SLUG . '&screen=extensions&ext=responsive-controls' ),
				// Gates the inline breakpoint editor (POST /breakpoints is manage_options).
				'canManageBreakpoints' => current_user_can( 'manage_options' ),
				// AI Tools "Rewrite with AI" selection button — shown only when
				// the toggle is on and the AI plugin backs the ability. The
				// rewrite runs the blocklane-pro/content-rewrite ability via the
				// core Abilities run route.
				// From the unit itself (blocklane_pro_ai_tools_editor_config), so
				// the endpoint string is not in this file — it ships in every
				// edition and the route exists only in Pro.
				'aiTools'              => $ai_tools_config,
				// The per-page override control reads defaultOn to say what
				// "Default" actually resolves to. (Its own availability comes
				// from the shared `enabled` map above — it used to be gated on
				// a header already using the feature, which made the override
				// one-way: the control was absent in exactly the case you
				// would want it for.)
				'transparentHeader'    => array(
					'defaultOn' => $this->transparent_header_in_use(),
				),
				// Cloud icon catalog (anon read; see icon-library-api.php).
				// The cloud catalog browse. Gated on the GATEWAY unit, not just
				// the toggle: the directory build contains no route to our
				// servers at all (guideline 7 — no external contact without
				// consent), so its constants are not defined and this must not
				// be reached for them.
				// The cloud catalog browse. The coordinates come from the
				// icon-library unit itself (blocklane_pro_icon_library_cloud_config),
				// so neither the host nor the key appears in this file — this
				// one ships in every edition, and the directory build must make
				// no outbound calls at all (guideline 7).
				'iconLibrary'          => function_exists( 'blocklane_pro_icon_library_cloud_config' )
					&& blocklane_pro_ext_enabled( 'icon-library', self::KNOWN_SLUGS['icon-library'] )
						? blocklane_pro_icon_library_cloud_config()
						: null,
				// Popup Bindings picker (popup-bindings.md). enabled mirrors
				// the popups runtime's gate outcome; the admin links are
				// manage_options-only, like the CPT itself.
				'popups'               => array(
					'enabled'     => $popups_on,
					'listPath'    => '/' . Branding::rest_namespace() . '/popups',
					'canManage'   => current_user_can( 'manage_options' ),
					'editBase'    => admin_url( 'post.php?action=edit&post=' ),
					'createUrl'   => admin_url( 'post-new.php?post_type=blocklane_popup' ),
					'previewBase' => home_url( '/?blocklane_popup_preview=' ),
				),
			)
		);

		// Webpack public path for dynamic imports — must run before the bundle.
		wp_add_inline_script(
			$handle,
			'window.__blocklaneProExtensionsBuildUrl = ' . wp_json_encode( $build_url ) . ';',
			'before'
		);

		// Preview styles (animation playback, etc.) — needed inside the editor canvas.
		$style_css = BLOCKLANE_PRO_PATH . '/inc/extensions/build/style-index.css';
		if ( file_exists( $style_css ) ) {
			wp_enqueue_style( 'blocklane-pro-extensions-style', $build_url . 'style-index.css', array(), $asset_file['version'] );
		}

		// Editor-only styles (control layout/grouping — e.g. the paragraph
		// Decoration Hover control sits with the core Decoration control).
		$editor_css = BLOCKLANE_PRO_PATH . '/inc/extensions/build/editor.css';
		if ( file_exists( $editor_css ) ) {
			wp_enqueue_style( $handle, $build_url . 'editor.css', array(), $asset_file['version'] );
		}
	}

	/*
	 * Front-end asset enqueues (the shared style-index.css bundle and the
	 * advanced-group behavior script) live in loader/frontend-loader.php — the
	 * bakeable runtime — so they keep loading after Blocklane Pro is removed.
	 */
}
