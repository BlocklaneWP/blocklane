<?php
/**
 * Settings: admin menu, asset enqueue, REST API registration.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_active_submenu' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'plugin_action_links_' . BLOCKLANE_PRO_BASENAME, array( $this, 'add_action_links' ) );
		// Pin the Blocklane Pro top-level menu immediately above Appearance and keep it
		// there. Priority 9999 so our reorder runs last and another plugin's
		// menu_order filter can't slot itself between us and Appearance.
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'pin_menu_position' ), 9999 );
		// Recolor the menu icon per state (gray at rest -> white on hover/current)
		// exactly like the native dashicons, via a currentColor CSS mask.
		add_action( 'admin_head', array( $this, 'print_menu_icon_styles' ) );
	}

	/**
	 * Anchor the Blocklane Pro top-level menu directly above Appearance (themes.php),
	 * keeping every other menu item in its existing relative order.
	 *
	 * @param array $order Ordered list of top-level menu slugs.
	 * @return array
	 */
	public function pin_menu_position( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$slug  = Branding::MENU_SLUG;
		$order = array_values( array_diff( $order, array( $slug ) ) );

		$anchor = array_search( 'themes.php', $order, true );
		if ( false === $anchor ) {
			// Appearance not present (edge case) — fall back to the top of the menu.
			array_unshift( $order, $slug );
		} else {
			array_splice( $order, $anchor, 0, $slug );
		}

		return $order;
	}

	public function add_action_links( $links ) {
		$settings_url = esc_url( add_query_arg( 'page', Branding::MENU_SLUG, admin_url( 'admin.php' ) ) );
		$settings     = '<a href="' . $settings_url . '">' . esc_html__( 'Settings', 'blocklane' ) . '</a>';

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Brand-mark for the top-level menu — the Blocklane B logomark (the same
	 * shape as BrandLogo.js, standalone) as a pre-encoded SVG data URI. A
	 * literal string, so no runtime base64_encode(); reused below for the
	 * currentColor mask that recolors the icon per menu state, and by
	 * Block_Branding as the inserter-badge mask — every brand mark reads
	 * from this one constant.
	 */
	const MENU_ICON_B64 = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0ODkuNSA1MTIiPjxwYXRoIGZpbGw9IiNhN2FhYWQiIGQ9Ik05OC41LjhjLS4xLjQtMS40IDYuOS0yLjggMTQuM3MtNC4xIDIxLjMtNS45IDMxYy0xLjkgOS42LTUgMjUuNy02LjkgMzUuN3MtNC42IDI0LTYgMzEuM2MtMS40IDcuMi0zLjEgMTYtMy43IDE5LjQtLjkgNS05LjYgNDkuOC0xMS44IDYxLjUtLjQgMi4xLS40IDIuOC4zIDMuMS40LjIgMTYuNy4yIDM2LjEgMCAzMy41LS4zIDM1LjYtLjQgNDIuMS0xLjcgMzEuOC02LjUgNTcuNS0yNi4xIDcxLTU0LjEgNS42LTExLjYgNi43LTE2IDE2LjMtNjYuNyAxLjgtOS41IDMuNC0xNy4zIDMuNC0xNy40LjMtLjQgMTguMiAzLjEgMTguNyAzLjcuMy4zLjMgMi0uMiA0LjEtNCAxOS42LTQuMSAyMC4xLTQuMSAzMy40IDAgMTEgLjIgMTMuOCAxLjQgMTkuNSA3LjIgMzQuMyAzMS40IDYyLjEgNjQgNzMuNCAxMSAzLjggMjAuNSA1LjQgMzIuMiA1LjQgMjEuMiAwIDM4LjItNS4xIDU2LjEtMTcgNy42LTUuMSAyMC4yLTE3LjYgMjUuNS0yNS4zIDEwLjEtMTUgMTUuOC0zMSAxNy00OC40IDIuNC0zMi4yLTEwLjYtNjIuOC0zNS42LTgzLjYtMTIuOS0xMC43LTI5LTE4LjEtNDYuMS0yMS03LTEuMi0xMS0xLjMtMTMzLjktMS4zUzk4LjkuMyA5OC42LjltLTQxLjggMjE2YzAgLjMtMSA1LjYtMi4yIDExLjgtMy45IDE5LjgtNi40IDMyLjktOC44IDQ0LjgtMS4yIDYuNC0zLjIgMTYuNi00LjQgMjIuOC0zLjQgMTcuNS02LjkgMzUuOC05LjQgNDguOC0xLjMgNi41LTMuMyAxNy00LjQgMjMuMi0yLjYgMTMuMy01LjUgMjguNi04LjcgNDUuMS0yLjMgMTItNC44IDI1LTguOCA0NS43LTEuMiA2LjItMy4yIDE2LjYtNC40IDIzLjItMS4zIDYuNS0zLjEgMTUuOS00LjEgMjAuOEwtLjIgNTEybDE3OS44LS4yIDE3OS43LS4yIDcuMi0xLjRjMzEuNC02LjEgNTYuMS0xOC43IDc4LjEtMzkuOSAyMC41LTE5LjggMzUuMS00NS42IDQxLjQtNzMuNSAyLjgtMTIuMyAzLjMtMTggMy4zLTMyLjIgMC0xNC42LS44LTIxLjItMy42LTMzLjUtMTAtNDMuMy0zOC45LTc5LjYtNzguOS05OS4yLTE0LjEtNi45LTI4LjMtMTEuNC00NC4xLTEzLjgtOS0xLjQtMzAuNy0xLjYtMzkuNC0uMy0zNC4yIDQuOC02Mi42IDE4LjgtODYuMSA0Mi40LTIyLjUgMjIuNS0zNi4zIDQ5LjktNDIgODIuOS0xLjEgNi43LTIuNSAxMy4xLTIuOSAxMy41LS4zLjMtMTYuNi0yLjYtMTguNC0zLjMtLjUtLjEtLjItMi44IDEuMS04LjkgMy44LTE4IDQuNS0yOS41IDIuOC00Mi43LTMuMy0yNS0xNi40LTQ3LjktMzYuNi02NC0xMS44LTkuNS0yOC40LTE2LjgtNDMuOC0xOS43LTYuNS0xLjEtNDAuNy0yLjEtNDAuNy0xLjEiLz48L3N2Zz4=';

	public function add_menu() {
		$icon = 'data:image/svg+xml;base64,' . self::MENU_ICON_B64;

		$page_suffix = add_menu_page(
			esc_html( Branding::plugin_name() ),
			esc_html( Branding::plugin_name() ),
			'manage_options',
			Branding::MENU_SLUG,
			array( $this, 'render_app_root' ),
			$icon,
			59
		);

		add_action( 'admin_print_scripts-' . $page_suffix, array( $this, 'enqueue_app_assets' ) );
		add_action( 'admin_print_styles-' . $page_suffix, array( $this, 'print_embed_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );

		// One wp-admin submenu item per dashboard screen, so wp-admin's own
		// submenu is the app's left nav (the wc-admin `&path=` slug trick).
		// WP builds each link as admin.php?page={slug}&screen={screen}, but
		// $plugin_page still resolves to the bare top-level slug, so the
		// top-level callback renders and the app's router reads ?screen=.
		// First item relabels the auto-created duplicate to "Dashboard".
		add_submenu_page(
			Branding::MENU_SLUG,
			esc_html( Branding::plugin_name() ),
			esc_html__( 'Dashboard', 'blocklane' ),
			'manage_options',
			Branding::MENU_SLUG,
			array( $this, 'render_app_root' )
		);

		foreach ( self::screens_manifest() as $screen => $label ) {
			// The callback never runs for these composite slugs — it exists so
			// get_plugin_page_hook() is non-empty, which makes the menu walker
			// emit an admin.php?page= URL instead of a bare-slug href.
			add_submenu_page(
				Branding::MENU_SLUG,
				$label,
				$label,
				'manage_options',
				Branding::MENU_SLUG . '&screen=' . $screen,
				array( $this, 'render_app_root' )
			);
		}
	}

	/**
	 * Screen slug => menu label for the dashboard screens surfaced as wp-admin
	 * submenu items, in menu order. Mirrors the app's screen registry
	 * (inc/onboarding/src/screens/registry.js) — keep the two in sync when
	 * adding a screen. Home is excluded: it's the bare-slug "Dashboard" item.
	 *
	 * @return array<string,string>
	 */
	/**
	 * Dashboard screen slug => the manifest unit that owns it, or null when
	 * core does.
	 *
	 * MIRROR CONTRACT with the `screen` field in edition-manifest.json; the
	 * same map drives the JS side through screens/components.js. Both doors
	 * that show or hide a screen — screens_manifest() and the localized
	 * toolScreens map — read it through tool_screen_on(), the one predicate.
	 */
	private static function screen_unit( string $slug ): ?string {
		$map = array(
			'dynamic-values' => 'module:dynamic-values',
			'content-types'  => 'module:content-types',
			'seo'            => 'module:seo',
			'forms'          => 'module:forms',
			'scripts'        => 'service:scripts',
			'site-privacy'   => 'module:site-lock',
			'extensions'     => 'module:extensions',
			'advanced'       => 'module:advanced',
			'child-theme'    => 'service:child-theme',
			'ai-mcp'         => 'module:ai-mcp',
		);
		return $map[ $slug ] ?? null;
	}

	/**
	 * Screen slug => menu label, for the screens THIS edition carries and the
	 * toggles allow, in nav order. Mirrors the app's screen registry
	 * (inc/onboarding/src/screens/registry.js's SCREEN_ORDER, and the copy in
	 * screens/meta/) — keep them in sync when adding a screen. Home is
	 * excluded: it is the bare-slug "Dashboard" item.
	 *
	 * The labels of the screens every edition carries are inline below. A
	 * screen whose unit only Pro carries takes its label from its own unit,
	 * which adds it on `blocklane_pro_dashboard_screen_labels`: a module from
	 * its boot class's constructor (Content_Types_Integration,
	 * Dynamic_Values_Integration, Abilities\Loader — constructed by
	 * Modules::boot() on plugins_loaded, before admin_menu), a service with no
	 * boot class from its lifecycle file, at plugin-file scope beside the REST
	 * routes behind the screen (service:scripts: scripts/lifecycle.php). So
	 * this core file names no Pro feature: its label
	 * leaves the free build with its unit, the way the app's meta files do,
	 * and dist-check scans this file with the derived labels
	 * (forbidden_ui_strings.scope in edition-manifest.json). A screen that is
	 * on but has no label is left out of the submenu — an unlabeled item is
	 * never drawn — and, when its unit loaded (a module's boot class, a
	 * service's lifecycle file), that is a unit that forgot its filter:
	 * _doing_it_wrong() says so. A unit that did not load (safe mode, a
	 * blocklane_pro_load_module veto, a missed file) has no REST controller
	 * either, so its screen leaves the submenu quietly.
	 *
	 * @return array<string, string>
	 */
	private static function screens_manifest(): array {
		$order = array( 'dynamic-values', 'content-types', 'seo', 'forms', 'scripts', 'site-privacy', 'extensions', 'advanced', 'child-theme', 'ai-mcp' );

		$shared = array(
			'seo'          => __( 'SEO', 'blocklane' ),
			'forms'        => __( 'Forms', 'blocklane' ),
			'site-privacy' => __( 'Site Visibility', 'blocklane' ),
			'extensions'   => __( 'Extensions', 'blocklane' ),
			'advanced'     => __( 'Advanced', 'blocklane' ),
			'child-theme'  => __( 'Create Child Theme', 'blocklane' ),
		);

		/**
		 * Filters the wp-admin submenu labels of the dashboard screens a unit
		 * owns. Each unit adds its own screen's label — a module from its boot
		 * class's constructor, a service from its lifecycle file; the screens
		 * every edition carries are not read from here.
		 *
		 * @param array<string, string> $labels Screen slug => menu label.
		 */
		$unit_labels = (array) apply_filters( 'blocklane_pro_dashboard_screen_labels', array() );

		// ONE predicate decides every screen — the edition first (an absent
		// unit has no code and no REST route behind its screen, so a toggle
		// could never bring it back), then that screen's own toggle rule.
		$screens = array();
		foreach ( $order as $slug ) {
			if ( ! self::tool_screen_on( $slug ) ) {
				continue;
			}
			$label = $shared[ $slug ] ?? ( is_string( $unit_labels[ $slug ] ?? null ) ? (string) $unit_labels[ $slug ] : '' );
			if ( '' === $label ) {
				if ( self::screen_unit_loaded( $slug ) ) {
					_doing_it_wrong(
						__METHOD__,
						esc_html( sprintf( 'The "%s" dashboard screen is on and its unit loaded, but no label arrived on blocklane_pro_dashboard_screen_labels — a module\'s boot class adds its screen\'s label in its constructor, a service\'s lifecycle file at file scope.', $slug ) ),
						'0.13.0'
					);
				}
				continue;
			}
			$screens[ $slug ] = $label;
		}

		return $screens;
	}

	/**
	 * Whether the unit that owns a dashboard screen loaded the code that adds
	 * its label this request. A module: its Modules::all() boot class is
	 * declared (the boot classes are loaded only by Modules::boot_module(),
	 * which constructs them right after, so a declared boot class is a booted
	 * module). Any other unit (a service, which has no boot class): its
	 * lifecycle file loaded (Modules::lifecycle_loaded()). False for a screen
	 * core owns.
	 *
	 * @param string $slug Dashboard screen slug.
	 */
	private static function screen_unit_loaded( string $slug ): bool {
		$unit = self::screen_unit( $slug );
		if ( null === $unit ) {
			return false;
		}
		if ( ! str_starts_with( $unit, 'module:' ) ) {
			return Modules::lifecycle_loaded( $unit );
		}
		$boot = Modules::all()[ substr( $unit, strlen( 'module:' ) ) ]['boot'] ?? null;
		return null !== $boot && class_exists( $boot, false );
	}

	/**
	 * Whether this edition shows a dashboard screen right now — the ONE
	 * predicate behind both doors, the wp-admin submenu (screens_manifest())
	 * and the localized toolScreens map the app deep-links through. Two
	 * doors reading two predicates is how the free Home came to offer a
	 * card for the dynamic-values screen, which the artifact did not carry
	 * (#845).
	 *
	 * Order: the edition test first — a screen whose UNIT this edition does
	 * not carry is off whatever its toggle says — then the toggle rule that
	 * screen has always had: SEO, Forms and AI MCP are opt-ins (default off,
	 * fail closed without the Advanced store); Create Child Theme is the
	 * child-theme opt-in; Site Visibility force-stays while a lock is live;
	 * the Site Tools module screens show unless switched off; the screens
	 * with no toggle (Home, Extensions, Advanced) are always on.
	 *
	 * @param string $slug Dashboard screen slug.
	 * @return bool
	 */
	public static function tool_screen_on( string $slug ): bool {
		$unit = self::screen_unit( $slug );
		if ( null !== $unit && ! Edition::has( $unit ) ) {
			return false;
		}
		switch ( $slug ) {
			case 'seo':
			case 'forms':
			case 'ai-mcp':
				return self::advanced_opt_in_on( $slug );
			case 'child-theme':
				return self::child_theme_tool_on();
			case 'site-privacy':
				return self::advanced_tool_on( $slug ) || self::site_lock_active();
			case 'content-types':
			case 'dynamic-values':
			case 'scripts':
				return self::advanced_tool_on( $slug );
			default:
				return true;
		}
	}

	/**
	 * Whether an Advanced "Site Tools" screen toggle is on. When the Advanced
	 * class is not loaded (safe mode, a blocklane_pro_load_module veto, or a
	 * classic theme — Modules::boot() runs below the block-theme gate) the
	 * STORED value decides, with the tool's shipped ON as the default for a
	 * row that never saved it: a tool the user turned off stays off
	 * everywhere. It used to return true here, so on a classic theme a tool
	 * screen's editor routes registered with their toggle read as ON (#873).
	 *
	 * @param string $slug Toggle slug (matches the screen slug).
	 * @return bool
	 */
	public static function advanced_tool_on( $slug ) {
		if ( ! class_exists( __NAMESPACE__ . '\Advanced', false ) ) {
			return self::advanced_stored( (string) $slug, true );
		}
		return Advanced::is_on( $slug );
	}

	/**
	 * Whether a default-OFF opt-in toggle is on. The inverse polarity of
	 * advanced_tool_on(): when the Advanced module isn't loaded (safe mode /
	 * a load-module veto), an opt-in falls back to its STORED toggle value —
	 * the same raw option the runtime reads — so absence of
	 * the store means off, while a stored ON keeps
	 * the screen reachable for a feature that is still emitting. Mirrors
	 * Modules::boot_on()/management_on().
	 *
	 * @param string $slug Toggle slug.
	 * @return bool
	 */
	public static function advanced_opt_in_on( $slug ) {
		if ( ! class_exists( __NAMESPACE__ . '\Advanced', false ) ) {
			return self::advanced_stored( (string) $slug, false );
		}
		return Advanced::is_on( $slug );
	}

	/**
	 * The stored Advanced row's answer for one toggle, for the paths that run
	 * when the Advanced class is not loaded to answer it — the ONE raw read of
	 * the row in this class, serving both polarities (a tool defaults ON, an
	 * opt-in OFF). isset() semantics, exactly as Advanced::get() and
	 * Content_Toggle::resolve() read the same row: a present, non-null value
	 * wins; a missing row, a non-array row, a missing key or a stored null
	 * means $default. Never writes. The raw readers of this row are pinned by
	 * bin/toggle-wiring-check.php's invariant 5 (#1004); a new one fails it
	 * by name.
	 */
	private static function advanced_stored( string $slug, bool $default ): bool {
		$stored = get_option( 'blocklane_pro_advanced', null );
		if ( ! is_array( $stored ) || ! isset( $stored[ $slug ] ) ) {
			return $default;
		}
		return ! empty( $stored[ $slug ] );
	}

	/**
	 * Whether a Site Visibility lock is currently enforced. Used to force the
	 * screen visible regardless of its toggle — you can't hide the only way to
	 * unlock a live lock.
	 *
	 * @return bool
	 */
	public static function site_lock_active() {
		return class_exists( __NAMESPACE__ . '\Site_Lock', false ) && Site_Lock::is_enabled();
	}

	/**
	 * Whether the Create Child Theme tool is enabled (Advanced screen's
	 * "Child Theme Generator" toggle). Gates the submenu item AND the tool's
	 * REST routes. A default-OFF opt-in, so it resolves like one: when the
	 * Advanced module isn't loaded (safe mode / a load-module veto) the
	 * STORED toggle decides — a site that turned the tool on keeps its
	 * screen reachable, while a fresh install correctly reads OFF. (The
	 * historical fail-open presented the tool as ON to every fresh install,
	 * whose screen then hit routes that 403'd — a confusingly broken screen
	 * instead of a hidden tool.)
	 *
	 * @return bool
	 */
	public static function child_theme_tool_on() {
		return self::advanced_opt_in_on( 'child-theme-tool' );
	}

	/**
	 * Point wp-admin's submenu highlight at the active screen's item. Without
	 * this, WP resolves $submenu_file to the bare page slug and "Dashboard"
	 * stays current on every screen.
	 *
	 * @param string|null $submenu_file The submenu file WP resolved.
	 * @return string|null
	 */
	public function highlight_active_submenu( $submenu_file ) {
		global $plugin_page;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing param.
		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';

		if (
			Branding::MENU_SLUG === $plugin_page &&
			$screen &&
			array_key_exists( $screen, self::screens_manifest() )
		) {
			return Branding::MENU_SLUG . '&screen=' . $screen;
		}

		return $submenu_file;
	}

	/**
	 * Make the top-level menu icon behave like a native dashicon: gray at rest,
	 * white on hover/current, adapting to the user's admin color scheme. WP bakes
	 * our SVG in as a fixed-color background, which can't recolor. So we drop that
	 * background and paint a currentColor block masked by the same SVG — currentColor
	 * follows the menu link's per-state color exactly like the core icon font does.
	 */
	public function print_menu_icon_styles() {
		$id   = 'toplevel_page_' . Branding::MENU_SLUG;
		$mask = 'data:image/svg+xml;base64,' . self::MENU_ICON_B64;
		?>
<style id="blocklane-pro-menu-icon">
	#<?php echo esc_attr( $id ); ?> .wp-menu-image { background-image: none !important; }
	#<?php echo esc_attr( $id ); ?> .wp-menu-image::before {
		content: "";
		/* Match the native dashicon ::before box exactly (inline-block 20x20 with
		   WP's 7px vertical padding) so the mark is centered in the row identically. */
		display: inline-block;
		width: 20px;
		height: 20px;
		vertical-align: top;
		background-color: currentColor;
		-webkit-mask: url("<?php echo esc_attr( $mask ); ?>") no-repeat 50% 50%;
		mask: url("<?php echo esc_attr( $mask ); ?>") no-repeat 50% 50%;
		/* Native dashicon glyphs occupy ~16px of the 20px box — matching
		   that keeps the mark optically level with its menu neighbours. */
		-webkit-mask-size: auto 16px;
		mask-size: auto 16px;
	}
</style>
		<?php
	}

	public function filter_admin_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && 'toplevel_page_' . Branding::MENU_SLUG === $screen->id ) {
			$classes .= ' blocklane-pro-embedded ';
		}

		return $classes;
	}

	/**
	 * The dashboard renders embedded in wp-admin (admin bar and admin menu stay,
	 * like core's Appearance → Fonts screen): zero out the content area's gutters
	 * and hide the pieces (footer, stray notices) that would break the app's
	 * frame. Inline rather than enqueued because it's tiny and avoids a second
	 * HTTP request.
	 */
	public function print_embed_styles() {
		?>
<style id="blocklane-pro-embed-css">
	body.blocklane-pro-embedded #wpcontent { padding-inline-start: 0; }
	body.blocklane-pro-embedded #wpbody-content { padding-bottom: 0; }
	body.blocklane-pro-embedded #wpfooter { display: none; }
	body.blocklane-pro-embedded #wpbody-content > .notice,
	body.blocklane-pro-embedded #wpbody-content > .updated,
	body.blocklane-pro-embedded #wpbody-content > .error { display: none !important; }
	/* Match the app's white canvas so edges/overscroll don't flash admin-gray. */
	body.blocklane-pro-embedded #wpwrap { background: #fff; }
</style>
		<?php
	}

	public function render_app_root() {
		echo '<div id="' . esc_attr( Branding::admin_app_dom_id() ) . '"></div>';
	}

	/**
	 * Which artifact this is, and what it does NOT contain.
	 *
	 * The free dashboard reads `absent` to render a Pro ROW in the place each
	 * missing feature's control would occupy (screens/pro-row.js). The row
	 * shows the feature's name, a "Pro" badge and its description, and no
	 * switch: a switch beside a feature this build does not include, even an
	 * inoperable one, reads as "this is yours, but locked" — the shape
	 * guidelines 5 and 9 speak to. Nothing
	 * here is restricted functionality: the feature's CODE is genuinely not in
	 * this artifact (its unit left the build), so this is an upsell for a
	 * separate product, which guideline 5 permits.
	 *
	 * KEYED BY UNIT ID, and strings only (the generator's token check proves
	 * no functional code rides with the catalog). The id is what a Pro row is
	 * built from, so the compiled bundle carries unit ids and never Pro's own
	 * copy — which is why the free build's forbidden-UI-string needles still
	 * hold.
	 *
	 * THE CATALOG IS TRANSLATED ONCE, by absent_catalog() below.
	 * Edition::absent() reads inc/edition.php, which is required as the main
	 * file's first statement — before `init`, where a __() call would fire
	 * WordPress's too-early notice and freeze the locale. So the catalog file
	 * stays pure data, and its translations come from the generated
	 * inc/edition-catalog-i18n.php — __() calls on literals, which
	 * `wp i18n make-pot` scans and absent_catalog() requires at admin-enqueue
	 * time, long after init (#997). The edition battery calls this with a
	 * `gettext` filter installed and asserts both halves (E42).
	 *
	 * TWO KEYS, TWO READERS. `units` is PRESENCE — every unit id this artifact
	 * contains, sorted; roles stay server-side — and the client's ONE presence
	 * signal, read by hasUnit() in inc/onboarding/src/edition.js and nowhere
	 * else (ESLint refuses a second reader). `absent` is COPY and is never a
	 * presence test: it has an entry only for a LABELED absent unit, so a
	 * control keyed on it flipped live the day a label was blanked (#1043);
	 * its one reader is screens/pro-row.js, and PHPStan holds Edition::absent()
	 * to absent_catalog() (blocklane.chokepointMember). E43 asserts the two keys
	 * disagree about no unit.
	 *
	 * @return array<string, mixed>
	 */
	public static function edition_payload(): array {
		$units = array_keys( Edition::units() );
		sort( $units );
		return array(
			'edition' => Edition::id(),
			'name'    => Edition::name(),
			'proUrl'  => Edition::pro_url(),
			'units'   => $units,
			'absent'  => self::absent_catalog(),
		);
	}

	/**
	 * The units this edition does not carry, each with its TRANSLATED label
	 * and blurb — label and blurb only: the one reader (screens/pro-row.js)
	 * draws the row from those two; `kind` rode along unread and left the
	 * payload (#988).
	 *
	 * The translations come from inc/edition-catalog-i18n.php, generated from
	 * the same manifest in the same run as inc/edition.php (--check keeps the
	 * two fresh together): a table of __() calls on literals, required here,
	 * after init. Never __( $variable ): that is the wordpress.org "Using
	 * variables" rejection, and PHPStan's blocklane.gettextLiteral rule fails
	 * it. A unit the strings file lacks (a stale build) shows its untranslated
	 * label rather than vanishing.
	 *
	 * @return array<string, array{label: string, blurb: string}>
	 */
	private static function absent_catalog(): array {
		$absent = Edition::absent();
		if ( array() === $absent ) {
			return array();
		}
		$file       = BLOCKLANE_PRO_PATH . '/inc/edition-catalog-i18n.php';
		$translated = is_file( $file ) ? (array) require $file : array();
		$catalog    = array();
		foreach ( $absent as $id => $unit ) {
			$catalog[ $id ] = array(
				'label' => (string) ( $translated[ $id ]['label'] ?? $unit['label'] ),
				'blurb' => (string) ( $translated[ $id ]['blurb'] ?? $unit['blurb'] ),
			);
		}
		return $catalog;
	}

	public function enqueue_app_assets() {
		$asset_file_path = BLOCKLANE_PRO_PATH . '/inc/onboarding/build/index.asset.php';

		if ( ! file_exists( $asset_file_path ) ) {
			return;
		}

		$asset_file = include $asset_file_path;
		$handle     = Branding::admin_app_handle();

		/**
		 * Filters the dashboard app script's dependencies. A unit whose screen
		 * needs a core script on this page adds the handle here and enqueues
		 * what that script needs, so this core file carries no unit's editor
		 * wiring and an edition without the unit loads none of it.
		 *
		 * @param string[] $deps Script handles from the build's asset file.
		 */
		$deps = array_values( array_filter( (array) apply_filters( 'blocklane_pro_dashboard_app_dependencies', $asset_file['dependencies'] ), 'is_string' ) );

		// The content-types screen's icon picker renders Dashicons glyphs.
		wp_enqueue_style( 'dashicons' );

		// wp.media for the dynamic-values screen's image picker (MediaUpload needs it).
		wp_enqueue_media();

		wp_enqueue_script(
			$handle,
			BLOCKLANE_PRO_URL . '/inc/onboarding/build/index.js',
			$deps,
			$asset_file['version'],
			true
		);

		$style_path = BLOCKLANE_PRO_PATH . '/inc/onboarding/build/style-index.css';

		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				$handle,
				BLOCKLANE_PRO_URL . '/inc/onboarding/build/style-index.css',
				array( 'wp-components' ),
				$asset_file['version']
			);
		}

		// The values the Create Child Theme form shows as placeholders — the
		// exact fallbacks generation resolves server-side when a field is left
		// blank (Helper::generate_child_theme) — plus the parent's screenshot,
		// which the generated child inherits.
		$parent_theme = wp_get_theme( get_template() );

		// Screenshot preview: the bundled branded child screenshot — the same
		// file generation copies into every child — cache-busted with the
		// file's mtime so an updated image always shows (the URL never changes
		// otherwise and browsers keep serving the stale cached copy).
		$shot_url  = BLOCKLANE_PRO_URL . '/inc/child-theme/screenshot.png';
		$shot_path = BLOCKLANE_PRO_PATH . '/inc/child-theme/screenshot.png';
		$shot_ver  = file_exists( $shot_path ) ? (string) filemtime( $shot_path ) : BLOCKLANE_PRO_VERSION;

		wp_localize_script(
			$handle,
			Branding::admin_js_object(),
			array(
				'edition'      => self::edition_payload(),
				'restUrl'      => esc_url_raw( rest_url( Branding::rest_namespace() . '/' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl'     => esc_url_raw( admin_url() ),
				'pluginUrl'    => esc_url_raw( BLOCKLANE_PRO_URL ),
				'version'      => BLOCKLANE_PRO_VERSION,
				// Environment for the dashboard error-boundary report (admin-only
				// screen; no secrets). Lets an errored screen produce a complete,
				// shareable diagnostic.
				'wpVersion'    => get_bloginfo( 'version' ),
				'phpVersion'   => PHP_VERSION,
				// The companion theme is a RECOMMENDATION, not a requirement —
				// the plugin runs on any block theme (run_plugin() checks
				// wp_is_block_theme(), nothing more). The dashboard surfaces
				// one card when it is not the active theme, and withholds
				// nothing either way. Deliberately not an admin notice: a
				// recommendation that interrupts is a nag.
				'companionTheme' => array(
					'active'    => Branding::RECOMMENDED_THEME_SLUG === get_template(),
					'installed' => wp_get_theme( Branding::RECOMMENDED_THEME_SLUG )->exists(),
					'name'      => wp_get_theme( Branding::RECOMMENDED_THEME_SLUG )->exists()
						? wp_get_theme( Branding::RECOMMENDED_THEME_SLUG )->get( 'Name' )
						: 'Blocklane',
					'themesUrl' => esc_url_raw( admin_url( 'themes.php' ) ),
				),
				// Whether the Create Child Theme tool is enabled (Advanced
				// toggle). The screen stays routable when off (deep links)
				// but renders a pointer to the toggle instead of the form.
				'childThemeTool' => self::child_theme_tool_on(),
				// Same pattern for the four Site Tools module screens: each
				// stays deep-linkable when off but renders a pointer to its
				// Advanced toggle instead of a screen whose REST route is
				// gone. Site Visibility counts as on whenever a lock is live
				// (its screen force-stays), matching screens_manifest.
				// Every key through tool_screen_on(), the same predicate the
				// submenu reads — so a screen this edition does not carry is
				// an explicit false here (the JS side fails open on a MISSING
				// key, never on a false one).
				'toolScreens'  => array(
					'content-types'  => self::tool_screen_on( 'content-types' ),
					'dynamic-values' => self::tool_screen_on( 'dynamic-values' ),
					'scripts'        => self::tool_screen_on( 'scripts' ),
					'site-privacy'   => self::tool_screen_on( 'site-privacy' ),
					'ai-mcp'         => self::tool_screen_on( 'ai-mcp' ),
					'seo'            => self::tool_screen_on( 'seo' ),
					'forms'          => self::tool_screen_on( 'forms' ),
				),
				'childTheme'   => array(
					'parentName' => $parent_theme->get( 'Name' ),
					'textDomain' => get_template() . '-child',
					'screenshot' => esc_url_raw( add_query_arg( 'v', $shot_ver, $shot_url ) ),
				),
				// Pass the theme's global stylesheet (theme.json compiled to CSS,
				// presets + element rules) into BlockPreview iframes so pattern
				// previews resolve var(--wp--preset--*) and look correct.
				'globalStyles' => function_exists( 'wp_get_global_stylesheet' )
					? wp_get_global_stylesheet()
					: '',
			)
		);

		wp_set_script_translations( $handle, 'blocklane', BLOCKLANE_PRO_PATH . '/languages' );
	}

	public function register_rest_routes() {
		// Gated feature modules self-register from the manifest (see Modules).
		//
		// The four hand-wired service controllers that used to be listed here
		// now register themselves from their own unit's lifecycle file. An
		// aggregator naming a controller class is an aggregator that fatals
		// when an edition does not carry it — the free build has no License
		// and no Abilities — and the gates it needs (advanced_tool_on and its
		// siblings, now public) are readable from anywhere.
		Modules::register_rest();
	}
}
