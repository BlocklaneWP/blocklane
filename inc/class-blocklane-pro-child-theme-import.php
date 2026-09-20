<?php
/**
 * Bakes a theme's Site Editor customizations into a generated child theme's
 * files, so a child created from a customized parent reproduces it — with the
 * customizations living in portable theme files (theme.json + templates/parts)
 * rather than the database.
 *
 * Block-theme customizations are stored in the DB keyed to the active theme's
 * stylesheet: global styles in a `wp_global_styles` post, edited templates and
 * template parts in `wp_template` / `wp_template_part` posts, all tagged with
 * the `wp_theme` taxonomy term. This reads those and writes them as files.
 *
 * Out of scope (v1): extracting image/font assets into the theme — media keeps
 * its library URLs, which resolve fine on the same site.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Child_Theme_Import {

	/**
	 * Summarize the customizations available to import from a theme.
	 *
	 * @param string $source_slug Stylesheet slug to read from.
	 * @return array{hasGlobalStyles:bool,templates:string[],parts:string[]}
	 */
	public static function summary( $source_slug ) {
		$gs = self::global_styles_data( $source_slug );

		return array(
			'hasGlobalStyles' => ! empty( $gs['settings'] ) || ! empty( $gs['styles'] ),
			'templates'       => self::edited_slugs( 'wp_template', $source_slug ),
			'parts'           => self::edited_slugs( 'wp_template_part', $source_slug ),
		);
	}

	/** Whether the source theme has anything worth importing. */
	public static function has_any( $source_slug ) {
		$s = self::summary( $source_slug );

		return $s['hasGlobalStyles'] || ! empty( $s['templates'] ) || ! empty( $s['parts'] );
	}

	/**
	 * Pick which theme to import FROM: the active theme if it has customizations
	 * of its own, otherwise the parent. A child created here is always a child of
	 * the parent, and "built on the parent" customizations are keyed to the parent
	 * (they don't apply — or import — while an empty child is active), so this
	 * falls back to the parent to pick them up.
	 *
	 * @return string Stylesheet slug to read customizations from.
	 */
	public static function resolve_source() {
		$active = get_stylesheet();
		if ( self::has_any( $active ) ) {
			return $active;
		}

		$parent = get_template();

		return ( $parent !== $active && self::has_any( $parent ) ) ? $parent : $active;
	}

	/**
	 * Write the source theme's customizations into the child theme directory.
	 * Relies on the already-initialized global $wp_filesystem (the generator
	 * sets it up before calling this).
	 *
	 * @param string $source_slug Theme to read customizations from.
	 * @param string $child_slug  New child theme stylesheet slug.
	 * @param string $child_dir   Absolute path to the child theme directory.
	 */
	public static function export( $source_slug, $child_slug, $child_dir ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		$ok = true;

		// 1. Global styles -> child theme.json (layered over the parent's).
		$gs       = self::global_styles_data( $source_slug );
		$settings = ! empty( $gs['settings'] ) ? self::prepare_child_settings( $gs['settings'], get_template() ) : array();
		$styles   = ! empty( $gs['styles'] ) ? $gs['styles'] : array();

		if ( $settings || $styles ) {
			$theme_json            = array( '$schema' => 'https://schemas.wp.org/trunk/theme.json' );
			$theme_json['version'] = isset( $gs['version'] ) ? (int) $gs['version'] : 3;
			if ( $settings ) {
				$theme_json['settings'] = $settings;
			}
			if ( $styles ) {
				$theme_json['styles'] = $styles;
			}

			$ok = $wp_filesystem->put_contents(
				$child_dir . '/theme.json',
				wp_json_encode( $theme_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			) && $ok;
		}

		// 2. Edited template parts -> parts/*.html.
		$parts = self::edited_posts( 'wp_template_part', $source_slug );
		if ( $parts ) {
			$ok = self::write_block_files( $child_dir . '/parts', $parts, $child_slug ) && $ok;
		}

		// 3. Edited templates -> templates/*.html.
		$templates = self::edited_posts( 'wp_template', $source_slug );
		if ( $templates ) {
			$ok = self::write_block_files( $child_dir . '/templates', $templates, $child_slug ) && $ok;
		}

		return $ok;
	}

	/**
	 * Write each post's block content to <dir>/<slug>.html. Slugs are run through
	 * sanitize_file_name so a crafted post_name can't escape the directory.
	 *
	 * @return bool False if the directory or any file could not be written.
	 */
	private static function write_block_files( $dir, $posts, $child_slug ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem->mkdir( $dir ) && ! $wp_filesystem->is_dir( $dir ) ) {
			return false;
		}

		$ok = true;
		foreach ( $posts as $p ) {
			$file = sanitize_file_name( $p->post_name );
			if ( '' === $file ) {
				$ok = false;
				continue;
			}

			$ok = $wp_filesystem->put_contents(
				$dir . '/' . $file . '.html',
				self::repoint_parts( $p->post_content, $child_slug )
			) && $ok;
		}

		return $ok;
	}

	/** Decoded global-styles user data for a theme (empty array if none). */
	private static function global_styles_data( $source_slug ) {
		$posts = self::edited_posts( 'wp_global_styles', $source_slug, 1 );
		if ( ! $posts ) {
			return array();
		}

		$data = json_decode( $posts[0]->post_content, true );

		return is_array( $data ) ? $data : array();
	}

	/** Preset setting paths that are flat arrays in a theme.json file. */
	const PRESET_PATHS = array(
		array( 'color', 'palette' ),
		array( 'color', 'gradients' ),
		array( 'color', 'duotone' ),
		array( 'typography', 'fontSizes' ),
		array( 'typography', 'fontFamilies' ),
		array( 'spacing', 'spacingSizes' ),
		array( 'shadow', 'presets' ),
	);

	/**
	 * Turn user-data settings into settings safe for the child's theme.json.
	 *
	 * Two problems are handled per preset path:
	 *  - User data stores presets origin-keyed ({ default, theme, custom }), but a
	 *    theme.json file expects a flat list — otherwise WP_Theme_JSON throws
	 *    "Undefined array key 'slug'".
	 *  - A child theme.json preset array REPLACES the parent's (it doesn't append),
	 *    so writing only the user's changes would wipe the parent's palette. We
	 *    layer by slug: parent originals -> the user's edits to them -> the user's
	 *    new additions, so edited parent colors carry over and new ones are added.
	 *
	 * Non-preset settings (and all `styles`) deep-merge fine and are left as-is.
	 *
	 * @param array  $settings    User-data settings.
	 * @param string $parent_slug Slug whose theme.json presets to preserve.
	 * @return array Settings ready to write to the child theme.json.
	 */
	private static function prepare_child_settings( $settings, $parent_slug ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$parent = self::theme_json_settings( $parent_slug );

		foreach ( self::PRESET_PATHS as $path ) {
			list( $group, $key ) = $path;

			if ( ! isset( $settings[ $group ][ $key ] ) || ! is_array( $settings[ $group ][ $key ] ) ) {
				continue;
			}

			$value = $settings[ $group ][ $key ];

			if ( wp_is_numeric_array( $value ) ) {
				continue; // already a flat list — leave as-is
			}

			// In user data, `theme` holds the user's EDITS to the parent's presets
			// and `custom` holds their ADDITIONS.
			$theme_edits = ( ! empty( $value['theme'] ) && is_array( $value['theme'] ) ) ? array_values( $value['theme'] ) : array();
			$custom      = ( ! empty( $value['custom'] ) && is_array( $value['custom'] ) ) ? array_values( $value['custom'] ) : array();

			if ( ! $theme_edits && ! $custom ) {
				unset( $settings[ $group ][ $key ] ); // nothing user-set -> inherit the parent's
				continue;
			}

			// Layer by slug, later wins: parent originals -> user edits -> user additions.
			// So edited parent colors override the originals and new colors are added.
			$parent_presets = ( isset( $parent[ $group ][ $key ] ) && wp_is_numeric_array( $parent[ $group ][ $key ] ) ) ? $parent[ $group ][ $key ] : array();

			$by_slug = array();
			foreach ( array_merge( $parent_presets, $theme_edits, $custom ) as $preset ) {
				if ( isset( $preset['slug'] ) ) {
					$by_slug[ $preset['slug'] ] = $preset;
				}
			}

			$settings[ $group ][ $key ] = array_values( $by_slug );
		}

		return $settings;
	}

	/** A theme's theme.json `settings` (flat presets), read from its file. */
	private static function theme_json_settings( $slug ) {
		global $wp_filesystem;

		$file = get_theme_root( $slug ) . '/' . $slug . '/theme.json';
		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $file ) ) {
			return array();
		}

		$data = json_decode( $wp_filesystem->get_contents( $file ), true );

		return ( is_array( $data ) && ! empty( $data['settings'] ) ) ? $data['settings'] : array();
	}

	/** Posts of a type tagged to the theme via the wp_theme taxonomy. */
	private static function edited_posts( $post_type, $source_slug, $limit = -1 ) {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $source_slug,
					),
				),
			)
		);
	}

	private static function edited_slugs( $post_type, $source_slug ) {
		return wp_list_pluck( self::edited_posts( $post_type, $source_slug ), 'post_name' );
	}

	/**
	 * Re-point every wp:template-part reference at the child theme. A part
	 * reference pinned to the parent (theme:"blocklane") does NOT resolve while the
	 * child is active, but one pinned to the child does — the child resolves its
	 * own exported parts and inherits the rest from the parent's files. So all
	 * references get the child's slug.
	 *
	 * @param string $content    Block markup.
	 * @param string $child_slug Child stylesheet slug.
	 * @return string
	 */
	private static function repoint_parts( $content, $child_slug ) {
		if ( false === strpos( $content, 'wp:template-part' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<!--\s+wp:template-part\s+(\{[^}]*\})\s*(\/?-->)/',
			static function ( $m ) use ( $child_slug ) {
				$attrs = json_decode( $m[1], true );

				if ( is_array( $attrs ) && isset( $attrs['slug'] ) ) {
					$attrs['theme'] = $child_slug;

					return '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' ' . $m[2];
				}

				return $m[0];
			},
			$content
		);
	}
}
