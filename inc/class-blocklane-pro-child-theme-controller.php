<?php
/**
 * Child Theme REST controller: POST blocklane-pro/v1/create-child-theme.
 *
 * Generates + activates a child theme of the active parent (blocklane). Gated to
 * manage_options; input is sanitized here before reaching the generator.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Child_Theme_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/create-child-theme',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'permission_check' ),
					// Declarative types; the callback re-sanitizes each field on
					// read (sanitize_text_field / esc_url_raw), which stays the
					// authoritative pass.
					'args'                => array(
						'themeName'            => array( 'type' => 'string' ),
						'themeUrl'             => array( 'type' => 'string' ),
						'description'          => array( 'type' => 'string' ),
						'author'               => array( 'type' => 'string' ),
						'authorUrl'            => array( 'type' => 'string' ),
						'version'              => array( 'type' => 'string' ),
						'textDomain'           => array( 'type' => 'string' ),
						'importCustomizations' => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/child-theme/customizations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'customizations' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	/**
	 * Summary of the active theme's importable Site Editor customizations, so the
	 * UI can offer (and describe) the "import into the child" option.
	 */
	public function customizations( \WP_REST_Request $request ) {
		unset( $request );


		$source               = Child_Theme_Import::resolve_source();
		$summary              = Child_Theme_Import::summary( $source );
		$summary['themeName'] = wp_get_theme( $source )->get( 'Name' );

		return rest_ensure_response( $summary );
	}

	public function permission_check() {
		// Creating theme files + activating a theme is an install-class action:
		// require manage_options + switch_themes AND install_themes. switch_themes
		// is NOT network-gated on multisite (site admins hold it), but install_themes
		// is super-admin-only there and also honors DISALLOW_FILE_MODS.
		if ( ! current_user_can( 'manage_options' )
			|| ! current_user_can( 'switch_themes' )
			|| ! current_user_can( 'install_themes' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to create a child theme.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function create( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$theme_name = isset( $params['themeName'] ) ? sanitize_text_field( $params['themeName'] ) : '';
		if ( '' === $theme_name ) {
			// Blank falls back to "{Parent} Child" — the same value the form
			// shows as the field's placeholder.
			$theme_name = wp_get_theme( get_template() )->get( 'Name' ) . ' Child';
		}

		$data = array(
			'themeName'   => $theme_name,
			'themeUrl'    => isset( $params['themeUrl'] ) ? esc_url_raw( $params['themeUrl'] ) : '',
			'description' => isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : '',
			'author'      => isset( $params['author'] ) ? sanitize_text_field( $params['author'] ) : '',
			'authorUrl'   => isset( $params['authorUrl'] ) ? esc_url_raw( $params['authorUrl'] ) : '',
			'version'     => isset( $params['version'] ) ? sanitize_text_field( $params['version'] ) : '1.0.0',
			'textDomain'  => isset( $params['textDomain'] ) ? sanitize_text_field( $params['textDomain'] ) : '',
			'importCustomizations' => ! empty( $params['importCustomizations'] ),
		);

		$result = Helper::create_child_theme( $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'status'   => 200,
				'slug'     => ! empty( $data['textDomain'] ) ? sanitize_title( $data['textDomain'] ) : sanitize_title( $data['themeName'] ),
				'message'  => __( 'Child theme created and activated.', 'blocklane' ),
				'imported' => is_array( $result ) ? $result['imported'] : null,
				// The style.css header values actually written — blank optional
				// fields resolve server-side, so the UI summary reads from here.
				'headers'  => is_array( $result ) && isset( $result['headers'] ) ? $result['headers'] : null,
			)
		);
	}
}
