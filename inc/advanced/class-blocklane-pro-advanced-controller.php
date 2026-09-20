<?php
/**
 * Advanced REST controller: GET/POST blocklane-pro/v1/advanced.
 *
 * GET returns the settings; POST saves them. Gated on manage_options.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Advanced_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/advanced',
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
				),
			)
		);

		// Reorder is a content action (used on the edit screen by editors), so it's
		// gated on the post type's edit_others_posts cap, not manage_options.
		register_rest_route(
			Branding::rest_namespace(),
			'/advanced/reorder',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => array( $this, 'reorder_permission' ),
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'order'     => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
					// Optional re-parent (hierarchical types): 'moved' changed its
					// parent to 'parent' (0 = top level) in the same operation.
					'moved'     => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'parent'    => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Replace media: accepts a multipart file for an attachment the user can
		// edit (when the feature is on). Gated on edit_post, not manage_options.
		register_rest_route(
			Branding::rest_namespace(),
			'/advanced/replace-media',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'replace_media' ),
				'permission_callback' => array( $this, 'replace_media_permission' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage these settings.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function get_settings( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'settings'  => Advanced::get(),
				'postTypes' => Advanced::eligible_post_types(),
			)
		);
	}

	public function save_settings( \WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		$saved    = Advanced::save( is_array( $settings ) ? $settings : array() );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response(
			array(
				'settings'  => Advanced::get(),
				'postTypes' => Advanced::eligible_post_types(),
			)
		);
	}

	/**
	 * Permission for the reorder endpoint: the post type must be enabled for
	 * manual ordering and the user must be able to edit others' items of it.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function reorder_permission( \WP_REST_Request $request ) {
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		if ( ! Advanced::is_reorder_type( $post_type ) ) {
			return false;
		}
		$type = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->edit_others_posts ) ) {
			return false;
		}
		// Re-parenting edits one specific post — require that cap too.
		$moved = absint( $request->get_param( 'moved' ) );
		return ! $moved || current_user_can( 'edit_post', $moved );
	}

	public function reorder( \WP_REST_Request $request ) {
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		$order     = (array) $request->get_param( 'order' );
		$moved     = absint( $request->get_param( 'moved' ) );

		if ( $moved ) {
			$result = Advanced::save_parent( $post_type, $moved, absint( $request->get_param( 'parent' ) ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$count = Advanced::save_order( $post_type, $order );

		return rest_ensure_response( array( 'reordered' => $count ) );
	}

	/**
	 * Permission for replace-media: the feature is on and the user can edit the
	 * target attachment.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function replace_media_permission( \WP_REST_Request $request ) {
		if ( ! Advanced::is_on( 'media-replacement' ) ) {
			return false;
		}
		$id = absint( $request->get_param( 'id' ) );
		// The target must be an actual attachment (not any post the user can edit),
		// and pushing new bytes into uploads is an upload — require upload_files.
		// Without these, a Contributor could write allowed-mime files into a public
		// uploads path via their own draft's ID.
		return $id
			&& 'attachment' === get_post_type( $id )
			&& current_user_can( 'upload_files' )
			&& current_user_can( 'edit_post', $id );
	}

	public function replace_media( \WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'blocklane_pro_no_file', __( 'No file was received.', 'blocklane' ), array( 'status' => 400 ) );
		}

		$result = Advanced::get_instance()->replace_media_file( $id, $files['file'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
