<?php
/**
 * Advanced: opt-in admin & content enhancements that integrate through native
 * WordPress surfaces (list-table columns, row actions, the media uploader, the
 * login page) so they feel exactly like core shipped them. The dashboard screen
 * is only the on/off panel; the features themselves appear in core admin.
 *
 * All off by default; hooks register only for enabled features. Each setting is
 * normalized by the type of its default (bool toggle, or int with clamping).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Advanced implements Bootable {

	const OPTION = 'blocklane_pro_advanced';

	/** User meta storing the last successful login (unix time). */
	const LAST_LOGIN_META = 'blocklane_pro_last_login';

	/** admin.php action for the Clone row action. */
	const DUPLICATE_ACTION = 'blocklane_pro_duplicate';

	/**
	 * Canonical settings + defaults. bool = toggle, int = number, array = slug list.
	 *
	 * child-theme-tool doesn't add a feature: it gates the Create Child Theme
	 * screen (menu item + REST routes). Off — the default, like everything
	 * here — means the theme-writing routes aren't registered at all; flip it
	 * on for the one-time generation, off again after. Generated child themes
	 * are never touched.
	 *
	 * The module-screen toggles (content-types, dynamic-values, scripts,
	 * site-privacy) are "surface" gates, default ON: off hides that module's
	 * dashboard screen and management REST routes ONLY — the front-end
	 * runtime keeps running (CPTs stay registered, bindings resolve, scripts
	 * keep printing, a lock stays enforced), so turning a screen off never
	 * touches live content or behavior. See Settings::advanced_tool_on().
	 */
	const DEFAULTS = array(
		'child-theme-tool'      => false,
		'popups'                => false,
		'content-types'         => true,
		'dynamic-values'        => true,
		'scripts'               => true,
		'site-privacy'          => true,
		// AI MCP abilities (WordPress Abilities API + MCP). Default OFF:
		// it grants an AI client write access to the site and needs the
		// WordPress "AI" plugin (Abilities API) plus the MCP Adapter, so it's
		// an opt-in that gates the whole abilities module's boot.
		'ai-mcp'          => false,
		// AI Tools: in-editor free-form rewrite (a content-rewrite ability +
		// a "Rewrite with AI" selection button). Default OFF; needs the
		// WordPress "AI" plugin and a connected provider.
		'ai-tools'        => false,
		// Basic SEO: per-post search title/description/schema/noindex plus
		// the front-end emission (meta description, Open Graph, JSON-LD).
		// Default OFF: sites running a dedicated SEO plugin shouldn't get a
		// second head-output surface without asking for it.
		'seo'                   => false,
		// Forms: the form-block suite (fields, submit, notifications) plus,
		// in later phases, the submission endpoint and inbox. Default OFF:
		// most sites already run a form plugin, and a second form surface
		// shouldn't appear without being asked for.
		'forms'                 => false,
		// Carousel: the carousel block suite (shell, track, slides,
		// controls). Default ON — it registers no REST or write path, most
		// sites want a slider, and off only stops NEW carousels from
		// registering; like Forms, existing saved markup degrades to
		// unregistered blocks.
		'carousel'              => true,
		'limit-revisions'       => false,
		'revisions-to-keep'     => 10,
		'last-login-column'     => false,
		'duplicate-content'     => false,
		'reorder-content'       => false,
		'reorder-post-types'    => array(),
		'safe-svg-upload'       => false,
		'media-replacement'     => false,
		'disable-comments'      => false,
		'disable-blog-features' => false,
		'limit-heartbeat'       => false,
		'heartbeat-interval'    => 60,
		// Opt-in DESTRUCTION (ships OFF): when ON, deleting the plugin also
		// removes every remaining Blocklane option, including the two the default
		// path keeps because they are the user's content — the uploaded SVG icons
		// and the Custom Scripts code. Read by uninstall.php.
		'clean-uninstall'       => false,
	);

	/** Post types never offered for manual ordering. */
	const REORDER_EXCLUDED = array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_face', 'wp_font_family' );

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->apply();
	}

	/**
	 * Normalized settings — every key this EDITION owns, cast by the type of
	 * its default. Keys the stored row holds for units this build does not
	 * carry are not returned: a reader never sees, edits or echoes a key it
	 * cannot write (that is what makes the dashboard's whole-object PUT safe
	 * to send back), and they survive in the row untouched (save()).
	 *
	 * @return array<string, bool|int|array<int, string>|string>
	 */
	public static function get(): array {
		return self::normalized( self::known() );
	}

	/**
	 * get()'s read, over a known() view the caller already derived: the
	 * stored row normalized to exactly the keys of $known. save() reads the
	 * row twice (the current values, then what it stored) through the ONE
	 * view it bound, instead of deriving the view again for each read.
	 *
	 * @param array<string, bool|int|array<int, string>|string> $known The known() view.
	 * @return array<string, bool|int|array<int, string>|string>
	 */
	private static function normalized( array $known ): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$out = array();
		foreach ( $known as $key => $default ) {
			if ( is_bool( $default ) ) {
				$out[ $key ] = isset( $stored[ $key ] ) ? (bool) $stored[ $key ] : $default;
			} elseif ( is_int( $default ) ) {
				$out[ $key ] = isset( $stored[ $key ] ) ? max( 0, min( 100, (int) $stored[ $key ] ) ) : $default;
			} elseif ( is_array( $default ) ) {
				$out[ $key ] = ( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) )
					? array_values( array_unique( array_map( 'sanitize_key', $stored[ $key ] ) ) )
					: $default;
			} else {
				$out[ $key ] = isset( $stored[ $key ] ) ? (string) $stored[ $key ] : $default;
			}
		}
		return $out;
	}

	/**
	 * The keys this EDITION owns — the one definition of "known" for this
	 * store, derived once per door: get() derives it for its one read, and
	 * save() derives it once and hands that bound view to every step (#1005).
	 *
	 * DEFAULTS is the SHIPPED table, identical in both editions: the schema Pro
	 * reads a free-written row through (a key free never authored falls to Pro's
	 * own default), and the table Content_Toggle mirrors. It is not what this
	 * build owns. Five of its toggles belong to units the free build does not
	 * carry, and every door that iterated the shipped table directly invented
	 * its own ownership or none: get() handed the free client Pro keys, the
	 * client PUT them back, the refusal rejected every free save (#969); the
	 * writer skipped them while the carry-through was told they were known and
	 * erased Pro's stored values (#968); the predicate that claimed to be the
	 * one answer had two of five callers (#970). One missing definition, four
	 * findings.
	 *
	 * get() reads this (through normalized()); save() binds it once, and the
	 * refusal takes its complement (absent()), both reads of the row
	 * normalize to it, the writer iterates it and with_foreign_keys() takes it
	 * as $known — an absent key is FOREIGN here by construction and rides
	 * through raw. A door that reads
	 * the DEFAULTS constant or calls Edition::toggle_unit() anywhere else is a
	 * PHPStan error, blocklane.chokepointMember (tools/phpstan-rules/rules.neon).
	 *
	 * Outside the mechanism, and pinned so nobody documents it wider: the RAW
	 * readers of the row are exactly the files bin/toggle-wiring-check.php's
	 * invariant 5 lists — Settings::advanced_stored() (the fallback for both
	 * toggle polarities when this class is not loaded), Modules::toggle_on_stored()
	 * (the boot gate's fallback), Content_Toggle::on() (the content runtimes'
	 * mirror of get()'s isset() semantics) and uninstall.php's final read. Each
	 * reads only and never writes; a fifth reader anywhere under inc/ fails
	 * that check by name (#1004). This class is final so the member rule's
	 * "referenced class" resolution cannot be walked around by a subclass
	 * reading DEFAULTS through its own name (#1006).
	 *
	 * Not memoized: Edition::data() already is, and a per-request cache is one
	 * more state a battery could leave stale. Computing it once per save() is
	 * a local variable, which outlives nothing.
	 *
	 * @return array<string, bool|int|array<int, string>|string> Key => shipped default, this edition's subset.
	 */
	private static function known(): array {
		$known = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$unit = Edition::toggle_unit( (string) $key );
			if ( null !== $unit && ! Edition::has( $unit ) ) {
				continue;
			}
			$known[ $key ] = $default;
		}
		return $known;
	}

	/**
	 * The complement of the known() view it is handed: shipped keys whose unit
	 * this edition does not carry. Empty in Pro. Read by save()'s refusal and
	 * nothing else.
	 *
	 * @param array<string, bool|int|array<int, string>|string> $known The known() view save() bound.
	 * @return array<string, bool|int|array<int, string>|string>
	 */
	private static function absent( array $known ): array {
		return array_diff_key( self::DEFAULTS, $known );
	}

	/**
	 * Persist from a request payload; values re-normalized. Unknown keys in the
	 * PAYLOAD are ignored; keys in the STORED row this edition does not own —
	 * another edition's, or a newer build's — are preserved verbatim
	 * (Helper::with_foreign_keys, handed known() as its template, so an absent
	 * unit's key is foreign by construction). Omitted keys keep their CURRENTLY
	 * STORED value (normalized to the view, as get() reads it) — never the shipped
	 * default: the REST route accepts partial maps, and filling absences from
	 * the shipped table let any partial payload silently reset every toggle
	 * the caller didn't mention.
	 *
	 * @param array<string, mixed> $settings The payload.
	 * @return array<string, bool|int|array<int, string>|string>|\WP_Error The normalized settings as stored, or the typed refusal (nothing written).
	 */
	public static function save( array $settings ): array|\WP_Error {
		// The view, once: every step below reads this binding (#1005).
		$known = self::known();

		// A key whose toggle belongs to a unit this edition does not carry is
		// refused before anything is written (#844). get() never hands one
		// out, so the only clients that send one are a stale Pro tab after
		// Pro left and a hand-built request; accepting it would store an ON
		// for a feature with no code behind it — honored, without anyone
		// opting in, the day Pro is installed. The whole request is refused
		// rather than the key ignored: a 200 would tell that stale tab its
		// Pro toggles saved (spec 2026-09-22 D1). In Pro, absent() is empty
		// and this loop is a no-op.
		foreach ( array_keys( self::absent( $known ) ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				return new \WP_Error(
					'blocklane_pro_absent_toggle',
					sprintf(
						/* translators: %s: the setting key. */
						__( 'The "%s" setting belongs to a feature this edition does not include.', 'blocklane' ),
						$key
					),
					array(
						'status' => 400,
						'toggle' => $key,
					)
				);
			}
		}

		$current = self::normalized( $known );

		// The writer authors exactly the keys this edition owns — $known, the
		// same view the read above used and the carry-through below is
		// handed. A key for a unit this build does not carry is never
		// authored here, from this build's copy of the other edition's
		// defaults or from anything else: on a site that never ran the other
		// edition it stays ABSENT, so that edition starts its feature at its
		// own default when it arrives; on a site that did, the stored value
		// rides through untouched.
		$next = array();
		foreach ( $known as $key => $default ) {
			if ( is_bool( $default ) ) {
				$next[ $key ] = isset( $settings[ $key ] ) ? (bool) $settings[ $key ] : $current[ $key ];
			} elseif ( is_int( $default ) ) {
				$next[ $key ] = isset( $settings[ $key ] ) ? max( 0, min( 100, (int) $settings[ $key ] ) ) : $current[ $key ];
			} elseif ( is_array( $default ) ) {
				$next[ $key ] = ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) )
					? array_values( array_unique( array_map( 'sanitize_key', $settings[ $key ] ) ) )
					: $current[ $key ];
			} else {
				$next[ $key ] = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : $current[ $key ];
			}
		}

		// Constrain reorder-post-types to types that are actually eligible for
		// manual ordering. sanitize_key above only guards the slug format, not that
		// the type exists and is orderable, so drop stale/removed/ineligible slugs.
		// Narrowed by type, not by truthiness: known() declares its values as
		// the union of every default's type, so the list must be asserted a
		// list here before array_intersect() can be typed over it.
		if ( isset( $next['reorder-post-types'] ) && is_array( $next['reorder-post-types'] ) && array() !== $next['reorder-post-types'] ) {
			$eligible                   = wp_list_pluck( self::eligible_post_types(), 'slug' );
			$next['reorder-post-types'] = array_values(
				array_intersect( $next['reorder-post-types'], $eligible )
			);
		}

		// Carry through every stored key this EDITION does not own — the
		// two-editions rule, shared by every store (Helper::with_foreign_keys
		// says why the RAW row and not self::get()). The template is known(),
		// never the shipped table: merge_foreign() keeps a raw key only when
		// it is absent from the template, so handing it the whole table made
		// the other edition's five keys "known" and erased their stored values
		// on every save (#968).
		$merged = Helper::with_foreign_keys( self::OPTION, $next, $known );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		update_option( self::OPTION, $merged );

		return self::normalized( $known );
	}

	public static function is_on( $key ) {
		$s = self::get();
		return ! empty( $s[ $key ] );
	}

	public static function get_int( $key ) {
		$s = self::get();
		return isset( $s[ $key ] ) ? (int) $s[ $key ] : 0;
	}

	/* ---- enforcement ------------------------------------------------------- */

	private function apply() {
		if ( self::is_on( 'limit-revisions' ) ) {
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 10, 2 );
		}

		if ( self::is_on( 'last-login-column' ) ) {
			add_action( 'wp_login', array( $this, 'record_last_login' ), 10, 2 );
			add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'users_column_content' ), 10, 3 );
		}

		if ( self::is_on( 'duplicate-content' ) ) {
			add_filter( 'post_row_actions', array( $this, 'duplicate_row_action' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'duplicate_row_action' ), 10, 2 );
			add_action( 'admin_action_' . self::DUPLICATE_ACTION, array( $this, 'handle_duplicate' ) );
		}

		if ( self::is_on( 'reorder-content' ) ) {
			add_action( 'load-edit.php', array( $this, 'setup_reorder_screen' ) );
			add_action( 'pre_get_posts', array( $this, 'apply_front_order' ) );
		}

		if ( self::is_on( 'safe-svg-upload' ) ) {
			require_once BLOCKLANE_PRO_PATH . '/inc/advanced/vendor/svg-sanitize/load.php';
			add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 4 );
			add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) );
			// The sideload path (media_sideload_image, importers, the REST
			// attachments controller's raw-body uploads) fires a DIFFERENT
			// prefilter hook, so without this an SVG that arrives that way would
			// be stored unsanitized.
			add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_svg_upload' ) );
			add_filter( 'wp_image_editors', array( $this, 'register_svg_editor' ) );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'svg_attachment_metadata' ), 10, 2 );
			add_filter( 'wp_get_attachment_metadata', array( $this, 'svg_attachment_metadata' ), 10, 2 );
			add_filter( 'wp_insert_attachment_data', array( $this, 'fix_svg_attachment_mime' ), 10, 2 );
			add_action( 'admin_head', array( $this, 'svg_admin_styles' ) );
		}

		if ( self::is_on( 'media-replacement' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_replace_assets' ) );
		}

		if ( self::is_on( 'disable-comments' ) ) {
			new Comments_Disabler();
		}

		if ( self::is_on( 'disable-blog-features' ) ) {
			new Blog_Features_Disabler();
		}

		if ( self::is_on( 'limit-heartbeat' ) ) {
			add_filter( 'heartbeat_settings', array( $this, 'heartbeat_interval' ) );
			add_action( 'init', array( $this, 'disable_frontend_heartbeat' ), 1 );
		}
	}

	/* ---- reorder content (drag menu_order) --------------------------------- */

	/** Post types the user enabled for manual ordering (empty if reorder is off). */
	public static function reorder_types() {
		if ( ! self::is_on( 'reorder-content' ) ) {
			return array();
		}
		$types = self::get()['reorder-post-types'];
		return is_array( $types ) ? $types : array();
	}

	public static function is_reorder_type( $post_type ) {
		return in_array( $post_type, self::reorder_types(), true );
	}

	/**
	 * Public post types that may be offered for manual ordering (for the dash).
	 *
	 * @return array<int,array{slug:string,label:string}>
	 */
	public static function eligible_post_types() {
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, self::REORDER_EXCLUDED, true ) ) {
				continue;
			}
			$out[] = array(
				'slug'  => $type->name,
				'label' => $type->labels->name,
			);
		}
		return $out;
	}

	/**
	 * On an enabled post type's edit screen, add the Sort-by-Order view and (when
	 * that view is active) load the drag-to-reorder behavior.
	 */
	public function setup_reorder_screen() {
		$screen    = get_current_screen();
		$post_type = $screen ? $screen->post_type : '';

		if ( ! $post_type || ! self::is_reorder_type( $post_type ) ) {
			return;
		}

		$type = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->edit_others_posts ) ) {
			return;
		}

		add_filter( 'views_' . $screen->id, array( $this, 'sort_by_order_view' ) );

		if ( $this->is_sort_view( $post_type ) ) {
			add_action( 'pre_get_posts', array( $this, 'show_all_in_admin_sort_view' ) );
			// The list table paginates hierarchical rows in its tree walker by the
			// screen's per-page option even when the query fetched everything, so
			// lift that too — dragging needs the whole tree on one page. (The JS
			// locks the per-page Screen Option and explains why.)
			add_filter( "edit_{$post_type}_per_page", array( $this, 'show_all_rows_per_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_reorder_assets' ) );
		}
	}

	/**
	 * Safe mode: whether a hierarchical type has too many rows to show the
	 * whole unpaginated tree on every default screen visit. Past the ceiling
	 * the list screen stays core (paginated, no tree) and the "Sort by
	 * Order" link opens the full tree explicitly instead.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	private static function tree_over_limit( $post_type ) {
		$counts = (array) wp_count_posts( $post_type );
		unset( $counts['trash'], $counts['auto-draft'] );

		/**
		 * Row-count ceiling for showing the manual-order tree on the default
		 * list screen of a hierarchical post type.
		 *
		 * @param int    $limit     Default 500.
		 * @param string $post_type
		 */
		$limit = (int) apply_filters( 'blocklane_pro_reorder_tree_limit', 500, $post_type );

		return array_sum( array_map( 'intval', $counts ) ) > $limit;
	}

	/** Whether the current list is showing the manual (menu_order) sort. */
	private function is_sort_view( $post_type ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view checks.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';

		if ( ! is_post_type_hierarchical( $post_type ) ) {
			return 'menu_order' === $orderby;
		}

		// Hierarchical types are manually ordered in their DEFAULT view — that's
		// the only one core renders as a tree (an explicit orderby, even
		// menu_order, produces a flat table). Reordering also needs the WHOLE
		// tree: on any filtered subset (search, status, date, author, taxonomy)
		// a drag would re-sequence just the visible rows against the rest of
		// the site's menu_order, so every filter opts out.
		if ( '' !== $orderby ) {
			return false;
		}
		// Any filter opts out. A CUSTOM taxonomy filters on its OWN query var
		// (its slug), not the generic 'cat'/'taxonomy', so a fixed blacklist
		// misses it — gather the query vars of every taxonomy attached to this
		// post type and check those too, else a drag on a taxonomy-filtered
		// subset would re-sequence just the visible rows against the rest.
		$tax_query_vars = array();
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax_obj ) {
			if ( ! empty( $tax_obj->query_var ) ) {
				$tax_query_vars[] = $tax_obj->query_var;
			}
		}
		$filters = array_merge( array( 's', 'm', 'author', 'cat', 'taxonomy', 'term' ), $tax_query_vars );
		foreach ( $filters as $filter ) {
			if ( ! empty( $_GET[ $filter ] ) ) {
				return false;
			}
		}
		// Safe mode: past the row-count ceiling the default screen stays
		// core, and the sort view needs the explicit flag carried by the
		// Sort by Order link.
		if ( self::tree_over_limit( $post_type ) && empty( $_GET['blocklane-pro-sort'] ) ) {
			return false;
		}
		$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return '' === $status || 'all' === $status;
	}

	/**
	 * Add a "Sort by Order" link to the list-table views.
	 *
	 * @param array $views
	 * @return array
	 */
	public function sort_by_order_view( $views ) {
		$post_type = get_current_screen() ? get_current_screen()->post_type : '';

		// Hierarchical types: the manual-order view IS the default tree view, so
		// the link clears sorting/filtering instead of adding an orderby (which
		// would make core render a flat table). Past the safe-mode ceiling the
		// link carries the explicit flag that opens the full tree on demand.
		if ( is_post_type_hierarchical( $post_type ) ) {
			$url = remove_query_arg( array( 'orderby', 'order', 'paged', 's', 'post_status', 'm', 'author', 'cat', 'taxonomy', 'term', 'blocklane-pro-sort' ) );
			if ( self::tree_over_limit( $post_type ) ) {
				$url = add_query_arg( 'blocklane-pro-sort', 1, $url );
			}
		} else {
			$url = add_query_arg(
				array(
					'orderby' => 'menu_order',
					'order'   => 'asc',
				),
				remove_query_arg( array( 'orderby', 'order', 'paged' ) )
			);
		}
		$current = $this->is_sort_view( $post_type ) ? ' class="current" aria-current="page"' : '';

		$views['blocklane_pro_order'] = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $url ),
			$current,
			esc_html__( 'Sort by Order', 'blocklane' )
		);

		return $views;
	}

	/** Show every row in the sort view so the whole set is draggable. */
	public function show_all_in_admin_sort_view( $query ) {
		if ( $query->is_main_query() ) {
			$query->set( 'posts_per_page', -1 );
		}
	}

	/** Per-page ceiling for the sort view (list-table walker pagination). */
	public function show_all_rows_per_page() {
		// Sized to the type's actual row count — a hardcoded cap would silently
		// truncate the tree on a big enough site, and drags against a partial
		// tree corrupt the order of whatever fell past the cap.
		$screen = get_current_screen();
		$counts = $screen && $screen->post_type ? (array) wp_count_posts( $screen->post_type ) : array();
		unset( $counts['trash'], $counts['auto-draft'] );
		return max( 1, array_sum( array_map( 'intval', $counts ) ) );
	}

	/** Enqueue the drag behavior + the data it needs (REST URL, nonce, post type). */
	public function enqueue_reorder_assets() {
		$screen    = get_current_screen();
		$post_type = $screen ? $screen->post_type : '';

		wp_enqueue_script(
			'blocklane-pro-reorder',
			BLOCKLANE_PRO_URL . '/inc/advanced/assets/reorder.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-a11y', 'wp-i18n' ),
			BLOCKLANE_PRO_VERSION,
			true
		);
		wp_enqueue_style(
			'blocklane-pro-reorder',
			BLOCKLANE_PRO_URL . '/inc/advanced/assets/reorder.css',
			array(),
			BLOCKLANE_PRO_VERSION
		);
		// Authoritative {id: parent} map for the tree. The JS prefers core's
		// Quick Edit inline data per row (it's refreshed on inline save), but
		// that data is absent when Quick Edit is disabled for the type or the
		// user can't edit a given row — without this fallback the tree would
		// silently read as flat there.
		$parents = array();
		if ( is_post_type_hierarchical( $post_type ) ) {
			$query = new \WP_Query(
				array(
					'post_type'              => $post_type,
					'posts_per_page'         => -1,
					'post_status'            => 'any',
					'fields'                 => 'id=>parent',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $query->posts as $row ) {
				$parents[ (int) $row->ID ] = (int) $row->post_parent;
			}
		}

		// The user's real per-page preference. The sort view lifts pagination,
		// so the Screen Options field would otherwise display (and, being
		// readonly-but-submitted, re-save) the overridden row count —
		// clobbering the preference that still applies to other views.
		$per_page = (int) get_user_option( "edit_{$post_type}_per_page" );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		wp_localize_script(
			'blocklane-pro-reorder',
			'blocklaneProReorder',
			array(
				'restUrl'      => esc_url_raw( rest_url( Branding::rest_namespace() . '/advanced/reorder' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'postType'     => $post_type,
				'hierarchical' => is_post_type_hierarchical( $post_type ),
				'parents'      => $parents,
				'perPage'      => $per_page,
				'i18n'         => array(
					'saveFailed'  => __( 'Couldn’t save the new order. Please refresh and try again.', 'blocklane' ),
					'indent'      => __( 'Indent', 'blocklane' ),
					'outdent'     => __( 'Outdent', 'blocklane' ),
					/* translators: 1: page title, 2: new parent page title. */
					'indented'    => __( '%1$s is now nested under %2$s.', 'blocklane' ),
					/* translators: %s: page title. */
					'outdented'   => __( '%s moved up one level.', 'blocklane' ),
					'moveUp'      => __( 'Move up', 'blocklane' ),
					'moveDown'    => __( 'Move down', 'blocklane' ),
					/* translators: %s: item title. */
					'movedUp'     => __( '%s moved up.', 'blocklane' ),
					/* translators: %s: item title. */
					'movedDown'   => __( '%s moved down.', 'blocklane' ),
					/* translators: %s: page title. */
					'collapse'    => __( 'Collapse %s', 'blocklane' ),
					/* translators: %s: page title. */
					'expand'      => __( 'Expand %s', 'blocklane' ),
					'collapseAll' => __( 'Collapse All', 'blocklane' ),
					'expandAll'   => __( 'Expand All', 'blocklane' ),
					'perPageInfo' => __( 'The Sort by Order view shows every item so the full order can be arranged at once. This setting applies to the other views.', 'blocklane' ),
				),
			)
		);
	}

	/**
	 * Front-end: order enabled post types by menu_order — but only when a query
	 * hasn't asked for a specific order, so events/recent lists can still sort by
	 * date by setting their own orderby.
	 *
	 * @param \WP_Query $query
	 */
	public function apply_front_order( $query ) {
		if ( is_admin() ) {
			return;
		}
		// An explicit orderby wins. It may be a string ('date') OR an array
		// (array( 'menu_order' => 'ASC', 'title' => 'ASC' )), so check emptiness
		// rather than casting — casting an array to string throws a warning.
		$orderby = $query->get( 'orderby' );
		if ( ! empty( $orderby ) ) {
			return;
		}

		$queried = $query->get( 'post_type' );
		$types   = array_filter( is_array( $queried ) ? $queried : array( $queried ) );
		if ( empty( $types ) ) {
			return;
		}

		$enabled = self::reorder_types();
		if ( empty( $enabled ) || array_diff( $types, $enabled ) ) {
			return; // only when every queried type uses manual ordering.
		}

		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Persist a new manual order. Re-sequences menu_order across the given IDs
	 * (validated to the post type), skipping revisions for the bulk change.
	 * Caller has already verified the capability + that the type is enabled.
	 *
	 * @param string $post_type
	 * @param int[]  $ordered_ids
	 * @return int Number of items reordered.
	 */
	public static function save_order( $post_type, $ordered_ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ordered_ids ) );
		_prime_post_caches( $ids, false, false );

		$position = 1;
		$changes  = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== $post_type ) {
				continue;
			}
			// A drag only shifts a window of rows; collect just the real moves.
			if ( (int) $post->menu_order !== $position ) {
				$changes[ $id ] = $position;
			}
			$position++;
		}

		if ( $changes ) {
			// One UPDATE for the whole re-sequence. A move-to-top renumbers
			// every row below it, and a wp_update_post per row runs the full
			// save pipeline each time — multi-second on big lists. menu_order
			// carries no revisions and a pure re-sequence has no meaningful
			// save_post semantics, so a direct write + cache clean is the
			// right weight. Every value is absint()-clean.
			/*
			 * ONE prepare(), not a loop of them concatenated into a string.
			 * The old form was safe — each arm was prepared — but it built
			 * prepare()'s first argument out of a variable, which is the one
			 * position the checker examines and cannot see into. Here the whole
			 * statement text is literal: count() gives the arm and id counts,
			 * and the arguments interleave id/menu_order for the CASE and then
			 * repeat the ids for the IN.
			 */
			$ids  = array_keys( $changes );
			$args = array();
			foreach ( $changes as $id => $menu_order ) {
				$args[] = $id;
				$args[] = $menu_order;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a bulk re-sequence that deliberately bypasses the per-post save pipeline; clean_post_cache() below is the cache write.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET menu_order = CASE ID "
						. implode( ' ', array_fill( 0, count( $changes ), 'WHEN %d THEN %d' ) )
						. ' END WHERE ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( $args, $ids )
				)
			);
			foreach ( array_keys( $changes ) as $id ) {
				clean_post_cache( $id );
			}
		}

		return $position - 1;
	}

	/**
	 * Re-parent one item of a hierarchical type (the Indent/Outdent row actions).
	 * Caller has already verified the capability + that the type is enabled;
	 * this validates the structure: same type, no self/descendant loops.
	 *
	 * @param string $post_type
	 * @param int    $moved_id
	 * @param int    $parent_id New parent, 0 for top level.
	 * @return true|\WP_Error
	 */
	public static function save_parent( $post_type, $moved_id, $parent_id ) {
		if ( ! is_post_type_hierarchical( $post_type ) ) {
			return new \WP_Error(
				'blocklane_pro_not_hierarchical',
				__( 'This content type doesn’t support nesting.', 'blocklane' ),
				array( 'status' => 400 )
			);
		}

		$moved = get_post( $moved_id );
		if ( ! $moved || $moved->post_type !== $post_type ) {
			return new \WP_Error(
				'blocklane_pro_invalid_item',
				__( 'That item can’t be moved.', 'blocklane' ),
				array( 'status' => 400 )
			);
		}

		if ( $parent_id ) {
			$parent = get_post( $parent_id );
			$loop   = $parent_id === $moved_id || in_array( $moved_id, get_post_ancestors( $parent_id ), true );
			if ( ! $parent || $parent->post_type !== $post_type || $loop ) {
				return new \WP_Error(
					'blocklane_pro_invalid_parent',
					__( 'That item can’t be nested there.', 'blocklane' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( (int) $moved->post_parent === $parent_id ) {
			return true; // Already there.
		}

		$result = wp_update_post(
			array(
				'ID'          => $moved_id,
				'post_parent' => $parent_id,
			),
			true
		);

		return is_wp_error( $result ) ? $result : true;
	}

	/* ---- limit revisions --------------------------------------------------- */

	/**
	 * Cap the number of stored revisions per post. (0 disables revisions.)
	 *
	 * @param int          $num
	 * @param \WP_Post|null $post
	 * @return int
	 */
	public function revisions_to_keep( $num, $post = null ) {
		unset( $num, $post );
		return self::get_int( 'revisions-to-keep' );
	}

	/* ---- limit heartbeat --------------------------------------------------- */

	/**
	 * Throttle the Heartbeat API polling interval (seconds), clamped to core's
	 * accepted 15–120s range. Autosave and post-locking keep working — just less
	 * chatty against admin-ajax.php.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function heartbeat_interval( $settings ) {
		$settings['interval'] = max( 15, min( 120, self::get_int( 'heartbeat-interval' ) ) );
		return $settings;
	}

	/**
	 * Drop the Heartbeat entirely on the front end — public pages don't need the
	 * admin-ajax polling. The admin keeps it (throttled) for autosave / post-locks.
	 */
	public function disable_frontend_heartbeat() {
		if ( ! is_admin() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/* ---- last-login column ------------------------------------------------- */

	/**
	 * Stamp the user's last login.
	 *
	 * @param string        $user_login
	 * @param \WP_User|null $user
	 */
	public function record_last_login( $user_login, $user = null ) {
		if ( ! $user instanceof \WP_User ) {
			$user = get_user_by( 'login', $user_login );
		}
		if ( $user instanceof \WP_User ) {
			update_user_meta( $user->ID, self::LAST_LOGIN_META, time() );
		}
	}

	/**
	 * Add a "Last Login" column to the Users list table.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function users_column( $columns ) {
		$columns['blocklane_pro_last_login'] = __( 'Last Login', 'blocklane' );
		return $columns;
	}

	/**
	 * Render the Last Login cell, styled like core's date columns.
	 *
	 * @param string $output
	 * @param string $column
	 * @param int    $user_id
	 * @return string
	 */
	public function users_column_content( $output, $column, $user_id ) {
		if ( 'blocklane_pro_last_login' !== $column ) {
			return $output;
		}

		$timestamp = (int) get_user_meta( $user_id, self::LAST_LOGIN_META, true );
		if ( ! $timestamp ) {
			return '<span aria-hidden="true">' . esc_html__( 'Never', 'blocklane' ) . '</span>'
				. '<span class="screen-reader-text">' . esc_html__( 'Never logged in', 'blocklane' ) . '</span>';
		}

		return sprintf(
			'%1$s<br><span class="description">%2$s</span>',
			esc_html( date_i18n( get_option( 'date_format' ), $timestamp ) ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable time difference, e.g. "2 hours". */
					__( '%s ago', 'blocklane' ),
					human_time_diff( $timestamp )
				)
			)
		);
	}

	/* ---- duplicate content ------------------------------------------------- */

	/**
	 * Add a "Clone" link to a row's actions, next to Edit / Trash, for any
	 * editable post type. Used for both post_row_actions and page_row_actions.
	 *
	 * @param array    $actions
	 * @param \WP_Post $post
	 * @return array
	 */
	public function duplicate_row_action( $actions, $post ) {
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->show_ui || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=' . self::DUPLICATE_ACTION . '&post=' . $post->ID ),
			self::DUPLICATE_ACTION . '_' . $post->ID
		);

		$actions['blocklane_pro_clone'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Clone', 'blocklane' )
		);

		return $actions;
	}

	/**
	 * Handle the Clone action: verify nonce + caps, copy the post, and open the
	 * new draft for editing.
	 */
	public function handle_duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, self::DUPLICATE_ACTION . '_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid or expired duplicate request.', 'blocklane' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'blocklane' ) );
		}

		$type = get_post_type_object( $post->post_type );
		if ( $type && isset( $type->cap->create_posts ) && ! current_user_can( $type->cap->create_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to create items of this type.', 'blocklane' ) );
		}

		$new_id = $this->duplicate_post( $post );
		if ( ! $new_id || is_wp_error( $new_id ) ) {
			wp_die( esc_html__( 'The item could not be duplicated.', 'blocklane' ) );
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	/**
	 * Copy a post to a new draft — content, taxonomy terms, and meta (minus
	 * WordPress-internal keys). Author and parent are preserved.
	 *
	 * @param \WP_Post $post
	 * @return int|\WP_Error New post ID.
	 */
	private function duplicate_post( $post ) {
		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'     => $post->post_title,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_status'    => 'draft',
					'post_password'  => $post->post_password,
					'post_type'      => $post->post_type,
					'post_parent'    => $post->post_parent,
					'post_author'    => (int) $post->post_author,
					'menu_order'     => $post->menu_order,
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
				)
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return $new_id;
		}

		// Taxonomy terms.
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, $terms, $taxonomy );
			}
		}

		// Meta, minus internal/edit-lock keys. wp_slash so add_post_meta's
		// unslashing round-trips the value (incl. serialized arrays) intact.
		$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_pingme', '_encloseme' );
		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}

		return $new_id;
	}

	/* ---- safe SVG upload --------------------------------------------------- */

	/**
	 * Allow the SVG mime type for users who can upload. Uploads are still
	 * sanitized on the way in (see sanitize_svg_upload).
	 *
	 * @param array $mimes
	 * @return array
	 */
	public function allow_svg_mime( $mimes ) {
		if ( current_user_can( 'upload_files' ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * WordPress' finfo check frequently misreads SVG, so a .svg upload gets
	 * blocked as "not permitted." Re-assert the type/ext for .svg files.
	 *
	 * @param array  $data     ext/type/proper_filename.
	 * @param string $file     Full path to the file.
	 * @param string $filename The name of the file.
	 * @param array  $mimes    Allowed mime types.
	 * @return array
	 */
	public function fix_svg_filetype( $data, $file, $filename, $mimes ) {
		unset( $file, $mimes );
		if ( preg_match( '/\.svg$/i', (string) $filename ) && current_user_can( 'upload_files' ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	/**
	 * Sanitize an SVG before it's stored — strips scripts, event handlers,
	 * external references, etc. via the bundled enshrined/svg-sanitize library.
	 * Anything that can't be sanitized is rejected (the upload fails) rather than
	 * stored unsafely.
	 *
	 * @param array $file The $_FILES entry being handled.
	 * @return array
	 */
	public function sanitize_svg_upload( $file ) {
		$is_svg = ( isset( $file['type'] ) && 'image/svg+xml' === $file['type'] )
			|| ( isset( $file['name'] ) && preg_match( '/\.svg$/i', $file['name'] ) );

		if ( ! $is_svg ) {
			return $file;
		}

		if ( empty( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) || ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
			// Fail closed: never store a raw, unsanitized SVG. If the sanitizer is
			// unavailable (lib stripped by a deploy exclude, unreadable temp file),
			// reject rather than pass the upload through untouched.
			$file['error'] = __( 'The SVG sanitizer is unavailable, so this file was not uploaded.', 'blocklane' );
			return $file;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the temp upload before it's moved.
		$dirty = file_get_contents( $file['tmp_name'] );
		if ( false === $dirty ) {
			$file['error'] = __( 'The SVG file could not be read.', 'blocklane' );
			return $file;
		}

		$sanitizer = new \enshrined\svgSanitize\Sanitizer();
		$sanitizer->minify( true );
		// Strip references to off-site resources (remote xlink:href, url() to
		// another origin) so a stored SVG can't phone home or leak the viewer's
		// IP — the "external references" this method's docblock promises to
		// remove. Rare legitimately-remote SVGs lose those references.
		$sanitizer->removeRemoteReferences( true );
		$clean = $sanitizer->sanitize( $dirty );

		if ( false === $clean || '' === trim( (string) $clean ) ) {
			$file['error'] = __( 'This SVG could not be sanitized safely, so it was not uploaded.', 'blocklane' );
			return $file;
		}

		$clean = self::svg_ensure_intrinsic_size( $clean );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- writing the sanitized markup back to the temp upload.
		if ( false === file_put_contents( $file['tmp_name'], $clean ) ) {
			$file['error'] = __( 'The sanitized SVG could not be saved.', 'blocklane' );
		}

		return $file;
	}

	/**
	 * Register the SVG no-op editor ahead of GD/Imagick so crop-demanding flows
	 * (the Site Icon picker, custom header crops) succeed for SVGs instead of
	 * erroring. It claims only SVGs (see Svg_Image_Editor::test()), so raster
	 * types still pick a real editor.
	 *
	 * @param string[] $editors Image editor class names, in priority order.
	 * @return string[]
	 */
	public function register_svg_editor( $editors ) {
		// Loaded here, not at boot: the WP_Image_Editor parent class doesn't
		// exist until core requires it in _wp_image_editor_choose(), right
		// before this filter fires. The guard covers any other call order.
		if ( ! class_exists( __NAMESPACE__ . '\\Svg_Image_Editor', false ) ) {
			require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
			require_once BLOCKLANE_PRO_PATH . '/inc/advanced/class-blocklane-pro-svg-editor.php';
		}
		array_unshift( $editors, __NAMESPACE__ . '\\Svg_Image_Editor' );
		return array_unique( $editors );
	}

	/**
	 * Add explicit width/height attributes (from the viewBox) to an SVG whose
	 * root element declares neither.
	 *
	 * A viewBox-only SVG has an intrinsic RATIO but no intrinsic SIZE, and
	 * layouts that resolve percentage sizing through the image's intrinsic
	 * size — the Site Logo block's editor CSS is one — compute 0x0 and the
	 * image visually vanishes. The attributes are scaled to a 512px longest
	 * side (keeping the viewBox ratio) rather than raw viewBox units: logo
	 * layouts also assume the intrinsic size EXCEEDS the displayed size the
	 * way raster logos do, and a 42-unit icon would clamp at 42px. The SVG
	 * still scales freely via CSS.
	 *
	 * @param string $svg Sanitized SVG markup.
	 * @return string
	 */
	public static function svg_ensure_intrinsic_size( $svg ) {
		if ( ! preg_match( '/<svg\b[^>]*>/i', $svg, $m ) ) {
			return $svg;
		}

		$tag = $m[0];

		// Root already sized, or no viewBox to derive a size from.
		if ( preg_match( '/\swidth=/i', $tag ) || preg_match( '/\sheight=/i', $tag ) || ! preg_match( '/\sviewBox=/i', $tag ) ) {
			return $svg;
		}

		$size  = self::svg_intrinsic_dimensions( $svg );
		$scale = 512 / max( $size['width'], $size['height'] );
		$w     = (int) round( $size['width'] * $scale );
		$h     = (int) round( $size['height'] * $scale );

		$core         = rtrim( substr( $tag, 0, -1 ) );
		$self_closing = '/' === substr( $core, -1 );
		if ( $self_closing ) {
			$core = rtrim( substr( $core, 0, -1 ) );
		}

		$new_tag = $core . sprintf( ' width="%d" height="%d"', $w, $h ) . ( $self_closing ? '/>' : '>' );

		return substr_replace( $svg, $new_tag, strpos( $svg, $tag ), strlen( $tag ) );
	}

	/**
	 * An SVG's intrinsic dimensions in user units: width/height attributes when
	 * they're plain numbers (px), else the viewBox. Rounded up so a 41.54-unit
	 * mark doesn't report 0. Falls back to a 512 square — the Site Icon size —
	 * when the SVG declares nothing usable.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return array{width:int,height:int}
	 */
	public static function svg_intrinsic_dimensions( $svg ) {
		$width  = 0;
		$height = 0;

		// Only a bare number or an explicit px counts as an intrinsic pixel
		// size. The trailing lookahead (quote / whitespace / slash / >) rejects
		// "100%" or "10em", which would otherwise be read as 100/10 px; those
		// fall through to the viewBox below, which carries the real ratio.
		if ( preg_match( '/<svg[^>]*\swidth=["\']?([0-9.]+)(?:px)?["\']?(?=["\'\s\/>])/i', $svg, $w )
			&& preg_match( '/<svg[^>]*\sheight=["\']?([0-9.]+)(?:px)?["\']?(?=["\'\s\/>])/i', $svg, $h ) ) {
			$width  = (float) $w[1];
			$height = (float) $h[1];
		}

		if ( ( ! $width || ! $height )
			&& preg_match( '/<svg[^>]*\sviewBox=["\']?\s*[0-9.eE+-]+[,\s]+[0-9.eE+-]+[,\s]+([0-9.eE+]+)[,\s]+([0-9.eE+]+)/i', $svg, $vb ) ) {
			$width  = (float) $vb[1];
			$height = (float) $vb[2];
		}

		if ( ! $width || ! $height ) {
			return array(
				'width'  => 512,
				'height' => 512,
			);
		}

		return array(
			'width'  => (int) ceil( $width ),
			'height' => (int) ceil( $height ),
		);
	}

	/**
	 * Correct the mime type on attachments created for an SVG file by flows
	 * that detect type via wp_getimagesize() — e.g. the Site Icon cropper's
	 * copy lands as "image/jpeg" because an SVG has no raster size to read
	 * (wp_copy_parent_attachment_properties() falls back to jpeg).
	 *
	 * @param array $data    Sanitized attachment post data about to be written.
	 * @param array $postarr Raw attachment post data, including 'file'.
	 * @return array
	 */
	public function fix_svg_attachment_mime( $data, $postarr ) {
		$file = isset( $postarr['file'] ) ? (string) $postarr['file'] : '';
		$mime = isset( $data['post_mime_type'] ) ? $data['post_mime_type'] : '';

		if ( $file && 'image/svg+xml' !== $mime && preg_match( '/\.svg$/i', $file ) ) {
			$data['post_mime_type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * Give SVG attachments real width/height metadata, read from the viewBox.
	 *
	 * Runs on both wp_generate_attachment_metadata (persisted at upload) and
	 * wp_get_attachment_metadata (backfills SVGs uploaded before this shipped).
	 * Without dimensions, the block editor's resize/scale controls compute
	 * sizes from undefined and write a 0x0 image — it visually disappears —
	 * and the Site Icon cropper has no numbers to do its math with.
	 *
	 * @param array|false $data          Attachment metadata (false when none stored).
	 * @param int         $attachment_id Attachment ID.
	 * @return array|false
	 */
	public function svg_attachment_metadata( $data, $attachment_id ) {
		if ( is_array( $data ) && ! empty( $data['width'] ) && ! empty( $data['height'] ) ) {
			return $data;
		}

		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $data;
		}

		// One file read per attachment per request: this filter fires for every
		// size lookup the media grid / editor makes.
		static $dims = array();

		if ( ! isset( $dims[ $attachment_id ] ) ) {
			$file = get_attached_file( $attachment_id );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment file.
			$svg = ( $file && is_readable( $file ) ) ? file_get_contents( $file ) : false;

			if ( false === $svg ) {
				$dims[ $attachment_id ] = false;
			} else {
				$dims[ $attachment_id ]         = self::svg_intrinsic_dimensions( $svg );
				$dims[ $attachment_id ]['file'] = _wp_relative_upload_path( $file );
			}
		}

		if ( false === $dims[ $attachment_id ] ) {
			return $data;
		}

		$data           = is_array( $data ) ? $data : array();
		$data['width']  = $dims[ $attachment_id ]['width'];
		$data['height'] = $dims[ $attachment_id ]['height'];

		if ( empty( $data['file'] ) ) {
			$data['file'] = $dims[ $attachment_id ]['file'];
		}

		return $data;
	}

	/**
	 * Make SVGs render at a sensible size in the media library / attachment UI
	 * (they have no intrinsic raster dimensions, so they otherwise overflow).
	 */
	public function svg_admin_styles() {
		echo '<style>.media-icon img[src$=".svg"],.attachment .thumbnail img[src$=".svg"],.attachment-info .thumbnail img[src$=".svg"]{width:100%;height:auto}td.media-icon img[src$=".svg"]{width:48px;height:auto}</style>';
	}

	/* ---- media replacement (in-modal) -------------------------------------- */

	/** Load the in-modal "Replace file" behavior wherever the media UI appears. */
	public function enqueue_media_replace_assets() {
		wp_enqueue_script(
			'blocklane-pro-media-replace',
			BLOCKLANE_PRO_URL . '/inc/advanced/assets/media-replace.js',
			array(),
			BLOCKLANE_PRO_VERSION,
			true
		);
		wp_localize_script(
			'blocklane-pro-media-replace',
			'blocklaneProMediaReplace',
			array(
				'restUrl' => esc_url_raw( rest_url( Branding::rest_namespace() . '/advanced/replace-media' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'label'   => __( 'Replace file', 'blocklane' ),
				'working' => __( 'Replacing…', 'blocklane' ),
				'done'    => __( 'Replaced ✓', 'blocklane' ),
				'error'   => __( 'Replace failed', 'blocklane' ),
			)
		);
	}

	/**
	 * Replace an attachment's file from an uploaded $_FILES-style entry (used by
	 * the REST endpoint). Runs the upload pipeline (so SVGs are sanitized), requires
	 * the same mime type so the URL/extension stay valid, then swaps in place.
	 *
	 * @param int   $id   Attachment ID.
	 * @param array $file A single $_FILES entry (name/type/tmp_name/error/size).
	 * @return array|\WP_Error { url } (cache-busted) on success.
	 */
	public function replace_media_file( $id, $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$old_mime = get_post_mime_type( $id );
		$upload   = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
			return new \WP_Error(
				'blocklane_pro_upload_failed',
				is_array( $upload ) ? $upload['error'] : __( 'The file could not be uploaded.', 'blocklane' ),
				array( 'status' => 400 )
			);
		}

		if ( $old_mime && $upload['type'] !== $old_mime ) {
			wp_delete_file( $upload['file'] );
			return new \WP_Error(
				'blocklane_pro_type_mismatch',
				sprintf(
					/* translators: %s: the original file's mime type. */
					__( 'The replacement must be the same type (%s) so the URL stays valid.', 'blocklane' ),
					$old_mime
				),
				array( 'status' => 400 )
			);
		}

		$swapped = $this->replace_attachment_file( $id, $upload['file'] );
		$left    = is_wp_error( $swapped ) && 'blocklane_pro_swap_aside_left' === $swapped->get_error_code();
		if ( is_wp_error( $swapped ) && ! $left ) {
			wp_delete_file( $upload['file'] );
			return new \WP_Error(
				'blocklane_pro_replace_failed',
				__( 'The file was uploaded but could not replace the original. Please try again.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		// The URL is unchanged; append a cache-buster so the UI shows the new file.
		$response = array( 'url' => add_query_arg( 't', time(), wp_get_attachment_url( $id ) ) );
		if ( $left ) {
			// The new file is in place; the old bytes are still on disk under
			// the set-aside name, and the media modal shows this instead of
			// "Replaced".
			$response['notice'] = sprintf(
				/* translators: %s: file name of the previous copy left in the uploads folder. */
				__( 'Replaced, but the previous file could not be removed: %s', 'blocklane' ),
				wp_basename( (string) ( $swapped->get_error_data()['aside'] ?? '' ) )
			);
		}
		return $response;
	}

	/**
	 * Swap an attachment's file in place: move the new file onto the original
	 * path (File_Ops::uploads_swap(), which keeps the original until the new
	 * file is there), then delete the old sub-size files and regenerate
	 * metadata/thumbnails. The attachment ID, filename, and URL are unchanged.
	 *
	 * @param int                       $id          Attachment ID.
	 * @param string                    $source_path Path to the new (already-validated) file.
	 * @param \WP_Filesystem_Base|null $fs          The swap's transport; null for the
	 *                                                direct one (a battery injects a failing one).
	 * @return true|\WP_Error True when swapped; 'blocklane_pro_swap_aside_left' when
	 *                        swapped with the previous copy still on disk (metadata
	 *                        is regenerated either way); any other error when the
	 *                        original is still the attachment's file.
	 */
	private function replace_attachment_file( int $id, string $source_path, ?\WP_Filesystem_Base $fs = null ): bool|\WP_Error {
		$old_path = get_attached_file( $id );
		if ( ! $old_path ) {
			return new \WP_Error( 'blocklane_pro_replace_no_file', 'Attachment ' . $id . ' has no file path.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$meta = wp_get_attachment_metadata( $id );
		$dir  = trailingslashit( dirname( $old_path ) );

		// Move the new content onto the original path FIRST (same name → same URL).
		// Only clean up old sizes and regenerate once the swap has actually
		// succeeded — otherwise a failed move would leave the attachment with its
		// thumbnails already deleted and metadata regenerated from the old file.
		$swapped = File_Ops::uploads_swap( $source_path, $old_path, $fs );
		if ( is_wp_error( $swapped ) && 'blocklane_pro_swap_aside_left' !== $swapped->get_error_code() ) {
			return $swapped;
		}

		// Remove the old generated sizes + the pristine "big image" original so
		// stale files don't linger under the reused path.
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && file_exists( $dir . $size['file'] ) ) {
					wp_delete_file( $dir . $size['file'] );
				}
			}
		}
		if ( ! empty( $meta['original_image'] ) && file_exists( $dir . $meta['original_image'] ) ) {
			wp_delete_file( $dir . $meta['original_image'] );
		}

		$new_meta = wp_generate_attachment_metadata( $id, $old_path );
		if ( $new_meta && ! is_wp_error( $new_meta ) ) {
			wp_update_attachment_metadata( $id, $new_meta );
		}

		return $swapped;
	}

}
