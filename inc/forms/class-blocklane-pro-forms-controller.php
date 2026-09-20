<?php
/**
 * Forms — management REST (the dashboard inbox + settings). Lives on the
 * pro namespace behind manage_options; the PUBLIC submit route is registered
 * by runtime.php on blocklane/v1 instead (content — see the spec).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forms_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/forms',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_overview' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/forms/submissions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_submissions' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'form_id'   => array(
							'type'              => 'string',
							'default'           => '',
							// Not bare 'sanitize_title': REST passes
							// ( $value, $request, $param ) and its 2nd
							// param is $fallback_title — an empty value
							// would return the Request object.
							'sanitize_callback' => static function ( $value ) {
								return sanitize_title( $value );
							},
						),
						'origin_id' => array(
							'type'              => 'integer',
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'status'    => array(
							'type'              => 'string',
							'default'           => '',
							'enum'              => array( '', 'unread', 'read' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'search'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'      => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'  => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/forms/submissions/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_submission' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'status' => array(
							'type'              => 'string',
							'required'          => true,
							'enum'              => Forms::STATUSES,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_submission' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/forms/submissions/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_submissions' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'form_id'   => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => static function ( $value ) {
								return sanitize_title( $value );
							},
						),
						'origin_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
						'status'    => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
						'search'    => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/forms/submissions/all',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_all_submissions' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						// The typed confirmation is server-enforced, not
						// just a client dialog — this route is irreversible.
						'confirm' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => static function ( $value ) {
								return 'DELETE' === $value;
							},
						),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/forms/submissions/(?P<id>\d+)/files/(?P<index>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'download_file' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	/**
	 * Stream one stored upload from a submission. Capability-checked (the
	 * route permission), path-checked (realpath must stay inside the private
	 * root), and served as an attachment with nosniff — the browser never
	 * interprets visitor bytes in the admin origin.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error JSON error; success exits after streaming.
	 */
	public function download_file( $request ) {
		// raw_fields, not get_submission: the client shape strips the
		// stored paths this route resolves.
		$fields = Forms::raw_fields( absint( $request['id'] ) );
		if ( null === $fields ) {
			return new \WP_Error( 'blocklane_forms_not_found', __( 'Submission not found.', 'blocklane' ), array( 'status' => 404 ) );
		}

		// Files across all file fields, in snapshot order — index addresses
		// that flat list (what the inbox renders).
		$files = array();
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['files'] ) && is_array( $field['files'] ) ) {
				foreach ( $field['files'] as $file ) {
					$files[] = $file;
				}
			}
		}

		$index = absint( $request['index'] );
		if ( ! isset( $files[ $index ] ) || empty( $files[ $index ]['stored'] ) ) {
			return new \WP_Error( 'blocklane_forms_file_not_found', __( 'File not found.', 'blocklane' ), array( 'status' => 404 ) );
		}
		$file = $files[ $index ];

		// The shared traversal guard — resolve refuses anything outside the
		// private upload root.
		$path = blocklane_pro_forms_resolve_stored_file( $file['stored'] );
		if ( false === $path ) {
			return new \WP_Error( 'blocklane_forms_file_missing', __( 'The stored file no longer exists.', 'blocklane' ), array( 'status' => 404 ) );
		}

		// ASCII fallback + RFC 5987 UTF-8 name; quotes/CR/LF stripped so the
		// visitor-chosen filename cannot splice headers.
		$name  = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : 'file';
		$name  = str_replace( array( '"', "\r", "\n", '\\' ), '', $name );
		$ascii = preg_replace( '/[^\x20-\x7E]/', '_', $name );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming our own stored file.
		readfile( $path );
		exit;
	}

	public function permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The screen bootstrap payload: forms present in the table, settings,
	 * and whether a mailer customization exists (the SMTP hint).
	 *
	 * @return WP_REST_Response
	 */
	public function get_overview() {
		blocklane_pro_forms_ensure_table();

		return rest_ensure_response(
			array(
				'forms'     => Forms::forms(),
				'origins'   => Forms::origins(),
				'settings'  => Forms::client_settings(),
				'smtp'      => array( 'configured' => Forms::smtp_configured() ),
				// Guarded: the loaded runtime can be an OLDER baked copy (the
				// mu bake wins the single-load guard, and a host can block
				// rebaking) — a v1 runtime has no mail_broken(). Degrade to
				// "healthy", never fatal the whole overview.
				'mail'      => array( 'broken' => function_exists( 'blocklane_pro_forms_mail_broken' ) ? blocklane_pro_forms_mail_broken() : false ),
				'turnstile' => array(
					'broken'    => blocklane_pro_forms_turnstile_broken(),
					'test_keys' => blocklane_pro_forms_turnstile_test_keys_stored(),
				),
			)
		);
	}

	/**
	 * Save module settings (partial payloads allowed).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error The payload, or the store's typed refusal.
	 */
	public function save_settings( $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$saved   = Forms::save( $payload );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// A channel the owner turns OFF site-wide can never send again, so
		// nothing would ever clear its broken flag — the non-dismissible
		// banner would pin for its full backstop week claiming emails are
		// failing that are, per the owner's own settings, not attempted.
		// A per-form override that still sends re-raises on its next
		// failure. (function_exists: the loaded runtime can be an older
		// bake without the mail helpers.)
		if ( function_exists( 'blocklane_pro_forms_mail_clear_broken' ) ) {
			$defaults = Forms::get()['defaults'];
			if ( empty( $defaults['notify_admin'] ) ) {
				blocklane_pro_forms_mail_clear_broken( 'admin' );
			}
			if ( empty( $defaults['auto_responder']['enabled'] ) ) {
				blocklane_pro_forms_mail_clear_broken( 'auto-responder' );
			}
		}

		// Probe a freshly-entered secret against siteverify right away:
		// Cloudflare judges the secret before the token, so a dummy token
		// distinguishes a rejected SECRET ('misconfigured') from a rejected
		// TOKEN ('rejected' = the secret is fine). Feeds the same broken
		// flag as the first-failure detector, so the banner appears — or
		// clears — on the very next overview load instead of waiting for a
		// visitor. 'unreachable' proves nothing about the key: leave as-is.
		if ( isset( $payload['turnstile_secret_key'] ) && '' !== trim( (string) $payload['turnstile_secret_key'] ) ) {
			$verdict = blocklane_pro_forms_turnstile_verify( 'blocklane-probe', trim( (string) $payload['turnstile_secret_key'] ), '' );
			if ( 'misconfigured' === $verdict['reason'] ) {
				blocklane_pro_forms_turnstile_flag_broken( 'misconfigured' );
			} elseif ( in_array( $verdict['reason'], array( 'verified', 'rejected' ), true ) ) {
				blocklane_pro_forms_turnstile_clear_broken();
			}
		}

		// The client shape, not the stored one — the secret stays server-side.
		return rest_ensure_response(
			array(
				'settings'  => Forms::client_settings(),
				'turnstile' => array(
					'broken'    => blocklane_pro_forms_turnstile_broken(),
					'test_keys' => blocklane_pro_forms_turnstile_test_keys_stored(),
				),
				// Carried so a save that cleared a channel (above) updates
				// the banner without a reload; guarded like the overview.
				'mail'      => array( 'broken' => function_exists( 'blocklane_pro_forms_mail_broken' ) ? blocklane_pro_forms_mail_broken() : false ),
			)
		);
	}

	/**
	 * The inbox list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_submissions( $request ) {
		blocklane_pro_forms_ensure_table();

		return rest_ensure_response(
			Forms::query(
				array(
					'form_id'   => $request['form_id'],
					'origin_id' => $request['origin_id'],
					'status'    => $request['status'],
					'search'    => $request['search'],
					'page'      => $request['page'],
					'per_page'  => $request['per_page'],
				)
			)
		);
	}

	/**
	 * Update one submission (status only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_submission( $request ) {
		$id = absint( $request['id'] );
		if ( ! Forms::get_submission( $id ) ) {
			return new \WP_Error( 'blocklane_pro_forms_not_found', __( 'Submission not found.', 'blocklane' ), array( 'status' => 404 ) );
		}
		Forms::set_status( $id, $request['status'] );

		return rest_ensure_response( array( 'item' => Forms::get_submission( $id ) ) );
	}

	/**
	 * Stream the filtered inbox as CSV. Same streaming shape as
	 * download_file: headers + echo + exit, attachment disposition and
	 * nosniff so the browser downloads instead of interpreting.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function export_submissions( $request ) {
		$csv = Forms::export_csv(
			array(
				'form_id'   => (string) $request->get_param( 'form_id' ),
				'origin_id' => (int) $request->get_param( 'origin_id' ),
				'status'    => (string) $request->get_param( 'status' ),
				'search'    => (string) $request->get_param( 'search' ),
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="form-submissions-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV byte stream, not HTML; cells are formula-neutralized at build.
		exit;
	}

	/**
	 * The danger-zone wipe. Batched with a per-request ceiling; the client
	 * re-calls while `remaining` is non-zero.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_all_submissions( $request ) {
		unset( $request );

		return rest_ensure_response( Forms::delete_all() );
	}

	/**
	 * Delete one submission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_submission( $request ) {
		$id = absint( $request['id'] );
		if ( ! Forms::get_submission( $id ) ) {
			return new \WP_Error( 'blocklane_pro_forms_not_found', __( 'Submission not found.', 'blocklane' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => Forms::delete( $id ) ) );
	}
}
