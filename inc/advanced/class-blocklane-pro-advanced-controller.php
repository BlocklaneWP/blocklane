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

	/**
	 * The reorder plan each request's permission callback authorized, keyed
	 * by the request object: the callback writes this map and nothing else
	 * (spec 2026-10-06-reorder-locked-rows, review F03). Weak, so a request
	 * that never reaches its callback leaves nothing behind.
	 *
	 * @var \WeakMap<\WP_REST_Request, array<int, int>>|null
	 */
	private ?\WeakMap $reorder_plans = null;

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

	/**
	 * The Advanced settings, EDITION-SHAPED: `settings` holds exactly the keys
	 * this build owns (Advanced::known() — DEFAULTS minus the toggles of absent units: 19 under free, 24 under Pro today), never
	 * a key for a unit this edition does not carry. That is a contract the
	 * client depends on: it PUTs this object back whole on every save, and
	 * save() refuses a payload naming an absent unit's key (#844, #969). The
	 * save response below is the same shape, and the client adopts it whole.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
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
	 * manual ordering, the user must be able to edit others' items of it and
	 * the re-parented item, and the request's plan must exist: every row it
	 * writes is one the user can edit_post, and rows they cannot are fixed
	 * points it numbers around (Advanced::reorder_plan(), which refuses with
	 * a 403 naming the row when the order needs one of those to move). The
	 * plan is kept for the callback, which writes exactly it.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function reorder_permission( \WP_REST_Request $request ): bool|\WP_Error {
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
		if ( $moved && ! current_user_can( 'edit_post', $moved ) ) {
			return false;
		}
		$plan = Advanced::reorder_plan( $post_type, array_map( 'absint', (array) $request->get_param( 'order' ) ), $moved, absint( $request->get_param( 'parent' ) ) );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$this->reorder_plans ??= new \WeakMap();
		$this->reorder_plans[ $request ] = $plan;
		return true;
	}

	/**
	 * Re-parent (when asked), then write the plan reorder_permission()
	 * authorized for THIS request — never a plan recomputed after
	 * save_parent()'s wp_update_post() has run other code.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reorder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$plan = $this->reorder_plans[ $request ] ?? null;
		if ( null === $plan ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		unset( $this->reorder_plans[ $request ] );

		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		$moved     = absint( $request->get_param( 'moved' ) );

		if ( $moved ) {
			$result = Advanced::save_parent( $post_type, $moved, absint( $request->get_param( 'parent' ) ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$count = Advanced::write_order( $plan );

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
