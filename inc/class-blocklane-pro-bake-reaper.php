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
	 * The bake system's artifacts — slug => whether a copied dir was written
	 * beside the bootstrap. The list IS the bookkeeping (docs/archive/
	 * bake-contract.md, "Current artifacts"): enumerate from it, never from
	 * the directory, and never touch blocklane-pro-scripts.php — that file is
	 * USER-authored content with its own migration (Scripts::migrate_legacy_file).
	 * This constant is the ONE copy of the list in the repo; the reaper's two
	 * call sites (lifecycle boot, uninstall) both read it from here.
	 */
	public const BAKED_BOOTSTRAPS = array(
		'blocklane-pro-extensions'     => true,
		'blocklane-pro-forms'          => true,
		'blocklane-pro-menu-designer'  => true,
		'blocklane-pro-popups'         => true,
		'blocklane-pro-dynamic-values' => false,
		'blocklane-pro-content-types'  => false,
	);

	/**
	 * Delete the baked bootstraps and sweep their copied dirs.
	 *
	 * Provenance discipline (#154): the "only delete what self-identifies as
	 * ours" belt applies to EVERY node, not just the top-level bootstrap.
	 * A .php node must carry the marker in its own head; any other node is
	 * deleted only when this reap positively identified the dir's bootstrap
	 * as ours in the same pass. Whatever fails the test stays, the rmdir
	 * fails on the non-empty dir, and the residue is left for a human —
	 * a directory is never destroyed wholesale by name.
	 *
	 * @return void
	 */
	public static function reap() {
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
			return;
		}

		foreach ( self::BAKED_BOOTSTRAPS as $slug => $has_dir ) {
			$file            = WPMU_PLUGIN_DIR . '/' . $slug . '.php';
			$bootstrap_ours  = false;

			if ( is_file( $file ) ) {
				// Belt: only delete a file that self-identifies as ours —
				// a same-named file we did not generate is left for a
				// human to judge.
				$head = (string) file_get_contents( $file, false, null, 0, 512 );
				if ( false !== stripos( $head, 'blocklane' ) ) {
					$bootstrap_ours = true;
					wp_delete_file( $file );
				}
			}

			if ( ! $has_dir ) {
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

					// Assets carry no marker of their own: delete them only on
					// this pass's positive evidence that the dir is a bake.
					if ( $bootstrap_ours ) {
						wp_delete_file( $pathname );
					}
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
