<?php
/**
 * Header, overlay and panel pairs (spec 2026-10-17-header-overlay-pairs
 * §3.7, with its security read's F5; spec "Mega Menu pairs", 2026-10-17,
 * §3.6): which menu toggle a pattern shows, which overlay part that toggle
 * opens, and which Mega Menu panel parts its items open, read from the
 * pattern's own tree and core's FILE door alone; and, inversely, which
 * registered patterns open a given overlay or panel pattern.
 *
 * The one derivation door for pairs: the public API (blocklane_pro_api_
 * pattern_pairs(), blocklane_pro_api_overlay_partners(),
 * blocklane_pro_api_panel_partners()) forwards here, and nothing else
 * re-derives a pair. It reads core's file door and the pattern registry,
 * so it lives beside Pattern_Taxonomy, which is pure, rather than inside
 * it. A database copy of a part (a migration's, a customization's) is
 * never read: a pair is what the theme ships, or (for a panel) what a
 * plugin supplies through core's file filter. It never calls the module
 * that supplies panels: the free edition carries this class and not that
 * module, so there no panel is supplied, and every panel reads missing.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pairs of a pattern's menu toggle and the overlay part it opens.
 */
final class Pattern_Pairs {

	/** Core's navigation shows its menu toggle below this width (core/navigation's style.css). */
	public const MENU_BREAKPOINT = 600;

	/** How deep a pattern reference is followed when reading a pattern's tree. */
	public const PATTERN_DEPTH = 4;

	/** The block types an overlay pattern is registered for. */
	public const OVERLAY_AREA = 'core/template-part/navigation-overlay';

	/** The block types a Mega Menu panel pattern is registered for. */
	public const PANEL_AREA = 'core/template-part/menu';

	/** The block whose items open panels, and the attribute naming each one's part. */
	private const MEGA_BLOCK = 'blocklane-pro/mega-menu';

	/** A template part slug the Site Editor writes: a-z, 0-9 and hyphens. */
	private const PART_SLUG = '/^[a-z0-9-]+$/D';

	/**
	 * Every pattern's pairs, by name, for one state of the registry and one
	 * theme (partners()' index): rebuilt when either changes.
	 *
	 * @var array{key: string, pairs: array<string, array<string, mixed>|\WP_Error>}|null
	 */
	private static ?array $index = null;

	/**
	 * A pattern's pairs: its menu toggle, the overlay that toggle opens, and
	 * the panels its Mega Menu items open.
	 *
	 * - `toggle`: null when no navigation shows a toggle (every one is
	 *   overlayMenu "never"); else `always` when one is "always", and
	 *   `below` the largest of core's 600 and, where Pro's mobile breakpoint
	 *   is on, that navigation's breakpoint.
	 * - `overlay`: null without a toggle; else the part a toggling
	 *   navigation names and its state: `theme` (the active theme's file is
	 *   one pointer to a registered pattern, named in `pattern`), `inline`
	 *   (any other file content), `missing` (the active theme has no file of
	 *   that slug), `foreign` (`theme//slug` naming another stylesheet, or a
	 *   slug outside the Site Editor's grammar, a-z, 0-9 and hyphens), or
	 *   `default` (the navigations name no part: core's own overlay).
	 * - `panels`: one entry per Mega Menu item, in document order (two items
	 *   naming one part are two entries), whatever the toggle, since the
	 *   items show at desktop widths either way. Each is the item's
	 *   menuSlug and its state: `missing` (no slug, or no part answers it),
	 *   `foreign` (outside the Site Editor's grammar, `theme//slug`
	 *   included), `theme` (a theme file holding one pointer to a registered
	 *   panel pattern, named in `pattern`), `supplied` (a plugin's part that
	 *   is no theme file, holding such a pointer: Pro's Mega Menu module),
	 *   or `inline` (a theme or supplied part holding anything else).
	 *
	 * @param array<string, mixed> $pattern A registry entry, or any array with `content`.
	 * @return array{toggle: array{always: bool, below: int}|null, overlay: array{slug: string, state: string, pattern: ?string}|null, panels: list<array{slug: string, state: string, pattern: ?string}>}|\WP_Error
	 *         blocklane_pairs_ambiguous when two toggling navigations name different overlays.
	 */
	public static function of( array $pattern ): array|\WP_Error {
		$tree = self::read_tree( is_string( $pattern['content'] ?? null ) ? $pattern['content'] : '' );
		$out  = array(
			'toggle'  => null,
			'overlay' => null,
			'panels'  => array_map( static fn( string $slug ): array => self::panel( $slug ), $tree['panels'] ),
		);
		$named = array();
		foreach ( $tree['navigations'] as $attrs ) {
			$menu = is_string( $attrs['overlayMenu'] ?? null ) ? $attrs['overlayMenu'] : 'mobile';
			if ( 'never' === $menu ) {
				continue;
			}
			$below         = empty( $attrs['mobileMenuBreakpointEnabled'] ) ? self::MENU_BREAKPOINT : max( self::MENU_BREAKPOINT, absint( $attrs['mobileMenuBreakpoint'] ?? 0 ) );
			$out['toggle'] = array(
				'always' => ( $out['toggle']['always'] ?? false ) || 'always' === $menu,
				'below'  => max( $out['toggle']['below'] ?? 0, $below ),
			);
			$slug          = is_string( $attrs['overlay'] ?? null ) ? trim( $attrs['overlay'] ) : '';
			if ( '' !== $slug ) {
				$named[ $slug ] = true;
			}
		}
		if ( null === $out['toggle'] ) {
			return $out;
		}
		if ( count( $named ) > 1 ) {
			return new \WP_Error( 'blocklane_pairs_ambiguous', 'Two navigations that show a menu toggle name different overlays.', array( 'overlays' => array_keys( $named ) ) );
		}
		$out['overlay'] = array() === $named
			? array(
				'slug'    => '',
				'state'   => 'default',
				'pattern' => null,
			)
			: self::resolve( (string) array_key_first( $named ) );
		return $out;
	}

