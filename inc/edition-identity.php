<?php
/**
 * Edition identity — the one reader of a rendered inc/edition.php, and the one
 * predicate every door runs before treating another directory as a build of
 * this tree.
 *
 * WHY THIS FILE EXISTS. Every door that asks "where is the other edition, and
 * is it really ours" — the coexistence guard on both sides, the hold, the
 * installer, uninstall — used to answer from a NAME: the rendered manifest
 * basename ("blocklane/blocklane.php"), or the directory an operator happened
 * to unpack a zip into. A name is what someone chose; it is not evidence. A
 * build of this tree is the only thing that carries a rendered inc/edition.php,
 * and that file names its edition, its main file, its text domain and its
 * rank. So identity is resolved by READING THAT FILE AS BYTES, and every door
 * asks its own question of the same answer (#854 #874 #828).
 *
 * LOADABLE FROM ANYWHERE. This file declares nothing at its top level (every
 * function is wrapped in function_exists), reads no BLOCKLANE_PRO_* constant,
 * includes nothing from wp-admin and registers no hook — so the main file can
 * require it before its first define(), uninstall.php can require it in a
 * bulk delete where the SECOND edition runs the FIRST edition's copy, and a
 * front-end request pays no admin include for it. WP_Error is the only
 * WordPress symbol it uses, and WordPress declares that before any plugin
 * loads.
 *
 * THE LENT-DECLARATION CONTRACT applies (inc/uninstall-fragments.php): in a
 * bulk delete of both editions the first edition's copy of every function
 * below serves the second. A changed signature is a NEW name (…_2) with the
 * old declaration kept, each in its own function_exists wrapper —
 * wp-includes/compat.php's rule. bin/uninstall-battery.php pins the signature
 * lines (U16), so a change here fails a battery before it fails a customer.
 *
 * COST, stated: one stat per candidate (is_file on inc/edition.php) and one
 * 8 KB read per build found. The free guard's candidates are the ACTIVE lists,
 * on which core already performs one file_exists per entry per request
 * (wp-includes/load.php); the installer and uninstall add one scandir of the
 * plugins directory and run at most daily / once.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Loadable from anywhere WordPress is loaded — never as a bare file.
}

if ( ! function_exists( 'blocklane_pro_edition_head' ) ) {

	/**
	 * Read the scalar head of a rendered inc/edition.php — as BYTES, never
	 * required.
	 *
	 * Parses every single-tab scalar line of the generator's grammar
	 * (bin/generate-edition.php, edition_render()):
	 *
	 *     \t'key' => 'string' | true | false | int ,
	 *
	 * into key => string|bool|int. Strings are unquoted and the two escapes
	 * var_export emits (\' and \\) are undone. The nested blocks (units,
	 * absent, toggles, uninstall_fragments) are two-tab lines and fall outside
	 * the anchor. GENERIC on purpose: a key the generator renders tomorrow is
	 * read by an older lender's copy of this function without a change.
	 *
	 * Requiring the file instead would be the download executing itself: a
	 * foreign or torn file must be able to fail here without running.
	 *
	 * @param string $dir Absolute directory of a plugin (the one holding its main file).
	 * @return array<string, string|bool|int>|\WP_Error
	 *   blocklane_pro_edition_no_file    — no inc/edition.php there: not a build of this tree.
	 *   blocklane_pro_edition_unreadable — the file is there but a required key
	 *                                      (edition, main, text_domain, rank) is missing:
	 *                                      an older build, a foreign file, or a torn one.
	 */
	function blocklane_pro_edition_head( string $dir ): array|\WP_Error {
		$dir  = rtrim( $dir, '/' );
		$file = $dir . '/inc/edition.php';
		if ( ! is_file( $file ) ) {
			return new \WP_Error( 'blocklane_pro_edition_no_file', 'No inc/edition.php under ' . basename( $dir ) . '/: not a build of this tree.' );
		}
		$bytes = file_get_contents( $file, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file read as data; requiring it is exactly what must not happen.
		if ( false === $bytes ) {
			return new \WP_Error( 'blocklane_pro_edition_unreadable', basename( $dir ) . '/inc/edition.php cannot be read.' );
		}
		$head = array();
		if ( preg_match_all( '/^\t\'([a-z_]+)\'\s*=>\s*(\'(?:[^\'\\\\]|\\\\.)*\'|true|false|-?[0-9]+),$/m', $bytes, $lines, PREG_SET_ORDER ) ) {
			foreach ( $lines as $line ) {
				$raw = $line[2];
				if ( 'true' === $raw ) {
					$head[ $line[1] ] = true;
				} elseif ( 'false' === $raw ) {
					$head[ $line[1] ] = false;
				} elseif ( "'" === $raw[0] ) {
					$head[ $line[1] ] = (string) preg_replace( '/\\\\(.)/s', '$1', substr( $raw, 1, -1 ) );
				} else {
					$head[ $line[1] ] = (int) $raw;
				}
			}
		}
		foreach ( array( 'edition', 'main', 'text_domain', 'rank' ) as $key ) {
			if ( ! array_key_exists( $key, $head ) ) {
				return new \WP_Error( 'blocklane_pro_edition_unreadable', basename( $dir ) . "/inc/edition.php has no '{$key}' line: an older build, a foreign file, or a torn one." );
			}
		}
		return $head;
	}
}

