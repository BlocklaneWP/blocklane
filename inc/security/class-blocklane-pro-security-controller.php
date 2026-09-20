<?php
/**
 * Security REST controller: GET/POST blocklane-pro/v1/security.
 *
 * GET returns the hardening toggles; POST saves them. Gated on manage_options.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Security_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/security',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'settings' => array(
							'type'                 => 'object',
							'description'          => __( 'Map of security toggle slug to enabled state.', 'blocklane' ),
							'additionalProperties' => array( 'type' => 'boolean' ),
						),
					),
				),
			)
		);
	}

	public function permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage security settings.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function get_settings( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'settings' => Security::get(),
				'forced'   => Security::forced(),
			)
		);
	}

	public function save_settings( \WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		$saved    = Security::save( is_array( $settings ) ? $settings : array() );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response(
			array(
				'settings' => Security::get(),
				'forced'   => Security::forced(),
			)
		);
	}
}
