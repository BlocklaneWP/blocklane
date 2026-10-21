<?php
/**
 * Loading the plugin's translations — both editions, one door
 * (spec 2026-10-15-bundled-patterns D7, security amendment F2).
 *
 * WHY EXPLICIT. The plugin and the Blocklane theme share the `blocklane` text
 * domain, and the theme loads its own pack explicitly at after_setup_theme
 * (its functions.php, blocklane_load_textdomain()). Once any file has loaded
 * a domain, WordPress's just-in-time loader never runs for it again, so the
 * plugin's wordpress.org pack (languages/plugins/blocklane-<locale>.mo) would
 * never load under the theme, and every plugin string, the bundled patterns'
 * titles and copy included, would stay English. load_textdomain() adds a
 * file to a domain that is already loaded (the translation controller merges
 * per domain), so both packs serve the one domain.
 *
 * WHY NOT CORE'S PLUGIN-DOMAIN HELPER. Since WordPress 6.7 it only registers
 * a just-in-time path, which the theme's load has already made dead, and
 * Plugin Check flags the call in a directory-hosted plugin. The free edition's
 * forbidden strings name it, so it cannot come back.
 *
 * Hooked on `init` (before the bundled patterns register at init 20) and on
 * `change_locale`, where switch_to_locale() has unloaded the domain.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_load_translations' ) ) {

	/**
	 * Load the plugin's language pack into the `blocklane` domain.
	 *
	 * @param string $locale Optional. The locale switched to; defaults to the current one.
	 * @return void
	 */
	function blocklane_pro_load_translations( string $locale = '' ): void {
		$locale = '' !== $locale ? $locale : determine_locale();
		load_textdomain( 'blocklane', WP_LANG_DIR . '/plugins/blocklane-' . $locale . '.mo', $locale );
	}

	add_action( 'init', 'blocklane_pro_load_translations' );
	add_action( 'change_locale', 'blocklane_pro_load_translations' );
}
