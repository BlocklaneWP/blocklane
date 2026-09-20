<?php
/**
 * Popups REST controller: the read-only popup list behind the block editor's
 * popup-binding picker (docs/specs/popup-bindings.md).
 *
 * The CPT maps every capability to manage_options (popups are site chrome),
 * so core REST rejects the editors who actually wire landing-page buttons.
 * This route leaks only IDs, titles, and statuses — never content or settings
 * — and only to logged-in users who can edit posts. Plugin-only on purpose:
 * the picker dies with the plugin while existing bindings keep rendering from
 * the bake (popups.md: editor/REST surfaces stay plugin-only).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Popups_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/popups',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_popups' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	public function permission_check() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to list popups.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function list_popups( \WP_REST_Request $request ) {
		unset( $request );

		// The runtime registers the CPT only when the whole popups gate chain
		// passes (safe mode, then the Advanced toggle — the license is not a
		// runtime gate anywhere in this plugin; see the License class
		// docblock) — checking the registration outcome keeps this route on
		// one source of truth instead of re-deriving the gates. Literal key:
		// the runtime's constant is only defined when the gates pass.
		if ( ! post_type_exists( 'blocklane_popup' ) ) {
			return rest_ensure_response( array() );
		}

		// Bindable ahead of publish (the render side ignores non-publish):
		// drafts, scheduled (future), pending, and private popups all belong
		// in the picker, else an existing binding to one is mislabeled "not
		// found". 100 is a UI ceiling for the picker, not a data contract —
		// the footer render has its own caps. Meta cache stays primed (one
		// query) because every row reads the settings meta below.
		$posts = get_posts(
			array(
				'post_type'              => 'blocklane_popup',
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			// The trigger rides along so the binding panel can say how the
			// popup opens besides the binding ("also opens automatically…")
			// — not sensitive, and it powers the Make click-only hint. The
			// settings helper lives in the runtime; post_type_exists above
			// guarantees it loaded.
			$settings = blocklane_pro_popups_settings( $post->ID );

			$items[] = array(
				'id'      => $post->ID,
				'title'   => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'blocklane' ),
				'status'  => $post->post_status,
				'trigger' => $settings['trigger'],
			);
		}

		return rest_ensure_response( $items );
	}
}
