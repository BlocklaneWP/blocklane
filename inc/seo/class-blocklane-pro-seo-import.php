<?php
/**
 * SEO meta import — per-post title/description/noindex(/schema) from other
 * SEO plugins' post meta into Blocklane's keys.
 *
 * Fill-only by design: a post's existing Blocklane value always wins, so an
 * import can be re-run safely and never clobbers anything set here. Yoast
 * and Rank Math store title TEMPLATES (token strings); values that are
 * nothing but tokens are their defaults — not authored content — and are
 * skipped, while mixed values get the common tokens resolved per post.
 *
 * Sources are detected by their DATA (post meta present), not by whether
 * the plugin is active — the usual moment to import is right after
 * deactivating the old plugin.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Import {

	/**
	 * Source slug => meta map. 'noindex_is' interprets the noindex meta
	 * value; 'tokens' marks sources whose titles/descriptions are template
	 * strings needing per-post resolution.
	 *
	 * @return array<string,array>
	 */
	public static function sources() {
		return array(
			'yoast'     => array(
				'label'       => 'Yoast SEO',
				'title'       => '_yoast_wpseo_title',
				'description' => '_yoast_wpseo_metadesc',
				'noindex'     => '_yoast_wpseo_meta-robots-noindex',
				'noindex_is'  => static function ( $value ) {
					return '1' === (string) $value;
				},
				'schema'      => '',
				'tokens'      => true,
			),
			'rank-math' => array(
				'label'       => 'Rank Math',
				'title'       => 'rank_math_title',
				'description' => 'rank_math_description',
				'noindex'     => 'rank_math_robots',
				'noindex_is'  => static function ( $value ) {
					return is_array( $value ) && in_array( 'noindex', $value, true );
				},
				'schema'      => '',
				'tokens'      => true,
			),
			'aioseo'    => array(
				'label'       => 'All in One SEO',
				'title'       => '_aioseo_title',
				'description' => '_aioseo_description',
				// Robots/noindex live in AIOSEO's own tables, not post meta.
				'noindex'     => '',
				'noindex_is'  => null,
				'schema'      => '',
				'tokens'      => true,
			),
			'seopress'  => array(
				'label'       => 'SEOPress',
				'title'       => '_seopress_titles_title',
				'description' => '_seopress_titles_desc',
				'noindex'     => '_seopress_robots_index',
				'noindex_is'  => static function ( $value ) {
					return 'yes' === (string) $value;
				},
				'schema'      => '',
				'tokens'      => true,
			),
			'jetpack'   => array(
				'label'       => 'Jetpack SEO',
				'title'       => 'jetpack_seo_html_title',
				'description' => 'advanced_seo_description',
				'noindex'     => 'jetpack_seo_noindex',
				'noindex_is'  => static function ( $value ) {
					return (bool) $value;
				},
				// Same '' | article | faq enum as ours — direct copy.
				'schema'      => 'jetpack_seo_schema_type',
				'tokens'      => false,
			),
		);
	}

	/**
	 * Every source that has data, with a per-source count of posts carrying
	 * any of its meta keys. Sources with nothing to import are omitted.
	 *
	 * @return array[] [ { source, label, count }, … ]
	 */
	public static function detect() {
		$found = array();

		foreach ( self::sources() as $slug => $def ) {
			$count = count( self::post_ids( $def ) );
			if ( $count > 0 ) {
				$found[] = array(
					'source' => $slug,
					'label'  => $def['label'],
					'count'  => $count,
				);
			}
		}

		return $found;
	}

	/**
	 * Import one source. Fill-only: existing Blocklane values are never
	 * overwritten.
	 *
	 * @param string $source Source slug.
	 * @return array|\WP_Error Counters: posts, title, description, noindex, schema, skipped.
	 */
	public static function run( $source ) {
		$sources = self::sources();
		if ( ! isset( $sources[ $source ] ) ) {
			return new \WP_Error(
				'blocklane_pro_seo_bad_source',
				__( 'Unknown import source.', 'blocklane' ),
				array( 'status' => 400 )
			);
		}

		$def = $sources[ $source ];
		$ids = self::post_ids( $def );

		$result = array(
			'posts'       => 0,
			'title'       => 0,
			'description' => 0,
			'noindex'     => 0,
			'schema'      => 0,
			'skipped'     => 0,
		);

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$touched = false;

			// Title and description share the fill-only + token flow.
			$fields = array(
				'title'       => array( $def['title'], BLOCKLANE_PRO_SEO_META_TITLE, 'sanitize_text_field' ),
				'description' => array( $def['description'], BLOCKLANE_PRO_SEO_META_DESCRIPTION, 'sanitize_textarea_field' ),
			);

			foreach ( $fields as $counter => $spec ) {
				list( $source_key, $target_key, $sanitize ) = $spec;

				if ( '' === $source_key ) {
					continue;
				}

				$value = (string) get_post_meta( $post_id, $source_key, true );
				$value = $def['tokens'] ? self::resolve_tokens( $value, $post ) : trim( $value );
				if ( '' === $value ) {
					continue;
				}

				if ( '' !== trim( (string) get_post_meta( $post_id, $target_key, true ) ) ) {
					++$result['skipped'];
					continue;
				}

				update_post_meta( $post_id, $target_key, call_user_func( $sanitize, $value ) );
				++$result[ $counter ];
				$touched = true;
			}

			// Noindex: only ever sets the flag (never clears one), and only
			// when the source says the post was hidden.
			if ( '' !== $def['noindex'] && $def['noindex_is'] ) {
				$raw = get_post_meta( $post_id, $def['noindex'], true );
				if ( call_user_func( $def['noindex_is'], $raw )
					&& ! get_post_meta( $post_id, BLOCKLANE_PRO_SEO_META_NOINDEX, true ) ) {
					update_post_meta( $post_id, BLOCKLANE_PRO_SEO_META_NOINDEX, true );
					++$result['noindex'];
					$touched = true;
				}
			}

			// Schema type (Jetpack only) — same enum, fill-only.
			if ( '' !== $def['schema'] ) {
				$type = (string) get_post_meta( $post_id, $def['schema'], true );
				if ( in_array( $type, array( 'article', 'faq' ), true )
					&& '' === (string) get_post_meta( $post_id, BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE, true ) ) {
					update_post_meta( $post_id, BLOCKLANE_PRO_SEO_META_SCHEMA_TYPE, $type );
					++$result['schema'];
					$touched = true;
				}
			}

			if ( $touched ) {
				++$result['posts'];
			}
		}

		return $result;
	}

	/**
	 * IDs of importable posts: any post (not revision/trash) carrying a
	 * non-empty value under one of the source's meta keys.
	 *
	 * @param array $def Source definition.
	 * @return int[]
	 */
	private static function post_ids( $def ) {
		global $wpdb;

		$keys = array_values(
			array_filter(
				array( $def['title'], $def['description'], $def['noindex'], $def['schema'] )
			)
		);

		if ( ! $keys ) {
			return array();
		}

		// The IN list is built inline in prepare()'s first argument, and the
		// prepare() inline in the call — see the SEO controller's aggregate for
		// why a variable in either position reads as unprepared to the checker.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-shot scan for importable rows; caching a discovery query would hide what another plugin just wrote.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key IN ( " . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . " )
				   AND pm.meta_value != ''
				   AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				   AND p.post_type NOT IN ( 'revision', 'nav_menu_item' )",
				$keys
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Matches template tokens WITHOUT eating authored %-delimited text.
	 * The double-percent spelling (Yoast) is unambiguously a token; the
	 * single-percent spelling (Rank Math) only counts for known variable
	 * names — "Save 10%-50% today" would otherwise lose "%-50%" to a
	 * greedy any-word match, and fill-only makes that corruption permanent.
	 */
	const TOKEN_PATTERN = '/%%[a-z0-9_\-]+%%|%(?:title|post_title|sep|separator|sitename|site_name|sitedesc|sitetitle|tagline|excerpt|excerpt_only|snippet|focuskw|focus_keyword|keyword|page|pagenumber|pagetotal|category|categories|primary_category|tag|tags|term|term_title|term_description|author|author_name|name|user_description|date|post_date|modified|post_modified|currentdate|currentday|currentmonth|currentyear|currenttime|id|seo_title|seo_description|archive_title|search_query|pt_single|pt_plural)%/i';

	/**
	 * Resolve a Yoast/Rank Math template value for one post.
	 *
	 * A value that is nothing but tokens (plus separators/whitespace) is the
	 * plugin's default template, not authored content — returns ''. Mixed
	 * values get the common tokens substituted (both %%yoast%% and
	 * %rank-math% spellings) and any unresolved KNOWN tokens dropped;
	 * %-delimited spans that aren't recognized tokens are authored text and
	 * pass through untouched.
	 *
	 * @param string   $raw  Stored value.
	 * @param \WP_Post $post Post the tokens resolve against.
	 * @return string Plain title/description text, '' when nothing usable.
	 */
	private static function resolve_tokens( $raw, $post ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		// No tokens at all — authored plain text, use as-is.
		if ( false === strpos( $raw, '%' ) ) {
			return $raw;
		}

		// Pure-template check: with every token removed, is anything left?
		$stripped = preg_replace( self::TOKEN_PATTERN, '', $raw );
		if ( '' === trim( $stripped, " \t\-–—|•·»/:" ) ) {
			return '';
		}

		$map = array(
			// Yoast spellings.
			'%%title%%'    => get_the_title( $post ),
			'%%sitename%%' => get_bloginfo( 'name' ),
			'%%sitedesc%%' => get_bloginfo( 'description' ),
			'%%sep%%'      => '–',
			// Rank Math spellings.
			'%title%'      => get_the_title( $post ),
			'%sitename%'   => get_bloginfo( 'name' ),
			'%sitedesc%'   => get_bloginfo( 'description' ),
			'%sep%'        => '–',
		);

		$value = strtr( $raw, $map );
		$value = preg_replace( self::TOKEN_PATTERN, '', $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * WP-CLI: `wp blocklane-pro seo-import --list` and
	 * `wp blocklane-pro seo-import <source>`.
	 */
	public static function register_cli() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command(
			'blocklane-pro seo-import',
			static function ( $args, $assoc_args ) {
				if ( ! empty( $assoc_args['list'] ) ) {
					$found = Seo_Import::detect();
					if ( ! $found ) {
						\WP_CLI::log( 'No importable SEO data found.' );
						return;
					}
					foreach ( $found as $row ) {
						\WP_CLI::log( sprintf( '%-10s %s — %d post(s)', $row['source'], $row['label'], $row['count'] ) );
					}
					return;
				}

				$source = isset( $args[0] ) ? (string) $args[0] : '';
				if ( ! isset( Seo_Import::sources()[ $source ] ) ) {
					\WP_CLI::error( 'Unknown source. Use --list to see sources with data. Valid: ' . implode( ', ', array_keys( Seo_Import::sources() ) ) );
				}

				$result = Seo_Import::run( $source );
				if ( is_wp_error( $result ) ) {
					\WP_CLI::error( $result->get_error_message() );
				}

				\WP_CLI::success(
					sprintf(
						'%d post(s) updated — %d title(s), %d description(s), %d noindex flag(s), %d schema type(s); %d value(s) skipped (already set in Blocklane).',
						$result['posts'],
						$result['title'],
						$result['description'],
						$result['noindex'],
						$result['schema'],
						$result['skipped']
					)
				);
			},
			array(
				'shortdesc' => 'Import per-post SEO meta (title/description/noindex/schema) from Yoast, Rank Math, AIOSEO, SEOPress, or Jetpack. Fill-only: existing Blocklane values are kept.',
			)
		);
	}
}
