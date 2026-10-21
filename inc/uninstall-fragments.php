<?php
/**
 * Run each unit's uninstall fragment, for the units THIS edition carries.
 *
 * A unit owns its own teardown for the same reason it owns its own lifecycle:
 * an aggregator that reaches into a unit an edition does not carry is either a
 * fatal or, worse, a string that had to ship so the aggregator could name it.
 * The licence seat release is exactly that case — it names the gateway host,
 * and the directory build must carry no route to our servers.
 *
 * Fragments are enumerated from the edition data, never from the filesystem:
 * the same rule uninstall.php follows for everything it deletes.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_uninstall_fragments' ) ) {

	/**
	 * Run the CALLING edition's uninstall fragments, from the calling edition's
	 * own directory, before the sibling guard.
	 *
	 * THE LIST COMES FROM THE CALLER'S DATA, NEVER FROM THIS FILE. Deleting both
	 * editions at once runs both uninstall.php files in ONE request, and this
	 * function is declared by whichever plugin loaded first — which is being
	 * deleted too. An earlier version kept the unit => fragment table as a
	 * local inside this declaration, so the LENDER's table decided what the
	 * CALLER tore down; the editions are allowed to be at different versions,
	 * and a fragment the lender's build did not know about would have been
	 * skipped silently (#821). Now the caller passes its own inc/edition.php
	 * data, whose `uninstall_fragments` the generator rendered from the
	 * manifest — units present in THAT edition with the uninstall role — and
	 * its own plugin root, so a borrowed declaration still runs the caller's
	 * fragments from the caller's files.
	 *
	 * CROSS-VERSION CONTRACT, pinned: the function name, its two parameters
	 * (edition data, plugin root) and the data key `uninstall_fragments`. A
	 * change to any of them is a NEW function name (…_2) with this declaration
	 * kept, so two builds' declarations coexist instead of one shadowing the
	 * other — wp-includes/compat.php's rule. The signature line is pinned as
	 * an exact string in bin/uninstall-battery.php (U16), with every other
	 * lent declaration: blocklane_pro_uninstall_plan(), the five functions of
	 * inc/edition-identity.php and File_Ops, which uninstall.php reaches
	 * through the same function_exists/class_exists shape (and Pro's two
	 * bake-leftovers classes, which that unit's fragment reaches the same way). What the
	 * pin does not cover: a body-only change under the same signature runs
	 * the LENDER's body for the caller at mismatched versions (#829). And the
	 * caller's side of the contract: the function_exists probe in front of a
	 * lent file's require names that file's NEWEST function (its last
	 * declaration), because an older lender declares the older names and
	 * probing one of those would skip the require and leave the new function
	 * undefined — U19 in the same battery holds uninstall.php to it.
	 *
	 * THE MIRROR CONTRACT, honestly: the `uninstall` role means one thing — the
	 * unit owns the fragment named by its uninstall_fragment, which
	 * bin/generate-edition.php proves exists, lies under the unit's own paths
	 * and declares nothing at top level. A fragment is the home for state that
	 * exists only in editions carrying the unit; state a sibling edition can
	 * read is swept by uninstall.php below the guard and must never be here.
	 *
	 * @param array<string, mixed> $edition    The calling edition's inc/edition.php data.
	 * @param string               $plugin_dir The calling plugin's ROOT directory.
	 * @return void
	 */
	function blocklane_pro_uninstall_fragments( array $edition, string $plugin_dir ): void {
		$fragments = isset( $edition['uninstall_fragments'] ) && is_array( $edition['uninstall_fragments'] )
			? $edition['uninstall_fragments']
			: array();
		foreach ( $fragments as $unit => $rel ) {
			$path = rtrim( $plugin_dir, '/' ) . '/' . (string) $rel;
			if ( ! is_file( $path ) ) {
				// A torn build; uninstall must still finish, and this line is the
				// only trace that a unit's teardown never ran.
				blocklane_pro_log_failure( 'Blocklane: uninstall fragment ' . (string) $rel . ' (unit ' . (string) $unit . ') is missing; its teardown was skipped.' );
				continue;
			}
			require_once $path;
		}
	}
}