	/**
	 * The registered, inserter-visible patterns whose overlay is the given
	 * overlay pattern, in registry order.
	 *
	 * @param string $name A pattern name.
	 * @return list<string>|\WP_Error blocklane_pairs_not_overlay for a name that is not a registered navigation-overlay pattern.
	 */
	public static function partners( string $name ): array|\WP_Error {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$overlay  = $registry->get_registered( $name );
		if ( ! is_array( $overlay ) || ! in_array( self::OVERLAY_AREA, (array) ( $overlay['blockTypes'] ?? array() ), true ) ) {
			return new \WP_Error( 'blocklane_pairs_not_overlay', 'That name is not a registered navigation overlay pattern.', array( 'name' => $name ) );
		}
		$out = array();
		foreach ( self::index() as $other => $pairs ) {
			if ( is_array( $pairs ) && ( $pairs['overlay']['pattern'] ?? null ) === $name ) {
				$out[] = (string) $other;
			}
		}
		return $out;
	}

	/**
	 * The registered, inserter-visible patterns with an item that opens the
	 * given panel pattern (a `theme` or `supplied` panel naming it), in
	 * registry order, each once however many of its items open it.
	 *
	 * @param string $name A pattern name.
	 * @return list<string>|\WP_Error blocklane_pairs_not_panel for a name that is not a registered Mega Menu panel pattern.
	 */
	public static function panel_partners( string $name ): array|\WP_Error {
		$panel = \WP_Block_Patterns_Registry::get_instance()->get_registered( $name );
		if ( ! is_array( $panel ) || ! in_array( self::PANEL_AREA, (array) ( $panel['blockTypes'] ?? array() ), true ) ) {
			return new \WP_Error( 'blocklane_pairs_not_panel', 'That name is not a registered Mega Menu panel pattern.', array( 'name' => $name ) );
		}
		$out = array();
		foreach ( self::index() as $other => $pairs ) {
			foreach ( is_array( $pairs ) ? $pairs['panels'] : array() as $entry ) {
				if ( $name === $entry['pattern'] ) {
					$out[ (string) $other ] = true;
				}
			}
		}
		return array_map( 'strval', array_keys( $out ) );
	}

	/**
	 * Every inserter-visible registered pattern's pairs, built once per
	 * registry state and theme.
	 *
	 * @return array<string, array<string, mixed>|\WP_Error>
	 */
	private static function index(): array {
		$all = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$key = md5( get_stylesheet() . '|' . implode( ',', array_column( $all, 'name' ) ) );
		if ( null !== self::$index && $key === self::$index['key'] ) {
			return self::$index['pairs'];
		}
		$pairs = array();
		foreach ( $all as $pattern ) {
			if ( false === ( $pattern['inserter'] ?? true ) ) {
				continue;
			}
			$pairs[ (string) $pattern['name'] ] = self::of( $pattern );
		}
		self::$index = array(
			'key'   => $key,
			'pairs' => $pairs,
		);
		return $pairs;
	}

