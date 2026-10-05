<?php
/**
 * Helper: theme.json runtime layering, child theme generator.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {}

	/**
	 * Generate and activate a child theme of the active FSE parent theme.
	 *
	 * Copies the bundled child-theme template, rewrites its style.css header from
	 * the submitted details, and switches the active theme to the new child.
	 *
	 * @param array $data themeName (required), themeUrl, description, author,
	 *                    authorUrl, version, textDomain.
	 * @return true|\WP_Error True on success, WP_Error (with a status) on failure.
	 */
	public static function create_child_theme( $data ) {
		$template_dir = BLOCKLANE_PRO_PATH . '/inc/child-theme';
		$parent_dir   = get_template_directory();
		$themes_dir   = dirname( $parent_dir );

		$theme_slug = ! empty( $data['textDomain'] ) ? sanitize_title( $data['textDomain'] ) : sanitize_title( $data['themeName'] );

		// An empty slug would resolve to the themes root directory.
		if ( '' === $theme_slug ) {
			return new \WP_Error(
				'blocklane_pro_child_theme_invalid_name',
				__( 'Please use a theme name (or text domain) that contains letters or numbers.', 'blocklane' ),
				array( 'status' => 400 )
			);
		}

		// Never write into the parent theme or over any existing theme.
		if ( $theme_slug === basename( $parent_dir ) ) {
			return new \WP_Error(
				'blocklane_pro_child_theme_parent_collision',
				__( 'That matches the parent theme. Choose a different name or text domain.', 'blocklane' ),
				array( 'status' => 409 )
			);
		}

		// Use the WP Filesystem API rather than raw file calls. Force the
		// 'direct' transport: this is a server-side REST action with no UI to
		// collect FTP/SSH credentials, so direct is the only workable method.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		$force_direct = static function () {
			return 'direct';
		};
		add_filter( 'filesystem_method', $force_direct );
		$fs_ready = WP_Filesystem();
		remove_filter( 'filesystem_method', $force_direct );

		if ( ! $fs_ready ) {
			return new \WP_Error(
				'blocklane_pro_child_theme_filesystem',
				__( 'Could not access the filesystem to create the theme.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		$child_dir = $themes_dir . '/' . $theme_slug;

		// Collision check against the filesystem (the source of truth) rather
		// than wp_get_theme()->exists(), whose cache can wrongly report a
		// just-deleted theme as still present. Catches existing themes AND
		// leftover directories, so we never overwrite or leave partial files.
		clearstatcache( true, $child_dir );
		if ( $wp_filesystem->is_dir( $child_dir ) ) {
			return new \WP_Error(
				'blocklane_pro_child_theme_exists',
				__( 'A theme with this slug already exists. Choose a different name or text domain.', 'blocklane' ),
				array( 'status' => 409 )
			);
		}

		if ( ! $wp_filesystem->mkdir( $child_dir ) ) {
			return new \WP_Error(
				'blocklane_pro_child_theme_mkdir',
				__( 'Could not create the theme directory.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		$style = $wp_filesystem->get_contents( $template_dir . '/style.css' );
		if ( false === $style ) {
			self::remove_generated_dir( $child_dir );
			return new \WP_Error(
				'blocklane_pro_child_theme_template',
				__( 'Could not read the child theme template.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		// Rewrite each header line. Values are inserted via a callback so they're
		// treated literally — never as preg_replace backreferences ($1, \1, …).
		// Blank optional fields fall back to THIS SITE — its name and URL — plus a
		// description derived from the active parent and a {parent}-child text
		// domain. They used to fall back to Blocklane's own name and URLs, which
		// put the plugin vendor's authorship on a customer's theme.
		$parent_theme = wp_get_theme( get_template() );
		$headers      = array(
			'Theme Name'  => $data['themeName'],
			'Template'    => get_template(),
			// Blank fields fall back to THIS SITE, never to Blocklane. The child
			// theme belongs to whoever generated it: shipping a customer a theme
			// whose style.css credits the plugin vendor is wrong on any theme,
			// and reads as a mistake now that Pro runs on themes we did not write.
			'Theme URI'   => ! empty( $data['themeUrl'] ) ? $data['themeUrl'] : home_url(),
			'Description' => ! empty( $data['description'] ) ? $data['description'] : sprintf( 'A child theme of %s.', $parent_theme->get( 'Name' ) ),
			'Author'      => ! empty( $data['author'] ) ? $data['author'] : ( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : (string) $parent_theme->get( 'Author' ) ),
			'Author URI'  => ! empty( $data['authorUrl'] ) ? $data['authorUrl'] : home_url(),
			'Version'     => ! empty( $data['version'] ) ? $data['version'] : '1.0.0',
			'Text Domain' => ! empty( $data['textDomain'] ) ? $data['textDomain'] : get_template() . '-child',
		);

		foreach ( $headers as $label => $value ) {
			// Header values live inside the style.css `/* … */` comment, so a stray
			// `*/` would close it early and corrupt the file. Loop until clean — a
			// single str_replace pass can re-form `*/` (e.g. `**//` -> `*/`). Values
			// are already newline-free, so this only guards the comment terminator.
			$value = (string) $value;
			while ( false !== strpos( $value, '*/' ) ) {
				$value = str_replace( '*/', '', $value );
			}
			// Anchor to the start of the header line (^…$/m) so a value that happens
			// to contain another header label can't hijack a later replacement.
			$style = preg_replace_callback(
				'/^' . preg_quote( $label, '/' ) . ':.*$/m',
				static function () use ( $label, $value ) {
					return $label . ': ' . $value;
				},
				$style,
				1
			);
		}

		// Write the files; on any failure remove what we created. The child
		// ships the bundled branded screenshot ("Blocklane Child Theme").
		$written = $wp_filesystem->put_contents( $child_dir . '/style.css', $style )
			&& $wp_filesystem->copy( $template_dir . '/functions.php', $child_dir . '/functions.php' )
			&& $wp_filesystem->copy( $template_dir . '/screenshot.png', $child_dir . '/screenshot.png' );

		if ( ! $written ) {
			self::remove_generated_dir( $child_dir );
			return new \WP_Error(
				'blocklane_pro_child_theme_write',
				__( 'Could not write the child theme files. Check theme directory permissions.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		// Optionally bake the source theme's Site Editor customizations into the
		// child's files (global styles -> theme.json, edited templates/parts ->
		// HTML) so the child reproduces the customized look.
		$imported = null;
		if ( ! empty( $data['importCustomizations'] ) ) {
			$imported = (bool) Child_Theme_Import::export( Child_Theme_Import::resolve_source(), $theme_slug, $child_dir );
		}

		// Only activate once WordPress recognizes it as a valid theme.
		wp_clean_themes_cache();
		if ( ! wp_get_theme( $theme_slug )->exists() ) {
			self::remove_generated_dir( $child_dir );
			return new \WP_Error(
				'blocklane_pro_child_theme_invalid',
				__( 'The generated theme could not be validated.', 'blocklane' ),
				array( 'status' => 500 )
			);
		}

		switch_theme( $theme_slug );

		// Headers included so the UI can show the values actually written
		// (blank optional fields resolve server-side; see $headers above).
		return array(
			'imported' => $imported,
			'headers'  => $headers,
		);
	}

	/**
	 * Best-effort cleanup of a directory created during child-theme generation.
	 * Only ever called for a directory this run just created (generation bails
	 * earlier if the directory already existed).
	 *
	 * @param string $dir Absolute directory path.
	 */
	private static function remove_generated_dir( $dir ) {
		global $wp_filesystem;

		if ( $wp_filesystem && $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->delete( $dir, true );
		}
	}

	/**
	 * Recursively drop empty-array members from a decoded global-styles
	 * (user theme.json) doc before it is re-encoded.
	 *
	 * Gutenberg leaves empty {} objects behind when styles are cleared in
	 * the editor. json_decode( $json, true ) turns those into empty PHP
	 * arrays, which wp_json_encode() emits as [] — and Gutenberg's deepmerge
	 * treats [] as non-mergeable, clobbering the corresponding theme subtree
	 * (the {}-vs-[] deepmerge poison). Every writer that round-trips the
	 * wp_global_styles post content MUST pass the decoded doc through here
	 * at encode time.
	 *
	 * @param array $data Decoded user theme.json data.
	 * @return array The same data with all recursively-empty arrays removed.
	 */
	public static function prune_empty_deep( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::prune_empty_deep( $value );
				if ( array() === $data[ $key ] ) {
					unset( $data[ $key ] );
				}
			}
		}
		return $data;
	}

	/**
	 * Atomically add one failed attempt to a throttle counter and return the
	 * running total. Used by the Security login limiter and the site-lock
	 * unlock form. A get_transient()+1 / set_transient() pair is a
	 * read-modify-write — two parallel failures both read N and both store
	 * N+1, letting a scripted attacker squeeze extra guesses into the window.
	 *
	 * - External object cache: wp_cache_add + wp_cache_incr in the 'transient'
	 *   group (atomic in Redis/Memcached, and get_transient() reads the same
	 *   group, so lock checks keep working unchanged).
	 * - Database transients: claim the row with set_transient( 0 ) — which
	 *   also sets the expiry that gives the counter GC — then one
	 *   `UPDATE … value = value + 1` statement increments atomically.
	 *
	 * The window is fixed (set at first failure), not sliding: refreshing the
	 * expiry on every failure would let an attacker hold a victim IP locked
	 * forever by dripping one request per window.
	 *
	 * @param string $key    Transient-style counter key.
	 * @param int    $window Counting window in seconds.
	 * @return int Attempts recorded in the current window.
	 */
	public static function increment_throttle_counter( $key, $window ) {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'transient', $window );
			$count = wp_cache_incr( $key, 1, 'transient' );
			return false === $count ? 1 : (int) $count;
		}

		global $wpdb;

		if ( false === get_transient( $key ) ) {
			set_transient( $key, 0, $window );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- an atomic increment of the throttle counter; get_transient() + set_transient() would race between requests.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
				'_transient_' . $key
			)
		);
		wp_cache_delete( '_transient_' . $key, 'options' );

		return (int) get_transient( $key );
	}

	/**
	 * Autoload budget for a cache row the front end reads on every request.
	 *
	 * Autoloading is a genuine win while the payload is small — the row rides
	 * alloptions and the read costs nothing. Past this size it inverts: every
	 * request on the site (admin, REST, cron included, none of which read
	 * these rows) pays the bytes. Rows over budget stay on-demand instead.
	 *
	 * This is the single source of truth for that threshold. Callers that
	 * cannot reach this class — the runtimes, which stay free of plugin classes
	 * so load order cannot trip them — carry their own guarded copies:
	 * blocklane_pro_popups_autoload_ok() in inc/popups/runtime.php and
	 * blocklane_pro_ext_autoload_ok() in inc/extensions/loader/runtime-helpers.php.
	 * Keep the number in sync across all three.
	 *
	 * @param mixed $value The value about to be stored.
	 * @return bool Whether the option should autoload.
	 */
	public static function autoload_ok( $value ) {
		return strlen( maybe_serialize( $value ) ) < self::AUTOLOAD_MAX_BYTES;
	}

	/**
	 * Byte ceiling for an autoloaded cache row. See autoload_ok().
	 *
	 * @var int
	 */
	const AUTOLOAD_MAX_BYTES = 32768;

	/**
	 * Merge a store's normalized next row over the RAW stored row, so keys this
	 * build does not know survive the save.
	 *
	 * Both editions write one option namespace, and they are allowed to be at
	 * different versions and to take turns being the one that runs (free wakes
	 * when Pro is deactivated; a free build older than Pro is the ordinary
	 * state, since directory review lags a Pro release). A store that rebuilds
	 * its row from THIS build's key table therefore erases every key the newer
	 * or larger edition added, silently, on its first save — the customer finds
	 * out by reinstalling. The rule every store follows: a writer preserves what
	 * it does not understand. Read the RAW row, never the store's get(): get()
	 * normalizes to the known keys and has already dropped exactly the keys this
	 * exists to keep. Read-time get() still returns known keys only — a
	 * dashboard never sees, edits or echoes a foreign key.
	 *
	 * The closure of the guarantee, so nobody documents it wider: top-level
	 * foreign keys are kept; inside a known key the merge recurses into exactly
	 * one shape — a NON-EMPTY, all-string-keyed template (a map of settings, such
	 * as seo.verification or forms.defaults) where both the next and the raw
	 * value are arrays. A list template (reorder-post-types, org_profiles) and
	 * an empty-map template (local_hours) are opaque: their members are values
	 * the store owns, not keys, and the store's normalizer writes them whole. A
	 * key that changes SHAPE or TYPE between versions must change NAME — a
	 * carry-through cannot protect a key both editions claim.
	 *
	 * A stored value that is not a row at all (a string, an object — only
	 * something outside the plugin writes that) is refused as a typed failure
	 * and the caller writes nothing; paving it over would be the silently
	 * emptied cache from the defect history. `false`, `''` and a missing row
	 * are "no row" (WordPress stores `false` as `''`).
	 *
	 * @param string              $option Option name.
	 * @param array<string,mixed> $next   The normalized row this build is about to write — known keys only.
	 * @param array<string,mixed> $known  The template of keys this build owns (a DEFAULTS table or defaults()).
	 * @return array<string,mixed>|\WP_Error The row to write, or blocklane_pro_option_unreadable when the stored value is not a row.
	 */
	public static function with_foreign_keys( string $option, array $next, array $known ): array|\WP_Error {
		$raw = get_option( $option, null );
		if ( null === $raw || false === $raw || '' === $raw ) {
			return $next;
		}
		if ( ! is_array( $raw ) ) {
			return new \WP_Error(
				'blocklane_pro_option_unreadable',
				sprintf( 'The stored value of %s is not a settings row; nothing was written. Inspect it with `wp option get %1$s`.', $option ),
				array(
					'status' => 500,
					'option' => $option,
				)
			);
		}
		return self::merge_foreign( $next, $raw, $known );
	}

	/**
	 * The merge behind with_foreign_keys(): $next wins on every key it names,
	 * foreign keys ride along, and string-keyed map templates recurse.
	 *
	 * @param array<string,mixed> $next  Known keys, normalized.
	 * @param array<mixed>        $raw   The stored row as read.
	 * @param array<string,mixed> $known The template this level of the store owns.
	 * @return array<string,mixed>
	 */
	private static function merge_foreign( array $next, array $raw, array $known ): array {
		$out = $next + array_diff_key( $raw, $known );
		foreach ( $known as $key => $template ) {
			if ( ! is_array( $template ) || array() === $template || ! self::is_string_keyed( $template ) ) {
				continue;
			}
			if ( isset( $next[ $key ] ) && is_array( $next[ $key ] ) && isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
				$out[ $key ] = self::merge_foreign( $next[ $key ], $raw[ $key ], $template );
			}
		}
		return $out;
	}

	/**
	 * Whether every key of an array is a string — the "map, not list" test the
	 * foreign-key merge recurses on.
	 *
	 * @param array<mixed> $value The array to test.
	 * @return bool
	 */
	private static function is_string_keyed( array $value ): bool {
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) ) {
				return false;
			}
		}
		return true;
	}
}
