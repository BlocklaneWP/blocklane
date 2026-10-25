<?php
/**
 * The `seo` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle().
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the default share-image URL once, for SEO options saved before
 * Seo::save() derived it — the runtime otherwise falls back to the two-query
 * live resolve on every page without a featured image.
 *
 * Uses the RUNTIME HELPER, not blocklane_pro\Seo::og_image_url(). This runs at
 * init 5 on the first request after ANY version change, front end included,
 * while the Seo CLASS loads only with the seo module — which is toggle-gated
 * behind an Advanced toggle that ships off. Calling the class here was a fatal
 * on every request for any site with a stored share image and the module
 * gated: the resync aborts before the version stamp is written, so it
 * re-enters and re-fatals forever. inc/seo/runtime.php is a content row in
 * Modules::content(), loaded license-ungated, so the helper is present
 * whenever this unit is.
 *
 * @return void
 */
function blocklane_pro_seo_resync_share_image(): void {
	$stored = get_option( 'blocklane_pro_seo', null );
	if ( ! is_array( $stored )
		|| empty( $stored['default_og_image_id'] )
		|| ! empty( $stored['default_og_image_url'] ) ) {
		return;
	}
	if ( ! function_exists( 'blocklane_pro_seo_og_image_url' ) ) {
		return;
	}
	$stored['default_og_image_url'] = blocklane_pro_seo_og_image_url( $stored['default_og_image_id'] );
	update_option( 'blocklane_pro_seo', $stored );
}
add_action( 'blocklane_pro_version_changed', 'blocklane_pro_seo_resync_share_image' );
