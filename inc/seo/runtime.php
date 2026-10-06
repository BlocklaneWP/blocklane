<?php
/**
 * Blocklane SEO — runtime (canonical source).
 *
 * Basic, out-of-the-box SEO: a per-post search title, meta description,
 * schema type, and noindex flag — edited in the block editor, emitted here.
 * Core already owns the rest of the surface (title tag, robots API,
 * rel=canonical, wp-sitemap.xml); this file only fills the gaps core has
 * deliberately left to plugins: the description tag, Open Graph tags, and
 * JSON-LD structured data.
 *
 * Like the Content Types runtime, this is emission of CONTENT, so it loads
 * OUTSIDE the module boot (see blocklane_pro_run_plugin): safe mode or a
 * disabled module must never strip a live site's meta tags. The license is
 * not involved — nothing gates on it at runtime (see the License class).
 *
 * Self-contained by construction: it assumes no module has booted, and the
 * two side-effect-free plugin classes it uses — the toggle reader,
 * Content_Toggle, and the inline-asset door its inline styles and JSON-LD
 * scripts go through, Inline_Asset (its meta and link tags are echoed here,
 * each value escaped at the sink) — are resolved by the classmap autoloader, registered at
 * plugin-file scope before any runtime loads, so load order can never trip
 * them (the must-use bake the old "core WordPress only" rule was written for
 * was retired in 2026-08). All declarations live inside one
 * `if ( ! function_exists() )` block so a second copy of the file is a clean
 * no-op.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single-load guard — a future mu copy loads first and wins; this copy no-ops.
if ( defined( 'BLOCKLANE_PRO_SEO_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_SEO_RUNTIME_LOADED', true );

// Safe mode silences every Blocklane Pro surface, the generated runtimes included.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// The Advanced toggle (ships OFF). Read through the one toggle reader, which
// resolves the stored row without loading the Advanced class: an absent row
// or key means the shipped default.
if ( ! \blocklane_pro\Content_Toggle::on( 'seo' ) ) {
	return;
}

if ( ! function_exists( 'blocklane_pro_seo_boot' ) ) {

	/** Post meta keys. Kept as plain constants so a bake can inline them. */
	define( 'BLOCKLANE_PRO_SEO_META_TITLE', 'blocklane_seo_title' );
	define( 'BLOCKLANE_PRO_SEO_META_DESCRIPTION', 'blocklane_seo_description' );
	define( 'BLOCKLANE_PRO_SEO_META_NOINDEX', 'blocklane_seo_noindex' );
	define( 'BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE', 'blocklane_seo_schema_type' );

	/** Allowed per-post schema types. '' = default (no structured data). */
	define( 'BLOCKLANE_PRO_SEO_SCHEMA_TYPES', array( '', 'article', 'faq' ) );

	/**
	 * Whether Blocklane SEO output should stand down.
	 *
	 * A dedicated SEO plugin owns the whole head when present — emitting a
	 * second description/OG/JSON-LD set is worse than emitting none. Detection
	 * is by each plugin's own stable constant/class so it works from
	 * plugins_loaded on the front end (is_plugin_active() is admin-only).
	 *
	 * Jetpack's SEO Tools are handled the other way around: this module is the
	 * site's primary, so blocklane_pro_seo_boot() asks Jetpack to yield via its
	 * own `jetpack_disable_seo_tools` filter rather than yielding to it.
	 *
	 * @return bool
	 */
	function blocklane_pro_seo_disabled() {
		static $disabled = null;

		if ( null === $disabled ) {
			$disabled = defined( 'WPSEO_VERSION' )                    // Yoast SEO.
				|| class_exists( 'RankMath' )                         // Rank Math.
				|| defined( 'AIOSEO_VERSION' )                        // All in One SEO.
				|| defined( 'SEOPRESS_VERSION' )                      // SEOPress.
				|| defined( 'THE_SEO_FRAMEWORK_VERSION' )             // The SEO Framework.
				|| defined( 'SLIM_SEO_VER' );                         // Slim SEO.
		}

		/**
		 * Lets themes/plugins suppress all Blocklane SEO front-end output
		 * (meta description, Open Graph, JSON-LD, title override).
		 *
		 * @param bool $disabled True to suppress output.
		 */
		return apply_filters( 'blocklane_pro_disable_seo', $disabled );
	}

	/**
	 * Register the SEO post meta for every post type (REST-exposed so the
	 * editor panel reads/writes them as entity props).
	 */
	function blocklane_pro_seo_register_meta() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		register_meta(
			'post',
			BLOCKLANE_PRO_SEO_META_TITLE,
			array(
				'type'              => 'string',
				'label'             => __( 'Search title', 'blocklane' ),
				'description'       => __( 'Replaces the page title in search results and the browser tab.', 'blocklane' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			BLOCKLANE_PRO_SEO_META_DESCRIPTION,
			array(
				'type'              => 'string',
				'label'             => __( 'Search description', 'blocklane' ),
				'description'       => __( 'The summary search engines may show under the title.', 'blocklane' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					return sanitize_textarea_field( (string) $value );
				},
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			BLOCKLANE_PRO_SEO_META_NOINDEX,
			array(
				'type'              => 'boolean',
				'label'             => __( 'Hide from search engines', 'blocklane' ),
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
			array(
				'type'              => 'string',
				'label'             => __( 'Schema type', 'blocklane' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'blocklane_pro_seo_sanitize_schema_type',
				'auth_callback'     => $auth,
				'show_in_rest'      => array(
					// Enum so core REST rejects unknown types with a proper
					// rest_invalid_param; the sanitize_callback covers
					// non-REST writes.
					'schema' => array(
						'type' => 'string',
						'enum' => BLOCKLANE_PRO_SEO_SCHEMA_TYPES,
					),
				),
			)
		);
	}

	/**
	 * Sanitize a schema type to the allowed list; unknown values become ''.
	 *
	 * @param mixed $value Submitted value.
	 * @return string One of BLOCKLANE_PRO_SEO_SCHEMA_TYPES.
	 */
	function blocklane_pro_seo_sanitize_schema_type( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, BLOCKLANE_PRO_SEO_SCHEMA_TYPES, true ) ? $value : '';
	}

	/**
	 * Registered meta only reaches the REST post object (and so the editor
	 * panel) on post types that support custom-fields — quietly add it to
	 * public UI types that lack it, the way Jetpack's SEO Tools do.
	 */
	function blocklane_pro_seo_add_custom_fields_support() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);

		foreach ( $types as $type ) {
			if ( ! post_type_supports( $type, 'custom-fields' ) ) {
				add_post_type_support( $type, 'custom-fields' );
			}
		}
	}

	/**
	 * Site-level SEO settings, straight from the option — never the plugin's
	 * Seo class, so the runtime stays self-contained (the class normalizes on
	 * write; this is a defensive read of already-normalized data).
	 *
	 * @return array
	 */
	function blocklane_pro_seo_settings() {
		static $settings = null;

		if ( null !== $settings ) {
			return $settings;
		}

		$stored = get_option( 'blocklane_pro_seo', array() );
		$stored = is_array( $stored ) ? $stored : array();

		// One defaults table, one generic rule: a stored key is cast by its
		// default's type, an absent key gets its default — mirroring
		// Seo::defaults()/normalize() on the management side. The rule being
		// generic (not per-key idioms) is the protection: a future
		// default-ON key can't silently read OFF on sites whose stored
		// option predates it.
		$defaults = array(
			'sitemap'                 => true,
			'sitemap_authors'         => true,
			'archive_canonicals'      => true,
			'html_sitemap'            => false,
			'llms_txt'                => false,
			'indexnow'                => false,
			'noindex_author_archives' => false,
			'noindex_date_archives'   => false,
			'local_business'          => false,
			'html_sitemap_page'       => 0,
			'default_og_image_id'     => 0,
			'default_og_image_url'    => '',
			'org_logo_id'             => 0,
			'indexnow_key'            => '',
			'title_format'            => '',
			'title_separator'         => '',
			'org_name'                => '',
			'org_description'         => '',
			'local_address'           => '',
			'local_city'              => '',
			'local_region'            => '',
			'local_postal_code'       => '',
			'local_country'           => '',
			'local_phone'             => '',
			'local_latitude'          => '',
			'local_longitude'         => '',
		);

		$settings = array();
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$settings[ $key ] = $default;
			} elseif ( is_bool( $default ) ) {
				$settings[ $key ] = ! empty( $stored[ $key ] );
			} elseif ( is_int( $default ) ) {
				$settings[ $key ] = absint( $stored[ $key ] );
			} else {
				$settings[ $key ] = trim( (string) $stored[ $key ] );
			}
		}

		// The list/map keys have their own shapes.
		foreach ( array( 'sitemap_exclude_types', 'sitemap_exclude_taxonomies' ) as $list ) {
			$settings[ $list ] = ( isset( $stored[ $list ] ) && is_array( $stored[ $list ] ) )
				? array_map( 'sanitize_key', $stored[ $list ] )
				: array();
		}

		$settings['org_profiles'] = ( isset( $stored['org_profiles'] ) && is_array( $stored['org_profiles'] ) )
			? array_values( array_filter( array_map( 'strval', $stored['org_profiles'] ) ) )
			: array();

		foreach ( array( 'verification', 'local_hours' ) as $map ) {
			$settings[ $map ] = ( isset( $stored[ $map ] ) && is_array( $stored[ $map ] ) )
				? $stored[ $map ]
				: array();
		}

		return $settings;
	}

	/**
	 * Print search-engine verification meta tags. Only on the front page —
	 * that's the URL the services fetch to confirm ownership.
	 */
	function blocklane_pro_seo_verification_tags() {
		if ( blocklane_pro_seo_disabled() || ! is_front_page() ) {
			return;
		}

		$meta_names = array(
			'google'    => 'google-site-verification',
			'bing'      => 'msvalidate.01',
			'pinterest' => 'p:domain_verify',
			'yandex'    => 'yandex-verification',
			'facebook'  => 'facebook-domain-verification',
		);

		$codes = blocklane_pro_seo_settings()['verification'];

		foreach ( $meta_names as $service => $name ) {
			if ( ! empty( $codes[ $service ] ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( (string) $codes[ $service ] ) . '" />' . "\n";
			}
		}
	}

	/**
	 * The queried singular post, or null on any other context.
	 *
	 * @return WP_Post|null
	 */
	function blocklane_pro_seo_current_post() {
		if ( ! is_singular() || post_password_required() ) {
			return null;
		}
		$post = get_post();
		return ( $post instanceof WP_Post ) ? $post : null;
	}

	/**
	 * The description for a post: custom meta, else excerpt, else a trimmed
	 * cut of the content. Always plain text; '' when nothing usable.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	function blocklane_pro_seo_description_for( $post ) {
		// Memoized: the meta tags (wp_head@2) and the Article node (@5) both
		// ask, and the fallback strips the full post content each time.
		static $memo = array();

		if ( isset( $memo[ $post->ID ] ) ) {
			return $memo[ $post->ID ];
		}

		$custom = (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_DESCRIPTION, true );
		if ( '' !== trim( $custom ) ) {
			$memo[ $post->ID ] = trim( $custom );
		} elseif ( ! empty( $post->post_excerpt ) ) {
			$memo[ $post->ID ] = wp_strip_all_tags( $post->post_excerpt, true );
		} else {
			$content           = strip_shortcodes( excerpt_remove_blocks( $post->post_content ) );
			$content           = wp_strip_all_tags( $content, true );
			$memo[ $post->ID ] = $content ? wp_trim_words( $content, 30 ) : '';
		}

		return $memo[ $post->ID ];
	}

	/**
	 * Replace the whole document title when a custom search title is set.
	 * The override is the full <title> — core's "Title – Site name" format
	 * is deliberately not appended, matching how search plugins behave.
	 *
	 * With no per-post title, the site-level title format applies instead
	 * (when set): a token string — %title% %site% %tagline% %sep% — built
	 * per post. It only runs on singular views, where %title% has a post to
	 * point at; archives and the front page keep core's own title.
	 *
	 * @param string $title Short-circuit value ('' = no override).
	 * @return string
	 */
	function blocklane_pro_seo_document_title( $title ) {
		if ( blocklane_pro_seo_disabled() ) {
			return $title;
		}

		$post = blocklane_pro_seo_current_post();
		if ( ! $post ) {
			return $title;
		}

		$custom = trim( (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_TITLE, true ) );

		// pre_get_document_title short-circuits wp_get_document_title() before
		// its escaping, so escape here.
		if ( '' !== $custom ) {
			return esc_html( $custom );
		}

		// The FORMAT stops at the front page (an explicit per-page custom
		// title above still applies): the homepage keeps core's own
		// "Site – Tagline", per this function's contract.
		if ( is_front_page() ) {
			return $title;
		}

		$settings = blocklane_pro_seo_settings();
		if ( '' === $settings['title_format'] ) {
			return $title;
		}

		$separator = '' !== $settings['title_separator'] ? $settings['title_separator'] : '–';
		$built     = strtr(
			$settings['title_format'],
			array(
				'%title%'   => get_the_title( $post ),
				'%site%'    => get_bloginfo( 'name' ),
				'%tagline%' => get_bloginfo( 'description' ),
				'%sep%'     => $separator,
			)
		);

		// An empty tagline (or title) can leave dangling separators/space —
		// collapse runs of whitespace and strip stray separators off the
		// ends. A regex, not trim(): trim()'s char-list is byte-based, and
		// the separator is usually a multibyte glyph ('–' = E2 80 93) whose
		// bytes would shear a neighboring character and leave invalid UTF-8
		// that esc_html() then blanks entirely.
		$built = trim( preg_replace( '/\s+/', ' ', $built ) );
		$sep   = preg_quote( $separator, '/' );
		$built = (string) preg_replace( '/^(?:' . $sep . '|\s)+|(?:' . $sep . '|\s)+$/u', '', $built );

		return '' !== $built ? esc_html( $built ) : $title;
	}

	/**
	 * Site-chosen title separator for the views the format doesn't cover
	 * (archives, search, the front page) — core's own "Title – Site" pieces
	 * just join with this instead.
	 *
	 * @param string $sep Core's separator.
	 * @return string
	 */
	function blocklane_pro_seo_title_separator( $sep ) {
		if ( blocklane_pro_seo_disabled() ) {
			return $sep;
		}

		$custom = blocklane_pro_seo_settings()['title_separator'];

		return '' !== $custom ? $custom : $sep;
	}

	/**
	 * Label the designated sitemap page in the Pages list. Only while the
	 * feature is on — a pointed-at-but-disabled page isn't acting as the
	 * sitemap, so no state.
	 *
	 * @param string[] $states Post state labels.
	 * @param \WP_Post $post   Row's post.
	 * @return string[]
	 */
	function blocklane_pro_seo_sitemap_post_state( $states, $post ) {
		if ( (int) $post->ID === blocklane_pro_seo_html_sitemap_target() ) {
			$states['blocklane_seo_sitemap'] = __( 'Sitemap Page', 'blocklane' );
		}

		return $states;
	}

	/**
	 * Drop excluded post types from core's XML sitemap.
	 *
	 * @param array $post_types Post type objects, keyed by name.
	 * @return array
	 */
	function blocklane_pro_seo_sitemap_post_types( $post_types ) {
		foreach ( blocklane_pro_seo_settings()['sitemap_exclude_types'] as $type ) {
			unset( $post_types[ $type ] );
		}

		return $post_types;
	}

	/**
	 * Drop excluded taxonomies from core's XML sitemap.
	 *
	 * @param array $taxonomies Taxonomy objects, keyed by name.
	 * @return array
	 */
	function blocklane_pro_seo_sitemap_taxonomies( $taxonomies ) {
		foreach ( blocklane_pro_seo_settings()['sitemap_exclude_taxonomies'] as $taxonomy ) {
			unset( $taxonomies[ $taxonomy ] );
		}

		return $taxonomies;
	}

	/**
	 * Author archives out of the XML sitemap when toggled off — core ships
	 * a users provider on by default, which many sites consider thin
	 * content (and a username leak).
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider (or false to drop).
	 * @param string                      $name     Provider name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	function blocklane_pro_seo_sitemap_providers( $provider, $name ) {
		if ( 'users' === $name && ! blocklane_pro_seo_settings()['sitemap_authors'] ) {
			return false;
		}

		return $provider;
	}

	/**
	 * The page the HTML sitemap renders on, or 0 when the feature is off.
	 * Not gated by blocklane_pro_seo_disabled(): like the XML sitemap
	 * toggle, this is explicit opt-in content, not duplicate meta output.
	 *
	 * @return int Page ID.
	 */
	function blocklane_pro_seo_html_sitemap_target() {
		$settings = blocklane_pro_seo_settings();

		return ( $settings['html_sitemap'] && $settings['html_sitemap_page'] )
			? (int) $settings['html_sitemap_page']
			: 0;
	}

	/**
	 * Append the HTML sitemap to the designated page's content — the
	 * homepage/posts-page arrangement: pick a page in SEO settings and the
	 * sitemap renders there, after whatever the page itself contains.
	 * Classic-theme path; block themes ride the post-content block filter
	 * below (this bails there, so the two never double up).
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	function blocklane_pro_seo_html_sitemap_content( $content ) {
		$target = blocklane_pro_seo_html_sitemap_target();

		if (
			! $target
			|| wp_is_block_theme()
			|| ! is_page( $target )
			|| ! in_the_loop()
			|| ! is_main_query()
		) {
			return $content;
		}

		return $content . blocklane_pro_seo_html_sitemap_markup( $target );
	}

	/**
	 * Block-theme path: append to the rendered Post Content block instead.
	 * The block short-circuits to '' on an EMPTY page before the_content
	 * ever runs (and block templates render outside the classic loop), so
	 * the render filter is the reliable seam — it fires either way, and the
	 * block's context pins the append to the sitemap page itself rather
	 * than any query loop that happens to share the template.
	 *
	 * @param string    $block_content Rendered block HTML.
	 * @param array     $block         Parsed block.
	 * @param \WP_Block $instance      Block instance (context carrier).
	 * @return string
	 */
	function blocklane_pro_seo_html_sitemap_render_block( $block_content, $block, $instance ) {
		unset( $block );

		$target = blocklane_pro_seo_html_sitemap_target();
		if ( ! $target || ! is_page( $target ) ) {
			return $block_content;
		}

		$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
		if ( $post_id !== $target ) {
			return $block_content;
		}

		return $block_content . blocklane_pro_seo_html_sitemap_markup( $target );
	}

	/**
	 * The sitemap itself: every public type grouped under its label —
	 * hierarchical types as a nested tree (menu order, like the Pages
	 * screen), flat types newest-first. Noindexed content and the sitemap
	 * page itself are left out, matching the XML sitemap's exclusions.
	 *
	 * @param int $target The sitemap page's ID (excluded from the list).
	 * @return string
	 */
	function blocklane_pro_seo_html_sitemap_markup( $target ) {
		// Render-once latch: a hybrid arrangement can reach BOTH append
		// paths (a classic template whose page content renders through a
		// post-content block fires the_content inside the block render AND
		// the block filter after it) — whichever path appends first wins,
		// the second is a no-op instead of a duplicate sitemap.
		static $rendered = false;
		if ( $rendered ) {
			return '';
		}
		$rendered = true;

		// Everything hidden from search stays out of the sitemap too. Only
		// the hierarchical walker (wp_list_pages) needs an ID list — scoped
		// to those types; the flat lists exclude in SQL via the meta clause.
		$types        = blocklane_pro_seo_ordered_types();
		$hierarchical = array_values( array_filter( $types, 'is_post_type_hierarchical' ) );
		$exclude      = blocklane_pro_seo_noindexed_ids( $hierarchical );
		$exclude[]    = $target;

		/**
		 * Cap per post type for the HTML sitemap's flat lists.
		 *
		 * @param int $limit Default 200.
		 */
		$limit = (int) apply_filters( 'blocklane_pro_seo_html_sitemap_limit', 200 );

		$sections = '';

		foreach ( $types as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}

			$items = '';

			if ( is_post_type_hierarchical( $type ) ) {
				$items = wp_list_pages(
					array(
						'post_type'   => $type,
						'title_li'    => '',
						'echo'        => false,
						// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- the HTML sitemap's exclusion list is the administrator's own short list of pages; wp_list_pages() has no other way to honor it.
						'exclude'     => implode( ',', array_map( 'absint', $exclude ) ),
						'sort_column' => 'menu_order,post_title',
					)
				);
			} else {
				$posts = get_posts(
					array(
						'post_type'      => $type,
						'post_status'    => 'publish',
						'posts_per_page' => $limit,
						'post__not_in'   => array( absint( $target ) ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one ID, the sitemap page itself.
						'meta_query'     => array( blocklane_pro_seo_noindex_meta_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, one sitemap page render.
					)
				);
				foreach ( $posts as $post ) {
					$items .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">'
						. esc_html( get_the_title( $post ) )
						. '</a></li>';
				}
			}

			if ( '' === trim( $items ) ) {
				continue;
			}

			// Native details/summary: each section collapses from its
			// heading with no JS, and stays open by default.
			$sections .= '<details class="blocklane-seo-sitemap__section" open>'
				. '<summary class="blocklane-seo-sitemap__summary"><h2 class="blocklane-seo-sitemap__heading">'
				. esc_html( $object->labels->name )
				. '</h2></summary>'
				. '<ul class="blocklane-seo-sitemap__tree">' . $items . '</ul>'
				. '</details>';
		}

		if ( '' === $sections ) {
			return '';
		}

		$nav = '<nav class="blocklane-seo-sitemap" aria-label="'
			. esc_attr__( 'Site map', 'blocklane' )
			. '">' . $sections . '</nav>';

		// Rendered through a constrained group so the theme's own layout
		// system centers it at content width — the append lands outside the
		// post-content wrapper (or the block emitted nothing at all for an
		// empty page), so it must bring its own container.
		return do_blocks(
			'<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. $nav
			. '</div><!-- /wp:group -->'
		);
	}

	/**
	 * Tree styles for the sitemap page — the Pages dashboard's reorder-tree
	 * language (a rail down each level's siblings with a tick into every
	 * child, 20px indent per level), in theme-neutral grays. Queued only on
	 * the designated page, at wp_enqueue_scripts:100 so the head prints it
	 * after the theme's styles (it still wins ties) and before the
	 * Customizer's CSS (wp_head:101).
	 */
	function blocklane_pro_seo_html_sitemap_styles(): void {
		$target = blocklane_pro_seo_html_sitemap_target();

		if ( ! $target || ! is_page( $target ) ) {
			return;
		}

		// The chevron sits to the RIGHT of section headings (native marker
		// hidden) and the same treatment repeats on parent pages via the
		// injected toggle buttons blocklane_pro_seo_html_sitemap_script() adds.
		\blocklane_pro\Inline_Asset::style(
			'blocklane-seo-sitemap',
			'.blocklane-seo-sitemap__section{margin-block-start:1.5em;}'
			. '.blocklane-seo-sitemap__summary{cursor:pointer;display:inline-flex;align-items:center;gap:0.5em;list-style:none;}'
			. '.blocklane-seo-sitemap__summary::-webkit-details-marker{display:none;}'
			. '.blocklane-seo-sitemap__summary .blocklane-seo-sitemap__heading{margin:0;}'
			. '.blocklane-seo-sitemap__summary::after{content:"";width:0.4em;height:0.4em;border-inline-end:2px solid currentColor;border-block-end:2px solid currentColor;transform:rotate(-45deg) translateY(-15%);opacity:0.55;transition:transform 0.15s ease;}'
			. '.blocklane-seo-sitemap__section[open]>.blocklane-seo-sitemap__summary::after{transform:rotate(45deg) translateY(-15%);}'
			. '.blocklane-seo-sitemap__section>ul{margin-block-start:0.75em;}'
			. '.blocklane-seo-sitemap ul{list-style:none;margin:0;padding:0;}'
			. '.blocklane-seo-sitemap li{position:relative;padding-block:0.15em;}'
			. '.blocklane-seo-sitemap ul ul{margin-inline-start:0.35em;padding-inline-start:20px;border-inline-start:1px solid rgba(128,128,128,0.35);}'
			. '.blocklane-seo-sitemap ul ul>li::before{content:"";position:absolute;inset-inline-start:-20px;top:0.85em;width:14px;height:1px;background:rgba(128,128,128,0.35);}'
			. '.blocklane-seo-sitemap__toggle{display:inline-flex;align-items:center;justify-content:center;width:1.3em;height:1.3em;margin-inline-start:0.35em;padding:0;background:transparent;border:0;cursor:pointer;color:inherit;vertical-align:middle;}'
			. '.blocklane-seo-sitemap__toggle::before{content:"";width:0.35em;height:0.35em;border-inline-end:2px solid currentColor;border-block-end:2px solid currentColor;transform:rotate(45deg);opacity:0.55;transition:transform 0.15s ease;}'
			. '.blocklane-seo-sitemap .is-collapsed>.blocklane-seo-sitemap__toggle::before{transform:rotate(-45deg);}'
			. '.blocklane-seo-sitemap .is-collapsed>ul.children{display:none;}'
		);
	}

	/**
	 * Parent pages on the sitemap page collapse like the sections: a chevron
	 * button after each parent link toggles its children. Injected
	 * client-side so the wp_list_pages walker stays stock. Printed only on the
	 * designated page, at wp_head:20, through the inline-asset door.
	 */
	function blocklane_pro_seo_html_sitemap_script(): void {
		$target = blocklane_pro_seo_html_sitemap_target();

		if ( ! $target || ! is_page( $target ) ) {
			return;
		}

		\blocklane_pro\Inline_Asset::print_script(
			'document.addEventListener("DOMContentLoaded",function(){'
			. 'document.querySelectorAll(".blocklane-seo-sitemap .page_item_has_children").forEach(function(li){'
			. 'var link=li.querySelector(":scope > a");if(!link){return;}'
			. 'var btn=document.createElement("button");'
			. 'btn.type="button";btn.className="blocklane-seo-sitemap__toggle";'
			. 'btn.setAttribute("aria-expanded","true");'
			. 'btn.setAttribute("aria-label",' . wp_json_encode( __( 'Toggle child pages', 'blocklane' ) ) . ');'
			. 'link.insertAdjacentElement("afterend",btn);'
			. 'btn.addEventListener("click",function(){'
			. 'var collapsed=li.classList.toggle("is-collapsed");'
			. 'btn.setAttribute("aria-expanded",String(!collapsed));'
			. '});'
			. '});'
			. '});',
			array( 'id' => 'blocklane-seo-sitemap-js' )
		);
	}

	/**
	 * Whether the current view is an archive the settings ask to noindex —
	 * the one answer robots, canonicals, and structured data all share, so
	 * the three can never disagree (noindex paired with a canonical or
	 * schema sends search engines conflicting signals).
	 *
	 * @return bool
	 */
	function blocklane_pro_seo_archive_noindexed() {
		$settings = blocklane_pro_seo_settings();

		return ( is_author() && $settings['noindex_author_archives'] )
			|| ( is_date() && $settings['noindex_date_archives'] );
	}

	/**
	 * Print a self-referential rel=canonical on archive views — core's
	 * rel_canonical only covers singular pages. Terms, post-type archives,
	 * authors, dates, and the posts index each point at their clean URL,
	 * with /page/N/ kept for paginated pages (a paginated archive is its
	 * own canonical, not page 1). Search and 404 emit nothing.
	 */
	function blocklane_pro_seo_archive_canonical() {
		if ( blocklane_pro_seo_disabled() || is_singular() || is_search() || is_404() ) {
			return;
		}

		// Not on the site-lock splash — see blocklane_pro_seo_meta_tags().
		if ( did_action( 'blocklane_pro_site_lock_splash' ) ) {
			return;
		}

		$settings = blocklane_pro_seo_settings();
		if ( ! $settings['archive_canonicals'] ) {
			return;
		}

		// A noindexed archive gets no canonical — conflicting signals.
		if ( blocklane_pro_seo_archive_noindexed() ) {
			return;
		}

		$url = '';

		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_home() ) {
			$url = get_permalink( (int) get_option( 'page_for_posts' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$url = get_term_link( get_queried_object() );
		} elseif ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$url  = get_post_type_archive_link( is_array( $type ) ? reset( $type ) : $type );
		} elseif ( is_author() ) {
			$url = get_author_posts_url( get_queried_object_id() );
		} elseif ( is_day() ) {
			$url = get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
		} elseif ( is_month() ) {
			$url = get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
		} elseif ( is_year() ) {
			$url = get_year_link( (int) get_query_var( 'year' ) );
		}

		if ( ! $url || is_wp_error( $url ) ) {
			return;
		}

		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$url = get_option( 'permalink_structure' )
				? trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged, 'paged' )
				: add_query_arg( 'paged', $paged, $url );
		}

		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
	}

	/**
	 * Keep the stored default share-image URL honest when its attachment
	 * changes: an edit (new file, regenerated sizes) re-resolves it, a
	 * deletion clears both the URL and the dangling ID. Cheap early-out —
	 * the settings option is autoloaded, so non-matching attachments cost
	 * nothing.
	 *
	 * @param int  $att_id  Attachment ID that changed.
	 * @param bool $deleted True when the attachment is being deleted.
	 */
	function blocklane_pro_seo_refresh_og_image_url( $att_id, $deleted = false ) {
		$stored = get_option( 'blocklane_pro_seo' );
		if ( ! is_array( $stored )
			|| empty( $stored['default_og_image_id'] )
			|| (int) $stored['default_og_image_id'] !== (int) $att_id ) {
			return;
		}

		if ( $deleted ) {
			$stored['default_og_image_id']  = 0;
			$stored['default_og_image_url'] = '';
		} else {
			$stored['default_og_image_url'] = blocklane_pro_seo_og_image_url( $att_id );
		}

		update_option( 'blocklane_pro_seo', $stored );
	}

	/**
	 * The share-image URL for an attachment. THE derivation rule — every
	 * caller goes through here so a future change (a cropped OG size, say)
	 * lands in one place instead of the five call sites this replaced.
	 *
	 * Plugin-side callers reach it through blocklane_pro\Seo::og_image_url(),
	 * which defers here when the runtime is loaded and mirrors this line when
	 * it is not; the runtime is self-contained and must never reach into the
	 * plugin, a contract from the bake era when it also ran as a generated
	 * copy outside the plugin (docs/archive/bake-contract.md).
	 *
	 * @param int $att_id Attachment ID.
	 * @return string Absolute URL, or '' when it cannot be resolved.
	 */
	function blocklane_pro_seo_og_image_url( $att_id ) {
		$att_id = (int) $att_id;
		return $att_id ? (string) wp_get_attachment_image_url( $att_id, 'full' ) : '';
	}

	/**
	 * Whether a stored absolute URL still belongs to this site.
	 *
	 * The share-image URL is resolved once at settings save so the front end
	 * never pays a per-page attachment lookup. That freeze goes stale when the
	 * site moves domain without a serialized-aware search-replace: the stored
	 * URL keeps pointing at the old host and the ID-resolve fallback never
	 * fires, because it is gated on the URL being EMPTY, not on it being
	 * wrong. A host comparison is a string check — no query — so the common
	 * case stays free while a moved site heals itself on the next request.
	 *
	 * Off-site hosts are treated as valid: a CDN or offload plugin rewriting
	 * attachment URLs to its own domain is doing exactly what it should.
	 *
	 * @param string $url Stored absolute URL.
	 * @return bool Whether the URL is usable as-is.
	 */
	function blocklane_pro_seo_og_image_url_is_current( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$stored_host = wp_parse_url( $url, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! $stored_host || ! $home_host ) {
			return true;
		}

		// Same host: current. A different host is only suspect when it looks
		// like a stale copy of OUR upload path rather than a deliberate
		// rewrite — which we cannot tell apart, so trust it and let the
		// uploads-path test below make the call.
		if ( strtolower( $stored_host ) === strtolower( $home_host ) ) {
			return true;
		}

		$uploads = wp_get_upload_dir();
		$base    = (string) $uploads['baseurl'];
		$base_host = $base ? wp_parse_url( $base, PHP_URL_HOST ) : '';

		// The uploads base is where this site currently serves attachments —
		// including through an offload plugin, which filters it. Matching it
		// means the URL is live; matching neither means it is a leftover from
		// somewhere this site no longer is.
		return $base_host && strtolower( $stored_host ) === strtolower( $base_host );
	}

	/**
	 * Daily revalidation of a stored share-image URL that LOOKS current.
	 *
	 * The host checks above are string comparisons, so they cannot see an
	 * offload/CDN plugin activated AFTER the settings save that rewrites
	 * attachment URLs per attachment (wp_get_attachment_url and friends)
	 * without touching home_url or the uploads base: the stored local URL
	 * keeps passing the host test while the plugin may have moved — or, with
	 * "remove local files", deleted — the file it points at. Re-deriving
	 * through blocklane_pro_seo_og_image_url() runs those filters, but costs
	 * the per-page attachment lookup that storing the URL exists to avoid. So
	 * pay it at most once a day: the first no-featured-image page after the
	 * stamp ages out re-derives and remembers the answer in its own small
	 * autoloaded row, and every other page reads that from alloptions for
	 * free. The probe is keyed to the exact stored URL it validated, so a
	 * settings re-save (which re-derives and re-stores) retires it
	 * immediately rather than after the TTL.
	 *
	 * The answer is served, never written back into blocklane_pro_seo — the
	 * settings row stays admin-save-only (no front-end read-modify-write to
	 * race a concurrent settings save), and a wrong answer ages out in a day.
	 * The probe write itself is a full overwrite of derived state, not a
	 * read-modify-write, so concurrent expired requests cannot clobber
	 * anything — they just derive the same answer twice.
	 *
	 * @param string $stored_url Stored URL, already host-checked as current.
	 * @param int    $att_id     The default share image attachment ID.
	 * @return string The URL to emit.
	 */
	function blocklane_pro_seo_og_image_probe( $stored_url, $att_id ) {
		$probe = get_option( 'blocklane_pro_seo_og_probe', null );
		if ( is_array( $probe )
			&& ( $probe['for'] ?? null ) === $stored_url
			&& (int) ( $probe['checked'] ?? 0 ) > time() - DAY_IN_SECONDS ) {
			return '' !== (string) ( $probe['url'] ?? '' ) ? (string) $probe['url'] : $stored_url;
		}

		$derived = blocklane_pro_seo_og_image_url( $att_id );
		update_option(
			'blocklane_pro_seo_og_probe',
			array(
				'for'     => $stored_url,
				'url'     => $derived,
				'checked' => time(),
			),
			true
		);

		// A failed resolve ('' — the attachment row is gone but the
		// delete_attachment hook never fired, e.g. a direct DB removal) keeps
		// the stored URL: a possibly dead image beats a guaranteed missing
		// one, and the deletion hook is the authoritative eraser.
		return '' !== $derived ? $derived : $stored_url;
	}

	add_action(
		'delete_attachment',
		function ( $att_id ) {
			blocklane_pro_seo_refresh_og_image_url( $att_id, true );
		}
	);
	add_filter(
		'wp_update_attachment_metadata',
		function ( $data, $att_id ) {
			blocklane_pro_seo_refresh_og_image_url( $att_id );
			return $data;
		},
		10,
		2
	);

	/**
	 * Print the meta description and Open Graph tags.
	 *
	 * A static front page goes through its own per-page flow like any other
	 * page (custom meta, excerpt, content cut); a posts-index front page has
	 * no post to draw from, so it falls back to the site tagline.
	 */
	function blocklane_pro_seo_meta_tags() {
		if ( blocklane_pro_seo_disabled() ) {
			return;
		}

		// Never on the site-lock splash: the splash reuses wp_head()/wp_footer()
		// with the HIDDEN request's query state, so these tags would describe
		// the page the lock exists to hide. The gate fires this action before
		// wp_head, so one did_action() answers it for the whole request.
		if ( did_action( 'blocklane_pro_site_lock_splash' ) ) {
			return;
		}

		$post     = blocklane_pro_seo_current_post();
		$is_front = is_front_page();

		if ( ! $post && ! $is_front ) {
			return;
		}

		if ( $post ) {
			$description = blocklane_pro_seo_description_for( $post );
		} else {
			$description = trim( (string) get_bloginfo( 'description' ) );
		}

		$custom_meta = $post ? (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_TITLE, true ) : '';
		$title       = '' !== trim( $custom_meta ) ? trim( $custom_meta ) : wp_get_document_title();
		$url         = $post ? get_permalink( $post ) : home_url( '/' );
		$image       = $post ? get_the_post_thumbnail_url( $post, 'full' ) : '';

		// No featured image? The site-wide default share image steps in, so
		// links shared from any page still unfurl with a card. Its URL is
		// resolved at settings save (Seo::save()) and stored — resolving the
		// attachment here cost two queries on every page without a featured
		// image.
		//
		// The live resolve covers three cases: options saved before the URL
		// key existed, and — since the stored URL is frozen at save — a site
		// that has moved domain or changed where it serves uploads from. That
		// staleness check is a host comparison, not a query, so the ordinary
		// path stays free. A URL the host check CANNOT fault still gets the
		// once-a-day probe: an offload/CDN plugin activated after the save
		// rewrites attachment URLs without moving any host, which only a real
		// re-derivation can see (blocklane_pro_seo_og_image_probe).
		if ( ! $image ) {
			$seo_settings = blocklane_pro_seo_settings();
			$image        = $seo_settings['default_og_image_url'];
			if ( $seo_settings['default_og_image_id'] ) {
				if ( ! blocklane_pro_seo_og_image_url_is_current( $image ) ) {
					$image = blocklane_pro_seo_og_image_url( $seo_settings['default_og_image_id'] );
				} else {
					// is_current() returned true, so $image is non-empty.
					$image = blocklane_pro_seo_og_image_probe( $image, $seo_settings['default_og_image_id'] );
				}
			}
		}

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		/**
		 * Whether Blocklane SEO should print Open Graph / Twitter card tags.
		 * Off automatically when a dedicated social/OG plugin is detected via
		 * blocklane_pro_disable_seo; this filter narrows just the social tags.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'blocklane_pro_seo_og_enabled', true ) ) {
			return;
		}

		$og = array(
			'og:type'        => $is_front ? 'website' : 'article',
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $url,
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:image'       => $image ? $image : '',
		);

		foreach ( $og as $property => $content ) {
			if ( '' !== $content && false !== $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
			}
		}

		echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
	}

	/**
	 * Ask search engines to skip a post marked noindex, and — when toggled —
	 * author and date archives site-wide (core already noindexes search
	 * results; these two are the other classic thin-content archives).
	 * noindex,follow: skip the page, still crawl the links on it.
	 *
	 * @param array $robots Core robots directives.
	 * @return array
	 */
	function blocklane_pro_seo_robots( $robots ) {
		if ( blocklane_pro_seo_disabled() ) {
			return $robots;
		}

		$post = blocklane_pro_seo_current_post();
		if ( $post && get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_NOINDEX, true ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		if ( blocklane_pro_seo_archive_noindexed() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * Keep noindexed posts out of core's wp-sitemap.xml.
	 *
	 * @param array $args WP_Query args for a sitemap page.
	 * @return array
	 */
	function blocklane_pro_seo_sitemap_exclude( $args ) {
		if ( blocklane_pro_seo_disabled() ) {
			return $args;
		}

		$exclusion = blocklane_pro_seo_noindex_meta_clause();

		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array( $exclusion ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded sitemap page query.
		} else {
			$args['meta_query'][] = $exclusion;
		}

		return $args;
	}

	/**
	 * Print the JSON-LD graph.
	 *
	 * Front page: WebSite + Organization anchors. Singular with a schema type:
	 * the Article or FAQPage node plus the Organization node its publisher
	 * reference points at. Nothing anywhere else — absence beats junk schema.
	 */
	function blocklane_pro_seo_jsonld() {
		if ( blocklane_pro_seo_disabled() ) {
			return;
		}

		// Not on the site-lock splash — see blocklane_pro_seo_meta_tags().
		if ( did_action( 'blocklane_pro_site_lock_splash' ) ) {
			return;
		}

		$graph = array();

		if ( is_front_page() ) {
			$graph[] = array(
				'@type'       => 'WebSite',
				'@id'         => home_url( '/#website' ),
				'url'         => home_url( '/' ),
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'publisher'   => array( '@id' => home_url( '/#organization' ) ),
			);
			$graph[] = blocklane_pro_seo_organization_node();
		}

		$post = blocklane_pro_seo_current_post();
		if ( $post && ! get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_NOINDEX, true ) ) {
			$type = blocklane_pro_seo_sanitize_schema_type(
				get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE, true )
			);

			$node = '';
			if ( 'article' === $type ) {
				$node = blocklane_pro_seo_article_node( $post );
			} elseif ( 'faq' === $type ) {
				$node = blocklane_pro_seo_faq_node( $post );
			}

			if ( $node ) {
				$graph[] = $node;
				if ( ! is_front_page() && 'article' === $type ) {
					$graph[] = blocklane_pro_seo_organization_node();
				}
			}
		}

		if ( ! $graph ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		// Through the inline-asset door: `<`, `>` and `&` are encoded, so no
		// value can close the tag; slashes stay unescaped.
		\blocklane_pro\Inline_Asset::print_json_ld( $data );
	}

	/**
	 * The Organization node shared by WebSite and Article publisher refs.
	 * Site-level settings override the theme-derived defaults: org name over
	 * the site title, the chosen logo over the customizer logo, plus an
	 * optional description and sameAs profile links.
	 *
	 * @return array
	 */
	function blocklane_pro_seo_organization_node() {
		$settings = blocklane_pro_seo_settings();

		$node = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => '' !== $settings['org_name'] ? $settings['org_name'] : get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		if ( '' !== $settings['org_description'] ) {
			$node['description'] = $settings['org_description'];
		}

		$logo_id = $settings['org_logo_id'] ? $settings['org_logo_id'] : (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$node['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		if ( $settings['org_profiles'] ) {
			$node['sameAs'] = $settings['org_profiles'];
		}

		// Local-business mode: the same node (same @id, so the WebSite and
		// Article publisher references still resolve) becomes a LocalBusiness
		// carrying whichever contact details are filled in.
		if ( $settings['local_business'] ) {
			$node['@type'] = 'LocalBusiness';

			$address = array();
			foreach ( array(
				'streetAddress'   => 'local_address',
				'addressLocality' => 'local_city',
				'addressRegion'   => 'local_region',
				'postalCode'      => 'local_postal_code',
				'addressCountry'  => 'local_country',
			) as $prop => $key ) {
				if ( '' !== $settings[ $key ] ) {
					$address[ $prop ] = $settings[ $key ];
				}
			}
			if ( $address ) {
				$node['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
			}

			if ( '' !== $settings['local_phone'] ) {
				$node['telephone'] = $settings['local_phone'];
			}

			if ( '' !== $settings['local_latitude'] && '' !== $settings['local_longitude'] ) {
				$node['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $settings['local_latitude'],
					'longitude' => (float) $settings['local_longitude'],
				);
			}

			$hours = blocklane_pro_seo_opening_hours( $settings['local_hours'] );
			if ( $hours ) {
				$node['openingHoursSpecification'] = $hours;
			}
		}

		return $node;
	}

	/**
	 * OpeningHoursSpecification nodes from the stored day map, with days
	 * sharing identical hours grouped into one node (the compact form
	 * Google's docs show). Days without a valid open AND close are closed.
	 *
	 * @param array $hours Day key => {open, close} map (24h HH:MM).
	 * @return array
	 */
	function blocklane_pro_seo_opening_hours( $hours ) {
		$days = array(
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday',
		);

		$grouped = array();
		foreach ( $days as $key => $label ) {
			$row   = ( isset( $hours[ $key ] ) && is_array( $hours[ $key ] ) ) ? $hours[ $key ] : array();
			$open  = isset( $row['open'] ) ? trim( (string) $row['open'] ) : '';
			$close = isset( $row['close'] ) ? trim( (string) $row['close'] ) : '';

			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $open )
				|| ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $close ) ) {
				continue;
			}

			$grouped[ $open . '|' . $close ][] = $label;
		}

		$specs = array();
		foreach ( $grouped as $times => $day_labels ) {
			list( $open, $close ) = explode( '|', $times );

			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => count( $day_labels ) === 1 ? $day_labels[0] : $day_labels,
				'opens'     => $open,
				'closes'    => $close,
			);
		}

		return $specs;
	}

	/**
	 * The Article node for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	function blocklane_pro_seo_article_node( $post ) {
		$node = array(
			'@type'            => 'Article',
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
		);

		$description = blocklane_pro_seo_description_for( $post );
		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );
		if ( $image ) {
			$node['image'] = $image;
		}

		return $node;
	}

	/**
	 * The FAQPage node for a post, built from its core/details blocks
	 * (summary = question, content = answer) and core/accordion-item blocks
	 * (heading = question, panel = answer), searched at any nesting depth.
	 * Returns '' when no usable Q&A pair exists — never an empty FAQPage.
	 *
	 * @param WP_Post $post Post.
	 * @return array|string
	 */
	function blocklane_pro_seo_faq_node( $post ) {
		// Memoized per post — the parse + per-details render is the
		// expensive part (the template's own body render is core's pass and
		// unavoidable from here, but this side never runs twice).
		static $memo = array();

		if ( ! isset( $memo[ $post->ID ] ) ) {
			$memo[ $post->ID ] = blocklane_pro_seo_collect_faq( parse_blocks( $post->post_content ) );
		}

		$questions = $memo[ $post->ID ];

		if ( ! $questions ) {
			return '';
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}

	/**
	 * Walk a parsed block tree collecting Question nodes from core/details
	 * and core/accordion-item blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array
	 */
	function blocklane_pro_seo_collect_faq( $blocks ) {
		$questions = array();

		foreach ( $blocks as $block ) {
			if ( 'core/details' === ( $block['blockName'] ?? '' ) ) {
				$html = render_block( $block );

				$question = '';
				$answer   = '';
				if ( preg_match( '#<summary[^>]*>(.*?)</summary>#s', $html, $m ) ) {
					$question = trim( wp_strip_all_tags( $m[1] ) );
					$answer   = trim( wp_strip_all_tags( str_replace( $m[0], '', $html ), true ) );
				}

				if ( '' !== $question && '' !== $answer ) {
					$questions[] = array(
						'@type'          => 'Question',
						'name'           => $question,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer,
						),
					);
				}
				continue;
			}

			if ( 'core/accordion' === ( $block['blockName'] ?? '' ) ) {
				// Rendered as a WHOLE, never per item: an accordion-item
				// rendered outside its parent loses the parent's
				// Interactivity context and trips
				// WP_Interactivity_API::evaluate notices.
				$html = render_block( $block );

				// One chunk per item, split on the item's WHOLE opening tag —
				// splitting mid-tag leaves the tag's own attribute tail as
				// bare text that survives the strip and pollutes the answer.
				// The lookahead (not \b) keeps the render-appended
				// `wp-block-accordion-item-is-layout-*` class from matching.
				// The question lives in the heading's toggle-title span, but
				// the toggle ICON rides the same heading element — so the
				// whole heading is removed before the answer strip, or every
				// answer would start with "+". The answer is everything after
				// the heading in the chunk, which survives arbitrarily nested
				// panel content.
				$chunks = preg_split( '#<div[^>]*\bwp-block-accordion-item(?![\w-])[^>]*>#', $html );
				array_shift( $chunks );
				foreach ( $chunks as $chunk ) {
					$question = '';
					$answer   = '';
					if ( preg_match( '#<h[1-6][^>]*wp-block-accordion-heading.*?</h[1-6]>#s', $chunk, $h )
						&& preg_match( '#<span[^>]*__toggle-title[^>]*>(.*?)</span>#s', $h[0], $m ) ) {
						$question = trim( wp_strip_all_tags( $m[1] ) );
						$answer   = trim( wp_strip_all_tags( str_replace( $h[0], '', $chunk ), true ) );
					}

					if ( '' !== $question && '' !== $answer ) {
						$questions[] = array(
							'@type'          => 'Question',
							'name'           => $question,
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $answer,
							),
						);
					}
				}
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$questions = array_merge(
					$questions,
					blocklane_pro_seo_collect_faq( $block['innerBlocks'] )
				);
			}
		}

		return $questions;
	}

	/**
	 * The "not hidden from search" meta_query clause — exclusion in SQL,
	 * shared by the XML sitemap, the HTML sitemap's flat lists, and
	 * llms.txt, so no unbounded ID list is ever built in PHP.
	 *
	 * @return array
	 */
	function blocklane_pro_seo_noindex_meta_clause() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => BLOCKLANE_PRO_SEO_META_NOINDEX,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => BLOCKLANE_PRO_SEO_META_NOINDEX,
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	/**
	 * Published content marked "hide from search engines", as IDs. Only for
	 * wp_list_pages' exclude list — it has no meta_query support — so the
	 * query is scoped to the hierarchical types being walked, never 'any'.
	 *
	 * @param string[] $types Post types to scan.
	 * @return int[] Post IDs.
	 */
	function blocklane_pro_seo_noindexed_ids( $types ) {
		if ( ! $types ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => BLOCKLANE_PRO_SEO_META_NOINDEX, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- scoped to the hierarchical types, one sitemap-page render.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * The listable post types with pages leading, posts second, any other
	 * types after in registration order — the site's structure before the
	 * feed content.
	 *
	 * @return string[]
	 */
	function blocklane_pro_seo_ordered_types() {
		$types = blocklane_pro_seo_admin_types();

		return array_values(
			array_unique(
				array_merge( array_intersect( array( 'page', 'post' ), $types ), $types )
			)
		);
	}

	/* ---- llms.txt ---------------------------------------------------------
	 *
	 * The llmstxt.org manifest: a markdown index of the site's published
	 * content at /llms.txt, so AI crawlers and assistants get a curated map
	 * instead of scraping nav menus. Generated from the same content the
	 * HTML sitemap lists, in its section order. Explicit opt-in content, so
	 * like the sitemaps it is NOT gated by blocklane_pro_seo_disabled().
	 */

	/**
	 * Serve /llms.txt when the feature is on.
	 *
	 * @param WP $wp The WP environment instance, mid parse.
	 */
	function blocklane_pro_seo_llms_txt( $wp ) {
		if ( ! blocklane_pro_seo_settings()['llms_txt'] ) {
			return;
		}

		if ( 'llms.txt' !== blocklane_pro_seo_request_path( $wp ) ) {
			return;
		}

		// Never while the site lock is gating: this manifest is the hidden
		// site's content inventory, and parse_request runs BEFORE the gate's
		// template_redirect hook can refuse it. Falling through lands the
		// request on the splash like any other locked URL. Visitor-agnostic
		// (is_gating, not a per-visitor bypass) for the same shared-cache
		// reason as Site_Lock::is_gating() documents.
		if ( class_exists( 'blocklane_pro\Site_Lock', false ) && \blocklane_pro\Site_Lock::is_gating() ) {
			return;
		}

		// Cached: AI crawlers poll this URL aggressively and the body costs
		// a query per content type. The flush hooks in boot() drop it on
		// content, meta, and settings changes; the TTL is just a backstop.
		$body = get_transient( 'blocklane_pro_seo_llms_txt' );
		if ( false === $body || ! is_string( $body ) ) {
			$body = blocklane_pro_seo_llms_txt_body();
			set_transient( 'blocklane_pro_seo_llms_txt', $body, HOUR_IN_SECONDS );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text document, built below from esc-free markdown.
		exit;
	}

	/** Drop the cached llms.txt body (content or settings changed). */
	function blocklane_pro_seo_llms_txt_flush() {
		delete_transient( 'blocklane_pro_seo_llms_txt' );
	}

	/**
	 * Flush the llms.txt cache when THIS module's post meta changes — the
	 * manifest reflects noindex state and curated descriptions.
	 *
	 * @param int|int[] $meta_id  Meta row ID(s) (unused).
	 * @param int       $post_id  Post ID (unused).
	 * @param string    $meta_key Changed key.
	 */
	function blocklane_pro_seo_llms_txt_flush_meta( $meta_id, $post_id, $meta_key ) {
		unset( $meta_id, $post_id );

		if ( in_array( $meta_key, array( BLOCKLANE_PRO_SEO_META_NOINDEX, BLOCKLANE_PRO_SEO_META_DESCRIPTION ), true ) ) {
			blocklane_pro_seo_llms_txt_flush();
		}
	}

	/**
	 * Build the manifest: H1 site title, blockquote summary (tagline, or the
	 * Organization description as fallback), then one H2 section per content
	 * type of markdown links — hierarchical types in menu order, flat types
	 * newest-first, noindexed content left out. A curated per-post SEO
	 * description becomes the link's annotation; auto-excerpts are left out
	 * (a map, not a content dump).
	 *
	 * @return string
	 */
	function blocklane_pro_seo_llms_txt_body() {
		$settings = blocklane_pro_seo_settings();

		/**
		 * Cap per post type for the llms.txt listing.
		 *
		 * @param int $limit Default 100.
		 */
		$limit = (int) apply_filters( 'blocklane_pro_seo_llms_txt_limit', 100 );

		$line = static function ( $text ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		};

		$out = '# ' . $line( get_bloginfo( 'name' ) ) . "\n";

		$summary = $line( get_bloginfo( 'description' ) );
		if ( '' === $summary ) {
			$summary = $line( $settings['org_description'] );
		}
		if ( '' !== $summary ) {
			$out .= "\n> " . $summary . "\n";
		}

		foreach ( blocklane_pro_seo_ordered_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'meta_query'     => array( blocklane_pro_seo_noindex_meta_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, cached llms.txt render.
					'orderby'        => is_post_type_hierarchical( $type ) ? 'menu_order title' : 'date',
					'order'          => is_post_type_hierarchical( $type ) ? 'ASC' : 'DESC',
				)
			);

			if ( ! $posts ) {
				continue;
			}

			$out .= "\n## " . $line( $object->labels->name ) . "\n\n";

			foreach ( $posts as $post ) {
				// Square brackets would end the markdown link text early.
				$title = str_replace( array( '[', ']' ), array( '(', ')' ), $line( get_the_title( $post ) ) );
				$item  = '- [' . $title . '](' . get_permalink( $post ) . ')';

				$description = $line( get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_DESCRIPTION, true ) );
				if ( '' !== $description ) {
					$item .= ': ' . $description;
				}

				$out .= $item . "\n";
			}
		}

		return $out;
	}

	/* ---- IndexNow ---------------------------------------------------------
	 *
	 * The modern replacement for the retired search-engine pings: content
	 * changes are pushed to api.indexnow.org (Bing, Yandex, Seznam, Naver —
	 * Google doesn't participate), which fans them out to every participating
	 * engine. Explicit opt-in content like the sitemaps, so it is NOT gated
	 * by blocklane_pro_seo_disabled(). The key file is served straight off
	 * parse_request — no rewrite rules, nothing to flush.
	 */

	/**
	 * Whether IndexNow submissions should happen at all: feature on, a key
	 * minted, and the site actually asking to be indexed.
	 *
	 * @return bool
	 */
	function blocklane_pro_seo_indexnow_active() {
		$settings = blocklane_pro_seo_settings();

		return $settings['indexnow']
			&& '' !== $settings['indexnow_key']
			&& 1 === (int) get_option( 'blog_public', 1 );
	}

	/**
	 * The requested path relative to the site root — the seam both virtual
	 * text files (the IndexNow key file, llms.txt) match against. Served off
	 * parse_request so no rewrite rule (and no flush) is ever needed.
	 *
	 * @param WP $wp The WP environment instance, mid parse.
	 * @return string Path without surrounding slashes ('' for the root).
	 */
	function blocklane_pro_seo_request_path( $wp ) {
		$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';

		if ( '' === $request ) {
			// Plain permalinks leave $wp->request empty — read the raw path,
			// minus any subdirectory the install lives in.
			$path      = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared verbatim against fixed filenames, never output.
			$request   = trim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' );
			$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			if ( '' !== $home_path && 0 === strpos( $request, $home_path . '/' ) ) {
				$request = substr( $request, strlen( $home_path ) + 1 );
			}
		}

		return $request;
	}

	/**
	 * Serve the key file at /<key>.txt — IndexNow's proof of site ownership.
	 * Submissions declare keyLocation, so the engines fetch exactly this URL.
	 *
	 * @param WP $wp The WP environment instance, mid parse.
	 */
	function blocklane_pro_seo_indexnow_key_file( $wp ) {
		$settings = blocklane_pro_seo_settings();
		if ( ! $settings['indexnow'] || '' === $settings['indexnow_key'] ) {
			return;
		}

		if ( blocklane_pro_seo_request_path( $wp ) !== $settings['indexnow_key'] . '.txt' ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $settings['indexnow_key'] );
		exit;
	}

	/**
	 * Per-request URL queue. The first queued URL arms one shutdown send, so
	 * a request touching several posts still submits a single batch.
	 *
	 * @param string|null $url URL to queue, or null to read the queue.
	 * @return string[] The queued URLs.
	 */
	function blocklane_pro_seo_indexnow_queue( $url = null ) {
		static $urls = array();

		if ( is_string( $url ) && '' !== $url ) {
			if ( ! $urls ) {
				add_action( 'shutdown', 'blocklane_pro_seo_indexnow_send' );
			}
			$urls[ $url ] = true;
		}

		return array_keys( $urls );
	}

	/**
	 * Submit the queued URLs — one non-blocking POST after the response is
	 * done; a slow or down endpoint can't slow a save.
	 */
	function blocklane_pro_seo_indexnow_send() {
		$urls = blocklane_pro_seo_indexnow_queue();
		if ( ! $urls || ! blocklane_pro_seo_indexnow_active() ) {
			return;
		}

		$settings = blocklane_pro_seo_settings();

		/**
		 * The IndexNow submission endpoint. Any participating engine's
		 * endpoint works (they share submissions); also handy to point at a
		 * local sink when testing.
		 *
		 * @param string $endpoint Default https://api.indexnow.org/indexnow.
		 */
		$endpoint = apply_filters( 'blocklane_pro_seo_indexnow_endpoint', 'https://api.indexnow.org/indexnow' );

		wp_remote_post(
			$endpoint,
			array(
				'timeout'  => 3,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'     => wp_json_encode(
					array(
						'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
						'key'         => $settings['indexnow_key'],
						'keyLocation' => home_url( '/' . $settings['indexnow_key'] . '.txt' ),
						'urlList'     => $urls,
					)
				),
			)
		);
	}

	/**
	 * Whether a post's URL is worth submitting: a viewable type, not
	 * password-protected, not hidden from search.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	function blocklane_pro_seo_indexnow_eligible( $post ) {
		return $post instanceof WP_Post
			&& is_post_type_viewable( $post->post_type )
			&& '' === $post->post_password
			&& ! get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_NOINDEX, true );
	}

	/**
	 * Queue on publish and on updates to published content. Hooked on
	 * wp_after_insert_post — NOT transition_post_status — because the block
	 * editor's REST save persists the request's meta (including the noindex
	 * flag) after the status transition fires; this hook runs once meta and
	 * terms are saved, so eligibility reads the state the author actually
	 * published. Revisions/autosaves never carry 'publish' status, so the
	 * status check filters them.
	 *
	 * @param int          $post_id     Post ID (unused — the object is given).
	 * @param WP_Post      $post        The saved post.
	 * @param bool         $update      Whether this was an update (unused).
	 * @param WP_Post|null $post_before Pre-update post (unused).
	 */
	function blocklane_pro_seo_indexnow_after_insert( $post_id, $post, $update = false, $post_before = null ) {
		unset( $post_id, $update, $post_before );

		if ( ! $post instanceof WP_Post
			|| 'publish' !== $post->post_status
			|| ! blocklane_pro_seo_indexnow_active()
			|| ! blocklane_pro_seo_indexnow_eligible( $post ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( $url ) {
			blocklane_pro_seo_indexnow_queue( $url );
		}
	}

	/**
	 * Queue a published post's URL as it's trashed or deleted, so engines
	 * recrawl and drop it. Hooked before the trash rename (wp_trash_post) —
	 * afterwards the permalink already carries the __trashed suffix.
	 *
	 * @param int $post_id Post ID.
	 */
	function blocklane_pro_seo_indexnow_removed( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post
			|| 'publish' !== $post->post_status
			|| ! blocklane_pro_seo_indexnow_active()
			|| ! blocklane_pro_seo_indexnow_eligible( $post ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( $url ) {
			blocklane_pro_seo_indexnow_queue( $url );
		}
	}

	/**
	 * Stage/commit store for the core Breadcrumbs block's trail.
	 *
	 * Core's Breadcrumbs block (WP 7.0) renders markup only — no
	 * BreadcrumbList schema. Its `block_core_breadcrumbs_items` filter hands
	 * over the final trail (including other plugins' modifications) but NOT
	 * the block instance, so the trail is STAGED there and committed —
	 * or discarded — moments later from the render_block filter, where the
	 * block's context says which post it rendered for. First committed
	 * trail wins.
	 *
	 * @param string     $op    'stage' (hold a just-filtered trail),
	 *                          'commit' (adopt the staged trail), or 'get'.
	 * @param array|null $items Trail for 'stage' (null discards the stage).
	 * @return array|null The committed trail, or null when none.
	 */
	function blocklane_pro_seo_breadcrumb_store( $op = 'get', $items = null ) {
		static $staged    = null;
		static $committed = null;

		if ( 'stage' === $op ) {
			$staged = is_array( $items ) ? $items : null;
		} elseif ( 'commit' === $op ) {
			if ( null === $committed && null !== $staged ) {
				$committed = $staged;
			}
			$staged = null;
		}

		return $committed;
	}

	/**
	 * Stage side: runs after every other `block_core_breadcrumbs_items`
	 * callback and passes the trail through untouched.
	 *
	 * @param array $items Breadcrumb items ({label, url?} maps).
	 * @return array Unchanged.
	 */
	function blocklane_pro_seo_capture_breadcrumbs( $items ) {
		blocklane_pro_seo_breadcrumb_store( 'stage', $items );

		return $items;
	}

	/**
	 * Commit side: decide whether the just-rendered Breadcrumbs block is THE
	 * page's trail. On singular views only a block rendering for the queried
	 * post counts — a Breadcrumbs block inside a Query Loop card carries
	 * some other post's context, and capturing it would emit schema for the
	 * wrong page (core requires postId context for the singular branch, so
	 * a header template part's block matches here). Non-singular views have
	 * no per-post trail; the first render wins.
	 *
	 * @param string    $block_content Rendered block HTML.
	 * @param array     $block         Parsed block (unused).
	 * @param \WP_Block $instance      Block instance (context carrier).
	 * @return string Unchanged.
	 */
	function blocklane_pro_seo_capture_breadcrumbs_render( $block_content, $block, $instance ) {
		unset( $block );

		if ( ! is_singular()
			|| (int) ( $instance->context['postId'] ?? 0 ) === get_queried_object_id() ) {
			blocklane_pro_seo_breadcrumb_store( 'commit' );
		} else {
			blocklane_pro_seo_breadcrumb_store( 'stage', null );
		}

		return $block_content;
	}

	/**
	 * Print BreadcrumbList JSON-LD matching the Breadcrumbs block the page
	 * actually rendered. Emitted from wp_footer — block themes render the
	 * template before wp_head, classic themes after, and the footer is the
	 * one point past both (JSON-LD is valid anywhere in the document).
	 * Nothing prints when no block rendered, on search/404 (their trails are
	 * messages, not locations), on a noindexed post, or when the trail is a
	 * single item — absence beats junk schema.
	 */
	function blocklane_pro_seo_breadcrumb_jsonld() {
		// Search/404 trails are messages, not locations, and a noindexed
		// archive gets no schema for the same reason it gets no canonical.
		if ( blocklane_pro_seo_disabled() || is_search() || is_404()
			|| blocklane_pro_seo_archive_noindexed() ) {
			return;
		}

		// Not on the site-lock splash — see blocklane_pro_seo_meta_tags().
		if ( did_action( 'blocklane_pro_site_lock_splash' ) ) {
			return;
		}

		$post = blocklane_pro_seo_current_post();
		if ( $post && get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_NOINDEX, true ) ) {
			return;
		}

		$items = blocklane_pro_seo_breadcrumb_store();
		if ( ! is_array( $items ) || count( $items ) < 2 ) {
			return;
		}

		$list = array();
		foreach ( $items as $item ) {
			$label = isset( $item['label'] ) ? trim( wp_strip_all_tags( (string) $item['label'] ) ) : '';
			if ( '' === $label ) {
				continue;
			}

			$node = array(
				'@type'    => 'ListItem',
				'position' => count( $list ) + 1,
				'name'     => $label,
			);
			if ( ! empty( $item['url'] ) ) {
				$node['item'] = esc_url_raw( (string) $item['url'] );
			}

			$list[] = $node;
		}

		// Google requires a URL on every item except the last (the current
		// page); a trail that breaks that rule is left unmarked.
		foreach ( array_slice( $list, 0, -1 ) as $node ) {
			if ( empty( $node['item'] ) ) {
				return;
			}
		}

		if ( count( $list ) < 2 ) {
			return;
		}

		$data = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		);

		\blocklane_pro\Inline_Asset::print_json_ld( $data );
	}

	/**
	 * Enqueue the block-editor SEO panel.
	 */
	function blocklane_pro_seo_enqueue_editor_assets() {
		// The bake-safe runtime can't rely on plugin constants existing.
		if ( ! defined( 'BLOCKLANE_PRO_PATH' ) || ! defined( 'BLOCKLANE_PRO_URL' ) ) {
			return;
		}

		$asset_path = BLOCKLANE_PRO_PATH . '/inc/seo/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = include $asset_path;

		wp_enqueue_script(
			'blocklane-pro-seo',
			BLOCKLANE_PRO_URL . '/inc/seo/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// The designated HTML-sitemap page, so the editor can show the
		// "you are editing the sitemap page" notice (core's posts-page
		// arrangement).
		wp_add_inline_script(
			'blocklane-pro-seo',
			'window.blocklaneProSeo = ' . wp_json_encode(
				array( 'htmlSitemapPage' => blocklane_pro_seo_html_sitemap_target() )
			) . ';',
			'before'
		);

		wp_set_script_translations( 'blocklane-pro-seo', 'blocklane', BLOCKLANE_PRO_PATH . '/languages' );

		$style = BLOCKLANE_PRO_PATH . '/inc/seo/build/index.css';
		if ( file_exists( $style ) ) {
			wp_enqueue_style(
				'blocklane-pro-seo',
				BLOCKLANE_PRO_URL . '/inc/seo/build/index.css',
				array(),
				$asset['version']
			);
		}
	}

	/**
	 * Hook everything up.
	 *
	 * Two gating regimes, on purpose. META-DRIVEN emission (title override,
	 * description/OG, robots, JSON-LD, the sitemap's per-post noindex
	 * exclusion) stands down behind blocklane_pro_seo_disabled() — when a
	 * dedicated SEO plugin owns the head, its own meta is the source of
	 * truth and Blocklane's per-post flags go inert as one unit.
	 * SITE-LEVEL MANAGEMENT (the sitemap toggle and include lists, the HTML
	 * sitemap, llms.txt, IndexNow) is explicit opt-in configuration of core
	 * behavior, not duplicate meta output, so it stays active regardless.
	 */
	function blocklane_pro_seo_boot() {
		add_action( 'init', 'blocklane_pro_seo_register_meta' );
		add_action( 'init', 'blocklane_pro_seo_add_custom_fields_support', 99 );
		add_action( 'rest_api_init', 'blocklane_pro_seo_add_custom_fields_support' );

		add_filter( 'pre_get_document_title', 'blocklane_pro_seo_document_title' );
		add_filter( 'document_title_separator', 'blocklane_pro_seo_title_separator' );
		add_action( 'wp_head', 'blocklane_pro_seo_verification_tags', 1 );
		add_action( 'wp_head', 'blocklane_pro_seo_meta_tags', 2 );
		// Priority 3: with the other head tags, well before core's own
		// rel_canonical (10) would matter — which never fires here anyway,
		// since this only emits on non-singular views.
		add_action( 'wp_head', 'blocklane_pro_seo_archive_canonical', 3 );
		add_action( 'wp_head', 'blocklane_pro_seo_jsonld', 5 );
		// BreadcrumbList rides the core Breadcrumbs block: stage its trail
		// off the items filter, commit it from the render filter (which
		// carries the block context the items filter lacks), emit the
		// matching schema from the footer.
		add_filter( 'block_core_breadcrumbs_items', 'blocklane_pro_seo_capture_breadcrumbs', PHP_INT_MAX );
		add_filter( 'render_block_core/breadcrumbs', 'blocklane_pro_seo_capture_breadcrumbs_render', 10, 3 );
		add_action( 'wp_footer', 'blocklane_pro_seo_breadcrumb_jsonld', 20 );
		add_filter( 'wp_robots', 'blocklane_pro_seo_robots' );
		add_filter( 'wp_sitemaps_posts_query_args', 'blocklane_pro_seo_sitemap_exclude' );

		// The HTML sitemap: appended to its designated page, tree styles
		// printed on that page only. Both content paths registered; each
		// bails on the other's theme type.
		add_filter( 'the_content', 'blocklane_pro_seo_html_sitemap_content', 20 );
		add_filter( 'render_block_core/post-content', 'blocklane_pro_seo_html_sitemap_render_block', 10, 3 );
		add_action( 'wp_enqueue_scripts', 'blocklane_pro_seo_html_sitemap_styles', 100 );
		add_action( 'wp_head', 'blocklane_pro_seo_html_sitemap_script', 20 );

		// The sitemap toggle turns core's wp-sitemap.xml off entirely; the
		// setting defaults on, matching core.
		add_filter(
			'wp_sitemaps_enabled',
			static function ( $enabled ) {
				return $enabled && blocklane_pro_seo_settings()['sitemap'];
			}
		);

		// Sitemap include controls: per-type and per-taxonomy exclusions,
		// and the author-archives provider toggle.
		add_filter( 'wp_sitemaps_post_types', 'blocklane_pro_seo_sitemap_post_types' );
		add_filter( 'wp_sitemaps_taxonomies', 'blocklane_pro_seo_sitemap_taxonomies' );
		add_filter( 'wp_sitemaps_add_provider', 'blocklane_pro_seo_sitemap_providers', 10, 2 );

		// The llms.txt manifest, served the same rewrite-free way. Its cache
		// flushes on any content/meta/settings change (cheap deletes, so
		// they're registered regardless of the toggle — a stale body must
		// never survive a toggle round-trip).
		add_action( 'parse_request', 'blocklane_pro_seo_llms_txt' );
		add_action( 'transition_post_status', 'blocklane_pro_seo_llms_txt_flush' );
		add_action( 'wp_trash_post', 'blocklane_pro_seo_llms_txt_flush' );
		add_action( 'before_delete_post', 'blocklane_pro_seo_llms_txt_flush' );
		add_action( 'update_option_blocklane_pro_seo', 'blocklane_pro_seo_llms_txt_flush' );
		add_action( 'updated_post_meta', 'blocklane_pro_seo_llms_txt_flush_meta', 10, 3 );
		add_action( 'added_post_meta', 'blocklane_pro_seo_llms_txt_flush_meta', 10, 3 );
		add_action( 'deleted_post_meta', 'blocklane_pro_seo_llms_txt_flush_meta', 10, 3 );

		// IndexNow: the key file, and pings on publish, update, trash, and
		// delete (wp_after_insert_post fires after the editor's meta is
		// saved; wp_trash_post fires before the __trashed slug rename).
		add_action( 'parse_request', 'blocklane_pro_seo_indexnow_key_file' );
		add_action( 'wp_after_insert_post', 'blocklane_pro_seo_indexnow_after_insert', 10, 4 );
		add_action( 'wp_trash_post', 'blocklane_pro_seo_indexnow_removed' );
		add_action( 'before_delete_post', 'blocklane_pro_seo_indexnow_removed' );

		// This module is the site's SEO surface — ask Jetpack's SEO Tools to
		// yield (its own supported filter for exactly this case). Dedicated
		// SEO plugins are the opposite: blocklane_pro_seo_disabled() yields
		// to them.
		add_filter( 'jetpack_disable_seo_tools', '__return_true' );

		add_action( 'enqueue_block_editor_assets', 'blocklane_pro_seo_enqueue_editor_assets' );

		if ( is_admin() ) {
			// "— Sitemap Page" beside the designated page's title, the same
			// state core gives the Front, Posts, and Privacy Policy pages.
			add_filter( 'display_post_states', 'blocklane_pro_seo_sitemap_post_state', 10, 2 );

			// After init so every post type is registered before the
			// per-type list-table hooks attach.
			add_action( 'admin_init', 'blocklane_pro_seo_register_admin_columns' );
			add_filter( 'default_hidden_columns', 'blocklane_pro_seo_default_hidden_columns', 10, 2 );
			add_action( 'restrict_manage_posts', 'blocklane_pro_seo_filter_dropdown' );
			add_action( 'pre_get_posts', 'blocklane_pro_seo_filter_query' );
			add_action( 'admin_print_styles-edit.php', 'blocklane_pro_seo_admin_column_styles' );
		}
	}

	/**
	 * The post types whose list tables get the SEO columns and filter:
	 * everything public with UI except attachments — the same set the
	 * dashboard's Content tab covers.
	 *
	 * @return string[]
	 */
	function blocklane_pro_seo_admin_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);
		unset( $types['attachment'] );

		return array_values( $types );
	}

	/**
	 * List-table columns: each post's SEO title, description, schema type,
	 * and search visibility on every public type's edit.php — the Rank Math
	 * arrangement. Registered columns automatically get a Screen Options
	 * checkbox, so users toggle them per screen; the meta cache is primed by
	 * the list query, so cells cost no extra queries. A management surface
	 * like the editor sidebar, so it stays available even while a dedicated
	 * SEO plugin owns emission.
	 */
	function blocklane_pro_seo_register_admin_columns() {
		foreach ( blocklane_pro_seo_admin_types() as $type ) {
			// Late priority: the reorder below pins Author/Date to the end
			// AFTER every other plugin has added its columns.
			add_filter( "manage_{$type}_posts_columns", 'blocklane_pro_seo_admin_columns', 9999 );
			add_action( "manage_{$type}_posts_custom_column", 'blocklane_pro_seo_admin_column_content', 10, 2 );
		}
	}

	/**
	 * Add the SEO columns ahead of Author and Date, which re-append at the
	 * very end — Author second-to-last, Date last, always. The column keys
	 * are the meta keys, so the cell renderer can read the value straight
	 * off the column name.
	 *
	 * @param array $columns Column key => label.
	 * @return array
	 */
	function blocklane_pro_seo_admin_columns( $columns ) {
		$author = isset( $columns['author'] ) ? $columns['author'] : null;
		$date   = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['author'], $columns['date'] );

		$columns[ BLOCKLANE_PRO_SEO_META_TITLE ]       = __( 'SEO title', 'blocklane' );
		$columns[ BLOCKLANE_PRO_SEO_META_DESCRIPTION ] = __( 'SEO description', 'blocklane' );
		$columns[ BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE ] = __( 'Schema', 'blocklane' );
		$columns[ BLOCKLANE_PRO_SEO_META_NOINDEX ]     = __( 'Search', 'blocklane' );

		if ( null !== $author ) {
			$columns['author'] = $author;
		}
		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Schema and Search start hidden for users who haven't customized their
	 * Screen Options yet — the two text columns carry the default view, the
	 * status pair is opt-in from the Screen Options panel. (A user's saved
	 * column prefs override this, per core's own behavior.)
	 *
	 * @param string[]   $hidden Column keys hidden by default.
	 * @param \WP_Screen $screen Current screen.
	 * @return string[]
	 */
	function blocklane_pro_seo_default_hidden_columns( $hidden, $screen ) {
		if ( in_array( $screen->post_type ?? '', blocklane_pro_seo_admin_types(), true ) && 'edit' === $screen->base ) {
			$hidden[] = BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE;
			$hidden[] = BLOCKLANE_PRO_SEO_META_NOINDEX;
		}

		return $hidden;
	}

	/**
	 * One cell. Title/description: the stored value, or a muted em dash when
	 * unset. Schema: the type label. Search: Visible / Hidden, matching the
	 * Content tab's column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Row's post ID.
	 */
	function blocklane_pro_seo_admin_column_content( $column, $post_id ) {
		if ( BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE === $column ) {
			$type = blocklane_pro_seo_sanitize_schema_type(
				get_post_meta( $post_id, $column, true )
			);
			if ( 'article' === $type ) {
				echo esc_html__( 'Article', 'blocklane' );
			} elseif ( 'faq' === $type ) {
				echo esc_html__( 'FAQ', 'blocklane' );
			} else {
				echo '<span class="blocklane-pro-seo-column-empty" aria-hidden="true">&#8212;</span>';
			}
			return;
		}

		if ( BLOCKLANE_PRO_SEO_META_NOINDEX === $column ) {
			if ( get_post_meta( $post_id, $column, true ) ) {
				echo '<span class="blocklane-pro-seo-column-hidden">' . esc_html__( 'Hidden', 'blocklane' ) . '</span>';
			} else {
				echo esc_html__( 'Visible', 'blocklane' );
			}
			return;
		}

		if ( BLOCKLANE_PRO_SEO_META_TITLE !== $column && BLOCKLANE_PRO_SEO_META_DESCRIPTION !== $column ) {
			return;
		}

		$value = trim( (string) get_post_meta( $post_id, $column, true ) );

		if ( '' === $value ) {
			echo '<span class="blocklane-pro-seo-column-empty" aria-hidden="true">&#8212;</span>';
			return;
		}

		echo esc_html( $value );
	}

	/**
	 * The SEO audit dropdown beside the list table's other filters: missing
	 * title / missing description / hidden from search.
	 *
	 * @param string $post_type The table's post type.
	 */
	function blocklane_pro_seo_filter_dropdown( $post_type ) {
		if ( ! in_array( $post_type, blocklane_pro_seo_admin_types(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, same as core's date/category dropdowns.
		$current = isset( $_GET['blocklane_seo_filter'] ) ? sanitize_key( wp_unslash( $_GET['blocklane_seo_filter'] ) ) : '';

		$options = array(
			''                    => __( 'SEO filter', 'blocklane' ),
			'missing_title'       => __( 'Missing SEO title', 'blocklane' ),
			'missing_description' => __( 'Missing description', 'blocklane' ),
			'noindexed'           => __( 'Hidden from search', 'blocklane' ),
		);

		echo '<select name="blocklane_seo_filter" id="blocklane-seo-filter">';
		foreach ( $options as $value => $label ) {
			// $current is request-derived, but selected() does not echo it: it COMPARES it and
			// returns either the literal " selected='selected'" or ''. The echoed values are
			// $value and $label from the local $options map, both escaped. Semgrep sees a tainted
			// variable reach an echo argument and cannot see that the callee discards it.
			// nosemgrep: php.lang.security.injection.echoed-request.echoed-request
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>'
				. esc_html( $label )
				. '</option>';
		}
		echo '</select>';
	}

	/**
	 * Apply the dropdown's choice to the list-table query. "Missing" matches
	 * both an absent meta row (never edited) and an emptied value, the same
	 * OR the dashboard's Content endpoint uses.
	 *
	 * @param \WP_Query $query The query being prepared.
	 */
	function blocklane_pro_seo_filter_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$filter = isset( $_GET['blocklane_seo_filter'] ) ? sanitize_key( wp_unslash( $_GET['blocklane_seo_filter'] ) ) : '';
		if ( '' === $filter ) {
			return;
		}

		$missing = static function ( $meta_key ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => $meta_key,
					'value' => '',
				),
			);
		};

		$clause = null;
		if ( 'missing_title' === $filter ) {
			$clause = $missing( BLOCKLANE_PRO_SEO_META_TITLE );
		} elseif ( 'missing_description' === $filter ) {
			$clause = $missing( BLOCKLANE_PRO_SEO_META_DESCRIPTION );
		} elseif ( 'noindexed' === $filter ) {
			$clause = array(
				'key'   => BLOCKLANE_PRO_SEO_META_NOINDEX,
				'value' => '1',
			);
		}

		if ( ! $clause ) {
			return;
		}

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = $clause;
		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, admin list filter.
	}

	/**
	 * Column widths for edit.php, so a long description doesn't squeeze the
	 * title column, plus the muted empty-state dash and the Hidden flag.
	 * Queued on edit.php's admin_print_styles-edit.php, so the head prints it
	 * after core's list-table CSS.
	 */
	function blocklane_pro_seo_admin_column_styles(): void {
		\blocklane_pro\Inline_Asset::style(
			'blocklane-pro-seo-columns',
			'.column-' . esc_attr( BLOCKLANE_PRO_SEO_META_TITLE ) . '{width:14%;}'
			. '.column-' . esc_attr( BLOCKLANE_PRO_SEO_META_DESCRIPTION ) . '{width:22%;}'
			. '.column-' . esc_attr( BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE ) . '{width:7%;}'
			. '.column-' . esc_attr( BLOCKLANE_PRO_SEO_META_NOINDEX ) . '{width:7%;}'
			. '.blocklane-pro-seo-column-empty{color:#a7aaad;}'
			. '.blocklane-pro-seo-column-hidden{color:#996800;}'
		);
	}
}

blocklane_pro_seo_boot();