if ( ! function_exists( 'blocklane_pro_edition_marks' ) ) {

	/**
	 * Is what sits in this directory a build of THIS tree? The one predicate,
	 * by CONTENT.
	 *
	 * The head must read (blocklane_pro_edition_head), its text domain must be
	 * ours, and the main file it names must exist beside it. The directory's
	 * NAME appears in the RESULT — 'basename' and 'dir' — and never in a test:
	 * a build under any folder name passes, which is what lets the hold, the
	 * installer and uninstall find a free copy an operator unpacked somewhere
	 * else, or a wordpress.org slug that is not the one the manifest guessed
	 * (#874).
	 *
	 * A SANITY CHECK, NOT AUTHORIZATION. Every mark here is copied out of our
	 * own published plugin in a minute. These catch OUR mistakes and folder
	 * collisions — a foreign plugin unpacked into a sibling's directory name, a
	 * directory with no main file, an older build without a rank line.
	 * Authorization for anything the installer downloads is the directory's
	 * own author_profile in the API response (inc/edition-sibling.php), and
	 * nothing in a zip can supply that.
	 *
	 * @param string $dir         Absolute directory of the candidate plugin.
	 * @param string $text_domain The text domain both editions carry.
	 * @return array<string, string|bool|int>|\WP_Error The head plus
	 *   'basename' ("<dir>/<main>") and 'dir' (the directory's own name); or
	 *   one of the head's errors, blocklane_pro_sibling_text_domain, or
	 *   blocklane_pro_sibling_no_main.
	 */
	function blocklane_pro_edition_marks( string $dir, string $text_domain ): array|\WP_Error {
		$dir  = rtrim( $dir, '/' );
		$head = blocklane_pro_edition_head( $dir );
		if ( is_wp_error( $head ) ) {
			return $head;
		}
		if ( $text_domain !== $head['text_domain'] ) {
			return new \WP_Error( 'blocklane_pro_sibling_text_domain', basename( $dir ) . ' declares text domain "' . $head['text_domain'] . '", not "' . $text_domain . '".' );
		}
		$main = (string) $head['main'];
		if ( '' === $main || str_contains( $main, '/' ) || ! is_file( $dir . '/' . $main ) ) {
			return new \WP_Error( 'blocklane_pro_sibling_no_main', basename( $dir ) . ' has no ' . $main . ' beside its inc/edition.php.' );
		}
		$head['basename'] = basename( $dir ) . '/' . $main;
		$head['dir']      = basename( $dir );
		return $head;
	}
}

