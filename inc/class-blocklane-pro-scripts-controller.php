<?php
/**
 * Scripts REST controller: GET/POST blocklane-pro/v1/scripts.
 *
 * Manages custom header/body/footer code stored in the blocklane_pro_scripts
 * option (see the Scripts class). The code is stored + output RAW by design (it's <script>/meta),
 * so there is no output sanitization — the entire safety model is this permission
 * gate on who may save: manage_options AND unfiltered_html (super-admin-only on
 * multisite). Do NOT add sanitize/kses here; it would break legitimate scripts.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scripts_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/scripts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scripts' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_scripts' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	public function permission_check() {
		// Writing arbitrary front-end code: require general admin AND the native
		// unfiltered-markup capability (super-admin-only on multisite).
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage custom scripts.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function get_scripts( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response( Scripts::get_settings() );
	}

	public function save_scripts( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		// Each section arrives as { enabled, code }. Raw by design — see the class
		// docblock. Gated by permission_check().
		$section = static function ( $value ) {
			if ( ! is_array( $value ) ) {
				return array( 'enabled' => true, 'code' => (string) $value );
			}
			return array(
				'enabled' => ! isset( $value['enabled'] ) || ! empty( $value['enabled'] ),
				'code'    => isset( $value['code'] ) ? (string) $value['code'] : '',
			);
		};

		$result = Scripts::save(
			array(
				'enabled' => ! empty( $params['enabled'] ),
				'header'  => $section( isset( $params['header'] ) ? $params['header'] : '' ),
				'body'    => $section( isset( $params['body'] ) ? $params['body'] : '' ),
				'footer'  => $section( isset( $params['footer'] ) ? $params['footer'] : '' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return what's actually stored (read back from the option).
		return rest_ensure_response( Scripts::get_settings() );
	}
}
