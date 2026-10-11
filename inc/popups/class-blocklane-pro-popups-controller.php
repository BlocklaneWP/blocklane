<?php
/**
 * Popups REST controller: the read-only popup list behind the block editor's
 * popup-binding picker (docs/specs/popup-bindings.md).
 *
 * The route admits users who can edit posts, and lists to each of them only
 * the popups they may read, by core's REST read rule
 * (blocklane_pro\Post_Access, spec 2026-10-10 wordpress.org review round 2,
 * §3.3): published popups to everyone it admits, and every status to a user
 * who can read_post a popup, which the CPT maps to manage_options. Each row
 * carries the popup's ID, title, status, its trigger and a summary of how it
 * opens; the trigger and the summary come from the popup's settings. A
 * published popup's title and trigger are what any Contributor, Author or
 * Editor sees, as /wp/v2/blocklane_popup already shows them. Plugin-only on
 * purpose: the picker dies with the plugin while existing bindings keep
 * rendering from the bake (popups.md: editor/REST surfaces stay plugin-only).
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

	/**
	 * Every popup the caller may read, for the binding panel's picker.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Unused.
	 */
	public function list_popups( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		// The runtime registers the CPT only when the whole popups gate chain
		// passes (safe mode, then the Advanced toggle — the license is not a
		// runtime gate anywhere in this plugin; see the License class
		// docblock) — checking the registration outcome keeps this route on
		// one source of truth instead of re-deriving the gates. Literal key:
		// the runtime's constant is only defined when the gates pass.
		if ( ! post_type_exists( 'blocklane_popup' ) ) {
			return new \WP_REST_Response( array() );
		}

		// Bindable ahead of publish (the render side ignores non-publish):
		// drafts, scheduled (future), pending, and private popups belong in
		// the picker of a user who can read them, else an existing binding
		// to one is mislabeled "not found". Post_Access narrows the statuses
		// to what the caller may list before the query, so the 100-row UI
		// ceiling counts only rows the caller sees, and then holds every row
		// to the read rule. The footer render has its own caps. Meta cache
		// stays primed (one query) because every row reads the settings meta
		// below.
		$posts = Post_Access::listable(
			array(
				'post_type'              => 'blocklane_popup',
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			)
		);

		if ( is_wp_error( $posts ) ) {
			return rest_convert_error_to_response( $posts );
		}

		$items = array();
		foreach ( $posts as $post ) {
			// The trigger rides along so the binding panel can say how the
			// popup opens besides the binding ("also opens automatically…")
			// — not sensitive, and it powers the Make click-only hint. The
			// settings helper lives in the runtime; post_type_exists above
			// guarantees it loaded.
			$settings = blocklane_pro_popups_settings( $post->ID );

			// `opens` is the SAYING half, derived from the editor option rows
			// (registry 3) rather than from a switch in the panel's JS: the
			// panel lives outside inc/popups/ and cannot read the vocabulary
			// without becoming a fourth copy of it (#1027). `trigger` stays
			// for the Make click-only flow, which WRITES a base value, and
			// for an older cached bundle. An empty summary means nothing
			// opens the popup by itself — `manual`, or a trigger this edition
			// has no arm for, for which "click only" is the true sentence.
			$summary = blocklane_pro_popups_trigger_summary( $settings['trigger'] );

			$items[] = array(
				'id'      => $post->ID,
				'title'   => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'blocklane' ),
				'status'  => $post->post_status,
				'trigger' => $settings['trigger'],
				'opens'   => array(
					'auto'    => '' !== $summary,
					'summary' => $summary,
				),
			);
		}

		return new \WP_REST_Response( $items );
	}
}