if ( ! function_exists( 'blocklane_pro_uninstall_plan' ) ) {

	/**
	 * What THIS uninstall may sweep, decided from what is ON DISK.
	 *
	 * OWNERSHIP, NOT ORDER OR LAYOUT. A row is swept when no build of this
	 * tree remains to read it. "Which folder", "which order the two were
	 * deleted in" and "which edition ran its uninstall" are not the question
	 * (#825 #828 #855); "does another build of this tree remain under the
	 * plugins directory, and does it carry this unit" is. Every other
	 * directory is read through inc/edition-identity.php — its own rendered
	 * inc/edition.php as bytes — so a build at ANY folder name counts (#874)
	 * and a foreign plugin at the canonical folder does not (#828).
	 *
	 * FAILS CLOSED. No own edition data (the file missing, or read without a
	 * rank or text domain) → nothing is swept, because nothing can be told
	 * apart (#827). A candidate that carries an inc/edition.php of THIS tree
	 * (its bytes name our text domain in the generator's grammar) but cannot
	 * be read as a build — an older build without a rank line, a torn one, a
	 * directory whose main file is gone — is "undecidable": it may still read
	 * our rows, so the shared sweep is refused and the directory is named. A
	 * third-party plugin that happens to ship some inc/edition.php of its
	 * own is neither a build nor undecidable; it is ignored.
	 *
	 *   fragments — run this edition's own unit fragments (the seat release,
	 *               the updater's rows): yes unless a TWIN — another build of
	 *               the SAME rank, which carries the same units and still
	 *               reads them — remains.
	 *   shared    — run the complete sweep below the guard: yes only when no
	 *               other build and nothing undecidable remains.
	 *
	 * Under the lent-declaration contract like everything in this file: in a
	 * bulk delete the second edition runs the first edition's copy, and the
	 * identity functions it needs are required here if the caller has not
	 * already lent them. This signature line is pinned by
	 * bin/uninstall-battery.php U16 — a change is a new name.
	 *
	 * @param array<string, mixed>|null $edition     The calling edition's inc/edition.php data, or null when the file is missing.
	 * @param string                    $plugin_dir  The calling plugin's ROOT directory (never a candidate).
	 * @param string                    $plugins_dir WP_PLUGIN_DIR, or a fixture root.
	 * @return array{fragments: bool, shared: bool, reason: string, others: list<string>}
	 *   `others` names every other build (by basename) and every undecidable
	 *   directory (by name, with the reader's error code) — what the log
	 *   prints, so a refusal always says what it FOUND.
	 */
	function blocklane_pro_uninstall_plan( ?array $edition, string $plugin_dir, string $plugins_dir ): array {
		if ( ! function_exists( 'blocklane_pro_edition_builds' ) ) {
			require_once __DIR__ . '/edition-identity.php';
		}
		if ( null === $edition || ! isset( $edition['rank'], $edition['text_domain'] ) || ! is_string( $edition['text_domain'] ) ) {
			return array(
				'fragments' => false,
				'shared'    => false,
				'reason'    => 'this plugin\'s own inc/edition.php is missing or unreadable, so nothing can be told apart and nothing is swept',
				'others'    => array(),
			);
		}
		$plugins_dir = rtrim( $plugins_dir, '/' );
		$text_domain = (string) $edition['text_domain'];
		$entries     = scandir( $plugins_dir );
		$dirs        = blocklane_pro_edition_candidate_dirs( array( basename( rtrim( $plugin_dir, '/' ) ) ), false === $entries ? array() : $entries );
		$builds      = blocklane_pro_edition_builds( $text_domain, $plugins_dir, $dirs );
		$known       = array();
		foreach ( $builds as $head ) {
			$known[ (string) $head['dir'] ] = true;
		}
		$undecidable = array();
		// The same line grammar the reader parses (blocklane_pro_edition_head:
		// a tab, the quoted key, any spacing around =>, the var_export'ed
		// value, a comma) — never a hand-typed byte string, which a renderer
		// realignment would silently stop matching, turning a torn build of
		// ours into "some other product" and the sweep fail-OPEN beside it.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export() builds the PHP string literal the renderer writes, so the grammar matches it exactly; nothing is logged.
		$domain_line = '/^\t\'text_domain\'\s*=>\s*' . preg_quote( var_export( $text_domain, true ), '/' ) . ',$/m';
		foreach ( $dirs as $dir ) {
			if ( isset( $known[ $dir ] ) || ! is_file( $plugins_dir . '/' . $dir . '/inc/edition.php' ) ) {
				continue;
			}
			$bytes = (string) file_get_contents( $plugins_dir . '/' . $dir . '/inc/edition.php', false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file read as data, never required.
			if ( ! preg_match( $domain_line, $bytes ) ) {
				continue; // some other product's file: not a build, not undecidable.
			}
			$head          = blocklane_pro_edition_marks( $plugins_dir . '/' . $dir, $text_domain );
			$undecidable[] = $dir . '/ (' . ( is_wp_error( $head ) ? $head->get_error_code() : 'unreadable' ) . ')';
		}
		$twin = false;
		foreach ( $builds as $head ) {
			if ( (int) $head['rank'] === (int) $edition['rank'] ) {
				$twin = true;
				break;
			}
		}
		$others = array_merge( array_keys( $builds ), $undecidable );
		if ( array() === $others ) {
			$reason = 'no other build of this tree is on disk';
		} elseif ( array() !== $undecidable ) {
			$reason = 'a directory carries an inc/edition.php of this tree that could not be read as a build; it may still read these rows';
		} else {
			$reason = 'another build of this tree is still installed and reads the same rows';
		}
		return array(
			'fragments' => ! $twin,
			'shared'    => array() === $others,
			'reason'    => $reason,
			'others'    => $others,
		);
	}
}

