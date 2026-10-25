<?php
/**
 * Filesystem work, routed through WordPress instead of PHP primitives.
 *
 * Two jobs: remove a directory tree the plugin owns (once hand-rolled by
 * uninstall.php), and write inside uploads (uploads_dir(), uploads_ensure(),
 * uploads_put(), uploads_swap()), refusing any path outside it.
 *
 * WHY THE WORDPRESS LAYER AND NOT PHP's own rmdir()/unlink(). Not to satisfy
 * a sniff. A raw call is performed by the web server user, and on a host whose
 * filesystem method is FTP or SSH that user may not be able to write there at
 * all. `delete_plugins()` has already opened $wp_filesystem with whatever
 * credentials the host required, so using it makes the removal work on hosts
 * where it previously could not.
 *
 * THE RULE THIS FILE KEEPS. This codebase has already had an uninstall routine
 * delete user-owned scripts alongside plugin state, because cleanup enumerated
 * a DIRECTORY instead of the plugin's own bookkeeping. So remove_tree()
 * refuses a symlinked root and never follows a symlink inside, so a planted
 * link cannot turn "remove our own directory" into "empty someone else's".
 *
 * A LENT DECLARATION. uninstall.php reaches this class behind class_exists,
 * and in a bulk delete of both editions the second edition runs the FIRST
 * edition's copy — so filesystem() and remove_tree() are a cross-version
 * contract: their signature lines are pinned as exact strings in
 * bin/uninstall-battery.php (U16), and a change to any of them is a NEW name
 * with the old method kept (wp-includes/compat.php's rule, #829). A body-only
 * change under the same signature runs the lender's body for the caller; keep
 * bodies backward-compatible.
 *
 * The one break of that rule, named (2026-10-05): the method that retired
 * the legacy Custom Scripts file left this class for a Pro-only unit
 * (service:bake-leftovers), because this file ships in the wordpress.org build
 * and that method moved files in the must-use plugins directory. A Pro build
 * from before that day, deleted in one request AFTER a newer build lent it
 * this class, would fatal at its own Clean Uninstall call of the old name.
 * Only Garrett's dev sites ever ran such a build (Pro has never run on a live
 * site), so the method was not kept.
 *
 * Self-contained: it is hand-required from uninstall.php and from the
 * bake-leftovers fragment, where no autoloader exists, so it may reference
 * only core, WP_Filesystem_Base and its own arguments — no BLOCKLANE_PRO_*
 * constants, no other first-party class.
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
	 * Write one file inside the uploads directory, through WordPress's direct
	 * transport (see uploads_direct() for why that transport).
	 *
	 * @param string $path     Absolute path; its directory must exist inside
	 *                         wp_upload_dir()'s basedir.
	 * @param string $contents The bytes.
	 * @return true|\WP_Error True when written; a typed refusal for a path
	 *                        outside uploads or a failed write.
	 */
	public static function uploads_put( string $path, string $contents ): bool|\WP_Error {
		$outside = self::uploads_refusal( $path );
		if ( null !== $outside ) {
			return $outside;
		}
		$fs = self::uploads_direct(); // Defines FS_CHMOD_FILE when nothing has.
		if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_write', 'Could not write ' . $path . '.' );
		}
		return true;
	}

	/**
	 * A folder inside uploads the plugin owns, one level under a folder that
	 * is already there: the path when it is a real directory resolving
	 * inside wp_upload_dir()'s basedir, created through the direct transport
	 * when absent. A link at the path, dangling or not, is refused, and so
	 * is anything there that is not a directory: the folder the plugin's
	 * files go into must be the folder it names, not one a link points at.
	 *
	 * The uploads basedir itself is made first when it does not exist yet
	 * (#2091: core creates it only on demand, so a fresh install or a new
	 * multisite subsite has none, and every folder below it was refused).
	 * It is core's own path, made the way core's wp_upload_dir() makes it,
	 * with wp_mkdir_p(); it is the containment root, so the levels above it
	 * are outside what this door judges, and a basedir that is itself a link
	 * stays acceptable, as it always was. The one level this door then makes
	 * under it keeps every check above.
	 *
	 * @param string $dir Absolute path; its parent must be the uploads basedir or exist inside it.
	 * @return true|\WP_Error True when the folder is there; a typed refusal otherwise.
	 */
	public static function uploads_dir( string $dir ): bool|\WP_Error {
		$dir   = untrailingslashit( $dir );
		$ready = self::uploads_base();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$outside = self::uploads_refusal( $dir );
		if ( null !== $outside ) {
			return $outside;
		}
		if ( file_exists( $dir ) ) {
			return is_dir( $dir ) ? true : new \WP_Error( 'blocklane_pro_uploads_not_dir', 'Refusing a path that is not a folder: ' . $dir . '.' );
		}
		$fs = self::uploads_direct(); // Defines FS_CHMOD_DIR when nothing has.
		if ( ! $fs->mkdir( $dir, FS_CHMOD_DIR ) || ! is_dir( $dir ) || is_link( $dir ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_mkdir', 'Could not create ' . $dir . '.' );
		}
		return true;
	}

	/**
	 * Make a file inside uploads hold exactly $contents: written when absent,
	 * rewritten when it holds other bytes (it is the plugin's file, and the
	 * bytes are the guarantee), left alone when it already holds them. The
	 * uploads door's refusals apply first (a path outside uploads, a link at
	 * the name); anything there that is not a regular file, a directory
	 * say, is refused rather than replaced.
 *
 * THE DOOR DECIDES PRESENCE TOO (#1860). A caller that asks file_exists()
 * before it writes has put the decision outside the door: a link at the
 * name to any existing file, a directory, or a file with other bytes all
 * answer "present", and the door's refusal of a link is never consulted.
 * So a file the plugin needs to hold given bytes is asked for here, and a
 * folder it needs through uploads_dir(); each judges what is already there.
	 *
	 * @param string $path     Absolute path; its directory must exist inside uploads.
	 * @param string $contents The bytes the file must hold.
	 * @return true|\WP_Error True when the file holds them; a typed refusal otherwise.
	 */
	public static function uploads_ensure( string $path, string $contents ): bool|\WP_Error {
		$outside = self::uploads_refusal( $path );
		if ( null !== $outside ) {
			return $outside;
		}
		if ( file_exists( $path ) && ! is_file( $path ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_not_file', 'Refusing a path that is not a regular file: ' . $path . '.' );
		}
		$fs = self::uploads_direct();
		if ( is_file( $path ) && $fs->get_contents( $path ) === $contents ) {
			return true;
		}
		if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_write', 'Could not write ' . $path . '.' );
		}
		return true;
	}

	/**
	 * Put a new file in place of an existing one inside uploads, keeping the
	 * original until the new file is in place (media replace: same name, same
	 * URL). Three moves: the original steps aside, the new file takes its
	 * name, the original goes. WP_Filesystem's own overwrite deletes the
	 * destination BEFORE it moves, which is the one order this must not use.
	 *
	 * Every step is checked, and a failure leaves the original at $target:
	 *
	 *   1 the step-aside move fails  — the target was never touched; a partial
	 *     copy the transport's copy fallback may have left at the aside name is
	 *     removed.
	 *   2 the new file's move fails  — whatever the copy fallback left at the
	 *     target is replaced by the original (overwrite, because a partial file
	 *     may sit there). If even that fails, the error names where the
	 *     original is kept ('blocklane_pro_swap_rollback', data `aside`).
	 *   3 the original's delete fails — the swap DID happen; the error
	 *     'blocklane_pro_swap_aside_left' (data `aside`) says the superseded
	 *     bytes are still on disk under that name, for the caller to report.
	 *
	 * @param string                    $source Absolute path of the new file.
	 * @param string                    $target Absolute path it replaces.
	 * @param \WP_Filesystem_Base|null $fs     The transport; null for the
	 *                                           direct one. A battery passes one
	 *                                           that fails a chosen step.
	 * @return true|\WP_Error
	 */
	public static function uploads_swap( string $source, string $target, ?\WP_Filesystem_Base $fs = null ): bool|\WP_Error {
		foreach ( array( $source, $target ) as $path ) {
			$outside = self::uploads_refusal( $path );
			if ( null !== $outside ) {
				return $outside;
			}
		}
		$fs ??= self::uploads_direct();
		if ( ! $fs->exists( $source ) ) {
			return new \WP_Error( 'blocklane_pro_swap_no_source', 'The replacement file ' . $source . ' does not exist.' );
		}

		$aside = $fs->exists( $target ) ? $target . '.blocklane-replaced-' . wp_generate_password( 8, false, false ) : '';
		if ( '' !== $aside && ! $fs->move( $target, $aside ) ) {
			if ( $fs->exists( $aside ) ) {
				$fs->delete( $aside );
			}
			return new \WP_Error( 'blocklane_pro_swap_aside', 'Could not move ' . $target . ' aside; it is untouched.' );
		}

		if ( ! $fs->move( $source, $target ) ) {
			if ( '' === $aside ) {
				// No original: whatever the copy fallback left at the target is
				// partial; a delete of nothing is a harmless false.
				$fs->delete( $target );
			} elseif ( ! $fs->move( $aside, $target, true ) ) {
				return new \WP_Error( 'blocklane_pro_swap_rollback', 'Could not move the replacement in, nor the original back; the original is kept at ' . $aside . '.', array( 'aside' => $aside ) );
			}
			return new \WP_Error( 'blocklane_pro_swap_move', 'Could not move the replacement onto ' . $target . '; the original is back in place.' );
		}

		if ( '' !== $aside && ! $fs->delete( $aside ) ) {
			return new \WP_Error( 'blocklane_pro_swap_aside_left', 'The file was replaced, but the previous copy could not be removed: ' . $aside . '.', array( 'aside' => $aside ) );
		}
		return true;
	}

	/**
	 * The uploads basedir, made when it does not exist yet (see
	 * uploads_dir()): true when it is a folder now, or why it is not.
	 *
	 * @return true|\WP_Error
	 */
	private static function uploads_base(): bool|\WP_Error {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_unavailable', 'The uploads folder is not available: ' . (string) $uploads['error'] );
		}
		$basedir = (string) $uploads['basedir'];
		if ( '' === $basedir || ( ! is_dir( $basedir ) && ! wp_mkdir_p( $basedir ) ) || ! is_dir( $basedir ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_unavailable', 'The uploads folder ' . $basedir . ' does not exist and could not be created.' );
		}
		return true;
	}

	/**
	 * Why a path may not be written through the uploads methods, or null
	 * when it may. Judged on the directory AND on the final path (#1846):
	 *
	 *   - the directory must exist and resolve inside wp_upload_dir()'s
	 *     basedir (realpath collapses `..` and symlinks, so a traversal or a
	 *     linked directory loses the prefix);
	 *   - the path itself must not be a symlink, dangling or not: a write
	 *     follows a link planted at the file name, wherever it points;
	 *   - a path that exists must itself resolve inside the basedir (its
	 *     own `..` or `.` name included).
	 *
	 * @param string $path Absolute path.
	 * @return \WP_Error|null
	 */
	private static function uploads_refusal( string $path ): ?\WP_Error {
		$uploads = wp_upload_dir( null, false );
		$base    = empty( $uploads['error'] ) ? realpath( (string) $uploads['basedir'] ) : false;
		if ( false === $base ) {
			// Nothing can be judged inside a folder that is not there; say so
			// instead of calling the path "outside" (#2091).
			return new \WP_Error( 'blocklane_pro_uploads_unavailable', 'The uploads folder is not available, so nothing can be written in it: ' . ( empty( $uploads['error'] ) ? (string) $uploads['basedir'] . ' does not exist.' : (string) $uploads['error'] ) );
		}
		$inside = static fn( string|false $real ): bool => false !== $real && str_starts_with( $real . '/', rtrim( $base, '/' ) . '/' );
		if ( ! $inside( realpath( dirname( $path ) ) ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_outside', 'Refusing a path outside the uploads directory: ' . $path . '.' );
		}
		if ( is_link( $path ) || ( file_exists( $path ) && ! $inside( realpath( $path ) ) ) ) {
			return new \WP_Error( 'blocklane_pro_uploads_outside', 'Refusing a path that is a link, or resolves outside the uploads directory: ' . $path . '.' );
		}
		return null;
	}

	/**
	 * WordPress's direct transport, for the uploads methods above only.
	 *
	 * Core writes media into uploads with PHP's own file functions on every
	 * host, whatever get_filesystem_method() says (wp_handle_upload(),
	 * wp_unique_filename(), the image editors), because the web server is the
	 * uploads directory's writer. So a write that belongs there goes through
	 * WP_Filesystem_Direct: the WordPress API, with exactly the reach the PHP
	 * primitive had, instead of a connection an FTP-method host could not open
	 * on a front-end request. Private, so the only way to it is through a
	 * method that has refused every path outside uploads; filesystem() above
	 * is the door for anything else.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private static function uploads_direct(): \WP_Filesystem_Direct {
		// Core loads these two only inside WP_Filesystem(), which this door
		// never calls.
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( null );
		// put_contents() and mkdir() chmod with these; WP_Filesystem() defines
		// them, which this door never calls. The same expressions core uses.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- core's own constant, defined exactly as WP_Filesystem() defines it.
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- core's own constant, defined exactly as WP_Filesystem() defines it.
		}
		return $fs;
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
