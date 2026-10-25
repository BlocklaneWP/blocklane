<?php
/**
 * The `popups` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle().
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The popup render cache is rebuilt from scratch on the next front-end read,
// so a version change simply drops it. It moved from a transient to a
// size-gated option; clear the old spelling too so sites upgrading through
// that change leave nothing behind.
//
// The rows also self-heal via the schema version stamped into the payload
// (BLOCKLANE_PRO_POPUPS_CACHE_SCHEMA) — that is the guarantee, since it holds
// whether or not this hook ran. This flush is the cheap belt to that
// suspenders.
add_action(
	'blocklane_pro_version_changed',
	static function () {
		delete_option( 'blocklane_pro_popup_rows' );
		delete_transient( 'blocklane_pro_popup_rows' );
	}
);
