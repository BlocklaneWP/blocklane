<?php
/**
 * The `runtime:content-types` unit's lifecycle — the seeds for the two rows
 * its render shim reads on every request.
 *
 * inc/content-types/mu-runtime.php (this unit's content runtime, shipped in
 * BOTH editions) reads blocklane_pro_content_types and blocklane_pro_taxonomies
 * on every request to register the types and taxonomies a site authored with
 * Pro. An absent row costs a miss query each time. The seeds used to live in
 * module:content-types' lifecycle file, which only Pro carries, so a free-only
 * site paid the two miss queries forever (#849). THE SEED BELONGS TO THE
 * READER: this file is owned by the unit that pays the cost, present wherever
 * the runtime is. Not in the runtime itself — the render path does not write,
 * and seeding is once-per-version housekeeping.
 *
 * A NAMED listener, so the battery can assert has_action() and call it.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed the two definition rows the render shim reads. Add-if-null, idempotent.
 *
 * @return void
 */
function blocklane_pro_ct_runtime_seed(): void {
	blocklane_pro_seed_options( 'blocklane_pro_content_types', 'blocklane_pro_taxonomies' );
}
add_action( 'blocklane_pro_version_changed', 'blocklane_pro_ct_runtime_seed' );
