<?php
/**
 * Block branding: a small cosmetic touch in the block inserter.
 *
 * Every Blocklane block gets a subtle brand mark in the top-right corner of
 * its inserter tile — faint at rest, brightening to the accent-colored Blocklane
 * mark on hover (the effect Blockera popularized, done here in pure CSS with
 * no per-block JS). One masked `::before` per tile; core owns the tile's own
 * `::after` (its hover overlay) and its position:relative, so we ride both.
 * Scoped by the shared `blocklane-` inserter-class prefix, so it covers every
 * current and future block in our namespaces (blocklane-pro/*, blocklane/*,
 * blocklane-patterns/*) and never touches a core or third-party block.
 *
 * Editor-only and always-on (no runtime, no REST); the mark is drawn with a
 * CSS mask of the brand glyph so it tints with the admin accent color.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Branding {

	const HANDLE = 'blocklane-pro-block-branding';

	/**
	 * The brand glyph used as a CSS mask — the admin-menu B, straight from
	 * Settings::MENU_ICON_B64 so the two marks can never drift apart. The
	 * SVG's baked-in fill is irrelevant: the mask contributes only its alpha,
	 * and the pseudo-element's background-color supplies the tint.
	 */
	const GLYPH = 'data:image/svg+xml;base64,' . Settings::MENU_ICON_B64;

	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Safe mode silences every Blocklane Pro surface (see the Modules class).
		if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
			return;
		}

		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	/** Attach the inline badge CSS to a src-less style handle in the editor. */
	public static function enqueue() {
		wp_register_style( self::HANDLE, false, array(), BLOCKLANE_PRO_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );
	}

	/**
	 * The badge CSS. One masked `::before` per tile — the mark rides the tile's
	 * own ink at rest and brightens to the accent color on hover, alongside
	 * core's own tile-hover treatment.
	 *
	 * @return string
	 */
	private static function css() {
		// Matches the button on every Blocklane block tile — the shared
		// `blocklane-` prefix is unique to our namespaces, so core tiles
		// (editor-block-list-item-paragraph, …) are never selected. The tile is
		// already position:relative in core, so the badge anchors to it.
		$tile   = '.block-editor-block-types-list__item[class*="editor-block-list-item-blocklane-"]';
		$hover  = '.block-editor-block-types-list__list-item:hover ' . $tile;
		$mask   = 'url("' . self::GLYPH . '") center / contain no-repeat';
		$accent = 'var(--wp-components-color-accent, var(--wp-admin-theme-color, #007cba))';

		return "
{$tile}::before {
	content: \"\";
	position: absolute;
	top: 10px;
	right: 12px;
	width: 14px;
	height: 14px;
	-webkit-mask: {$mask};
	mask: {$mask};
	background-color: currentColor;
	opacity: 0.2;
	transition: opacity 0.1s ease-out, background-color 0.1s ease-out;
	pointer-events: none;
}
{$hover}::before { background-color: {$accent}; opacity: 0.4; }
";
	}
}
