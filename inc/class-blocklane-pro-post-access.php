<?php
/**
 * Post_Access — the one per-object read rule for posts a route lists to a
 * caller (spec 2026-10-10 wordpress.org review round 2, §3.3).
 *
 * wordpress.org's reviewer refused a list route that checked a type-level
 * capability (edit_posts) and then returned every popup, drafts and private
 * ones included, to any user who had it. Round 1 had authorized each object
 * the reorder route WRITES; nothing authorized each object a route READS for
 * a caller below the objects' own capability. Every object such a route
 * carries passes this class.
 *
 * MIRROR CONTRACT: can_list() is the generic posts controller's read rule,
 * the one /wp/v2/<type> applies to this type today —
 * WP_REST_Posts_Controller::check_read_permission()
 * (wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php:1784–1817,
 * WordPress 7.1) — without its show_in_rest test, which says whether the
 * REST API serves a type at all, not whether a user may read an object of
 * it. The review battery's WA5 compares the two over every status, role and
 * type it plants. If /wp/v2/blocklane_popup is ever narrowed to read_post
 * for every status (the shape of core's blocks controller), this rule and
 * the popup picker that reads it must move with it: the two are one decision.
 *
 * listable()'s status narrowing mirrors
 * WP_REST_Posts_Controller::sanitize_post_statuses() (:3193–3222): without
 * the type's edit_posts, a caller lists published posts, plus private ones
 * with read_private_posts. It runs BEFORE the query, so a row cap counts only
 * rows the caller may see.
 *
 * Outside this rule on purpose: Advanced::refusal_name(), which names a row
 * the reorder route refused by the admin list table's rule (the list table
 * shows non-private drafts to anyone who reaches the screen); WR10/WR11 pin
 * that rule.
 *
 * No state and no hooks.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Access {

	/**
	 * Whether the current user may see this post in a list: core's REST read
	 * rule (see the class docblock).
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function can_list( \WP_Post $post ): bool {
		if ( 'publish' === $post->post_status || current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}
		$status = get_post_status_object( $post->post_status );
		if ( $status && $status->public ) {
			return true;
		}
		if ( 'inherit' === $post->post_status && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );
			if ( $parent instanceof \WP_Post ) {
				return self::can_list( $parent );
			}
		}
		// An inherit post with no parent counts as published, as in
		// get_post_status().
		return 'inherit' === $post->post_status;
	}

	/**
	 * The one way a route lists posts to a caller: get_posts() with the
	 * statuses narrowed to what the caller may list, then every result held
	 * to can_list().
	 *
	 * @param array<string, mixed> $args get_posts() arguments. `post_type` must name one
	 *                                    registered type; `post_status` defaults to publish;
	 *                                    `fields` is ignored (whole posts are judged).
	 * @return list<\WP_Post>|\WP_Error The listable posts, or blocklane_pro_post_access_type.
	 */
	public static function listable( array $args ): array|\WP_Error {
		$type   = $args['post_type'] ?? null;
		$object = is_string( $type ) ? get_post_type_object( $type ) : null;
		if ( ! $object instanceof \WP_Post_Type ) {
			return new \WP_Error( 'blocklane_pro_post_access_type', 'Post_Access::listable() lists one registered post type at a time.' );
		}
		$statuses = array_values( array_map( 'strval', (array) ( $args['post_status'] ?? array( 'publish' ) ) ) );
		if ( ! current_user_can( $object->cap->edit_posts ) ) {
			$private  = current_user_can( $object->cap->read_private_posts );
			$statuses = array_values( array_filter( $statuses, static fn( string $status ): bool => 'publish' === $status || ( 'private' === $status && $private ) ) );
		}
		if ( array() === $statuses ) {
			return array();
		}
		$args['post_status'] = $statuses;
		unset( $args['fields'] );
		$listable = array();
		foreach ( get_posts( $args ) as $post ) {
			if ( $post instanceof \WP_Post && self::can_list( $post ) ) {
				$listable[] = $post;
			}
		}
		return $listable;
	}
}
