<?php
/**
 * The `security` unit's lifecycle.
 *
 * Loaded at plugin-file scope through Modules::lifecycle().
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An absent row read on every request costs a miss query. Seed an empty
// autoloaded one; the security store normalizes an empty array to its
// defaults exactly like an absent row. The one seeder body is in
// inc/bootstrap.php (#850); this unit reads the row, so this unit seeds it.
blocklane_pro_seed_on_version_change( 'blocklane_pro_security' );