	/**
	 * One walk of the markup, pattern references expanded through the
	 * registry to PATTERN_DEPTH, each once: the attributes of every
	 * navigation, and every Mega Menu item's menuSlug ('' when it has none),
	 * both in document order.
	 *
	 * @param string $content Block markup.
	 * @return array{navigations: list<array<string, mixed>>, panels: list<string>}
	 */
	private static function read_tree( string $content ): array {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$found    = array(
			'navigations' => array(),
			'panels'      => array(),
		);
		$seen     = array();
		$walk     = static function ( array $blocks, int $depth ) use ( &$walk, &$found, &$seen, $registry ): void {
			foreach ( $blocks as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}
				$name  = (string) ( $block['blockName'] ?? '' );
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				if ( 'core/navigation' === $name ) {
					$found['navigations'][] = $attrs;
				}
				if ( self::MEGA_BLOCK === $name ) {
					$found['panels'][] = is_string( $attrs['menuSlug'] ?? null ) ? $attrs['menuSlug'] : '';
				}
				$slug = is_string( $attrs['slug'] ?? null ) ? $attrs['slug'] : '';
				if ( 'core/pattern' === $name && $depth < self::PATTERN_DEPTH && '' !== $slug && ! isset( $seen[ $slug ] ) && $registry->is_registered( $slug ) ) {
					$seen[ $slug ] = true;
					$walk( parse_blocks( (string) ( $registry->get_registered( $slug )['content'] ?? '' ) ), $depth + 1 );
				}
				$walk( (array) ( $block['innerBlocks'] ?? array() ), $depth );
			}
		};
		$walk( parse_blocks( $content ), 0 );
		return $found;
	}

	/**
	 * A named overlay part, resolved the way core resolves a bare slug
	 * (the active theme, then its parent), from FILES only: a part is
	 * accepted only as a template part the theme ships as a file (type
	 * wp_template_part, source theme, has_theme_file: F5), so neither a
	 * database copy nor a plugin-registered template of that slug answers.
	 *
	 * @param string $named The navigation's overlay value.
	 * @return array{slug: string, state: string, pattern: ?string}
	 */
	private static function resolve( string $named ): array {
		$slug = $named;
		if ( str_contains( $named, '//' ) ) {
			list( $theme, $slug ) = explode( '//', $named, 2 );
			if ( get_stylesheet() !== $theme ) {
				return self::state( $named, 'foreign' );
			}
		}
		// Only a slug the Site Editor writes reaches core's file read, which
		// builds a path from it unchecked: anything else names no part of
		// this theme's.
		if ( 1 !== preg_match( self::PART_SLUG, $slug ) ) {
			return self::state( $named, 'foreign' );
		}
		return self::read_part( $slug, null, false );
	}

	/**
	 * A Mega Menu item's panel part (spec "Mega Menu pairs" §3.6). The
	 * item names a bare slug, so `theme//slug` is foreign here, unlike an
	 * overlay: the block resolves its slug under the active stylesheet.
	 *
	 * @param string $slug The item's menuSlug ('' when it has none).
	 * @return array{slug: string, state: string, pattern: ?string}
	 */
	private static function panel( string $slug ): array {
		if ( '' === $slug ) {
			return self::state( $slug, 'missing' );
		}
		if ( 1 !== preg_match( self::PART_SLUG, $slug ) ) {
			return self::state( $slug, 'foreign' );
		}
		return self::read_part( $slug, self::PANEL_AREA, true );
	}

	/**
	 * A part of the active theme's id, through core's FILE door alone
	 * (get_block_file_template(): the stylesheet's file, then its parent's,
	 * then core's registry, then the get_block_file_template filter), so a
	 * database copy is never read. Accepted only as a wp_template_part: a
	 * theme file (source theme, has_theme_file), or, where `$supplied`, a
	 * plugin's part that is no theme file. Its state is that kind when its
	 * content is one pointer to a registered pattern (registered for
	 * `$area` when one is given), else `inline`; any other answer is
	 * `missing`.
	 *
	 * @param string      $slug     A slug in the Site Editor's grammar.
	 * @param string|null $area     The block type the pointed-at pattern must be registered for, or null for any.
	 * @param bool        $supplied Whether a plugin-supplied part answers (panels) or only a theme file (overlays, F5).
	 * @return array{slug: string, state: string, pattern: ?string}
	 */
	private static function read_part( string $slug, ?string $area, bool $supplied ): array {
		$part = get_block_file_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		$kind = null;
		if ( $part instanceof \WP_Block_Template && 'wp_template_part' === $part->type ) {
			if ( 'theme' === $part->source && true === $part->has_theme_file ) {
				$kind = 'theme';
			} elseif ( $supplied && 'plugin' === $part->source && true !== $part->has_theme_file ) {
				$kind = 'supplied';
			}
		}
		if ( null === $kind ) {
			return self::state( $slug, 'missing' );
		}
		$blocks = array_values(
			array_filter(
				parse_blocks( (string) $part->content ),
				static fn( array $b ): bool => null !== $b['blockName'] || '' !== trim( $b['innerHTML'] )
			)
		);
		$target  = 1 === count( $blocks ) && 'core/pattern' === $blocks[0]['blockName'] && is_string( $blocks[0]['attrs']['slug'] ?? null ) ? $blocks[0]['attrs']['slug'] : '';
		$pattern = '' === $target ? null : \WP_Block_Patterns_Registry::get_instance()->get_registered( $target );
		$known   = is_array( $pattern ) && ( null === $area || in_array( $area, (array) ( $pattern['blockTypes'] ?? array() ), true ) );
		return array(
			'slug'    => $slug,
			'state'   => $known ? $kind : 'inline',
			'pattern' => $known ? $target : null,
		);
	}

	/**
	 * A part's state with no pattern.
	 *
	 * @param string $slug  The slug as named.
	 * @param string $state missing or foreign.
	 * @return array{slug: string, state: string, pattern: ?string}
	 */
	private static function state( string $slug, string $state ): array {
		return array(
			'slug'    => $slug,
			'state'   => $state,
			'pattern' => null,
		);
	}
}
