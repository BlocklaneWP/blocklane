<?php
/**
 * Branding: single source of truth for vendor-specific strings.
 *
 * Constants in the EDITABLE section drive the plugin's INTERNAL identity —
 * option keys, slugs, handles, namespaces — which both editions share. The
 * product NAME is not a constant: it is edition data (Edition::name(),
 * rendered into inc/edition.php by the generator), read through
 * plugin_name(), so the free build calls itself "Blocklane" in the menu,
 * the notices and the dialogs without a second copy of this class (#847).
 *
 * Note: text-domain strings in __()/_e()/esc_html__() calls, namespace
 * declarations, function names, and the plugin header MUST remain literal —
 * gettext extraction, the autoloader, and WordPress's plugin parser all
 * read source statically. Those are handled outside this class.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Branding {

	// ===== Editable per release =====

	const VENDOR_KEY    = 'blocklane_pro';
	const VENDOR_SLUG   = 'blocklane-pro';
	const VENDOR_CAMEL  = 'blocklanePro';
	const VENDOR_CONST  = 'BLOCKLANE_PRO';

	const AUTHOR        = 'Garrett Johnson';

	const MENU_SLUG     = 'blocklane-pro';

	// ===== Companion theme =====

	/**
	 * The theme Blocklane Pro is designed around — a recommendation, not a
	 * requirement. The plugin runs on any block theme; the only hard theme
	 * condition is wp_is_block_theme(), checked in run_plugin().
	 *
	 * Requiring this slug never protected revenue: the theme is free. What is
	 * paid for is the license key — a key buys installs, updates and support,
	 * enforced by the update gateway (see the License class docblock), not by
	 * anything in the running plugin.
	 */
	const RECOMMENDED_THEME_SLUG = 'blocklane';

	// ===== Derived =====

	/**
	 * The product name of THIS artifact — 'Blocklane Pro' in the Pro build,
	 * 'Blocklane' in the free build. Edition data, never a literal: every
	 * user-visible sentence that names the product reads it from here.
	 *
	 * @return string
	 */
	public static function plugin_name(): string {
		return Edition::name();
	}

	public static function option_key() {
		return self::VENDOR_KEY;
	}

	/**
	 * The i18n text domain — 'blocklane', NOT the vendor slug.
	 *
	 * wordpress.org keys language packs on the plugin SLUG and Plugin Check
	 * flags a Text Domain that differs from it, so the directory build's domain
	 * is fixed at 'blocklane'. Free is a build target of this tree rather than
	 * a fork, so the source carries one domain and both editions use it; Pro is
	 * not on wordpress.org, so nothing pulls the other way.
	 */
	public static function text_domain() {
		return 'blocklane';
	}

	public static function rest_namespace() {
		return self::VENDOR_SLUG . '/v1';
	}

	public static function admin_app_dom_id() {
		return self::VENDOR_SLUG . '-app';
	}

	public static function admin_app_handle() {
		return self::VENDOR_SLUG . '-app';
	}

	public static function admin_js_object() {
		return self::VENDOR_CAMEL . 'Admin';
	}

	public static function extensions_handle() {
		return self::VENDOR_SLUG . '-extensions';
	}

	public static function extensions_js_object() {
		return self::VENDOR_CAMEL . 'Extensions';
	}
}
