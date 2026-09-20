<?php
/**
 * Forms — the management-side store: the module settings option and the
 * submissions-table queries the dashboard inbox reads. The submission
 * pipeline itself lives in runtime.php (content, license-ungated); this
 * class is tooling and boots through the gated module manifest.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forms implements Bootable {

	const OPTION = 'blocklane_pro_forms';

	/** Allowed submission statuses. */
	const STATUSES = array( 'unread', 'read' );

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {

		// Forms abilities ride the same opt-in as the rest of the Abilities
		// surface: registration is deliberately skipped unless the AI MCP
		// toggle is on (it grants AI clients access to submission data).
		if ( class_exists( __NAMESPACE__ . '\Advanced', false ) && Advanced::is_on( 'ai-mcp' ) ) {
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 30 );
		}
	}

	/**
	 * Read-only Abilities: list forms and read submissions, for AI clients
	 * over MCP (the existing ai-mcp module carries the transport).
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'blocklane-pro/forms-list',
			array(
				'label'               => __( 'List forms', 'blocklane' ),
				'description'         => __( 'List the site’s Blocklane forms that have received submissions, with total and unread counts.', 'blocklane' ),
				'category'            => 'blocklane-design',
				'execute_callback'    => static function () {
					blocklane_pro_forms_ensure_table();

					return Forms::forms();
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'blocklane-pro/forms-submissions',
			array(
				'label'               => __( 'Read form submissions', 'blocklane' ),
				'description'         => __( 'Read stored Blocklane form submissions — filter by form, originating page (origin_id), status, or search, paged. Read-only.', 'blocklane' ),
				'category'            => 'blocklane-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'form_id'   => array( 'type' => 'string' ),
						'origin_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'status'    => array(
							'type' => 'string',
							'enum' => array( 'unread', 'read' ),
						),
						'search'    => array( 'type' => 'string' ),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input = array() ) {
					blocklane_pro_forms_ensure_table();

					return Forms::query( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Setting defaults. purge_days 0 = keep forever (deleting user data
	 * silently is a footgun; the setting surfaces the tradeoff).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'purge_days'           => 0,
			'max_upload_mb'        => 0,
			// Danger-zone opt-in: uninstall keeps submissions/uploads unless
			// this is on ("what you build is yours" stays the default).
			'delete_on_uninstall'  => false,
			// Sender identity (v3): header-level From on form emails. Empty
			// = WordPress default. NOT transport — SMTP stays per-site.
			'email_from_name'      => '',
			'email_from_address'   => '',
			'smtp_hint_dismissed'  => false,
			'turnstile_enabled'    => false,
			// Test mode swaps in Cloudflare's official dummy keys (widget
			// renders, every check passes) so Turnstile is buildable with no
			// keys — the stored keys below are ignored while it's on.
			'turnstile_test_mode'  => false,
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
			'turnstile_theme'      => 'auto',
			'turnstile_appearance' => 'always',
			'turnstile_size'       => 'normal',
			// Site-wide form defaults (v2): the values every form inherits
			// unless its own Submission settings override them. Resolved
			// live by blocklane_pro_forms_resolve_config() — the stored
			// values here start at the built-in fallbacks, so "unset" and
			// "never touched" are the same state. The built-ins come from
			// blocklane_pro_forms_default_settings() in runtime.php — THE
			// single source, also feeding the resolver's fallbacks. The
			// inline copy below is a defensive fallback for a runtime that
			// predates the helper; keep it mirroring the helper, never edit
			// it alone.
			'defaults'             => function_exists( 'blocklane_pro_forms_default_settings' )
				? blocklane_pro_forms_default_settings()
				: array(
					'recipients'         => '',
					'subject'            => '',
					'success_action'     => 'message',
					'redirect_url'       => '',
					'store_submissions'  => true,
					'notify_admin'       => true,
					'auto_responder'     => array(
						'enabled' => false,
						'subject' => '',
						'message' => '',
					),
					'replace_on_success' => false,
				),
		);
	}

	/**
	 * Current settings, normalized.
	 *
	 * @return array
	 */
	public static function get() {
		return self::normalize( get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * The settings shaped for the admin client. The Turnstile secret is a
	 * bearer credential (it forges "human" verdicts for this site) and
	 * never round-trips to the browser: the client gets a boolean and
	 * writes a replacement blind — omit the key on save to keep the
	 * stored one (partial saves already do).
	 *
	 * @return array
	 */
	public static function client_settings() {
		$settings = self::get();
		$stored   = (string) $settings['turnstile_secret_key'];

		// "Set" must mean USABLE, not merely present. The secret is encrypted
		// with the site's auth salts, so rotating AUTH_KEY/SECURE_AUTH_KEY
		// orphans the ciphertext: decrypt returns '' and verification quietly
		// fails open, while a presence check kept reporting the key as
		// configured. The operator then sees a healthy screen and no
		// Turnstile. Report decryptability, and flag the orphaned case
		// separately so the UI can say re-enter it rather than just "empty".
		$usable   = '' !== $stored
			&& function_exists( 'blocklane_pro_forms_decrypt_secret' )
			&& '' !== blocklane_pro_forms_decrypt_secret( $stored );

		$settings['turnstile_secret_set']      = $usable;
		$settings['turnstile_secret_orphaned'] = '' !== $stored && ! $usable;
		unset( $settings['turnstile_secret_key'] );

		return $settings;
	}

	/**
	 * Save (partial payloads allowed — omitted keys keep current values).
	 * Unknown keys in the PAYLOAD are ignored; unknown keys in the STORED row,
	 * top-level or inside the `defaults` map, are preserved
	 * (Helper::with_foreign_keys — the other edition, or a newer version of
	 * this one, may own them). The row stays non-autoloaded.
	 *
	 * @param array $settings Incoming settings.
	 * @return array|\WP_Error The stored settings, or the helper's typed refusal (nothing written).
	 */
	public static function save( array $settings ): array|\WP_Error {
		$merged = Helper::with_foreign_keys( self::OPTION, self::normalize( $settings, self::get() ), self::defaults() );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}
		update_option( self::OPTION, $merged, false );

		return self::get();
	}

	/**
	 * Normalize a settings payload against a fallback map.
	 *
	 * @param array $in       Incoming values.
	 * @param array $fallback Values for omitted keys.
	 * @return array
	 */
	private static function normalize( $in, $fallback ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$out['purge_days'] = isset( $in['purge_days'] )
			? min( 3650, absint( $in['purge_days'] ) )
			: $fallback['purge_days'];

		$out['max_upload_mb'] = isset( $in['max_upload_mb'] )
			? min( 1024, absint( $in['max_upload_mb'] ) )
			: ( isset( $fallback['max_upload_mb'] ) ? $fallback['max_upload_mb'] : 0 );

		$out['smtp_hint_dismissed'] = isset( $in['smtp_hint_dismissed'] )
			? (bool) $in['smtp_hint_dismissed']
			: $fallback['smtp_hint_dismissed'];

		$out['delete_on_uninstall'] = isset( $in['delete_on_uninstall'] )
			? (bool) $in['delete_on_uninstall']
			: ( isset( $fallback['delete_on_uninstall'] ) ? (bool) $fallback['delete_on_uninstall'] : false );

		$out['email_from_name'] = isset( $in['email_from_name'] )
			? sanitize_text_field( trim( (string) $in['email_from_name'] ) )
			: ( isset( $fallback['email_from_name'] ) ? (string) $fallback['email_from_name'] : '' );

		// Invalid input clears rather than persists — a bad From is worse
		// than the WordPress default it would silently replace.
		$from_address             = isset( $in['email_from_address'] )
			? sanitize_email( trim( (string) $in['email_from_address'] ) )
			: ( isset( $fallback['email_from_address'] ) ? (string) $fallback['email_from_address'] : '' );
		$out['email_from_address'] = is_email( $from_address ) ? $from_address : '';

		$out['turnstile_enabled'] = isset( $in['turnstile_enabled'] )
			? (bool) $in['turnstile_enabled']
			: ( isset( $fallback['turnstile_enabled'] ) ? $fallback['turnstile_enabled'] : false );

		$out['turnstile_test_mode'] = isset( $in['turnstile_test_mode'] )
			? (bool) $in['turnstile_test_mode']
			: ( isset( $fallback['turnstile_test_mode'] ) ? $fallback['turnstile_test_mode'] : false );

		foreach ( array( 'turnstile_site_key', 'turnstile_secret_key' ) as $key ) {
			$out[ $key ] = isset( $in[ $key ] )
				? sanitize_text_field( trim( (string) $in[ $key ] ) )
				: ( isset( $fallback[ $key ] ) ? $fallback[ $key ] : '' );

			// Cloudflare's dummy keys never persist here: test mode supplies
			// them itself (without writing them), so a stored dummy is only
			// ever pre-test-mode residue — purging on every save is what
			// lets test mode hand back clean fields when it's turned off.
			// function_exists is load-bearing, not defensive habit: runtime.php
			// RETURNS EARLY when safe mode is on or the Forms Advanced toggle is
			// off (runtime.php:39 and :51), and these helpers are defined ~1200
			// lines below that — so with Forms disabled the symbol genuinely does
			// not exist while this sanitizer can still run. Degrades to no purge
			// rather than a fatal.
			if ( function_exists( 'blocklane_pro_forms_turnstile_is_dummy_key' )
				&& blocklane_pro_forms_turnstile_is_dummy_key( $out[ $key ] ) ) {
				$out[ $key ] = '';
			}
		}

		// Secret at rest (v3): encrypt on every save — plaintext residue
		// migrates transparently the first time settings are saved. Gated on
		// BOTH symbols for the same reason as above (runtime.php returns early
		// under safe mode / Forms-off, so neither helper exists then), and on
		// both rather than one because encrypting a secret nothing can decrypt
		// would read back as "misconfigured". encrypt_secret itself passes
		// through already-encrypted values.
		if ( function_exists( 'blocklane_pro_forms_decrypt_secret' )
			&& function_exists( 'blocklane_pro_forms_encrypt_secret' ) ) {
			$out['turnstile_secret_key'] = blocklane_pro_forms_encrypt_secret( $out['turnstile_secret_key'] );
		}

		$theme                  = isset( $in['turnstile_theme'] ) ? (string) $in['turnstile_theme'] : ( isset( $fallback['turnstile_theme'] ) ? $fallback['turnstile_theme'] : 'auto' );
		$out['turnstile_theme'] = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';

		$appearance                  = isset( $in['turnstile_appearance'] ) ? (string) $in['turnstile_appearance'] : ( isset( $fallback['turnstile_appearance'] ) ? $fallback['turnstile_appearance'] : 'always' );
		$out['turnstile_appearance'] = in_array( $appearance, array( 'always', 'interaction-only' ), true ) ? $appearance : 'always';

		$size                  = isset( $in['turnstile_size'] ) ? (string) $in['turnstile_size'] : ( isset( $fallback['turnstile_size'] ) ? $fallback['turnstile_size'] : 'normal' );
		$out['turnstile_size'] = in_array( $size, array( 'normal', 'flexible', 'compact' ), true ) ? $size : 'normal';

		$out['defaults'] = self::normalize_defaults(
			isset( $in['defaults'] ) ? $in['defaults'] : null,
			isset( $fallback['defaults'] ) && is_array( $fallback['defaults'] ) ? $fallback['defaults'] : array()
		);

		return $out;
	}

	/**
	 * Normalize the site-wide form defaults sub-array (partial payloads
	 * allowed, like the parent settings). The runtime re-sanitizes on read
	 * (blocklane_pro_forms_form_defaults) — this keeps the STORED values
	 * clean too, so exports and direct reads are safe.
	 *
	 * @param mixed $in       Incoming defaults payload (null = keep fallback).
	 * @param array $fallback Values for omitted keys.
	 * @return array
	 */
	private static function normalize_defaults( $in, array $fallback ) {
		$in       = is_array( $in ) ? $in : array();
		$builtin  = self::defaults();
		$fallback = array_merge( $builtin['defaults'], $fallback );
		$out      = array();

		$out['recipients'] = isset( $in['recipients'] )
			? sanitize_text_field( (string) $in['recipients'] )
			: $fallback['recipients'];
		$out['subject']    = isset( $in['subject'] )
			? sanitize_text_field( (string) $in['subject'] )
			: $fallback['subject'];

		$action                = isset( $in['success_action'] ) ? (string) $in['success_action'] : (string) $fallback['success_action'];
		$out['success_action'] = in_array( $action, array( 'message', 'redirect' ), true ) ? $action : 'message';

		$out['redirect_url'] = isset( $in['redirect_url'] )
			? esc_url_raw( (string) $in['redirect_url'] )
			: $fallback['redirect_url'];

		$out['store_submissions'] = isset( $in['store_submissions'] )
			? (bool) $in['store_submissions']
			: (bool) $fallback['store_submissions'];
		$out['notify_admin']      = isset( $in['notify_admin'] )
			? (bool) $in['notify_admin']
			: (bool) $fallback['notify_admin'];

		$auto_in  = isset( $in['auto_responder'] ) && is_array( $in['auto_responder'] ) ? $in['auto_responder'] : null;
		$auto_fb  = is_array( $fallback['auto_responder'] ) ? array_merge( $builtin['defaults']['auto_responder'], $fallback['auto_responder'] ) : $builtin['defaults']['auto_responder'];
		$auto_src = null === $auto_in ? $auto_fb : $auto_in;

		$out['auto_responder'] = array(
			'enabled' => isset( $auto_src['enabled'] )
				? (bool) $auto_src['enabled']
				: (bool) $auto_fb['enabled'],
			'subject' => isset( $auto_src['subject'] )
				? sanitize_text_field( (string) $auto_src['subject'] )
				: (string) $auto_fb['subject'],
			'message' => isset( $auto_src['message'] )
				? sanitize_textarea_field( (string) $auto_src['message'] )
				: (string) $auto_fb['message'],
		);

		$out['replace_on_success'] = isset( $in['replace_on_success'] )
			? (bool) $in['replace_on_success']
			: (bool) $fallback['replace_on_success'];

		return $out;
	}

	/**
	 * Whether something is customizing the mailer (an SMTP plugin or a
	 * phpmailer_init customization). Drives the deliverability hint — we
	 * don't own SMTP, we surface its absence.
	 *
	 * @return bool
	 */
	public static function smtp_configured() {
		return (bool) has_action( 'phpmailer_init' );
	}

	/**
	 * Distinct forms present in the submissions table (for the inbox filter).
	 *
	 * @return array[] { form_id, form_name, total, unread }
	 */
	public static function forms() {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_id, MAX(form_name) AS form_name, COUNT(*) AS total,
					SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) AS unread
				FROM %i GROUP BY form_id ORDER BY MAX(id) DESC",
				$table
			)
		);

		$forms = array();
		foreach ( (array) $rows as $row ) {
			$forms[] = array(
				'form_id'   => (string) $row->form_id,
				'form_name' => '' !== (string) $row->form_name ? (string) $row->form_name : (string) $row->form_id,
				'total'     => (int) $row->total,
				'unread'    => (int) $row->unread,
			);
		}

		return $forms;
	}

	/**
	 * Distinct origins (viewing pages) present in the table, newest first —
	 * the inbox "Submitted from" filter. Display names come from each
	 * origin's NEWEST snapshot, so the filter keeps working (and keeps its
	 * label) after the origin page is renamed or deleted.
	 *
	 * Capped: this runs on EVERY overview fetch (screen mount and each
	 * row-action refresh), and unlike forms() its cardinality is unbounded
	 * — a long-lived lead-gen site accrues one group per landing page ever
	 * seen. The newest N origins are plenty for a filter dropdown; rows
	 * beyond it stay reachable by the form/status/search filters.
	 *
	 * @return array[] { origin_id, title, url, total }
	 */
	public static function origins() {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		/**
		 * Filter the maximum number of distinct origins offered in the
		 * inbox filter (newest first).
		 *
		 * @param int $limit Default 200.
		 */
		$limit = max( 1, absint( apply_filters( 'blocklane_pro_forms_origins_limit', 200 ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT origin_id, COUNT(*) AS total, MAX(id) AS last_id
				FROM %i WHERE origin_id > 0
				GROUP BY origin_id ORDER BY MAX(id) DESC LIMIT %d",
				$table,
				$limit
			)
		);
		if ( ! $rows ) {
			return array();
		}

		// One %d per id: count() is the one call whose arguments the sniff
		// steps over, which is what makes this the documented idiom for an IN
		// list rather than a hole the checker happens not to see.
		$last_ids = array_map( 'absint', wp_list_pluck( $rows, 'last_id' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
		$snapshots = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, origin FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $last_ids ), '%d' ) ) . ')',
				array_merge( array( $table ), $last_ids )
			),
			OBJECT_K
		);

		$origins = array();
		foreach ( $rows as $row ) {
			$snapshot = isset( $snapshots[ $row->last_id ] ) ? json_decode( (string) $snapshots[ $row->last_id ]->origin, true ) : null;
			$snapshot = is_array( $snapshot ) ? $snapshot : array();
			$title    = isset( $snapshot['title'] ) && '' !== $snapshot['title'] ? (string) $snapshot['title'] : '';

			$origins[] = array(
				'origin_id' => (int) $row->origin_id,
				'title'     => '' !== $title
					? $title
					/* translators: %d: post id of a deleted/unnamed origin page. */
					: sprintf( __( 'Page #%d', 'blocklane' ), (int) $row->origin_id ),
				'url'       => isset( $snapshot['url'] ) ? (string) $snapshot['url'] : '',
				'total'     => (int) $row->total,
			);
		}

		return $origins;
	}

	/**
	 * Query submissions for the inbox.
	 *
	 * @param array $args { form_id, origin_id, status, search, page, per_page }.
	 * @return array { items, total, total_pages }
	 */
	public static function query( array $args ) {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		$page     = max( 1, absint( isset( $args['page'] ) ? $args['page'] : 1 ) );
		$per_page = min( 100, max( 1, absint( isset( $args['per_page'] ) ? $args['per_page'] : 20 ) ) );

		/*
		 * OPTIONAL-PARAMETER TERMS, not an assembled WHERE.
		 *
		 * The old builder imploded prepared fragments into $where_sql and
		 * interpolated that into prepare(). It was safe — every fragment was a
		 * literal with placeholders — but it cannot be made to READ safe:
		 * prepare()'s first argument is the one thing the sniff examines, and
		 * any variable in it is flagged no matter what it holds. Nor should a
		 * reader have to reconstruct the statement from four branches to know
		 * what runs.
		 *
		 * So the statement text is fixed, and an absent filter is expressed as
		 * a value: '' for a string, 0 for an id. `( %s = '' OR form_id = %s )`
		 * is TRUE for every row when the filter is empty and narrows when it is
		 * set. prepare() inlines the literals, so the engine constant-folds the
		 * dead half of each term and still uses the form_id index.
		 *
		 * The WHERE below is written out TWICE, in the count and in the page
		 * query, deliberately: a shared $where variable is the very thing this
		 * rewrite removes. They are a MIRROR — change one, change the other;
		 * the forms battery asserts total === count(items) across every filter
		 * combination, which is what catches them drifting apart.
		 */
		$form_id   = ! empty( $args['form_id'] ) ? sanitize_title( $args['form_id'] ) : '';
		$origin_id = ! empty( $args['origin_id'] ) ? absint( $args['origin_id'] ) : 0;
		$status    = ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) ? (string) $args['status'] : '';
		// search_text holds only submitted values (see the storage listener),
		// so this matches what visitors typed rather than the JSON snapshot's
		// structural tokens.
		$like = ! empty( $args['search'] )
			? '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%'
			: '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it, and the inbox must read what was just written.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// MIRROR: the WHERE here is identical to the one below.
				"SELECT COUNT(*) FROM %i
				  WHERE ( %s = '' OR form_id = %s )
				    AND ( %d = 0 OR origin_id = %d )
				    AND ( %s = '' OR status = %s )
				    AND ( %s = '' OR form_name LIKE %s OR search_text LIKE %s )",
				$table,
				$form_id,
				$form_id,
				$origin_id,
				$origin_id,
				$status,
				$status,
				$like,
				$like,
				$like
			)
		);
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// MIRROR: the WHERE here is identical to the one above.
				"SELECT * FROM %i
				  WHERE ( %s = '' OR form_id = %s )
				    AND ( %d = 0 OR origin_id = %d )
				    AND ( %s = '' OR status = %s )
				    AND ( %s = '' OR form_name LIKE %s OR search_text LIKE %s )
				  ORDER BY id DESC
				  LIMIT %d OFFSET %d",
				$table,
				$form_id,
				$form_id,
				$origin_id,
				$origin_id,
				$status,
				$status,
				$like,
				$like,
				$like,
				$per_page,
				( $page - 1 ) * $per_page
			)
		);
		// phpcs:enable

		return array(
			'items'       => array_map( array( __CLASS__, 'format_row' ), (array) $rows ),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * One submission by id.
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public static function get_submission( $id ) {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, absint( $id ) ) );

		return $row ? self::format_row( $row ) : null;
	}

	/**
	 * Update a submission's status.
	 *
	 * @param int    $id     Row id.
	 * @param string $status One of STATUSES.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		return false !== $wpdb->update(
			blocklane_pro_forms_table(),
			array( 'status' => $status ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * The decoded fields snapshot straight from the table — for server-side
	 * consumers (file cleanup, download streaming) that need the stored
	 * paths format_row() strips from the client shape.
	 *
	 * @param int $id Row id.
	 * @return array|null Decoded fields, or null when the row is absent.
	 */
	public static function raw_fields( $id ) {
		global $wpdb;
		$table = blocklane_pro_forms_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own submissions table; core has no API for it.
		$json = $wpdb->get_var( $wpdb->prepare( 'SELECT fields FROM %i WHERE id = %d', $table, absint( $id ) ) );

		if ( null === $json ) {
			return null;
		}
		$fields = json_decode( (string) $json, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Delete a submission — and its stored uploads, which die with the row.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$fields = self::raw_fields( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$deleted = (bool) $wpdb->delete( blocklane_pro_forms_table(), array( 'id' => absint( $id ) ), array( '%d' ) );

		if ( $deleted && $fields ) {
			blocklane_pro_forms_delete_stored_files( blocklane_pro_forms_stored_paths( $fields ) );
		}

		return $deleted;
	}

	/**
	 * Build a CSV of every submission matching the inbox filters (v3).
	 *
	 * Columns: Date, Form, Status, Origin URL, then the union of field
	 * names across exported rows in first-seen order — headers use the
	 * ROW-SNAPSHOT labels (self-describing rows render correctly even
	 * after the form changed; same principle as the inbox). File fields
	 * export display filenames, never stored paths (format_row already
	 * strips them). Every cell is formula-neutralized: spreadsheet apps
	 * execute cells starting = + - @ (or tab/CR), so visitor-typed values
	 * get a leading apostrophe — the Giraforms teardown's one free-tier
	 * idea worth keeping, done at build time not display time.
	 *
	 * @param array $args { form_id, origin_id, status, search }.
	 * @return string CSV bytes (UTF-8, BOM-prefixed for Excel).
	 */
	public static function export_csv( array $args ) {
		$columns = array(); // name => label, first-seen order.
		$items   = array();

		// Page through everything matching the filters; the ceiling (200
		// pages = 20k rows) is a runaway guard, not an expected limit — but a
		// guard that trims an export silently is worse than the runaway it
		// prevents, because the file looks complete. $truncated below turns it
		// into something the operator can see.
		$truncated = false;
		for ( $page = 1; $page <= 200; $page++ ) {
			$batch = self::query(
				array_merge(
					$args,
					array(
						'page'     => $page,
						'per_page' => 100,
					)
				)
			);
			foreach ( $batch['items'] as $item ) {
				$items[] = $item;
				foreach ( $item['fields'] as $field ) {
					if ( ! is_array( $field ) || empty( $field['name'] ) ) {
						continue;
					}
					$name = (string) $field['name'];
					if ( ! isset( $columns[ $name ] ) ) {
						$label            = isset( $field['label'] ) ? trim( wp_strip_all_tags( (string) $field['label'] ) ) : '';
						$columns[ $name ] = '' !== $label ? $label : $name;
					}
				}
			}
			if ( count( $batch['items'] ) < 100 ) {
				break;
			}
			if ( 200 === $page ) {
				// A full final page means the ceiling cut the export short.
				$truncated = true;
			}
		}

		$handle = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory CSV assembly.
		// BOM so Excel detects UTF-8 instead of mangling non-ASCII values.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$header = array(
			__( 'Date', 'blocklane' ),
			__( 'Form', 'blocklane' ),
			__( 'Status', 'blocklane' ),
			__( 'Origin URL', 'blocklane' ),
		);
		foreach ( $columns as $label ) {
			$header[] = self::csv_cell( $label );
		}
		fputcsv( $handle, $header, ",", '"', "" );

		if ( $truncated ) {
			// A row inside the file itself, not just a UI message: the export
			// gets mailed around and opened far from the screen that made it.
			// Padded to the header's width: a one-cell record between the
			// header and the data makes strict parsers reject the whole file,
			// which would turn "your export is short" into "your export is
			// unreadable".
			fputcsv(
				$handle,
				array_pad(
					array(
						self::csv_cell(
							sprintf(
								/* translators: %s: number of exported rows. */
								__( 'NOTE: this export was truncated at %s rows. Narrow the filters and export again to get the rest.', 'blocklane' ),
								number_format_i18n( count( $items ) )
							)
						),
					),
					count( $header ),
					''
				),
				",",
				'"',
				""
			);
		}

		foreach ( $items as $item ) {
			$by_name = array();
			foreach ( $item['fields'] as $field ) {
				if ( is_array( $field ) && ! empty( $field['name'] ) ) {
					$by_name[ (string) $field['name'] ] = $field;
				}
			}

			// Every cell goes through csv_cell(), the fixed four included. They
			// were emitted raw: form_name is the form block's authored 'name'
			// attribute, so anyone who can publish a post containing a form
			// picks it, and the origin URL carries whatever query string the
			// visitor arrived with. A leading = + - @ (or tab/CR) in either is
			// a live formula the moment the export opens in a spreadsheet.
			$row = array(
				self::csv_cell( isset( $item['created'] ) ? (string) $item['created'] : '' ),
				self::csv_cell( (string) $item['form_name'] ),
				self::csv_cell( (string) $item['status'] ),
				self::csv_cell( isset( $item['origin']['url'] ) ? (string) $item['origin']['url'] : '' ),
			);
			foreach ( array_keys( $columns ) as $name ) {
				$row[] = self::csv_cell( isset( $by_name[ $name ] ) ? self::csv_field_value( $by_name[ $name ] ) : '' );
			}
			fputcsv( $handle, $row, ",", '"', "" );
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $csv;
	}

	/**
	 * One field's exportable value: arrays join with "; ", file fields
	 * export their display filenames.
	 *
	 * @param array $field Row-snapshot field.
	 * @return string
	 */
	private static function csv_field_value( array $field ) {
		if ( ! empty( $field['files'] ) && is_array( $field['files'] ) ) {
			$names = array();
			foreach ( $field['files'] as $file ) {
				if ( is_array( $file ) && ! empty( $file['name'] ) ) {
					$names[] = (string) $file['name'];
				}
			}

			return implode( '; ', $names );
		}
		$value = isset( $field['value'] ) ? $field['value'] : '';

		return is_array( $value ) ? implode( '; ', array_map( 'strval', $value ) ) : (string) $value;
	}

	/**
	 * Neutralize spreadsheet formula injection: a leading apostrophe forces
	 * text interpretation of cells starting with a formula trigger.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Delete EVERY submission (v3 danger-zone action). Rides the row-delete
	 * path so stored uploads die with their rows, in bounded batches with a
	 * per-request ceiling — the client re-calls while `remaining` is
	 * non-zero, so a huge inbox can't blow the request budget.
	 *
	 * @return array{deleted:int, remaining:int}
	 */
	public static function delete_all() {
		global $wpdb;

		$table   = blocklane_pro_forms_table();
		$deleted = 0;

		for ( $batch = 0; $batch < 25; $batch++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own submissions table; core has no API for it.
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id ASC LIMIT 200', $table ) );
			if ( ! $ids ) {
				break;
			}

			$before = $deleted;
			foreach ( $ids as $id ) {
				if ( self::delete( (int) $id ) ) {
					$deleted++;
				}
			}

			// A row that will not delete (a locked file, a filter refusing it)
			// is re-selected by the very next pass, so without this the loop
			// spends all 25 batches re-reading the same wedged ids and the
			// caller loops 40 more times over the same nothing. One pass that
			// deletes zero means no further pass can do better.
			if ( $deleted === $before ) {
				break;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own submissions table; core has no API for it.
		$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		return array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		);
	}

	/**
	 * A table row shaped for the REST/inbox surface. The fields column is a
	 * self-describing snapshot, so rows render correctly even after the form
	 * changed (the whole point of storing { name, label, type, value }).
	 *
	 * @param object $row DB row.
	 * @return array
	 */
	private static function format_row( $row ) {
		$fields = json_decode( (string) $row->fields, true );
		$fields = is_array( $fields ) ? $fields : array();

		// The stored path never ships to the browser: the download route
		// addresses files by index, and on servers that ignore .htaccess
		// the unguessable name is the only thing gating a direct fetch.
		// Keys are stripped (not entries), so client and server file
		// indexes stay aligned; raw_fields() serves the server-side needs.
		foreach ( $fields as &$field ) {
			if ( is_array( $field ) && ! empty( $field['files'] ) && is_array( $field['files'] ) ) {
				foreach ( $field['files'] as &$file ) {
					if ( is_array( $file ) ) {
						unset( $file['stored'] );
					}
				}
				unset( $file );
			}
		}
		unset( $field );

		$excerpt = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || 'hidden' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				continue;
			}
			$value = isset( $field['value'] ) ? $field['value'] : '';
			$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			if ( '' === $value ) {
				continue;
			}
			$excerpt[] = $value;
			if ( count( $excerpt ) >= 3 ) {
				break;
			}
		}

		$origin = array();
		if ( isset( $row->origin ) && is_string( $row->origin ) && '' !== $row->origin ) {
			$decoded = json_decode( $row->origin, true );
			$origin  = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'id'        => (int) $row->id,
			'form_id'   => (string) $row->form_id,
			'form_name' => '' !== (string) $row->form_name ? (string) $row->form_name : (string) $row->form_id,
			'source_id' => (int) $row->source_id,
			'origin_id' => isset( $row->origin_id ) ? (int) $row->origin_id : 0,
			'origin'    => $origin,
			'fields'    => $fields,
			'excerpt'   => implode( ' — ', $excerpt ),
			'status'    => (string) $row->status,
			'date'      => get_date_from_gmt( (string) $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'created'   => (string) $row->created_at,
		);
	}
}
