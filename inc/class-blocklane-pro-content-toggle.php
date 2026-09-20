<?php
/**
 * Content_Toggle — the one reader of the Advanced toggles that the
 * license-ungated content runtimes consult at file scope, before any module
 * can have loaded.
 *
 * Four runtimes (carousel, forms, seo, popups) each read the stored
 * `blocklane_pro_advanced` row with their own literal and their own
 * semantics, and nothing checked the literal: a typo'd slug failed OPEN in
 * the carousel (default on) and CLOSED in the other three, silently either
 * way (#504). The rule is Advanced::get()'s — the stored value when the key
 * is present, else the shipped default — in one place, over one table, and
 * an unknown slug is a defect in source that throws.
 *
 * MIRROR CONTRACT with Advanced::DEFAULTS
 * (inc/advanced/class-blocklane-pro-advanced.php): same keys, same shipped
 * defaults, for these four toggles. Not a reference to Advanced::DEFAULTS
 * on purpose: referencing it autoloads Advanced, and merely defining a
 * module's class flips the class_exists( …, false ) probes elsewhere that
 * read "loaded" as "booted". bin/modules-battery.php asserts the mirror.
 *
 * Side-effect free: no hooks, no I/O beyond the one get_option() in on().
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Content_Toggle {

	/**
	 * Toggle slug => the shipped default. Carousel ships ON (it registers no
	 * REST or write path); the other three ship OFF.
	 */
	public const DEFAULTS = array(
		'carousel' => true,
		'forms'    => false,
		'seo'      => false,
		'popups'   => false,
	);

	/**
	 * Whether a content toggle is on for this site.
	 */
	public static function on( string $slug ): bool {
		return self::resolve( $slug, get_option( 'blocklane_pro_advanced', null ) );
	}

	/**
	 * The toggle's value from a stored row: the stored value when the key is
	 * present and not null, the shipped default when the row is absent, not
	 * an array, lacks the key, or holds null — isset() semantics, exactly as
	 * Advanced::get() reads the same row, so the screen and the runtime can
	 * never disagree on a stored null. An unknown slug is a defect in source,
	 * never a soft skip — it throws (the same class of mistake as a typo'd
	 * class name).
	 *
	 * @param mixed $stored The stored option row (or whatever get_option() returned).
	 * @throws \LogicException For a slug that is not a content toggle.
	 */
	public static function resolve( string $slug, mixed $stored ): bool {
		if ( ! array_key_exists( $slug, self::DEFAULTS ) ) {
			$known = implode( ', ', array_keys( self::DEFAULTS ) );
			// esc_html() because an uncaught exception's message is written
			// into an HTML response on a display_errors host — that sink is
			// real. Square brackets rather than quotes so the message contains
			// none of < > & ' ", which makes the escape a byte-identity on
			// every legal slug and leaves it biting only an illegal one.
			throw new \LogicException( esc_html( "Content_Toggle: unknown content toggle slug [{$slug}]; known: {$known}" ) );
		}
		if ( ! is_array( $stored ) || ! isset( $stored[ $slug ] ) ) {
			return self::DEFAULTS[ $slug ];
		}
		return (bool) $stored[ $slug ];
	}
}
