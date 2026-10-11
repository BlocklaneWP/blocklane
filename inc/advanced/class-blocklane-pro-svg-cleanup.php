<?php
/**
 * Svg_Cleanup: the consented check of the SVG files a pre-1.0.2 upload hole
 * could have stored (hotfix S1, cleanup-pass redesign). The class docblock
 * says who is in scope, when it arms, what consent changes, and what is
 * outside it.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Before 1.0.2, with Safe SVG Uploads on, an SVG sent through
 * wp_upload_bits() (XML-RPC, the importer) or a plugin's own upload action
 * was stored without being cleaned. The doors are closed now; a file the
 * hole stored is still served. This class finds the files the hole COULD
 * have stored and changes them only when an administrator asks.
 *
 * SCOPE (facts an uploader cannot rewrite afterwards). An attachment is in
 * scope when its stored file name is an SVG (Advanced::is_svg_name(), over a
 * LIKE superset in SQL), and its ID is at or below the ceiling: the highest
 * attachment ID when this build first armed, so everything uploaded through
 * the fixed doors afterwards is out. Within scope, trusted() — whether the
 * attachment's author may publish scripts at all (unfiltered_html, checked
 * now) — splits the files. An untrusted author's file (an Author, a
 * Contributor, a site administrator on multisite, author 0) is what Clean
 * checks. "May publish scripts" is unfiltered_html as the site grants it
 * today: on a site that defines DISALLOW_UNFILTERED_HTML no user has it,
 * administrators included, so every SVG in scope is Clean's, an
 * administrator's logo too, and nothing is listed for review. A trusted author's file is listed for review and never read or
 * written (decision D9): a file credited to an administrator by
 * wp_delete_user()'s reassignment, or by a later promotion, lands there.
 * Every post status counts, trash included, because a trashed file is still
 * served. An attachment whose attached-file meta is gone is invisible here.
 *
 * WHEN. arm() is the blocklane_pro_version_changed listener and writes the
 * state row once: add_option() never replaces a row, so the row, in any
 * shape, is the marker, and an update or edition switch never re-arms it.
 * The witness is Advanced::configured(): a site whose Advanced row does not
 * exist never turned Safe SVG Uploads on (turning it on saves that row), so
 * it arms as "none" and never shows a notice — a fresh install says nothing
 * about files Blocklane never touched. The toggle's state today is policy for
 * new uploads, not a record of past exposure, so it gates nothing here.
 *
 * CONSENT. Nothing runs on its own: there is no cron event, no admin_init
 * driver, nothing on a visitor's request. A notice on the Dashboard, Plugins
 * and Media screens, to a user who can manage options, names the counts and
 * offers Clean and Dismiss, both POST forms through admin-post.php that
 * authorize() checks (POST, manage_options, a nonce). Clean runs one bounded
 * batch per click (run_batch()): at most BATCH attachments and BUDGET_SECONDS,
 * each untrusted file rewritten in place by Advanced::clean_svg_file() only
 * when its cleaned bytes differ — the bytes a new upload of it would get.
 * Clean writes wherever the attachment's own (protected) meta points,
 * including the editor's full-orig backup. Dismiss is per user (user meta),
 * so another administrator still sees the notice and the row stays owed; it
 * is the owner's choice and leaves every file as it is (decision D8, fold F4).
 *
 * WHAT IS NEVER DONE. Nothing is moved, renamed, quarantined or deleted: a
 * file the cleaner cannot read, or one past the markup bound, is left
 * unchanged and listed with links so the owner can delete or replace it
 * through core. A file not on disk (offload storage) is listed as missing.
 *
 * SAFE UNDER FAILURE. A batch holds the plugin's one option-row lock
 * (Version_Migration::claim_lock( LOCK, lock_timeout() )), so two clicks or
 * two administrators never judge the same file at once; lock_timeout() is
 * longer than a batch can run (its budget plus one bounded file), which is
 * the lock's whole guarantee. Before each attachment is judged its ID is
 * saved as `inflight`; a request that dies on a file leaves that mark, and
 * the next batch lists the file as left, moves past it and never judges it
 * again, so one heavy file cannot stall every click.
 *
 * OUTSIDE IT, by decision: files by trusted authors are listed, never
 * checked; a site with no Advanced row when this build first booted (an
 * uninstall then reinstall after a hole build included) is never checked; a
 * downgrade to a hole build after the check ran stores files nothing scans;
 * wp_upload_bits() writes that never became attachments; offloaded files;
 * and a site with no wp-admin visitor, which has no one to consent either.
 */
