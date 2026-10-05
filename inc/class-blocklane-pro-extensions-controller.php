<?php
/**
 * Extensions REST controller: read/write the enabled-extensions map.
 *
 * Local-only feature — no Supabase dependency. Gated to manage_options;
 * the React admin app sends the standard wp_rest nonce on X-WP-Nonce.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Extensions_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/extensions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_extensions' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_extension' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static function ( $value ) {
								return is_string( $value ) && '' !== $value;
							},
						),
						'enabled' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/breakpoints',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_breakpoints' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_breakpoints' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'tablet' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 360,
							'maximum'  => 2000,
						),
						'mobile' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 240,
							'maximum'  => 1600,
						),
					),
				),
			)
		);

		// The grid canvas tools belong to extension:advanced-grid: the route
		// exists exactly when the unit does, decided once here at the one
		// registration site (#1015). Under free the unit is absent and this
		// controller registers nothing for it.
		if ( Edition::has( 'extension:advanced-grid' ) ) {
			register_rest_route(
				Branding::rest_namespace(),
				'/grid-canvas-tools',
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_grid_canvas_tools' ),
						'permission_callback' => array( $this, 'permission_check' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'save_grid_canvas_tools' ),
						'permission_callback' => array( $this, 'permission_check' ),
						'args'                => array(
							'enabled' => array(
								'type'     => 'boolean',
								'required' => true,
							),
						),
					),
				)
			);
		}
	}

	/**
	 * Read the canvas grid tools switch.
	 *
	 * @param \WP_REST_Request $request Request (unused).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_grid_canvas_tools( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array( 'enabled' => Extensions_Handler::get_grid_canvas_tools() )
		);
	}

	/**
	 * Save the canvas grid tools setting. A write the store did not take is
	 * refused (500) rather than echoed back as saved.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_grid_canvas_tools( \WP_REST_Request $request ) {
		$written = Extensions_Handler::set_grid_canvas_tools( (bool) $request->get_param( 'enabled' ) );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		return rest_ensure_response(
			array( 'enabled' => Extensions_Handler::get_grid_canvas_tools() )
		);
	}

	public function get_breakpoints( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'breakpoints' => Extensions_Handler::get_breakpoints(),
				'defaults'    => Extensions_Handler::default_breakpoints(),
			)
		);
	}

	public function save_breakpoints( \WP_REST_Request $request ) {
		$saved = Extensions_Handler::set_breakpoints(
			(int) $request->get_param( 'tablet' ),
			(int) $request->get_param( 'mobile' )
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response(
			array(
				'breakpoints' => $saved,
				'defaults'    => Extensions_Handler::default_breakpoints(),
			)
		);
	}

	public function permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				sprintf(
					/* translators: %s: plugin name. */
					__( 'You do not have permission to manage %s extensions.', 'blocklane' ),
					Branding::plugin_name()
				),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function list_extensions( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'enabled' => Extensions_Handler::get_instance()->get_enabled_extensions(),
			)
		);
	}

	/**
	 * Toggle one extension. A write the store did not take is refused (500)
	 * rather than answered with the unchanged map — the client's optimistic
	 * state must never outlive a write that never landed.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_extension( \WP_REST_Request $request ) {
		$slug    = (string) $request->get_param( 'slug' );
		$enabled = (bool) $request->get_param( 'enabled' );

		// Whitelist against the canonical PHP-defined slug list so a rogue
		// value in wp_options can never round-trip through the API.
		if ( ! Extensions_Handler::is_known_slug( $slug ) ) {
			return new \WP_Error(
				'blocklane_pro_unknown_extension',
				/* translators: %s: extension slug */
				sprintf( __( 'Unknown extension: %s', 'blocklane' ), $slug ),
				array( 'status' => 400 )
			);
		}

		$written = Extensions_Handler::get_instance()->set_extension_enabled( $slug, $enabled );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		return rest_ensure_response(
			array(
				'enabled' => Extensions_Handler::get_instance()->get_enabled_extensions(),
			)
		);
	}
}
