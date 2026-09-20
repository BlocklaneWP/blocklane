<?php
/**
 * Filesystem work, routed through WordPress instead of PHP primitives.
 *
 * Two jobs, both of which uninstall.php and the Scripts runtime were each
 * hand-rolling with `@rename` / `@mkdir` / `@rmdir` / `@unlink`: retire the
 * legacy mu-plugins scripts file out of the load path WITHOUT deleting it, and
 * remove a directory tree the plugin owns.
 *
 * WHY THE WORDPRESS LAYER AND NOT rename(). Not to satisfy a sniff. A raw
 * rename() is performed by the web server user, and on a host whose filesystem
 * method is FTP or SSH that user cannot write there at all — the move silently
 * failed and the user's scripts kept executing forever with nothing left to
 * manage them. `delete_plugins()` has already opened $wp_filesystem with
 * whatever credentials the host required, so using it makes the move work on
 * hosts where it previously could not.
 *
 * THE RULE THIS FILE EXISTS TO KEEP. This codebase has already had an uninstall
 * routine delete user-owned scripts alongside plugin state, because cleanup
 * enumerated a DIRECTORY instead of the plugin's own bookkeeping. So:
 *
 *   - retire_legacy_scripts() NEVER calls a delete. Not once, on any path. The
 *     worst outcome it can produce is "the file is still where it was", which
 *     is also what the default (non-opt-in) path does on every site.
 *   - remove_tree() refuses a symlinked root and never follows a symlink
 *     inside, so a planted link cannot turn "remove our own directory" into
 *     "empty someone else's".
 *
 * A LENT DECLARATION. uninstall.php reaches this class behind class_exists,
 * and in a bulk delete of both editions the second edition runs the FIRST
 * edition's copy — so filesystem(), retire_legacy_scripts() and
 * remove_tree() are a cross-version contract: their signature lines are
 * pinned as exact strings in bin/uninstall-battery.php (U16), and a change
 * to any of them is a NEW name with the old method kept (wp-includes/
 * compat.php's rule, #829). A body-only change under the same signature
 * runs the lender's body for the caller; keep bodies backward-compatible.
 *
 * Self-contained by the same rule as Bake_Reaper: it is hand-required from
 * uninstall.php, where no autoloader exists, so it may reference only core,
 * WP_Filesystem_Base and its own arguments — no BLOCKLANE_PRO_* constants, no
 * other first-party class.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filesystem operations that go through WP_Filesystem.
 */
final class File_Ops {

	/**
	 * The connected filesystem, or a typed reason there is none.
	 *
	 * Three contexts reach this, and each has an answer:
	 *
	 *   wp-admin Delete — delete_plugins() has ALREADY called
	 *   request_filesystem_credentials() and WP_Filesystem() before
	 *   uninstall_plugin() runs, and bails if the connection failed. So the
	 *   global is connected with whatever the host needed, FTP included.
	 *
	 *   WP-CLI — the global is unset, but WP-CLI forces the filesystem method
	 *   to 'direct', so WP_Filesystem() with no arguments connects.
	 *
	 *   A third-party caller of uninstall_plugin() — direct hosts connect; every
	 *   non-direct host gets the WP_Error, FTP_* constants or not. WP_Filesystem()
	 *   with no arguments hands `false` to the FTP transport, which fails on an
	 *   empty hostname; the constants are read only by
	 *   request_filesystem_credentials(), which this deliberately never calls
	 *   (#822 — the earlier docblock claimed wider than the mechanism).
	 *
	 * request_filesystem_credentials() is deliberately NOT called here: there
	 * is no interactive request to prompt in, and it would print a form into
	 * whatever is running.
	 *
	 * @return \WP_Filesystem_Base|\WP_Error
	 */
	public static function filesystem(): \WP_Filesystem_Base|\WP_Error {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base && ! $wp_filesystem->errors->has_errors() ) {
			return $wp_filesystem;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( true !== \WP_Filesystem() || ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return new \WP_Error(
				'blocklane_pro_fs_unavailable',
				'No WP_Filesystem connection (method: ' . get_filesystem_method() . ').'
			);
		}

		return $wp_filesystem;
	}