final class Svg_Cleanup {

	/** The state row; see state(). Not autoloaded: only the notice reads it, on three screens. */
	const OPTION = 'blocklane_pro_svg_check';

	/** The option row a batch holds as its lock; see Version_Migration::claim_lock(). */
	const LOCK = 'blocklane_pro_svg_check_lock';

	/** admin-post action (and nonce action) that runs one batch. */
	const CLEAN_ACTION = 'blocklane_pro_svg_check_clean';

	/** admin-post action (and nonce action) that dismisses the notice for the current user. */
	const DISMISS_ACTION = 'blocklane_pro_svg_check_dismiss';

	/** User meta: this user dismissed the notice. */
	const DISMISSED_META = 'blocklane_pro_svg_check_dismissed';

	/** Attachments judged per batch, at most. */
	const BATCH = 10;

	/** Seconds after which a batch stops at the next attachment. */
	const BUDGET_SECONDS = 5;

	/** Links printed per list in the notice; the rest are counted. */
	const LINKS = 20;

	/** The lists a finished check records, by outcome. */
	const LISTS = array( 'cleaned', 'left', 'missing', 'review' );

	/** Outcomes, worst last: an attachment is recorded under its worst file's. */
	const RANK = array( 'clean', 'cleaned', 'missing', 'left' );

	/**
	 * Seconds after which a held lock counts as abandoned: longer than a
	 * batch can run (BUDGET_SECONDS, then one file whose parse the markup
	 * bound keeps to about a second), and longer than PHP would let the
	 * request live, so a lock is only ever taken over from a request that is
	 * gone.
	 */
	public static function lock_timeout(): int {
		$limit = (int) ini_get( 'max_execution_time' );
		return $limit > 0 ? max( 120, $limit + 30 ) : 600;
	}

	/**
	 * Whether a user may publish scripts at all, checked now: the one trust
	 * predicate. Author 0 and a deleted user cannot be shown to, so they are
	 * not trusted.
	 *
	 * @param int $user_id The attachment's post_author.
	 */
	public static function trusted( int $user_id ): bool {
		return user_can( $user_id, 'unfiltered_html' );
	}

