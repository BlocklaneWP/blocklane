<?php
/**
 * Bundled patterns — the registrar, carried by BOTH editions.
 *
 * WHAT IT IS. Registers the Blocklane-tier patterns this plugin ships
 * (spec 2026-10-15-bundled-patterns): every `patterns/<slug>.php` as tier
 * `free`. The Pro edition's contributor (`bundled-patterns:pro`,
 * pro/runtime.php) adds its own directory as tier `pro` on this file's
 * `blocklane_pro_bundled_patterns_tiers` action, through this file's door,
 * blocklane_pro_bundled_patterns_register_dir(); the free stage removes the
 * contributor whole, so this file holds no Pro loop (security amendment F1). The
 * files are written only by the theme repo's capture door
 * (`tools/patterns/capture.mjs --target=plugin`), which stores the editor's
 * own bytes; nothing here is hand-authored markup. The registration list is
 * the glob: no hand list sits beside the directory.
 *
 * WHERE THE TIER COMES FROM. The registering code, never the pattern file:
 * this file passes `free` for its own directory, the contributor `pro` for
 * its. Every registration goes through Pattern_Tiers, the one writer of a
 * Blocklane pattern's tier.
 *
 * THE FILE SHAPE. A pattern file prints its markup and returns its
 * properties: `title`, optional `description`, `categories`, `keywords`,
 * `blockTypes`, `postTypes`, `templateTypes` (core's, or a template the
 * plugin registers: Site Lock's coming-soon and maintenance), `viewportWidth`
 * and `requires`. The markup is
 * the output buffer, never a property, and the name is `blocklane/<slug>`
 * from the file name. `requires` is the list of non-core blocks the markup
 * uses; a pattern whose block is not registered (its feature is off, or a
 * torn build) is skipped silently, the normal state of a site that has not
 * turned Forms or Popups on. Include-returns-props instead of core's header
 * comment because core translates headers through a variable gettext call,
 * which this tree's GettextLiteralRule refuses.
 *
 * THE DOORS, named.
 * 1. Safe mode: the return at the top of this file.
 * 2. Classic themes: nothing registers (patterns of block markup belong to
 *    block themes). Any block theme registers them (decision D3 = A).
 * 3. A name already registered is never overwritten: the theme's pattern
 *    wins a collision, and Pattern_Tiers records nothing for the name.
 * 4. A file that throws, returns a non-array, returns an unknown key, or
 *    prints nothing is skipped and logged once a day whatever WP_DEBUG says
 *    (blocklane_pro_log_failure()); the output-buffer level is restored
 *    whatever the file did.
 * 5. A file name outside the slug grammar is ignored here and fails
 *    bin/bundled-patterns-check.php (BP1) in CI.
 *
 * THE OVERLAY SUPPLY (spec 2026-10-17-header-overlay-pairs §3.6, §16). A
 * bundled header's navigation names its overlay by a bare part slug,
 * `navigation-overlay-<design>`. The Blocklane theme ships those parts; on
 * any other block theme this file supplies them, each one line pointing at
 * the plugin's own copy of the overlay pattern (`navigation-<design>`,
 * captured from the theme's under the theme's name, so on Blocklane the
 * theme's registration wins by door 3). Precedence is database > theme
 * file > supplied, through two core filters: get_block_file_template (one
 * part) and get_block_templates (the lists). Nothing is written: no post,
 * no option, no file; deactivating the plugin removes the supply, and a
 * user's saved copy of a supplied part is their own database part.
 *
 * A function file (function_exists-wrapped, no classes, no namespace, no
 * plugin constant), the runtime rule: __DIR__ resolves under either edition's
 * directory name.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single-load guard: the plugin requires this file once, from the Modules
// content table; two editions on one site never both boot (the loser's
// bootstrap returns at its guard), so this is belt, not authority.
if ( defined( 'BLOCKLANE_PRO_BUNDLED_PATTERNS_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_BUNDLED_PATTERNS_RUNTIME_LOADED', true );

// Safe mode silences every Blocklane surface, this one included (door 1).
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

if ( ! function_exists( 'blocklane_pro_bundled_patterns_register' ) ) {

	/**
	 * The URL of the bundled patterns' image directory, with no trailing
	 * slash. A pattern file prints it through esc_url() and appends
	 * `/<file>.webp`; it is the only absolute URL a bundled pattern carries.
	 *
	 * @return string
	 */
	function blocklane_pro_bundled_patterns_url(): string {
		return untrailingslashit( plugins_url( 'assets', __FILE__ ) );
	}

	/**
	 * Run one pattern file: its printed markup and its returned properties.
	 *
	 * The buffer level is restored whatever the file does (door 4), and a
	 * file that leaves a buffer of its own open is refused rather than
	 * guessed at.
	 *
	 * @param string $file Absolute path of a pattern file.
	 * @return array{props: mixed, content: string}|\WP_Error
	 */
	function blocklane_pro_bundled_patterns_run( string $file ): array|\WP_Error {
		$level  = ob_get_level();
		$props  = null;
		$failed = null;
		$nested = false;
		$output = '';
		ob_start();
		try {
			$props = include $file;
		} catch ( \Throwable $e ) {
			$failed = $e;
		} finally {
			$nested = ob_get_level() !== $level + 1;
			while ( ob_get_level() > $level ) {
				$output = (string) ob_get_clean();
			}
		}
		if ( null !== $failed ) {
			return new \WP_Error( 'blocklane_bundled_pattern_threw', get_class( $failed ) . ': ' . $failed->getMessage() );
		}
		if ( $nested ) {
			return new \WP_Error( 'blocklane_bundled_pattern_buffer', 'the file left an output buffer open or closed one it did not open' );
		}
		return array(
			'props'   => $props,
			'content' => $output,
		);
	}

	/**
	 * Validate a pattern file's returned properties (door 4).
	 *
	 * @param mixed $props What the file returned.
	 * @return array<string, mixed>|\WP_Error The properties, or why they were refused.
	 */
	function blocklane_pro_bundled_patterns_shape( mixed $props ): array|\WP_Error {
		if ( ! is_array( $props ) ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'the file did not return an array' );
		}
		$lists   = array( 'categories', 'keywords', 'blockTypes', 'postTypes', 'templateTypes', 'requires' );
		$strings = array( 'title', 'description' );
		$unknown = array_diff( array_keys( $props ), $lists, $strings, array( 'viewportWidth', 'enhances' ) );
		if ( array() !== $unknown ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'unknown key ' . implode( ', ', array_map( 'strval', $unknown ) ) );
		}
		if ( ! isset( $props['title'] ) || ! is_string( $props['title'] ) || '' === trim( $props['title'] ) ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'the title is not a non-empty string' );
		}
		if ( isset( $props['description'] ) && ! is_string( $props['description'] ) ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'the description is not a string' );
		}
		foreach ( $lists as $key ) {
			if ( ! isset( $props[ $key ] ) ) {
				continue;
			}
			$list = $props[ $key ];
			if ( ! is_array( $list ) || ! array_is_list( $list ) || count( array_filter( $list, 'is_string' ) ) !== count( $list ) ) {
				return new \WP_Error( 'blocklane_bundled_pattern_shape', $key . ' is not a list of strings' );
			}
		}
		if ( isset( $props['viewportWidth'] ) && ! is_int( $props['viewportWidth'] ) ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'viewportWidth is not an integer' );
		}
		if ( isset( $props['enhances'] ) && ( ! is_string( $props['enhances'] ) || 1 !== preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/D', $props['enhances'] ) ) ) {
			return new \WP_Error( 'blocklane_bundled_pattern_shape', 'enhances is not a pattern name' );
		}
		return $props;
	}

	/**
	 * Whether a pattern's `enhances` (the pattern one tier down, read by
	 * the Pro Showcase's tier ladder) names a registered pattern of the
	 * tier below this one: for a free pattern, one no Blocklane
	 * registration claims (the theme's); for a Pro one, a Blocklane free
	 * one. Never the pattern itself.
	 *
	 * @param string $name     The pattern being registered.
	 * @param string $enhances The name it says it enhances.
	 * @param string $tier     Its tier.
	 */
	function blocklane_pro_bundled_patterns_enhances_ok( string $name, string $enhances, string $tier ): bool {
		if ( $enhances === $name || ! WP_Block_Patterns_Registry::get_instance()->is_registered( $enhances ) ) {
			return false;
		}
		$below = \blocklane_pro\Pattern_Tiers::of( $enhances );
		// Written from the free tier's side: this file names no Pro tier
		// (BR12, F1); a registration of any other known tier is Pro's.
		return 'free' === $tier ? null === $below : 'free' === $below;
	}

	/**
	 * Whether a pattern's features are present: every required block is
	 * registered, and when it names post types, at least one exists. A
	 * missing feature is the normal state of a site with Forms or Popups
	 * off, so it is never logged.
	 *
	 * @param array<string, mixed> $props Validated properties.
	 * @return bool
	 */
	function blocklane_pro_bundled_patterns_available( array $props ): bool {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( (array) ( $props['requires'] ?? array() ) as $block ) {
			if ( ! $registry->is_registered( $block ) ) {
				return false;
			}
		}
		$post_types = (array) ( $props['postTypes'] ?? array() );
		if ( array() !== $post_types && array() === array_filter( $post_types, 'post_type_exists' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * The door: register every pattern file of one directory with one tier.
	 * This file calls it for its own directory; the Pro contributor calls it
	 * for its directory from the tiers action.
	 *
	 * @param string $dir  Absolute directory of `<slug>.php` pattern files.
	 * @param string $tier The tier the registering code claims for them.
	 * @return void
	 */
	function blocklane_pro_bundled_patterns_register_dir( string $dir, string $tier ): void {
		$patterns = WP_Block_Patterns_Registry::get_instance();
		$files    = (array) glob( $dir . '/*.php' );
		sort( $files, SORT_STRING );
		foreach ( $files as $file ) {
			$file = (string) $file;
			$slug = basename( $file, '.php' );
			// Door 5: the grammar BP1 enforces in CI.
			if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
				continue;
			}
			$name = 'blocklane/' . $slug;
			// Door 3: never overwrite; the theme wins a collision.
			if ( $patterns->is_registered( $name ) ) {
				// The theme's own registration of an overlay still records
				// the supply of its part slug: the supply points at the name,
				// and the registry answers the theme's pattern at use time.
				// Skipping the record here left the slug unsupplied after a
				// theme switch (bundled-patterns battery BR25; spec
				// 2026-10-18-pro-patterns-wave2 §13.5). Only the active
				// theme's own file counts: another plugin's collision
				// supplies nothing, as before.
				$theirs = (array) $patterns->get_registered( $name );
				if ( blocklane_pro_bundled_patterns_is_theme_file( (string) ( $theirs['filePath'] ?? '' ) ) ) {
					blocklane_pro_bundled_patterns_overlay_record( $slug, $theirs );
				}
				continue;
			}
			$ran = blocklane_pro_bundled_patterns_run( $file );
			if ( is_wp_error( $ran ) ) {
				blocklane_pro_log_failure( "Blocklane: bundled pattern {$name} was not registered: " . $ran->get_error_message() . '.' );
				continue;
			}
			$props = blocklane_pro_bundled_patterns_shape( $ran['props'] );
			if ( is_wp_error( $props ) ) {
				blocklane_pro_log_failure( "Blocklane: bundled pattern {$name} was not registered: " . $props->get_error_message() . '.' );
				continue;
			}
			if ( '' === trim( $ran['content'] ) ) {
				blocklane_pro_log_failure( "Blocklane: bundled pattern {$name} was not registered: it printed no markup." );
				continue;
			}
			if ( ! blocklane_pro_bundled_patterns_available( $props ) ) {
				continue;
			}
			unset( $props['requires'] );
			// An `enhances` that does not name the pattern one tier down is
			// dropped, never trusted. A debug line, not a failure: the target
			// is absent in ordinary setups (another block theme, a theme
			// pattern renamed), and the drop is the designed outcome.
			if ( isset( $props['enhances'] ) && ! blocklane_pro_bundled_patterns_enhances_ok( $name, $props['enhances'], $tier ) ) {
				blocklane_pro_log( "Blocklane: bundled pattern {$name}'s enhances ({$props['enhances']}) is not a registered pattern one tier down; ignored." );
				unset( $props['enhances'] );
			}
			// Pattern_Tiers logs its own refusals. An overlay is recorded
			// whatever it answered: the supply asks the registry at use time.
			\blocklane_pro\Pattern_Tiers::register(
				$name,
				$props + array(
					'content' => $ran['content'],
					'source'  => 'plugin',
				),
				$tier
			);
			blocklane_pro_bundled_patterns_overlay_record( $slug, $props );
		}
	}

	/**
	 * The overlays this request's registrations can supply, by part slug:
	 * every bundled pattern registered for the navigation-overlay area whose
	 * name is `navigation-<design>`, under the part slug
	 * `navigation-overlay-<design>` (the theme generator's rule, header and
	 * overlay pairs spec §3.2; bin/bundled-patterns-check.php BP-overlay-name
	 * holds the name). Read with no argument; the registrar adds one overlay
	 * per registration it made.
	 *
	 * @param array<string, array{pattern: string, title: string, description: string}> $add Part slug => what supplies it.
	 * @return array<string, array{pattern: string, title: string, description: string}>
	 */
	function blocklane_pro_bundled_patterns_overlays( array $add = array() ): array {
		static $overlays = array();
		$overlays = $add + $overlays;
		return $overlays;
	}

	/**
	 * Whether a pattern file belongs to the active theme or its parent: a
	 * regular file under either directory, compared as real paths so a
	 * symlinked theme folder (a Studio site's) still matches.
	 *
	 * @param string $path The registered pattern's filePath, or ''.
	 * @return bool
	 */
	function blocklane_pro_bundled_patterns_is_theme_file( string $path ): bool {
		$real = '' !== $path ? realpath( $path ) : false;
		if ( false === $real || ! is_file( $real ) ) {
			return false;
		}
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
			$root = realpath( $dir );
			if ( false !== $root && str_starts_with( $real, rtrim( $root, '/' ) . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Record a pattern the registrar just registered, when it is an overlay.
	 * The part's title is the pattern title's descriptor (the text after
	 * `<Lead>: `), as the theme names its own overlay parts.
	 *
	 * @param string               $slug  The pattern file's slug.
	 * @param array<string, mixed> $props Its validated properties.
	 */
	function blocklane_pro_bundled_patterns_overlay_record( string $slug, array $props ): void {
		if ( ! str_starts_with( $slug, 'navigation-' ) || ! in_array( 'core/template-part/navigation-overlay', (array) ( $props['blockTypes'] ?? array() ), true ) ) {
			return;
		}
		$title = (string) $props['title'];
		$colon = strpos( $title, ': ' );
		blocklane_pro_bundled_patterns_overlays(
			array(
				'navigation-overlay-' . substr( $slug, strlen( 'navigation-' ) ) => array(
					'pattern'     => 'blocklane/' . $slug,
					'title'       => false === $colon ? $title : substr( $title, $colon + 2 ),
					'description' => (string) ( $props['description'] ?? '' ),
				),
			)
		);
	}

	/**
	 * The overlay part this plugin supplies under a slug on the active theme,
	 * or null (header and overlay pairs spec §3.6 and §16, the free supply).
	 * A bundled header names its overlay by a bare part slug; on Blocklane
	 * the theme ships that part, and on any other block theme the plugin
	 * supplies it: one line pointing at the plugin's own copy of the overlay
	 * pattern, so the copy stays in the translated PHP pattern.
	 *
	 * Precedence is database > theme file > supplied, and this is the one
	 * place that decides "supplied". It supplies nothing:
	 * - on a classic theme (block markup is for block themes, as door 2);
	 * - for a slug no registration of this request recorded, or whose
	 *   pattern is no longer registered (a pointer to a missing pattern
	 *   would open an empty dialog);
	 * - when the active theme or its parent ships a part file of that slug
	 *   (_get_block_template_file(), the file read core's own resolver
	 *   makes), so a Blocklane theme's own part always wins.
	 * A database copy never reaches here: core's resolvers (the navigation
	 * overlay, the template-part block, get_block_template()) query the
	 * database first, and the list door keeps whatever the query found.
	 *
	 * @param string $slug A template part slug.
	 * @return \WP_Block_Template|null
	 */
	function blocklane_pro_bundled_patterns_supplied_part( string $slug ): ?\WP_Block_Template {
		$overlay = blocklane_pro_bundled_patterns_overlays()[ $slug ] ?? null;
		if ( null === $overlay || ! wp_is_block_theme() || ! WP_Block_Patterns_Registry::get_instance()->is_registered( $overlay['pattern'] ) ) {
			return null;
		}
		// A private core function: should core rename it, supply nothing
		// rather than fatal (fail closed; the theme-file test cannot run).
		if ( ! function_exists( '_get_block_template_file' ) || null !== _get_block_template_file( 'wp_template_part', $slug ) ) {
			return null;
		}
		$part                 = new \WP_Block_Template();
		$part->type           = 'wp_template_part';
		$part->theme          = get_stylesheet();
		$part->slug           = $slug;
		$part->id             = $part->theme . '//' . $slug;
		$part->title          = $overlay['title'];
		$part->description    = $overlay['description'];
		$part->content        = '<!-- wp:pattern ' . wp_json_encode( array( 'slug' => $overlay['pattern'] ), JSON_UNESCAPED_SLASHES ) . " /-->\n";
		$part->source         = 'plugin';
		$part->origin         = 'plugin';
		$part->plugin         = explode( '/', plugin_basename( __FILE__ ) )[0];
		$part->status         = 'publish';
		$part->area           = 'navigation-overlay'; // Core's WP_TEMPLATE_PART_AREA_NAVIGATION_OVERLAY.
		$part->has_theme_file = false;
		$part->is_custom      = false;
		return $part;
	}

	/**
	 * The single-part door (core's get_block_file_template filter, which the
	 * navigation overlay, the template-part block and get_block_template()
	 * reach after the database): a template part of the active theme that
	 * nothing resolved AS A TEMPLATE PART gets the supplied overlay.
	 *
	 * "As a template part" is the test, not "resolved": core's file read
	 * falls through to the template registry by slug, whatever the type
	 * (block-template-utils.php:1412), so a `wp_template` registered under
	 * an overlay's slug would otherwise render as that overlay (pairs spec
	 * §12 F7). The supplied part replaces such a result; a template part
	 * from the theme or another plugin is kept as found.
	 *
	 * @param mixed  $found What core (or an earlier callback) resolved.
	 * @param string $id    Template id, `<stylesheet>//<slug>`.
	 * @param string $type  wp_template or wp_template_part.
	 * @return mixed
	 */
	function blocklane_pro_bundled_patterns_supply_part( mixed $found, string $id, string $type ): mixed {
		if ( 'wp_template_part' !== $type || ( $found instanceof \WP_Block_Template && 'wp_template_part' === $found->type ) ) {
			return $found;
		}
		$parts = explode( '//', $id, 2 );
		if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] ) {
			return $found;
		}
		return blocklane_pro_bundled_patterns_supplied_part( $parts[1] ) ?? $found;
	}

	/**
	 * The list door (core's get_block_templates filter: the Site Editor,
	 * the navigation inspector's Overlay selector, REST): the supplied
	 * overlays join the list, so core's inspector names them instead of
	 * calling a header's overlay "(missing)". A slug the list already holds
	 * (a database copy, a theme file, another plugin's part) is never added
	 * twice, the query's area and slug__in are honored, and a query for one
	 * database post (wp_id) gets nothing added. (Core overwrites a caller's
	 * slug__not_in with the database's slugs before this filter, so the
	 * list itself is the one record of what is already there.)
	 *
	 * @param array<int, mixed>    $found The templates core found.
	 * @param array<string, mixed> $query get_block_templates()' query.
	 * @param string               $type  wp_template or wp_template_part.
	 * @return array<int, mixed>
	 */
	function blocklane_pro_bundled_patterns_supply_parts( array $found, array $query, string $type ): array {
		if ( 'wp_template_part' !== $type || isset( $query['wp_id'] ) || ( isset( $query['area'] ) && 'navigation-overlay' !== $query['area'] ) ) {
			return $found;
		}
		$held = array();
		foreach ( $found as $template ) {
			if ( $template instanceof \WP_Block_Template ) {
				$held[ $template->slug ] = true;
			}
		}
		foreach ( array_keys( blocklane_pro_bundled_patterns_overlays() ) as $slug ) {
			$slug = (string) $slug;
			if ( isset( $held[ $slug ] )
				|| ( ! empty( $query['slug__in'] ) && ! in_array( $slug, (array) $query['slug__in'], true ) ) ) {
				continue;
			}
			$part = blocklane_pro_bundled_patterns_supplied_part( $slug );
			if ( null !== $part ) {
				$found[] = $part;
			}
		}
		return $found;
	}

	/**
	 * Register every bundled pattern this edition carries, on `init` 20:
	 * after core's theme patterns (init 10) and after the forms blocks. The
	 * free tier first, then whatever the tiers action adds.
	 *
	 * @return void
	 */
	function blocklane_pro_bundled_patterns_register(): void {
		// Door 2: block markup is for block themes; any block theme will do.
		if ( ! wp_is_block_theme() ) {
			return;
		}
		blocklane_pro_bundled_patterns_register_dir( __DIR__ . '/patterns', 'free' );

		/**
		 * Fires after the free tier registers: the Pro edition's contributor
		 * registers its own directory here, through
		 * blocklane_pro_bundled_patterns_register_dir().
		 */
		do_action( 'blocklane_pro_bundled_patterns_tiers' );
	}

	add_action( 'init', 'blocklane_pro_bundled_patterns_register', 20 );
	// Late, so an earlier part (another plugin's) is kept as found.
	add_filter( 'get_block_file_template', 'blocklane_pro_bundled_patterns_supply_part', 20, 3 );
	add_filter( 'get_block_templates', 'blocklane_pro_bundled_patterns_supply_parts', 20, 3 );
}
