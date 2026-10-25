<?php
/**
 * The `advanced` unit's lifecycle: the consented check of the SVG files a
 * pre-1.0.2 upload hole could have stored (Svg_Cleanup) — the listener that
 * arms it, its one notice, and the two admin-post forms on that notice.
 * Nothing else: no cron event and no admin_init driver, so nothing runs
 * unless an administrator asks (the review battery's WC0 reads this exact
 * set from the hook registry).
 *
 * Loaded at plugin-file scope through Modules::lifecycle(). Both editions
 * carry Safe SVG Uploads, so both carry this file.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'blocklane_pro_version_changed', array( 'blocklane_pro\\Svg_Cleanup', 'arm' ) );
add_action( 'admin_notices', array( 'blocklane_pro\\Svg_Cleanup', 'render_notice' ) );
add_action( 'admin_post_blocklane_pro_svg_check_clean', array( 'blocklane_pro\\Svg_Cleanup', 'handle_clean' ) );
add_action( 'admin_post_blocklane_pro_svg_check_dismiss', array( 'blocklane_pro\\Svg_Cleanup', 'handle_dismiss' ) );
