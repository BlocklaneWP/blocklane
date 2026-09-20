<?php
/**
 * Disable Comments enforcer.
 *
 * Turns the PUBLIC comment system off site-wide the way core would if it
 * shipped a single switch for it: entirely runtime filters + actions, nothing
 * destructive. Existing comments stay in the database (just hidden) and every
 * effect reverts the moment the toggle is switched back off.
 *
 * What stays on: WordPress 7.1 Notes. Core stores editorial notes as comments
 * (`comment_type = 'note'`) and serves them through the same REST routes, but
 * a note is an authenticated editorial surface gated on `edit_post` — never
 * rendered on the front end, never reachable logged-out — not the spam and
 * enumeration surface this switch exists to close. So the comment routes stay
 * registered and are constrained to notes (#191): a request that is not about
 * a note is refused before the route's permission callback runs, and the
 * collection query is pinned to notes as the second door.
 *
 * Registered by Advanced::apply() only when the `disable-comments` toggle is on.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comments_Disabler {

	/** Core blocks that render the comment UI on the front end. */
	const COMMENT_BLOCKS = array(
		'core/comments',
		'core/post-comments', // Legacy alias.
		'core/post-comments-form',
		'core/latest-comments', // Queries comments independently — blank it too.
	);

	public function __construct() {
		// Front end: nothing is open, nothing renders.
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'comments_array', '__return_empty_array', 20 );
		add_filter( 'get_comments_number', '__return_zero', 20 );
		add_filter( 'render_block', array( $this, 'hide_comment_blocks' ), 10, 2 );

		// Feeds: stop advertising the comment feed and 404 the endpoint.
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_action( 'template_redirect', array( $this, 'block_comment_feeds' ), 9 );

		// REST: keep the comment routes for Notes; refuse every request that is
		// not about a note (the spam + enumeration surface), and pin the
		// collection query to notes so no other type can be listed.
		add_filter( 'rest_request_before_callbacks', array( $this, 'refuse_non_note_requests' ), 10, 3 );
		add_filter( 'rest_comment_query', array( $this, 'pin_query_to_notes' ), 10, 2 );

		// Post types: remove comment/trackback support (metabox, column, quick-edit).
		add_action( 'init', array( $this, 'remove_comment_support' ), 100 );

		// Admin chrome: the Comments menu, dashboard widget, and admin-bar bubble.
		add_action( 'admin_menu', array( $this, 'remove_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'redirect_comment_admin_pages' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widget' ) );
		add_action( 'admin_bar_menu', array( $this, 'remove_admin_bar_node' ), 999 );

		// Discussion settings page: hide the now-inert comment settings + explain.
		add_action( 'admin_head-options-discussion.php', array( $this, 'hide_discussion_comment_settings' ) );
		add_action( 'admin_notices', array( $this, 'discussion_comment_notice' ) );
	}

	/**
	 * Blank out the core comment blocks so a template that includes them renders
	 * nothing. (`comments_open` being false already refuses new submissions.)
	 *
	 * @param string $content Rendered block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function hide_comment_blocks( $content, $block ) {
		$name = isset( $block['blockName'] ) ? $block['blockName'] : '';

		return in_array( $name, self::COMMENT_BLOCKS, true ) ? '' : $content;
	}

	/**
	 * Serve a 404 for comment-feed requests instead of the feed.
	 */
	public function block_comment_feeds() {
		if ( ! is_comment_feed() ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Refuse any comment-route request that is not about a note, before the
	 * route's own permission callback runs. Core then applies its note rules
	 * (a logged-in user with `edit_post` on the note's post, a post type whose
	 * editor support carries `notes`) to what is left, so nothing here widens
	 * what a note request could do before the switch existed.
	 *
	 * The collection route is a note request only when `type=note` is asked
	 * for explicitly — core's default is `comment`, which is exactly the
	 * enumeration this switch closes. The item routes are note requests only
	 * when the addressed comment IS a note. Everything else gets the same 404
	 * the removed route used to give, so a probe cannot tell the switch from
	 * an absent route.
	 *
	 * @param mixed                $response Result to send instead of the route's, or null.
	 * @param array<string, mixed> $handler  Route handler.
	 * @param \WP_REST_Request     $request  Request.
	 * @return mixed
	 */
	public function refuse_non_note_requests( mixed $response, array $handler, \WP_REST_Request $request ): mixed {
		if ( null !== $response ) {
			return $response; // An earlier filter already decided.
		}

		$route = $request->get_route();

		if ( '/wp/v2/comments' === $route ) {
			return 'note' === $request['type'] ? $response : self::no_route_error();
		}

		if ( 1 === preg_match( '#^/wp/v2/comments/(\d+)$#', $route, $m ) ) {
			$comment = get_comment( (int) $m[1] );

			return ( $comment instanceof \WP_Comment && 'note' === $comment->comment_type )
				? $response
				: self::no_route_error();
		}

		return $response;
	}

	/**
	 * Pin every comment collection query to notes. The guard above already
	 * refused non-note requests; this closes the second door — a query that
	 * reaches WP_Comment_Query with any other type while the switch is on.
	 *
	 * @param array<string, mixed> $args    WP_Comment_Query arguments.
	 * @param \WP_REST_Request     $request Request.
	 * @return array<string, mixed>
	 */
	public function pin_query_to_notes( array $args, \WP_REST_Request $request ): array {
		$args['type'] = 'note';

		return $args;
	}

	/**
	 * The 404 core gives for an unregistered route — the response the removed
	 * comment routes produced before #191, kept identical on purpose.
	 */
	private static function no_route_error(): \WP_Error {
		return new \WP_Error(
			'rest_no_route',
			__( 'No route was found matching the URL and request method.' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string, core's domain.
			array( 'status' => 404 )
		);
	}

	/**
	 * Drop comment + trackback support from every post type. This alone removes
	 * the Discussion metabox, the Comments list-table column, and the quick-edit
	 * comment toggle — no per-screen patching required.
	 *
	 * Notes are unaffected: core gates them on the `editor` support's `notes`
	 * flag (WP_REST_Comments_Controller::check_post_type_supports_notes), never
	 * on `comments` support.
	 */
	public function remove_comment_support() {
		foreach ( get_post_types() as $type ) {
			if ( post_type_supports( $type, 'comments' ) ) {
				remove_post_type_support( $type, 'comments' );
			}
			if ( post_type_supports( $type, 'trackbacks' ) ) {
				remove_post_type_support( $type, 'trackbacks' );
			}
		}
	}

	public function remove_admin_menu() {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Bounce direct navigation to the comment admin screens back to the dashboard.
	 */
	public function redirect_comment_admin_pages() {
		global $pagenow;

		if ( 'edit-comments.php' === $pagenow || 'comment.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	public function remove_dashboard_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function remove_admin_bar_node( $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'comments' );
	}

	/**
	 * Hide the comment settings on the Discussion page while comments are off —
	 * every control there (default post settings, other comment settings, comment
	 * pagination, notifications, moderation, disallowed keys) is inert. Core keeps
	 * them all in one form-table, keyed by the default_comment_status control, so
	 * one rule covers the lot; the Avatars table (its own use) stays. Prints only
	 * on this screen. `:has()` degrades gracefully where unsupported.
	 */
	public function hide_discussion_comment_settings() {
		echo '<style>table.form-table:has(#default_comment_status){display:none}</style>' . "\n";
	}

	/**
	 * Explain on the Discussion screen why those settings are gone, so it reads as
	 * intentional rather than broken.
	 */
	public function discussion_comment_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'options-discussion' !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: plugin name. */
					__( 'Comments are turned off site-wide by %s, so the comment settings below are hidden and inactive.', 'blocklane' ),
					Branding::plugin_name()
				)
			),
			esc_url( admin_url( 'admin.php?page=' . Branding::MENU_SLUG . '&screen=advanced' ) ),
			esc_html(
				sprintf(
					/* translators: %s: plugin name. */
					__( 'Manage in %s → Advanced', 'blocklane' ),
					Branding::plugin_name()
				)
			)
		);
	}
}
