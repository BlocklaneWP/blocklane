<?php
/**
 * Menu Button: the tag processor that replaces the toggle's contents
 * (spec 2026-10-15-navigation-toggle).
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A tag processor that can replace what lies between a tag and its
 * closer: bookmarks give their places, and a text replacement swaps the
 * span between them when the document is next read.
 */
final class Navigation_Toggle_Markup extends \WP_HTML_Tag_Processor {

	/**
	 * The current tag's contents, as written, or null when it has no closer.
	 * The cursor ends on the closer.
	 *
	 * @return string|null
	 */
	public function inner(): ?string {
		$span = $this->inner_span();
		return null === $span ? null : substr( $this->get_updated_html(), $span[0], $span[1] - $span[0] );
	}

	/**
	 * Replace the current tag's contents with HTML AS GIVEN: unlike core's
	 * directives processor, nothing is escaped here, so the caller
	 * escapes every value it puts in. The cursor ends on the closer.
	 *
	 * @param string $html The new contents.
	 * @return bool Whether a closer was found and the contents replaced.
	 */
	public function replace_inner( string $html ): bool {
		$span = $this->inner_span();
		if ( null === $span ) {
			return false;
		}
		$this->lexical_updates[] = new \WP_HTML_Text_Replacement( $span[0], $span[1] - $span[0], $html );
		return true;
	}

	/**
	 * Where the current tag's contents start and end, from its opener to
	 * the first closer of the same name at the same depth.
	 *
	 * @return array{0: int, 1: int}|null
	 */
	private function inner_span(): ?array {
		$this->get_updated_html();
		$name = $this->get_tag();
		if ( null === $name || $this->is_tag_closer() ) {
			return null;
		}
		$this->set_bookmark( 'blocklane-toggle-open' );
		$depth = 1;
		$found = false;
		while ( $this->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			if ( $name !== $this->get_tag() ) {
				continue;
			}
			$depth += $this->is_tag_closer() ? -1 : 1;
			if ( 0 === $depth ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$this->release_bookmark( 'blocklane-toggle-open' );
			return null;
		}
		$this->set_bookmark( 'blocklane-toggle-close' );
		$start = $this->bookmarks['blocklane-toggle-open']->start + $this->bookmarks['blocklane-toggle-open']->length;
		$end   = $this->bookmarks['blocklane-toggle-close']->start;
		$this->release_bookmark( 'blocklane-toggle-open' );
		$this->release_bookmark( 'blocklane-toggle-close' );
		return array( $start, $end );
	}
}
