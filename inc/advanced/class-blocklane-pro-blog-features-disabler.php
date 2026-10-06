<?php
/**
 * Blog Features enforcer ("not a blog" cleanup).
 *
 * Removes the rarely-used blogging leftovers for a cleaner editor and Writing
 * settings page — Post Formats, Post via Email, and the Update Services pings.
 * All runtime, nothing destructive: no options are deleted and every effect
 * reverts the moment the toggle is switched back off.
 *
 * Registered by Advanced::apply() only when the `disable-blog-features` toggle
 * is on.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blog_Features_Disabler {

	public function __construct() {
		// Post Formats: drop theme support so the editor's Post Format panel (and
		// the Default Post Format setting) disappear. Runs after the theme has
		// declared its support (theme fires on after_setup_theme @10).
		add_action( 'after_setup_theme', array( $this, 'remove_post_formats' ), 999 );

		// Post via Email: remove the whole section server-side via core's own
		// filter — it's inert legacy plumbing that also stores mail credentials.
		add_filter( 'enable_post_by_email_configuration', '__return_false' );

		// Update Services: stop the default ping-o-matic pings fired on publish.
		add_filter( 'pre_option_ping_sites', '__return_empty_string' );

		// Writing settings page: hide the now-inert Update Services + Default Post
		// Format rows, and explain why.
		add_action( 'admin_print_styles-options-writing.php', array( $this, 'hide_writing_sections' ) );
		add_action( 'admin_notices', array( $this, 'writing_notice' ) );
	}

	public function remove_post_formats() {
		remove_theme_support( 'post-formats' );
	}

	/**
	 * Hide the leftover Writing-page sections. Post via Email is already gone
	 * (server-side filter), so the only `h2.title` section left is Update Services
	 * — hide its heading, its paragraph, and (on public sites) its ping_sites
	 * textarea. Also hide the Default Post Format row. Queued only on this
	 * screen, into its head's style print, so the rows never flash.
	 */
	public function hide_writing_sections(): void {
		Inline_Asset::style(
			'blocklane-pro-writing-hide',
			'#wpbody-content form h2.title,'
			. '#wpbody-content form h2.title + p,'
			. '#wpbody-content form h2.title + p + textarea,'
			. '#wpbody-content form tr:has(#default_post_format){display:none}'
		);
	}

	/**
	 * Explain on the Writing screen why those settings are gone, so it reads as
	 * intentional rather than broken.
	 */
	public function writing_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'options-writing' !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: plugin name. */
					__( 'Blog features (Post Formats, Post via Email, and Update Services) are turned off by %s, so those settings are hidden and inactive.', 'blocklane' ),
					Branding::plugin_name()
				)
			),
			esc_url( admin_url( 'admin.php?page=' . Branding::MENU_SLUG . '&screen=advanced' ) ),
			esc_html(
				sprintf(
					/* translators: %s: plugin name. */
					__( 'Manage in %s → Advanced', 'blocklane' ),
					Branding::plugin_name()
				)
			)
		);
	}
}
