<?php
/**
 * Bake_Reaper — removes the retired bake system's generated mu-plugins.
 *
 * Pre-2026-08 versions stamped generated copies of the module runtimes into
 * mu-plugins, where they load first and win each runtime's single-load guard —
 * silently pinning a site to OLD module code after every update (the
 * 2026-08-05 dev-site fatal's class). The bake system is gone; this reaps its
 * generated bootstraps. Only top-level mu-plugins .php files auto-load, so
 * deleting the bootstraps is the whole cure — their copied dirs become inert
 * and are swept best-effort, per-node provenance-checked (#154).
 *
 * Loaded from TWO contexts, and deliberately self-contained for the second:
 * the version-change listener in blocklane_pro_boot_lifecycle(), and
 * uninstall.php — where the main plugin file never runs, so this class uses
 * no BLOCKLANE_PRO_* constants and no other plugin classes. Only
 * WPMU_PLUGIN_DIR and core functions.
 *
 * A LENT DECLARATION. uninstall.php requires this file behind class_exists,
 * and in a bulk delete of both editions the second edition's uninstall runs
 * the FIRST edition's copy of this class — so reap() is a cross-version
 * contract: its signature line is pinned as an exact string in
 * bin/uninstall-battery.php (U16), and a change is a NEW name with the old
 * method kept (wp-includes/compat.php's rule, #829). A body-only change
 * under the same signature runs the lender's body for the caller.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bake_Reaper {

	/**
	 * The bake system's artifacts — slug => [ the Plugin Name its generator
	 * wrote, whether a copied dir was written beside the bootstrap ]. The list
	 * IS the bookkeeping (docs/archive/bake-contract.md, "Current artifacts"):
	 * enumerate from it, never from the directory, and never touch
	 * blocklane-pro-scripts.php. That file is USER-authored content: in Pro,
	 * Scripts::migrate_legacy_file() moves its code into an option; in the free
	 * edition (no Scripts unit) nothing reads it, and only uninstall.php's
	 * File_Ops::retire_legacy_scripts() moves it out of the load path. This
	 * constant is the ONE copy of the list in the repo; the reaper's two call
	 * sites (lifecycle boot, uninstall) both read it from here.
	 *
	 * What the generators wrote is a FIXTURE, not a recollection:
	 * bin/fixtures/bake-bootstraps.json holds each writer's real output,
	 * captured by running it from its tagged tree (bin/capture-bake-
	 * bootstraps.php), and the uninstall battery's U17a/U17e plant those bytes.
	 * Tags: pro/v0.3.1, the last release that shipped the writers (0.4.0's
	 * changelog announces their removal; no 0.3.2 was released), and
	 * archive/runtime-bake-v0.3.2, their last state before commit 8c034583
	 * retired them. Both tags produce the same bytes, and every revision of
	 * each writer in history opens with the same three string literals
	 * ("<?php\n", "/**\n", " * Plugin Name: <name>\n"), never PHP_EOL. The
	 * writers saved through WP_Filesystem, whose FTP transports upload in
	 * binary mode, so no generator could have produced CRLF or a BOM; a file
	 * that has either was edited by someone, and is left alone.
	 */
	public const BAKED_BOOTSTRAPS = array(
		'blocklane-pro-extensions'     => array( 'Blocklane Pro: Extensions', true ),
		'blocklane-pro-forms'          => array( 'Blocklane Pro: Forms', true ),
		'blocklane-pro-menu-designer'  => array( 'Blocklane Pro: Menu Designer', true ),
		'blocklane-pro-popups'         => array( 'Blocklane Pro: Popups', true ),
		'blocklane-pro-dynamic-values' => array( 'Blocklane Pro: Dynamic Values', false ),
		'blocklane-pro-content-types'  => array( 'Blocklane Pro: Content Types', false ),
	);

	/**
	 * Delete the baked bootstraps and sweep their copied dirs.
	 *
	 * Provenance discipline (#154): nothing is deleted on its NAME. A
	 * bootstrap is ours only when it opens with the exact header its
	 * generator wrote (is_bake_bootstrap()); a third-party file that merely
	 * shares the name, or mentions Blocklane, is left alone. The copied dir
	 * beside it is swept only on that positive identification in the same
	 * pass, and even then a .php node must carry the word in its own head.
	 * Whatever fails the test stays, the rmdir fails on the non-empty dir,
	 * and the residue is left for a human — a directory is never destroyed
	 * wholesale by name.
	 *
	 * The trade-off that rule makes, named: a copied dir whose bootstrap is
	 * ALREADY gone (an earlier sweep that stopped at an unreadable child) is
	 * never swept again, because nothing left in it identifies it. Its files
	 * are inert — only top-level mu-plugins files load, and every copied
	 * runtime opens with an ABSPATH guard — so the cost is disk residue, not
	 * behavior (U17d pins it).
	 *
	 * @return void
	 */
	public static function reap() {
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
			return;
		}

		foreach ( self::BAKED_BOOTSTRAPS as $slug => list( $plugin_name, $has_dir ) ) {
			$file           = WPMU_PLUGIN_DIR . '/' . $slug . '.php';
			$bootstrap_ours = is_file( $file ) && self::is_bake_bootstrap( $file, $plugin_name );

			if ( $bootstrap_ours ) {
				wp_delete_file( $file );
			}

			// No positive identification this pass, no sweep: a dir of the
			// same name beside a file we did not write is not ours either.
			if ( ! $has_dir || ! $bootstrap_ours ) {
				continue;
			}

			$dir = WPMU_PLUGIN_DIR . '/' . $slug;
			if ( ! is_dir( $dir ) || is_link( $dir ) ) {
				continue;
			}

			// Best-effort sweep of the copied dir (inert once its bootstrap
			// is gone): children first, dir last, provenance per node.
			// Guarded end to end: an unreadable or race-deleted (sub)dir
			// throws UnexpectedValueException, and an uncaught throw here
			// fatals the request BEFORE Version_Migration writes its stamp —
			// so the same fatal would repeat on every request (#168).
			// CATCH_GET_CHILD keeps the walk alive past unreadable children;
			// the outer catch abandons just this dir for a human.
			try {
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::CHILD_FIRST,
					\RecursiveIteratorIterator::CATCH_GET_CHILD
				);
				foreach ( $iterator as $node ) {
					$pathname = $node->getPathname();

					if ( $node->isDir() && ! $node->isLink() ) {
						if ( self::dir_is_empty( $pathname ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
							rmdir( $pathname );
						}
						continue;
					}

					if ( 'php' === strtolower( (string) pathinfo( $pathname, PATHINFO_EXTENSION ) ) ) {
						$head = (string) file_get_contents( $pathname, false, null, 0, 512 );
						if ( false === stripos( $head, 'blocklane' ) ) {
							continue; // Not provably ours — leave it.
						}
						wp_delete_file( $pathname );
						continue;
					}

					// Assets carry no marker of their own: this pass's positive
					// identification of the bootstrap is the evidence.
					wp_delete_file( $pathname );
				}
			} catch ( \UnexpectedValueException $e ) {
				continue; // Unreadable — leave the dir for a human, keep reaping the rest.
			}

			if ( self::dir_is_empty( $dir ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $dir );
			}
		}
	}

	/**
	 * Whether a file is a bake bootstrap: it opens with the exact lines its
	 * generator wrote — the PHP open tag, the docblock opener and
	 * ` * Plugin Name: <name>` — byte for byte. A file of the same name that
	 * does not is someone else's.
	 *
	 * @param string $file        Absolute path of the candidate bootstrap.
	 * @param string $plugin_name The Plugin Name its generator wrote.
	 * @return bool
	 */
	private static function is_bake_bootstrap( string $file, string $plugin_name ): bool {
		$head = (string) file_get_contents( $file, false, null, 0, 512 );

		return str_starts_with( $head, "<?php\n/**\n * Plugin Name: {$plugin_name}\n" );
	}

	/**
	 * Whether a directory holds nothing but its dot entries.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool
	 */
	private static function dir_is_empty( $dir ) {
		$entries = scandir( $dir );

		return is_array( $entries ) && 2 === count( $entries );
	}
}
