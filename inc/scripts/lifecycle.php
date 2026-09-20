<?php
/**
 * The `scripts` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle(). Registration
 * ORDER INSIDE THIS FILE IS LOAD-BEARING: the legacy-file import runs before
 * the row seed, and both ride the same hook.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom Scripts used to keep its settings inside a generated mu-plugin.
// Move any surviving file into the option and remove it — a no-op on every
// site that never had one.
add_action(
	'blocklane_pro_version_changed',
	static function () {
		blocklane_pro\Scripts::migrate_legacy_file();
	}
);

// Custom Scripts joined the seeded-rows list when it moved out of its
// generated file: Scripts::boot() reads the row on every front-end request,
// and a site that has never written a script would otherwise pay a miss query
// for it forever. Seeding is only half — Scripts::save() also stores the empty
// row instead of deleting it, or the next "clear everything" would bring the
// miss back.
//
// Registered AFTER migrate_legacy_file() above so it runs after it on the same
// hook, and the import gate tests for stored CODE rather than a stored row, so
// this cannot cancel a pending legacy import either way round. The one seeder
// body is in inc/bootstrap.php (#850); this unit reads the row, so this unit
// seeds it.
blocklane_pro_seed_on_version_change( 'blocklane_pro_scripts' );

// The Scripts editor REST routes. The toggle hides the EDITOR; it deliberately
// does not silence code the user has already saved — that emission is
// Scripts::boot(), in this unit's content runtime, and is not behind this gate.
add_action(
	'rest_api_init',
	static function () {
		if ( blocklane_pro\Settings::advanced_tool_on( 'scripts' ) ) {
			( new blocklane_pro\Scripts_Controller() )->register_routes();
		}
	}
);
