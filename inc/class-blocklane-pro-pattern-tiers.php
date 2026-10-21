<?php
/**
 * Pattern_Tiers: the one writer of a Blocklane pattern's tier.
 *
 * Blocklane registers patterns of its own (the forms starters, the bundled
 * carousels, and the Free plugin's upgrades of theme patterns), and a
 * reader of public API v1 (blocklane_pro_api_pattern_tier()) must be told
 * which product each one belongs to. A pattern's own properties cannot say it: anything registering
 * a pattern can write any property. So the tier is recorded here, by the code that registers the
 * pattern, at the moment it registers it, and read back only from this
 * map. register_block_pattern() is called nowhere else in the plugin
 * (PHPStan blocklane.chokepointCall), so a Blocklane pattern cannot be
 * registered without stating its tier.
 *
 * The map lives for one request and holds only names registered through
 * register(). A name another plugin re-registers later keeps the tier
 * recorded here; that is accepted (case B8 of the pattern library spec of
 * 2026-10-14), because core itself then treats the newer
 * registration as the pattern.
 *
 * A core path, carried by both editions: the free build's forms register
 * through it too. Side-effect free until called: no hooks, no I/O.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pattern_Tiers {

	/** The tiers a Blocklane registration may claim. The theme tier is never claimed: it is read from the theme. */
	public const TIERS = array( 'free', 'pro' );

	/**
	 * Pattern name => the tier its registration claimed, this request.
	 *
	 * @var array<string, string>
	 */
	private static array $tiers = array();

	/**
	 * Register a pattern with core and record its tier.
	 *
	 * Nothing is recorded unless core accepted the registration, so the map
	 * never names a pattern core refused.
	 *
	 * @param string               $name  Pattern name, namespaced.
	 * @param array<string, mixed> $props Pattern properties, as register_block_pattern() takes them.
	 * @param string               $tier  'free' or 'pro'.
	 * @return true|\WP_Error True when registered; blocklane_pattern_tiers_unknown_tier, blocklane_pattern_tiers_held or blocklane_pattern_tiers_refused.
	 */
	public static function register( string $name, array $props, string $tier ): bool|\WP_Error {
		if ( ! in_array( $tier, self::TIERS, true ) ) {
			blocklane_pro_log( "Blocklane: pattern {$name} was not registered: \"{$tier}\" is not a tier a registration may claim." );
			return new \WP_Error( 'blocklane_pattern_tiers_unknown_tier', 'A Blocklane pattern registers as free or pro.', array( 'name' => $name ) );
		}
		// Held for now (Garrett, 2026-10-16: "drop all preconfigured and
		// dynamic, content type related ones for now"): a pattern that needs
		// setup beyond activating the plugin is not registered. The Pro+
		// code stays for their return.
		if ( self::holds_setup() && self::needs_setup( parse_blocks( is_string( $props['content'] ?? null ) ? $props['content'] : '' ) ) ) {
			return new \WP_Error( 'blocklane_pattern_tiers_held', 'A pattern that needs setup is held back for now.', array( 'name' => $name ) );
		}
		if ( ! register_block_pattern( $name, $props ) ) {
			blocklane_pro_log( "Blocklane: core refused to register pattern {$name}." );
			return new \WP_Error( 'blocklane_pattern_tiers_refused', 'Core refused the pattern registration.', array( 'name' => $name ) );
		}
		self::$tiers[ $name ] = $tier;
		return true;
	}

	/**
	 * Whether patterns that need setup are held back (Garrett, 2026-10-16).
	 * On by default; the filter is the way back, and the batteries' way to
	 * prove the Pro+ code they keep.
	 */
	public static function holds_setup(): bool {
		return (bool) apply_filters( 'blocklane_pro_hold_setup_patterns', true );
	}

	/**
	 * Whether a pattern's blocks query a post type that is not one of
	 * core's built-in types (spec section 16.1): a core/query's
	 * query.postType (unless it inherits the page's query), or a dynamic
	 * blocklane/carousel-track's, at any depth. Such a pattern needs a
	 * content type the site has to set up (Pro+). Built-in is core's own
	 * flag (`_builtin`), the rule the carousel's free query list starts
	 * from too (blocklane_pro_carousel_query_post_types()), so "free can
	 * query it" and "needs no setup" are one test, not two lists. The one
	 * door: public API v1's blocklane_pro_api_pattern_needs_setup() reads it.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	public static function needs_setup( array $blocks ): bool {
		$builtin = array_values( get_post_types( array( '_builtin' => true ) ) );
		$stack   = $blocks;
		while ( array() !== $stack ) {
			$block = array_pop( $stack );
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name  = $block['blockName'] ?? null;
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$query = is_array( $attrs['query'] ?? null ) ? $attrs['query'] : array();
			$type  = is_string( $query['postType'] ?? null ) && '' !== $query['postType'] ? $query['postType'] : 'post';
			// A query that inherits the page's own query reads whatever the
			// template shows, never a type of its own.
			$reads = ( 'core/query' === $name && empty( $query['inherit'] ) )
				|| ( 'blocklane/carousel-track' === $name && 'dynamic' === ( $attrs['sourceType'] ?? null ) );
			if ( $reads && ! in_array( $type, $builtin, true ) ) {
				return true;
			}
			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
				$stack[] = $inner;
			}
		}
		return false;
	}

	/**
	 * The tier a Blocklane registration claimed for a pattern this request,
	 * ('free' or 'pro'), or null when no registration through register()
	 * named it.
	 */
	public static function of( string $name ): ?string {
		return self::$tiers[ $name ] ?? null;
	}
}