	/** The highest attachment ID: everything at or below it predates the fixed doors when arm() runs. */
	public static function ceiling(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read when the check arms; no API answers the highest attachment ID.
		return (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
	}

	/**
	 * The state a first arm() writes. Pure: owed only when the site is
	 * configured (the witness) and something is in scope.
	 *
	 * @param bool $configured Whether the Advanced row exists.
	 * @param int  $ceiling    The highest attachment ID now.
	 * @param int  $owed       How many in-scope SVG attachments there are.
	 * @return array{state: string, ceiling: int, cursor: int, inflight: int, cleaned: list<int>, left: list<int>, missing: list<int>, review: list<int>}
	 */
	public static function initial_state( bool $configured, int $ceiling, int $owed ): array {
		return array(
			'state'    => $configured && $owed > 0 ? 'owed' : 'none',
			'ceiling'  => $ceiling,
			'cursor'   => 0,
			'inflight' => 0,
			'cleaned'  => array(),
			'left'     => array(),
			'missing'  => array(),
			'review'   => array(),
		);
	}

	/**
	 * The `blocklane_pro_version_changed` listener: write the state row once.
	 * It runs serialized under the version pass's own lock. add_option()
	 * never replaces an existing row, so a row in any shape — none, owed,
	 * done, or not an array at all — is left exactly as it is.
	 */
	public static function arm(): void {
		$configured = Advanced::configured();
		$ceiling    = self::ceiling();
		$owed       = $configured ? array_sum( self::owed_counts( 0, $ceiling ) ) : 0;
		add_option( self::OPTION, self::initial_state( $configured, $ceiling, $owed ), '', false );
	}

	/**
	 * The state row, normalized, or null when there is none or it is not an
	 * array. An unknown state reads as none.
	 *
	 * @return array{state: string, ceiling: int, cursor: int, inflight: int, cleaned: list<int>, left: list<int>, missing: list<int>, review: list<int>}|null
	 */
	public static function state(): ?array {
		$raw = get_option( self::OPTION, null );
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$state = array(
			'state'    => in_array( $raw['state'] ?? '', array( 'owed', 'done' ), true ) ? (string) $raw['state'] : 'none',
			'ceiling'  => (int) ( $raw['ceiling'] ?? 0 ),
			'cursor'   => (int) ( $raw['cursor'] ?? 0 ),
			'inflight' => (int) ( $raw['inflight'] ?? 0 ),
			'cleaned'  => array(),
			'left'     => array(),
			'missing'  => array(),
			'review'   => array(),
		);
		foreach ( self::LISTS as $list ) {
			$state[ $list ] = array_values( array_unique( array_map( 'intval', is_array( $raw[ $list ] ?? null ) ? $raw[ $list ] : array() ) ) );
		}
		return $state;
	}

	/**
	 * Write the state row.
	 *
	 * @param array{state: string, ceiling: int, cursor: int, inflight: int, cleaned: list<int>, left: list<int>, missing: list<int>, review: list<int>} $state
	 */
	private static function save( array $state ): void {
		update_option( self::OPTION, $state, false );
	}

	/**
	 * The scope's one query: attachments with ID in ( $after, $ceiling ]
	 * whose attached file's name holds ".svg" in any case (a superset;
	 * classify() keeps the SVGs), ordered by ID.
	 *
	 * @param int $after   Exclusive lower ID bound (the cursor).
	 * @param int $ceiling Inclusive upper ID bound.
	 * @param int $limit   At most this many rows; 0 for all.
	 * @return list<array{id: int, author: int, file: string}>
	 */
	private static function rows( int $after, int $ceiling, int $limit ): array {
		global $wpdb;
		// One literal statement for both shapes: "all" is the largest LIMIT
		// PHP can name, so prepare() never takes a statement built in a
		// variable.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the scope's one query; no API filters attachments by their file's name.
		$found = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_author AS author, m.meta_value AS file FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file' WHERE p.post_type = %s AND p.ID > %d AND p.ID <= %d AND LOWER( m.meta_value ) LIKE %s ORDER BY p.ID ASC LIMIT %d",
				'attachment',
				$after,
				$ceiling,
				'%' . $wpdb->esc_like( '.svg' ) . '%',
				$limit > 0 ? $limit : PHP_INT_MAX
			),
			ARRAY_A
		);
		$rows  = array();
		foreach ( $found as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id > 0 && ( array() === $rows || $rows[ count( $rows ) - 1 ]['id'] !== $id ) ) {
				$rows[] = array(
					'id'     => $id,
					'author' => (int) ( $row['author'] ?? 0 ),
					'file'   => (string) ( $row['file'] ?? '' ),
				);
			}
		}
		return $rows;
	}

	/**
	 * One row, judged: whether its stored name is an SVG at all, and whether
	 * its author is trusted (asked once per author per call).
	 *
	 * @param array{id: int, author: int, file: string} $row
	 * @param array<int, bool>                         $trust Answers so far, by author.
	 * @return array{id: int, svg: bool, trusted: bool}
	 */
	private static function classify( array $row, array &$trust ): array {
		$trust[ $row['author'] ] ??= self::trusted( $row['author'] );
		return array(
			'id'      => $row['id'],
			'svg'     => Advanced::is_svg_name( wp_basename( $row['file'] ) ),
			'trusted' => $trust[ $row['author'] ],
		);
	}

	/**
	 * How many in-scope SVG attachments there are in ( $after, $ceiling ]:
	 * those Clean checks (untrusted authors) and those it lists for review
	 * (trusted authors). A name that only looks like an SVG ("x.svg.png") is
	 * not counted.
	 *
	 * @return array{check: int, review: int}
	 */
	public static function owed_counts( int $after, int $ceiling ): array {
		$counts = array(
			'check'  => 0,
			'review' => 0,
		);
		$trust  = array();
		foreach ( self::rows( $after, $ceiling, 0 ) as $row ) {
			$row = self::classify( $row, $trust );
			if ( ! $row['svg'] ) {
				continue;
			}
			if ( $row['trusted'] ) {
				++$counts['review'];
			} else {
				++$counts['check'];
			}
		}
		return $counts;
	}

	/**
	 * The next rows of the scope after the cursor, judged by classify(): a
	 * batch walks them in ID order, and passes a non-SVG row without
	 * judging it.
	 *
	 * @return list<array{id: int, svg: bool, trusted: bool}>
	 */
	public static function owed_page( int $after, int $ceiling, int $limit ): array {
		$trust = array();
		$page  = array();
		foreach ( self::rows( $after, $ceiling, max( 1, $limit ) ) as $row ) {
			$page[] = self::classify( $row, $trust );
		}
		return $page;
	}

	/**
	 * What one file's verdict means for the check. Pure, so every branch is
	 * a row of its own (WC13). An unknown failure is never "clean".
	 *
	 * @param bool|\WP_Error $verdict Advanced::clean_svg_file()'s answer.
	 * @param bool           $present Whether the file is on disk now.
	 * @return string clean, cleaned, abort, missing or left.
	 */
	public static function outcome( bool|\WP_Error $verdict, bool $present ): string {
		if ( false === $verdict ) {
			return 'clean';
		}
		if ( true === $verdict ) {
			return 'cleaned';
		}
		$code = $verdict->get_error_code();
		if ( 'blocklane_svg_sanitizer_missing' === $code ) {
			return 'abort';
		}
		if ( 'blocklane_svg_unreadable' === $code && ! $present ) {
			return 'missing';
		}
		return 'left';
	}

	/**
	 * One batch of the consented Clean, under the lock: at most $limit
	 * attachments of the scope after the cursor, stopping at the next
	 * attachment once $budget seconds have passed. Each untrusted file is
	 * rewritten only when its cleaned bytes differ; each trusted one is
	 * listed for review unread. The cursor and the lists are saved after
	 * every attachment, and `inflight` before each one is judged.
	 *
	 * @param int   $limit  Attachments, at most.
	 * @param float $budget Seconds.
	 * @return array{state: string, ceiling: int, cursor: int, inflight: int, cleaned: list<int>, left: list<int>, missing: list<int>, review: list<int>}|\WP_Error
	 *         The state after the batch, or why it did not run.
	 */
	public static function run_batch( int $limit = self::BATCH, float $budget = self::BUDGET_SECONDS ): array|\WP_Error {
		if ( ! Version_Migration::claim_lock( self::LOCK, self::lock_timeout() ) ) {
			return new \WP_Error( 'blocklane_svg_check_busy', __( 'Another check of the SVG files is running. Wait a moment, then try again.', 'blocklane' ) );
		}
		try {
			$state = self::state();
			if ( null === $state || 'owed' !== $state['state'] ) {
				return new \WP_Error( 'blocklane_svg_check_not_owed', __( 'There are no SVG files left to check.', 'blocklane' ) );
			}

			// A request that died judging a file left its mark: list the
			// file as left, move past it, and never judge it again.
			if ( $state['inflight'] > 0 ) {
				$state['left'][]   = $state['inflight'];
				$state['cursor']   = $state['inflight'];
				$state['inflight'] = 0;
				self::save( $state );
			}

			$start = microtime( true );
			$page  = self::owed_page( $state['cursor'], $state['ceiling'], max( 1, $limit ) + 1 );
			$slice = array_slice( $page, 0, max( 1, $limit ) );
			$done  = 0;
			foreach ( $slice as $row ) {
				if ( $row['svg'] && $row['trusted'] ) {
					$state['review'][] = $row['id'];
				} elseif ( $row['svg'] ) {
					$state['inflight'] = $row['id'];
					self::save( $state );
					$worst = 'clean';
					foreach ( self::files( $row['id'] ) as $path ) {
						$outcome = self::outcome( Advanced::clean_svg_file( $path ), is_file( $path ) );
						if ( 'abort' === $outcome ) {
							$state['inflight'] = 0;
							self::save( $state );
							return new \WP_Error( 'blocklane_svg_sanitizer_missing', __( 'The SVG sanitizer is missing from this copy of the plugin, so nothing was checked. Reinstall the plugin, then try again.', 'blocklane' ) );
						}
						if ( array_search( $outcome, self::RANK, true ) > array_search( $worst, self::RANK, true ) ) {
							$worst = $outcome;
						}
					}
					if ( 'clean' !== $worst ) {
						$state[ $worst ][] = $row['id'];
					}
				}
				$state['cursor']   = $row['id'];
				$state['inflight'] = 0;
				self::save( $state );
				++$done;
				if ( microtime( true ) - $start >= $budget ) {
					break;
				}
			}

			if ( count( $page ) <= max( 1, $limit ) && count( $slice ) === $done ) {
				$state['state'] = 'done';
				self::save( $state );
			}
			return $state;
		} finally {
			Version_Migration::release_lock( self::LOCK );
		}
	}

	/**
	 * Every SVG file an attachment keeps on disk: the attached file, its
	 * sub-sizes, the pre-scaling original and the edit backups (the editor's
	 * full-orig copy included) — the files wp_delete_attachment_files() would
	 * remove, read the same way. Each name is a basename in the attached
	 * file's folder.
	 *
	 * @param int $id Attachment ID.
	 * @return list<string>
	 */
	public static function files( int $id ): array {
		$file = get_attached_file( $id, true );
		if ( ! is_string( $file ) || '' === $file ) {
			return array();
		}
		$dir   = dirname( $file );
		$names = array( wp_basename( $file ) );
		$meta  = wp_get_attachment_metadata( $id, true );
		if ( is_array( $meta ) ) {
			// array_column() skips an entry with no file, as a damaged row may hold.
			$names   = array_merge( $names, array_column( (array) ( $meta['sizes'] ?? array() ), 'file' ) );
			$names[] = (string) ( $meta['original_image'] ?? '' );
		}
		$backups = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
		$names   = array_merge( $names, array_column( is_array( $backups ) ? $backups : array(), 'file' ) );

		$paths = array();
		foreach ( $names as $name ) {
			$name = wp_basename( (string) $name );
			if ( '' !== $name && Advanced::is_svg_name( $name ) ) {
				$paths[] = $dir . '/' . $name;
			}
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Whether the current request may run $action: a POST, from a user who
	 * can manage options, carrying a valid nonce for $action. Never dies, so
	 * the review battery can drive it; the handlers turn its error into the
	 * response.
	 *
	 * @param string $action CLEAN_ACTION or DISMISS_ACTION.
	 */
	public static function authorize( string $action ): bool|\WP_Error {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return new \WP_Error( 'blocklane_svg_check_method', __( 'This action needs the form on the notice.', 'blocklane' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'blocklane_svg_check_forbidden', __( 'Sorry, you are not allowed to do that.', 'blocklane' ) );
		}
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( false === wp_verify_nonce( $nonce, $action ) ) {
			return new \WP_Error( 'blocklane_svg_check_nonce', __( 'The link you followed has expired. Go back, reload the page and try again.', 'blocklane' ) );
		}
		return true;
	}

	/**
	 * The Clean form's handler: authorize, one batch, and back where the
	 * owner came from.
	 */
	public static function handle_clean(): void {
		$allowed = self::authorize( self::CLEAN_ACTION );
		if ( is_wp_error( $allowed ) ) {
			wp_die( esc_html( $allowed->get_error_message() ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		wp_raise_memory_limit( 'admin' );
		$run = self::run_batch();
		if ( is_wp_error( $run ) ) {
			// Busy and not owed (a double submit, a stale tab, a second
			// administrator after the check finished) are expected states:
			// 409. Only a missing sanitizer is a server failure: 500.
			wp_die( esc_html( $run->get_error_message() ), '', array( 'response' => in_array( $run->get_error_code(), array( 'blocklane_svg_check_busy', 'blocklane_svg_check_not_owed' ), true ) ? 409 : 500, 'back_link' => true ) );
		}
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * Hide the notice for one user. The state row is untouched, so the
	 * check stays owed and every other administrator still sees it.
	 *
	 * @param int $user_id The user who dismissed it.
	 */
	public static function dismiss( int $user_id ): void {
		update_user_meta( $user_id, self::DISMISSED_META, 1 );
	}

	/**
	 * The Dismiss form's handler: authorize, dismiss for this user, and back
	 * where they came from.
	 */
	public static function handle_dismiss(): void {
		$allowed = self::authorize( self::DISMISS_ACTION );
		if ( is_wp_error( $allowed ) ) {
			wp_die( esc_html( $allowed->get_error_message() ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		self::dismiss( get_current_user_id() );
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * The one notice, to a user who can manage options, on the Dashboard,
	 * Plugins and Media screens, until that user dismisses it: while the
	 * check is owed and something is left in scope, the counts and the two
	 * forms; once it is done, or owed with nothing left in scope, each
	 * non-empty list with links (nothing at all when every list is empty).
	 */
	public static function render_notice(): void {
		if ( ! blocklane_pro_notice_where_actionable( 'manage_options', array( 'dashboard', 'plugins', 'upload' ) ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}
		$state = self::state();
		if ( null === $state || 'none' === $state['state'] ) {
			return;
		}
		if ( 'done' === $state['state'] ) {
			self::render_done( $state );
			return;
		}

		$left = self::owed_counts( $state['cursor'], $state['ceiling'] );
		if ( 0 === $left['check'] + $left['review'] ) {
			// Nothing left to offer Clean for (files deleted, authors
			// promoted, or only non-SVG rows past the cursor), but a run may
			// already have recorded files: say what it found, as when done.
			self::render_done( $state );
			return;
		}
		echo '<div class="notice notice-warning" id="blocklane-pro-svg-check-notice"><p><strong>' . esc_html__( 'Safe SVG Uploads:', 'blocklane' ) . '</strong> '
			. esc_html__( 'before this update, an SVG file added through XML-RPC or another plugin\'s upload form by a user who cannot add scripts to your site (an Author, for example) could be stored without being cleaned.', 'blocklane' ) . '</p>';
		if ( $left['check'] > 0 ) {
			/* translators: %d: number of files. */
			echo '<p>' . esc_html( sprintf( _n( '%d SVG file in your Media Library was added that way, or could have been. Clean saves it the way a new upload is saved; nothing changes until you choose to clean.', '%d SVG files in your Media Library were added that way, or could have been. Clean saves each the way a new upload is saved; nothing changes until you choose to clean.', $left['check'], 'blocklane' ), $left['check'] ) ) . '</p>';
		}
		if ( $left['review'] > 0 ) {
			/* translators: %d: number of files. */
			echo '<p>' . esc_html( sprintf( _n( '%d other SVG file was added before this update by, or is now credited to, a user who can add scripts. Clean lists it for review and does not change it.', '%d other SVG files were added before this update by, or are now credited to, users who can add scripts. Clean lists them for review and does not change them.', $left['review'], 'blocklane' ), $left['review'] ) ) . '</p>';
		}
		self::render_form( self::CLEAN_ACTION, __( 'Clean SVG files', 'blocklane' ), true );
		self::render_form( self::DISMISS_ACTION, __( 'Dismiss', 'blocklane' ), false );
		echo '</div>';
	}

	/**
	 * The finished check's notice: one sentence per non-empty list, its
	 * first LINKS items linked to their edit screens, and Dismiss.
	 *
	 * @param array{state: string, ceiling: int, cursor: int, inflight: int, cleaned: list<int>, left: list<int>, missing: list<int>, review: list<int>} $state
	 */
	private static function render_done( array $state ): void {
		$sentences = array(
			/* translators: %d: number of files. */
			'cleaned' => _n( '%d SVG file was cleaned and is now saved the way a new upload is saved:', '%d SVG files were cleaned and are now saved the way a new upload is saved:', count( $state['cleaned'] ), 'blocklane' ),
			/* translators: %d: number of files. */
			'left'    => _n( '%d SVG file could not be checked and was left unchanged, so it may still carry scripts and is still served. Delete or replace it:', '%d SVG files could not be checked and were left unchanged, so they may still carry scripts and are still served. Delete or replace them:', count( $state['left'] ), 'blocklane' ),
			/* translators: %d: number of files. */
			'missing' => _n( '%d SVG file is not in this site\'s uploads folder (it may be in offload storage), so it was not checked:', '%d SVG files are not in this site\'s uploads folder (they may be in offload storage), so they were not checked:', count( $state['missing'] ), 'blocklane' ),
			/* translators: %d: number of files. */
			'review'  => _n( '%d SVG file was added before this update by, or is now credited to, a user who can add scripts. It was not changed. If it came from an account you removed, review it:', '%d SVG files were added before this update by, or are now credited to, users who can add scripts. They were not changed. If any came from an account you removed, review them:', count( $state['review'] ), 'blocklane' ),
		);
		$lists = array_filter( self::LISTS, static fn( string $list ): bool => array() !== $state[ $list ] );
		if ( array() === $lists ) {
			return;
		}
		echo '<div class="notice notice-warning" id="blocklane-pro-svg-check-notice"><p><strong>' . esc_html__( 'Safe SVG Uploads checked the SVG files added before this update.', 'blocklane' ) . '</strong></p>';
		foreach ( $lists as $list ) {
			echo '<p>' . esc_html( sprintf( $sentences[ $list ], count( $state[ $list ] ) ) ) . ' ';
			self::render_links( $state[ $list ] );
			echo '</p>';
		}
		self::render_form( self::DISMISS_ACTION, __( 'Dismiss', 'blocklane' ), false );
		echo '</div>';
	}

	/**
	 * Up to LINKS items, each linked to its edit screen when the current user
	 * can edit it, then "and N more". Every part is escaped where it prints.
	 *
	 * @param list<int> $ids Attachment IDs.
	 */
	private static function render_links( array $ids ): void {
		$shown = array_slice( $ids, 0, self::LINKS );
		foreach ( $shown as $n => $id ) {
			$title = get_the_title( $id );
			/* translators: %d: Media Library item ID. */
			$label = '' !== $title ? $title : sprintf( __( 'item %d', 'blocklane' ), $id );
			$edit  = get_edit_post_link( $id, 'url' );
			echo $n > 0 ? ', ' : '';
			if ( null !== $edit && '' !== $edit ) {
				echo '<a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a>';
			} else {
				echo esc_html( $label );
			}
		}
		$more = count( $ids ) - count( $shown );
		if ( $more > 0 ) {
			/* translators: %d: number of further files. */
			echo esc_html( sprintf( _n( ', and %d more.', ', and %d more.', $more, 'blocklane' ), $more ) );
		} else {
			echo '.';
		}
	}

	/**
	 * One of the notice's POST forms: admin-post.php, its action, its nonce.
	 *
	 * @param string $action  CLEAN_ACTION or DISMISS_ACTION.
	 * @param string $label   The button's text.
	 * @param bool   $primary Whether it is the primary button.
	 */
	private static function render_form( string $action, string $label, bool $primary ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		wp_nonce_field( $action );
		echo '<p><button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></p></form>';
	}
}
