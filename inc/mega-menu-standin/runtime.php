<?php
/**
 * Mega Menu — the block editor's stand-in (data preservation, both editions).
 *
 * WHAT IT IS. While the real blocklane-pro/mega-menu block is not registered
 * on the server (the free edition; Pro with its menu-designer module vetoed,
 * or on a classic theme, where the module boots behind the block-theme gate),
 * the editor bundle built from src/ registers the block type CLIENT-SIDE with
 * `inserter: false`: an editor opening a page authored with Pro sees an
 * intentional, read-only block instead of core's "unsupported block"
 * placeholder, every Pro attribute round-trips a save byte for byte (the
 * bundle serializes with the same inc/shared/mega-menu/save-fallback.js Pro's
 * registration uses), and the one deliberate exit, a user-initiated,
 * undoable "convert to core navigation" action, is the bundle's.
 *
 * WHAT IT IS NOT. A capability. It hands a never-paid user no feature: a
 * hand-typed mega-menu comment renders as the same core navigation submenu
 * core gives away, and the inserter never offers the block. It is the fourth
 * entry of the edition doctrine's data-preservation allowlist (Amendment 14),
 * which is why `runtime:mega-menu-standin` is carried by BOTH editions with
 * no edition filter and no toggle. It moved here from the theme on
 * 2026-09-20: a theme may ship neither a custom block nor a compiled file
 * whose originals it cannot ship (Theme Review §5, §9).
 *
 * THE DOORS are the helper's, named in inc/class-blocklane-pro-standin.php:
 * the registry, safe mode, the enqueue and its alarm, the translations, the
 * localized inspector copy. This file names what is the mega menu's alone:
 * the block, its script handle, the unit whose presence registers the real
 * block (and the module that boots it behind the block-theme gate), and what
 * the inspector says here, per reason — a closure, so the __() calls run at
 * enqueue, never at plugins_loaded. It DECLARES NOTHING and defines no
 * constant; a second require would reach Standin::register(), which refuses
 * the block by name. bin/standin-shape-check.php (wiring check 8) refuses
 * anything but this guard and one Standin::register() call — and a
 * `static fn` closure here, whose body it reads as file scope.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\blocklane_pro\Standin::register(
	array(
		'block'   => 'blocklane-pro/mega-menu',
		'handle'  => 'blocklane-pro-mega-menu-standin',
		'file'    => __FILE__,
		'owner'   => 'module:menu-designer',
		'module'  => 'menu-designer',
		'wanted'  => null,
		'strings' => static function (): array {
			return array(
				/* translators: Inspector note on a saved mega menu in the block editor, when the running plugin does not include Blocklane Pro. */
				\blocklane_pro\Standin::REASON_EDITION     => __( 'This mega menu was built with Blocklane Pro, which is not active on this site. It is kept exactly as saved and works as a plain dropdown on the site.', 'blocklane' ),
				/* translators: Inspector note on a saved mega menu in the block editor, when Blocklane Pro is active but the site's theme is not a block theme. */
				\blocklane_pro\Standin::REASON_CLASSIC     => __( 'Blocklane\'s editing tools need a block theme, so this mega menu cannot be edited with the active theme. It is kept exactly as saved and works as a plain dropdown on the site.', 'blocklane' ),
				/* translators: Inspector note on a saved mega menu in the block editor, when Blocklane Pro is active but its Mega Menu module did not start on this site. */
				\blocklane_pro\Standin::REASON_NOT_RUNNING => __( 'The Mega Menu module is not running on this site. This item is kept exactly as saved and works as a plain dropdown on the site.', 'blocklane' ),
			);
		},
	)
);
