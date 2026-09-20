<?php
/**
 * SEO REST controller: GET/POST blocklane-pro/v1/seo.
 *
 * GET returns the settings plus the site identity the screen's previews
 * render from; POST saves them. `search_visible` rides alongside the
 * settings map: it mirrors core's blog_public option — the same option the
 * Site Visibility screen writes — so the two screens can never disagree.
 * Gated on manage_options.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Controller implements Rest_Registrable {

	public function register_routes() {
		register_rest_route(
			Branding::rest_namespace(),
			'/seo',
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

		register_rest_route(
			Branding::rest_namespace(),
			'/seo/overview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/seo/import',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_import_sources' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_import' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'source' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			Branding::rest_namespace(),
			'/seo/content',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_content' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'     => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'schema'   => array(
							'type'              => 'string',
							'default'           => '',
							'enum'              => array( '', 'none', 'article', 'faq' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'orderby'  => array(
							'type'              => 'string',
							'default'           => 'title',
							'enum'              => array( 'title', 'type' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'order'    => array(
							'type'              => 'string',
							'default'           => 'asc',
							'enum'              => array( 'asc', 'desc' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_content_row' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * The post types the Content tab lists and the Overview counts across.
	 * Delegates to the runtime's canonical list (which also drives the
	 * edit.php columns and filter) so the two surfaces can never cover
	 * different sets; the inline fallback only matters if a future baked
	 * runtime copy ever drops the helper.
	 *
	 * @return string[]
	 */
	private function content_types() {
		if ( function_exists( 'blocklane_pro_seo_admin_types' ) ) {
			return blocklane_pro_seo_admin_types();
		}

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
	 * Overview: factual coverage counts (state, not a score) plus the
	 * visibility and verification flags the cards render. Mirrors what the
	 * Settings tab manages so the two can't drift.
	 */
	public function get_overview( \WP_REST_Request $request ) {
		unset( $request );

		$types = $this->content_types();

		$total = 0;
		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		$counts   = $this->coverage_counts( $types );
		$settings = Seo::get();

		return rest_ensure_response(
			array(
				'visibility' => array(
					'search_engines_visible' => 1 === (int) get_option( 'blog_public', 1 ),
					'sitemap_active'         => $settings['sitemap'],
					'site_locked'            => class_exists( __NAMESPACE__ . '\Site_Lock', false ) && Site_Lock::is_enabled(),
				),
				'verification' => array_map(
					static function ( $code ) {
						return '' !== $code;
					},
					$settings['verification']
				),
				'coverage'   => array(
					'total'               => $total,
					'with_schema'         => $counts[ BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE ],
					'with_title'          => $counts[ BLOCKLANE_PRO_SEO_META_TITLE ],
					'with_description'    => $counts[ BLOCKLANE_PRO_SEO_META_DESCRIPTION ],
					'with_search_visible' => max( 0, $total - $counts[ BLOCKLANE_PRO_SEO_META_NOINDEX ] ),
				),
			)
		);
	}

	/**
	 * How many published posts carry each SEO meta, in ONE grouped
	 * aggregate — the WP_Query-per-metric shape costs a SQL_CALC_FOUND_ROWS
	 * scan apiece just to read found_posts. Noindex counts exact '1'
	 * matches; the text metas count any non-empty value.
	 *
	 * @param string[] $types Post types.
	 * @return array<string,int> Meta key => count (every key present).
	 */
	private function coverage_counts( $types ) {
		global $wpdb;

		$keys = array(
			BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
			BLOCKLANE_PRO_SEO_META_TITLE,
			BLOCKLANE_PRO_SEO_META_DESCRIPTION,
			BLOCKLANE_PRO_SEO_META_NOINDEX,
		);

		$counts = array_fill_keys( $keys, 0 );

		if ( ! $types ) {
			return $counts;
		}

		/*
		 * The IN lists are built INLINE, in prepare()'s first argument, and the
		 * prepare() itself is inline in the call below. Both moves are for the
		 * same reason: the sniff examines only that first argument and flags
		 * any variable in it — so a $type_in built one line earlier reads
		 * exactly like an interpolated value, and a $sql handed to
		 * get_results() reads like an unprepared query. count() is the one
		 * function whose arguments it steps over, which is what makes
		 * implode( array_fill( …, count( … ) ) ) the documented idiom rather
		 * than a hole it happens not to see.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one reporting aggregate on the SEO screen, counting rows the screen has just changed.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key, COUNT( DISTINCT pm.post_id ) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_status = 'publish'
				   AND p.post_type IN ( " . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . " )
				   AND pm.meta_key IN ( " . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . " )
				   AND pm.meta_value != ''
				   AND ( pm.meta_key <> %s OR pm.meta_value = '1' )
				 GROUP BY pm.meta_key",
				array_merge( $types, $keys, array( BLOCKLANE_PRO_SEO_META_NOINDEX ) )
			)
		);

		foreach ( (array) $rows as $row ) {
			$counts[ $row->meta_key ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Content list: one page of published content across all public types,
	 * with each row's SEO field state for the table and its raw values for
	 * the edit drawer.
	 */
	public function get_content( \WP_REST_Request $request ) {
		$types     = $this->content_types();
		$type      = $request->get_param( 'type' );
		$post_type = ( $type && in_array( $type, $types, true ) ) ? array( $type ) : $types;

		// The table's sort, from the view's sortable columns (title/type).
		// Type alone leaves rows within a type unordered, so it carries a
		// title tiebreak — pagination stays deterministic either way.
		$order   = 'desc' === $request->get_param( 'order' ) ? 'DESC' : 'ASC';
		$orderby = 'type' === $request->get_param( 'orderby' )
			? array(
				'type'  => $order,
				'title' => 'ASC',
			)
			: array( 'title' => $order );

		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			's'                      => (string) $request->get_param( 'search' ),
			'paged'                  => (int) $request->get_param( 'page' ),
			'posts_per_page'         => (int) $request->get_param( 'per_page' ),
			'orderby'                => $orderby,
			'update_post_term_cache' => false,
		);

		$schema = (string) $request->get_param( 'schema' );
		if ( 'none' === $schema ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, SEO screen only.
			$args['meta_query'] = array(
				array(
					'relation' => 'OR',
					array(
						'key'     => BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
						'value' => '',
					),
				),
			);
		} elseif ( in_array( $schema, array( 'article', 'faq' ), true ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, SEO screen only.
			$args['meta_query'] = array(
				array(
					'key'   => BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
					'value' => $schema,
				),
			);
		}

		$query = new \WP_Query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->content_row( $post );
		}

		$type_options = array();
		foreach ( $types as $slug ) {
			$object = get_post_type_object( $slug );
			if ( $object ) {
				$type_options[] = array(
					'slug'  => $slug,
					'label' => $object->labels->singular_name,
				);
			}
		}

		return rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'types'       => $type_options,
				// Site identity for the Edit SEO drawer's search preview
				// (favicon circle + site name, the real result anatomy).
				'site'        => array(
					'title' => (string) get_bloginfo( 'name' ),
					'icon'  => (string) get_site_icon_url( 64 ),
				),
			)
		);
	}

	/**
	 * Save one row's SEO fields from the edit drawer. The row's author cap is
	 * checked per post; update_post_meta runs each key's registered sanitizer.
	 */
	public function save_content_row( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, $this->content_types(), true ) ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Content not found.', 'blocklane' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this content.', 'blocklane' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$fields = array(
			'seo_title'       => BLOCKLANE_PRO_SEO_META_TITLE,
			'seo_description' => BLOCKLANE_PRO_SEO_META_DESCRIPTION,
			'schema_type'     => BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE,
		);

		foreach ( $fields as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( null !== $value ) {
				update_post_meta( $post_id, $meta_key, (string) $value );
			}
		}

		$noindex = $request->get_param( 'noindex' );
		if ( null !== $noindex ) {
			update_post_meta( $post_id, BLOCKLANE_PRO_SEO_META_NOINDEX, rest_sanitize_boolean( $noindex ) );
		}

		return rest_ensure_response( $this->content_row( get_post( $post_id ) ) );
	}

	/**
	 * One table row.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private function content_row( $post ) {
		$object = get_post_type_object( $post->post_type );

		return array(
			'id'              => (int) $post->ID,
			'title'           => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
			'type'            => $post->post_type,
			'type_label'      => $object ? (string) $object->labels->singular_name : $post->post_type,
			'url'             => (string) get_permalink( $post ),
			'edit_url'        => (string) get_edit_post_link( $post, 'raw' ),
			'seo_title'       => (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_TITLE, true ),
			'seo_description' => (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_DESCRIPTION, true ),
			'schema_type'     => (string) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE, true ),
			'noindex'         => (bool) get_post_meta( $post->ID, BLOCKLANE_PRO_SEO_META_NOINDEX, true ),
			'excerpt'         => wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), true ), 25 ),
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

		return rest_ensure_response( $this->payload() );
	}

	/** Sources with importable data, for the Settings tab's import section. */
	public function get_import_sources() {
		return rest_ensure_response( array( 'sources' => Seo_Import::detect() ) );
	}

	/** Run one source's fill-only import; returns its counters. */
	public function run_import( \WP_REST_Request $request ) {
		return rest_ensure_response( Seo_Import::run( (string) $request->get_param( 'source' ) ) );
	}

	public function save_settings( \WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		if ( is_array( $settings ) ) {
			$saved = Seo::save( $settings );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		// blog_public is core's option (shared with the Site Visibility
		// screen); only touch it when the payload mentions it.
		$search_visible = $request->get_param( 'search_visible' );
		if ( null !== $search_visible ) {
			update_option( 'blog_public', rest_sanitize_boolean( $search_visible ) ? 1 : 0 );
		}

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * The response shape both routes return.
	 *
	 * @return array
	 */
	private function payload() {
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		$settings = Seo::get();

		$org_logo_url = $settings['org_logo_id']
			? (string) wp_get_attachment_image_url( $settings['org_logo_id'], 'full' )
			: '';

		$og_image_url = Seo::og_image_url( $settings['default_og_image_id'] );

		// Published pages for the HTML-sitemap page picker — capped: this
		// list rides every settings GET and POST, and the picker is a
		// dropdown, not a directory. The designated page is appended when
		// it falls outside the cap so the select never loses its value.
		$pages  = array();
		$target = (int) $settings['html_sitemap_page'];
		$seen   = false;
		foreach ( get_pages( array( 'sort_column' => 'post_title', 'number' => 100 ) ) as $page ) {
			$seen    = $seen || (int) $page->ID === $target;
			$pages[] = array(
				'id'    => (int) $page->ID,
				'title' => html_entity_decode( get_the_title( $page ), ENT_QUOTES ),
				'url'   => (string) get_permalink( $page ),
			);
		}
		if ( $target && ! $seen ) {
			$page = get_post( $target );
			if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
				$pages[] = array(
					'id'    => (int) $page->ID,
					'title' => html_entity_decode( get_the_title( $page ), ENT_QUOTES ),
					'url'   => (string) get_permalink( $page ),
				);
			}
		}

		// The sitemap include lists — core's own base sets (public types
		// minus attachments, public taxonomies), listed RAW so excluded
		// entries still show as unchecked boxes rather than vanishing.
		$sitemap_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$sitemap_types[] = array(
				'slug'  => $type->name,
				'label' => (string) $type->labels->name,
			);
		}

		$sitemap_taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			// Post formats are technically public but never a sitemap
			// concern — listing them is noise.
			if ( 'post_format' === $taxonomy->name ) {
				continue;
			}
			$sitemap_taxonomies[] = array(
				'slug'  => $taxonomy->name,
				'label' => (string) $taxonomy->labels->name,
			);
		}

		return array(
			'settings'       => $settings,
			'search_visible' => 1 === (int) get_option( 'blog_public', 1 ),
			// Whether a Site Visibility lock is currently enforcing — the
			// screen surfaces it: a locked site isn't reachable by crawlers
			// no matter what the toggles here say.
			'site_locked'    => class_exists( __NAMESPACE__ . '\Site_Lock', false ) && Site_Lock::is_enabled(),
			'site'           => array(
				'title'       => (string) get_bloginfo( 'name' ),
				'tagline'     => (string) get_bloginfo( 'description' ),
				'url'         => (string) home_url( '/' ),
				'icon'        => (string) get_site_icon_url(),
				'logo'        => $logo_url,
				'orgLogo'     => $org_logo_url,
				'ogImage'     => $og_image_url,
				'sitemapUrl'  => (string) home_url( '/wp-sitemap.xml' ),
			),
			'pages'          => $pages,
			'sitemapTypes'   => $sitemap_types,
			'sitemapTaxes'   => $sitemap_taxonomies,
		);
	}
}
