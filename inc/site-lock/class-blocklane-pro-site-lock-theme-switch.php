<?php
/**
 * Warn before activating a classic theme takes Site Visibility offline.
 *
 * Blocklane Pro's feature modules — the Site Lock gate among them — boot only
 * on a block theme: blocklane_pro_run_plugin() checks wp_is_block_theme() and
 * returns before Modules::boot() otherwise. That is deliberate and normal for
 * a block-theme plugin, but it means activating a classic theme turns a live
 * Coming Soon or Maintenance gate OFF and puts the site in public view. Core
 * says nothing at the moment of the switch, so this does: a confirm dialog on
 * Appearance → Themes that names the running mode and what activating costs.
 *
 * This covers the one door the plugin is still running behind. The doors it
 * cannot cover — WP-CLI, the network admin, the theme installer, a Customizer
 * "Activate & Publish" — are caught AFTER the fact by the second line on the
 * requires-a-block-theme notice (see blocklane-pro.php).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the Appearance → Themes confirm dialog while the lock is gating.
 */
class Site_Lock_Theme_Switch {

	const HANDLE = 'blocklane-pro-site-lock-theme-switch';

	/**
	 * Register the hooks. Called from Site_Lock_Gate's constructor, so it
	 * inherits the module's own boot conditions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue the dialog on the Themes screen — and only where it can still
	 * tell the truth: the user must be able to switch themes at all, and the
	 * lock must actually be gating someone. An inert passwordless Coming Soon
	 * costs nothing to lose, so warning about it would be noise (the same test
	 * the toolbar badge and the cache purge already apply).
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'themes.php' !== $hook_suffix || ! current_user_can( 'switch_themes' ) ) {
			return;
		}
		if ( ! Site_Lock::is_gating() ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			BLOCKLANE_PRO_URL . '/inc/site-lock/assets/theme-switch-warning.js',
			array(),
			BLOCKLANE_PRO_VERSION,
			true
		);
		wp_enqueue_style(
			self::HANDLE,
			BLOCKLANE_PRO_URL . '/inc/site-lock/assets/theme-switch-warning.css',
			array(),
			BLOCKLANE_PRO_VERSION
		);

		wp_localize_script( self::HANDLE, 'blocklaneProThemeSwitchWarning', self::data() );
	}

	/**
	 * The dialog's copy and the block-theme roster it decides against.
	 *
	 * @return array<string,mixed>
	 */
	private static function data() {
		$coming_soon = Site_Lock::MODE_COMING_SOON === Site_Lock::mode();
		$mode_label  = $coming_soon
			? __( 'Coming Soon', 'blocklane' )
			: __( 'Maintenance Mode', 'blocklane' );

		return array(
			// Stylesheets that ARE block themes. The dialog warns about
			// everything NOT on this list, so a theme the roster somehow
			// misses gets a warning rather than silence — the failure this
			// whole dialog exists to prevent.
			'blockThemes'  => self::block_theme_stylesheets(),
			'title'        => sprintf(
				/* translators: %s: the active site visibility mode, e.g. "Coming Soon". */
				__( 'Activating this theme turns off %s', 'blocklane' ),
				$mode_label
			),
			/* translators: 1: theme name, 2: plugin name, 3: the active site visibility mode. */
			'body'         => __( '%1$s is a classic theme. %2$s only runs on a block theme, so activating %1$s stops %3$s from being enforced and your site becomes publicly visible immediately.', 'blocklane' ),
			'reassurance'  => sprintf(
				/* translators: %s: the active site visibility mode, e.g. "Coming Soon". */
				__( 'Your %s settings are kept. Switching back to a block theme starts enforcing them again.', 'blocklane' ),
				$mode_label
			),
			'confirmLabel' => __( 'Activate anyway', 'blocklane' ),
			'cancelLabel'  => __( 'Cancel', 'blocklane' ),
			'modeLabel'    => $mode_label,
			'pluginName'   => Branding::plugin_name(),
			// Used only when the theme's name can't be read from the DOM.
			'fallbackName' => __( 'This theme', 'blocklane' ),
		);
	}

	/**
	 * Every installed block theme's stylesheet.
	 *
	 * Read from WP_Theme rather than core's client-side theme collection: one
	 * authority, deliberately, since a second source that can disagree is a
	 * mirror to keep in sync. Anything absent from this roster is treated as
	 * classic and warned about.
	 *
	 * @return string[]
	 */
	private static function block_theme_stylesheets() {
		$block_themes = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( $theme->is_block_theme() ) {
				$block_themes[] = (string) $stylesheet;
			}
		}
		return $block_themes;
	}
}
