<?php
/**
 * Site Lock REST controller: GET/POST blocklane-pro/v1/site-lock.
 *
 * GET returns the current setting — whether a password is set and whether it
 * can be revealed, but never the cleartext itself. The cleartext is served
 * only by the separate GET /site-lock/reveal route, on demand, when the admin
 * clicks the reveal control. POST saves. Everything gated on manage_options.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Lock_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/site-lock',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_setting' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_setting' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'enabled'            => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'mode'               => array(
							'type'    => 'string',
							'enum'    => array( Site_Lock::MODE_COMING_SOON, Site_Lock::MODE_MAINTENANCE ),
							'default' => Site_Lock::MODE_COMING_SOON,
						),
						// No sanitize_callback: compared/stored as a hash, never echoed.
						'password'           => array(
							'type'    => 'string',
							'default' => '',
						),
						'clear_password'     => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'regenerate_preview' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						// No default: absent means "leave blog_public untouched".
						'discourage_search'  => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		// Reveal-on-demand: the cleartext password leaves the server only when an
		// admin explicitly asks for it, instead of riding along on every settings
		// read (shrinks exposure in logs, dev tools, and shoulder-surfing range).
		register_rest_route(
			Branding::rest_namespace(),
			'/site-lock/reveal',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'reveal_password' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);
	}

	/**
	 * The cleartext site password, for the admin reveal control. Empty for a
	 * pre-upgrade (legacy) password that can't be decrypted.
	 *
	 * @return \WP_REST_Response
	 */
	public function reveal_password() {
		return rest_ensure_response( array( 'password' => Site_Lock::reveal() ) );
	}

	public function permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage site visibility.', 'blocklane' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function get_setting( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response( $this->payload() );
	}

	public function save_setting( \WP_REST_Request $request ) {
		// Coming Soon can't be "on" without a password — the store coerces that
		// invariant (enabled → off when no password remains), so a save that removes
		// the password simply returns the site to public rather than erroring.
		$saved = Site_Lock::save(
			array(
				'enabled'            => (bool) $request->get_param( 'enabled' ),
				'mode'               => (string) $request->get_param( 'mode' ),
				'password'           => $request->get_param( 'password' ),
				'clear_password'     => (bool) $request->get_param( 'clear_password' ),
				'regenerate_preview' => (bool) $request->get_param( 'regenerate_preview' ),
			)
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// Mirror WordPress core's "Discourage search engines" setting (the
		// blog_public option, shared with Settings → Reading): 1 = indexable,
		// 0 = discouraged. Only touched when the field is actually sent, so other
		// saves that reuse this endpoint (e.g. regenerating the preview link) don't
		// silently reset it.
		$discourage = $request->get_param( 'discourage_search' );
		if ( null !== $discourage ) {
			update_option( 'blog_public', $discourage ? 0 : 1 );
		}

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * The setting for the UI. Deliberately excludes the cleartext password —
	 * the UI fetches that on demand from /site-lock/reveal when the admin
	 * clicks the eye. canReveal distinguishes a revealable password from a
	 * pre-upgrade (legacy) hash that can't be decrypted (hasPassword stays
	 * true for both).
	 *
	 * @return array
	 */
	private function payload() {
		$preview_key = Site_Lock::preview_key();

		return array(
			'enabled'          => Site_Lock::is_enabled(),
			'mode'             => Site_Lock::mode(),
			'hasPassword'      => Site_Lock::has_password(),
			'canReveal'        => '' !== Site_Lock::reveal(),
			// Core "Discourage search engines" checkbox (blog_public), surfaced so
			// this screen and Settings → Reading share one value. True = discouraged.
			'discourageSearch' => 0 === (int) get_option( 'blog_public', 1 ),
			// Shareable preview link — unlocks Coming Soon without the password.
			// Admin-only (this endpoint is manage_options); regenerate to revoke.
			'previewUrl'       => '' !== $preview_key
				? add_query_arg( Site_Lock_Gate::PREVIEW_PARAM, $preview_key, home_url( '/' ) )
				: '',
			// Deep-link straight to each splash template in the Site Editor, keyed
			// by mode so the UI links to whichever one is selected.
			'editUrls'         => array(
				Site_Lock::MODE_COMING_SOON => $this->template_edit_url( 'coming-soon' ),
				Site_Lock::MODE_MAINTENANCE => $this->template_edit_url( 'maintenance' ),
			),
		);
	}

	/**
	 * Site Editor edit link for a splash template. Prefers the author's
	 * customization (stored under the active theme's namespace) and falls back to
	 * the plugin-registered default — mirroring how the gate resolves it.
	 *
	 * @param string $slug 'coming-soon' | 'maintenance'.
	 * @return string
	 */
	private function template_edit_url( $slug ) {
		$id = 'blocklane-pro//' . $slug;

		if ( function_exists( 'get_block_template' ) ) {
			$custom = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
			if ( $custom && ! empty( $custom->content ) ) {
				$id = get_stylesheet() . '//' . $slug;
			}
		}

		return admin_url(
			'site-editor.php?' . http_build_query(
				array(
					'postType' => 'wp_template',
					'postId'   => $id,
					'canvas'   => 'edit',
				)
			)
		);
	}
}