	/**
	 * Move the legacy mu-plugins scripts file out of the load path, keeping it.
	 *
	 * The file holds the SITE OWNER'S OWN CODE. Deleting it is never correct,
	 * and leaving it in mu-plugins after the plugin is gone means it executes
	 * on every request forever with nothing left to manage it. So it moves into
	 * a subdirectory — which is what stops WordPress auto-loading it, since
	 * only top-level *.php in mu-plugins is loaded.
	 *
	 * It KEEPS its .php extension. wp-content is web-served, and an earlier
	 * revision that renamed it to .txt published the user's code at a
	 * predictable URL (verified: 200, with the file body). A .php is executed
	 * instead, and the generated file opens with an ABSPATH guard, so a direct
	 * request gets nothing.
	 *
	 * @param \WP_Filesystem_Base $fs       A connected filesystem.
	 * @param string              $mu_dir   The mu-plugins directory.
	 * @param string              $filename Mirrors Scripts::LEGACY_MU_FILENAME.
	 * @return true|\WP_Error True when the file is gone from the load path — the
	 *                        native type is bool|WP_Error because PHP 8.1, this
	 *                        plugin's floor, has no standalone `true` type —
	 *                        including when it was never there.
	 */
	public static function retire_legacy_scripts( \WP_Filesystem_Base $fs, string $mu_dir, string $filename = 'blocklane-pro-scripts.php' ): bool|\WP_Error {
		$mu_dir = rtrim( str_replace( '\\', '/', $mu_dir ), '/' );
		$source = $mu_dir . '/' . $filename;

		if ( is_link( $source ) ) {
			// Following it would move whatever it points at. Never.
			return new \WP_Error( 'blocklane_pro_retire_symlink', 'The legacy scripts file is a symlink; leaving it untouched.' );
		}
		if ( ! is_file( $source ) ) {
			return true;
		}

		$remote_dir = $fs->find_folder( $mu_dir );
		if ( ! is_string( $remote_dir ) || '' === $remote_dir ) {
			return new \WP_Error( 'blocklane_pro_retire_unresolved', 'Could not resolve ' . $mu_dir . ' on the filesystem connection.' );
		}
		$remote_dir = trailingslashit( $remote_dir );
		$kept_dir   = $remote_dir . 'blocklane-pro-scripts-removed';

		// FS_CHMOD_DIR is defined inside WP_Filesystem(), which filesystem() never
		// calls when a connected global already exists — so it may be undefined
		// here (#823). Same expression core uses to define it.
		$chmod_dir = defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : ( fileperms( ABSPATH ) & 0777 | 0755 );
		if ( ! $fs->is_dir( $kept_dir ) && ! $fs->mkdir( $kept_dir, $chmod_dir ) ) {
			return new \WP_Error( 'blocklane_pro_retire_mkdir', 'Could not create ' . $kept_dir . '.' );
		}

		// A free name, so an earlier retirement is never overwritten.
		$target = $kept_dir . '/' . $filename;
		$n      = 1;
		while ( $fs->exists( $target ) && $n < 100 ) {
			$target = $kept_dir . '/blocklane-pro-scripts-' . $n . '.php';
			++$n;
		}
		if ( $fs->exists( $target ) ) {
			return new \WP_Error( 'blocklane_pro_retire_no_free_name', 'No free name in ' . $kept_dir . ' after 100 tries.' );
		}

		// $overwrite false, always: this call may not clobber anything.
		if ( ! $fs->move( $remote_dir . $filename, $target, false ) ) {
			return new \WP_Error( 'blocklane_pro_retire_move', 'Could not move ' . $source . ' to ' . $target . '.' );
		}

		// The move is only done when the source is actually gone. WP's direct
		// implementation falls back to copy-then-delete, and deletes the source
		// only after confirming the destination exists — but assert it here
		// rather than trust it, because "still in the load path" is the one
		// outcome this function exists to prevent.
		clearstatcache( true, $source );
		if ( file_exists( $source ) ) {
			return new \WP_Error( 'blocklane_pro_retire_still_present', $source . ' is still in the mu-plugins load path.' );
		}

		return true;
	}

	/**
	 * Remove a directory tree the plugin owns.
	 *
	 * Enumerated locally, deleted through the filesystem connection. Symlinks
	 * are removed as links and never followed: a link inside the tree is
	 * deleted, a link AS the tree's root is refused outright. That is the whole
	 * point — without it, one planted symlink turns "remove our own uploads
	 * directory" into "empty whatever it points at".
	 *
	 * Deliberately NOT $fs->delete( $dir, true ): WP_Filesystem_Direct::dirlist()
	 * types entries with is_dir(), which FOLLOWS symlinks, so its recursion
	 * would descend through a planted link.
	 *
	 * @param \WP_Filesystem_Base $fs  A connected filesystem.
	 * @param string              $dir Absolute path of the directory to remove.
	 * @return true|\WP_Error True when the tree is gone, including when it was
	 *                        never there.
	 */
	public static function remove_tree( \WP_Filesystem_Base $fs, string $dir ): bool|\WP_Error {
		$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		if ( '' === $dir ) {
			return new \WP_Error( 'blocklane_pro_tree_empty_path', 'Refusing to remove an empty path.' );
		}
		if ( is_link( $dir ) ) {
			return new \WP_Error( 'blocklane_pro_tree_symlink_root', $dir . ' is a symlink; leaving it and its target untouched.' );
		}
		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$remote_root = $fs->find_folder( $dir );
		if ( ! is_string( $remote_root ) || '' === $remote_root ) {
			return new \WP_Error( 'blocklane_pro_tree_unresolved', 'Could not resolve ' . $dir . ' on the filesystem connection.' );
		}
		$remote_root = untrailingslashit( $remote_root );
		$left        = array();

		$walk = new \RecursiveIteratorIterator(
			// No FOLLOW_SYMLINKS: a link is an entry to delete, not a door.
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $walk as $item ) {
			$path   = str_replace( '\\', '/', $item->getPathname() );
			$remote = $remote_root . substr( $path, strlen( $dir ) );
			// isLink() FIRST: a symlink to a directory answers isDir() true,
			// and rmdir on it would be the wrong call for the wrong object.
			$ok = $item->isLink() || ! $item->isDir()
				? $fs->delete( $remote, false, 'f' )
				: $fs->rmdir( $remote );
			if ( ! $ok ) {
				$left[] = $path;
			}
		}

		if ( ! $fs->rmdir( $remote_root ) ) {
			$left[] = $dir;
		}

		if ( array() !== $left ) {
			return new \WP_Error(
				'blocklane_pro_tree_incomplete',
				'Could not remove ' . count( $left ) . ' path(s) under ' . $dir . '.',
				array( 'left' => $left )
			);
		}

		return true;
	}
}