if ( ! function_exists( 'blocklane_pro_edition_candidate_dirs' ) ) {

	/**
	 * The directories worth asking, from the lists a door is deciding about.
	 *
	 * An entry with a slash is a plugin basename ("dir/main.php" — the active
	 * lists) and contributes its directory; an entry without one is a
	 * directory name itself (a scandir of the plugins directory). ".", "..",
	 * a single-file plugin ("hello.php" has no directory to be a build of) and
	 * the caller's own directory are dropped, and the result is deduplicated
	 * in first-seen order. Names only, never paths: the caller says which
	 * plugins directory they live under.
	 *
	 * @param list<string> $exclude_dirs     Directory names to leave out (the caller's own).
	 * @param list<string> ...$basename_lists Any number of lists to draw from.
	 * @return list<string>
	 */
	function blocklane_pro_edition_candidate_dirs( array $exclude_dirs, array ...$basename_lists ): array {
		$out = array();
		foreach ( $basename_lists as $list ) {
			foreach ( $list as $entry ) {
				$entry = (string) $entry;
				if ( str_contains( $entry, '/' ) ) {
					$dir = dirname( $entry );
				} elseif ( str_ends_with( $entry, '.php' ) ) {
					continue; // a single-file plugin: no directory, so nothing to read.
				} else {
					$dir = $entry;
				}
				if ( '' === $dir || '.' === $dir || '..' === $dir || in_array( $dir, $exclude_dirs, true ) || in_array( $dir, $out, true ) ) {
					continue;
				}
				$out[] = $dir;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'blocklane_pro_edition_builds' ) ) {

	/**
	 * Every build of this tree among the candidate directories: basename => head.
	 *
	 * A candidate that fails the marks is simply not a build — a foreign
	 * plugin, an empty directory, a stale row whose directory is gone. That is
	 * an answer, not an error, so nothing is returned for it. A caller that
	 * must tell "not ours" from "ours but torn" (uninstall's plan keeps a torn
	 * sibling's rows) asks blocklane_pro_edition_head() of that candidate
	 * itself.
	 *
	 * @param string       $text_domain The text domain both editions carry.
	 * @param string       $plugins_dir Absolute plugins directory the names live under.
	 * @param list<string> $dirs        Directory names (blocklane_pro_edition_candidate_dirs).
	 * @return array<string, array<string, string|bool|int>> basename => head.
	 */
	function blocklane_pro_edition_builds( string $text_domain, string $plugins_dir, array $dirs ): array {
		$builds = array();
		foreach ( $dirs as $dir ) {
			$path = rtrim( $plugins_dir, '/' ) . '/' . $dir;
			// A stray FILE in plugins/ (.DS_Store, a leftover zip) is a
			// candidate name but not a plugin directory: probing under it is an
			// open_basedir warning on hosts that set one, and that warning
			// broke wp-admin's headers (#1014). One check at the one resolver
			// every door (hold, installer, uninstall plan) reads through.
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$head = blocklane_pro_edition_marks( $path, $text_domain );
			if ( ! is_wp_error( $head ) ) {
				$builds[ (string) $head['basename'] ] = $head;
			}
		}
		return $builds;
	}
}

if ( ! function_exists( 'blocklane_pro_edition_outranking' ) ) {

	/**
	 * The basenames of every build among the candidates ranked above $rank.
	 *
	 * The coexistence guard's question. Because its candidates are the ACTIVE
	 * lists, "found" means active in this scope AND on disk AND readable AND
	 * ours, in one predicate: a removed higher edition is not found (this copy
	 * runs — #826), a renamed one is (this copy yields — #852). An empty list
	 * is an answer, never a failure.
	 *
	 * @param int          $rank        This copy's own rank (inc/edition.php 'rank').
	 * @param string       $text_domain The text domain both editions carry.
	 * @param string       $plugins_dir Absolute plugins directory.
	 * @param list<string> $dirs        Candidate directory names.
	 * @return list<string> Basenames, in candidate order.
	 */
	function blocklane_pro_edition_outranking( int $rank, string $text_domain, string $plugins_dir, array $dirs ): array {
		$above = array();
		foreach ( blocklane_pro_edition_builds( $text_domain, $plugins_dir, $dirs ) as $basename => $head ) {
			if ( (int) $head['rank'] > $rank ) {
				$above[] = (string) $basename;
			}
		}
		return $above;
	}
}
