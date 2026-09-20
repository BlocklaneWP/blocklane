<?php
/**
 * Extensions front-end loader — the canonical entry point for everything the
 * extensions do on the FRONT of the site: per-extension render filters, block
 * attribute registration, and the shared front-end asset enqueues.
 *
 * One home: this file lives in the plugin (inc/extensions/loader/) and is
 * required in-process, so the extensions render only while Blocklane Pro is
 * active. It was also stamped into wp-content/mu-plugins/ until 2026-08; the
 * single-load guard below survives that removal because the file is still
 * reachable from more than one require path. Editor and REST surfaces (the
 * builder half) stay plugin-only — see Extensions_Handler.
 *
 * Toggle defaults here mirror Extensions_Handler::KNOWN_SLUGS; reads are
 * option-first against the shipped default (see runtime-helpers.php).
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BLOCKLANE_PRO_EXT_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_EXT_RUNTIME_LOADED', true );

require_once __DIR__ . '/runtime-helpers.php';
// The attribute schema registrar: every extension's block attributes on the
// core blocks, in both editions, so a save in either edition writes back what
// the other wrote (#858). ABOVE the safe-mode return on purpose — it is data
// preservation with no behavior to silence, and a safe-mode save must not
// delete attributes either (Spec C D3).
require_once __DIR__ . '/attribute-schema.php';

// Safe mode silences every Blocklane Pro surface, the generated runtimes included —
// define BLOCKLANE_PRO_SAFE_MODE in wp-config to troubleshoot with all of it off
// (see the plugin's Modules class). Helpers above stay loaded (no side effects);
// so does the attribute registrar, which changes nothing a visitor or an editor
// can see.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// Term-image covers are content (a published archive's images), so they
// load unconditionally, before the per-extension toggle map below.
if ( blocklane_pro_edition_has( 'extension:cover-term-image' ) ) {
	require_once __DIR__ . '/cover-term-image/cover-term-image.php';
}

// The icon runtime is the renderer for placed icons — content, not tooling —
// so it loads regardless of the extension toggle: a switched-off picker must
// never blank icons already placed in saved content. The picker/REST half
// stays plugin-side, behind its own capability checks.
if ( blocklane_pro_edition_has( 'extension:icon-library' ) ) {
	require_once __DIR__ . '/icon-library/icon-library.php';
}

// Tab descriptions are authored, visible copy — content, not tooling — so the
// projection (spans + aria-describedby) renders regardless of the Advanced
// Tabs toggle, by the same principle as icons above (ruled 2026-08-28, issue
// #379). The toggle still gates the editor UI; layout classes ride saved
// markup and degrade with the shared stylesheet as documented in the spec.
if ( blocklane_pro_edition_has( 'extension:advanced-tabs' ) ) {
	require_once __DIR__ . '/advanced-tabs/advanced-tabs.php';
}

/*
 * Per-extension front-end PHP, gated by the toggles. Slugs are NOT 1:1 with
 * dir names (hover-colors -> hover-color, animation-designer -> animation).
 * JS-only extensions (smart-sync, advanced-paragraph, background-url,
 * pattern-editing) have no PHP loader — their saved markup is static and
 * their CSS rides the shared bundle below.
 */
$blocklane_pro_ext_frontend_map = array(
	'hover-colors'        => array( 'hover-color/hover-color.php', true, 'hover-colors' ),
	'advanced-group'      => array( 'advanced-group/advanced-group.php', true, 'advanced-group' ),
	'button-icons'        => array( 'button-icons/button-icons.php', true, 'button-icons' ),
	'animation-designer'  => array( 'animation/animation.php', true, 'animation-designer' ),
	'advanced-grid'       => array( 'advanced-grid/advanced-grid.php', true, 'advanced-grid' ),
	'class-manager'       => array( 'class-manager/class-manager-frontend.php', false, 'class-manager' ),
	'video-modal'         => array( 'video-modal/video-modal.php', true, 'video-modal' ),
	'transparent-header'  => array( 'transparent-header/transparent-header.php', true, 'transparent-header' ),
	'text-wrap'           => array( 'text-wrap/text-wrap.php', true, 'text-wrap' ),
	'responsive-controls' => array( 'responsive-controls/responsive-controls.php', true, 'responsive-controls' ),
);

foreach ( $blocklane_pro_ext_frontend_map as $blocklane_pro_ext_slug => $blocklane_pro_ext_def ) {
	if ( ! blocklane_pro_edition_has( 'extension:' . $blocklane_pro_ext_def[2] ) ) {
		continue; // Not in this artifact at all.
	}
	if ( ! blocklane_pro_ext_enabled( $blocklane_pro_ext_slug, $blocklane_pro_ext_def[1] ) ) {
		continue; // Present, switched off.
	}
	$blocklane_pro_ext_file = __DIR__ . '/' . $blocklane_pro_ext_def[0];
	if ( ! is_file( $blocklane_pro_ext_file ) ) {
		// The edition says this unit is here and the disk disagrees: a torn
		// deploy. Say so once rather than rendering silently wrong forever —
		// the file_exists() that used to stand here made this case invisible.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a shipped-build defect.
		error_log( 'Blocklane: extension runtime inc/extensions/loader/' . $blocklane_pro_ext_def[0] . ' is missing. Reinstall the plugin.' );
		continue;
	}
	require_once $blocklane_pro_ext_file;
}
unset( $blocklane_pro_ext_frontend_map, $blocklane_pro_ext_slug, $blocklane_pro_ext_def, $blocklane_pro_ext_file );

if ( ! function_exists( 'blocklane_pro_ext_any_enabled' ) ) {

	/**
	 * Whether any extension this artifact carries is switched on.
	 *
	 * Reads Extensions_Handler::known_slugs() — the edition-filtered table —
	 * rather than the private copy of the defaults that used to live here. Two
	 * copies of one table is how the mirror-drift defects in this codebase
	 * start, and this one had already drifted out of reach of the edition
	 * filter by construction.
	 */
	function blocklane_pro_ext_any_enabled() {
		foreach ( blocklane_pro\Extensions_Handler::known_slugs() as $slug => $default ) {
			if ( blocklane_pro_ext_enabled( $slug, $default ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The shared front-end CSS bundle (style-index.css). It paints hover-color,
	 * button-icons, paragraph hover decoration, the animation base/keyframes,
	 * text-wrap, and advanced-group — the loaders above emit classes/vars and
	 * rely on it. Video-modal is deliberately NOT in this bundle (issue #437,
	 * decision D3): its trigger/modal CSS ships only as
	 * loader/video-modal/video-modal.css, enqueued when a trigger renders.
	 */
	function blocklane_pro_ext_enqueue_frontend_style() {
		if ( is_admin() || ! blocklane_pro_ext_any_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'blocklane-pro-extensions-frontend',
			blocklane_pro_ext_base_url() . '/build/style-index.css',
			array(),
			blocklane_pro_ext_version()
		);
	}

	/** Register the Advanced Group behavior script; render marks it used. */
	function blocklane_pro_ext_enqueue_frontend_scripts() {
		if ( is_admin() || ! blocklane_pro_ext_enabled( 'advanced-group', true ) ) {
			return;
		}

		// Registered here, enqueued only when a rendered block needs the
		// behaviors (blocklane_pro_advanced_group_mark_used): a group with our
		// attributes, or a core sticky group in the header — the script also
		// fixes core's sticky offset, with no Blocklane attribute involved.
		wp_register_script(
			'blocklane-pro-extensions-advanced-group',
			blocklane_pro_ext_base_url() . '/build/advanced-group-frontend.js',
			array(),
			blocklane_pro_ext_version(),
			true
		);

		// A block theme renders its template BEFORE wp_head, so render_block
		// (and any mark-used call) already ran by the time we get here. Attach
		// now that the handle exists.
		if ( blocklane_pro_advanced_group_used() ) {
			blocklane_pro_advanced_group_attach();
		}
	}

	/**
	 * Whether this request rendered a block that needs the Advanced Group
	 * behavior script. Sticky across the request; pass true to set it.
	 *
	 * @param bool $mark Mark the script as needed.
	 * @return bool Whether the script is needed.
	 */
	function blocklane_pro_advanced_group_used( $mark = false ) {
		static $used = false;
		if ( $mark ) {
			$used = true;
		}
		return $used;
	}

	/**
	 * Attach the breakpoints config and enqueue the behavior script — once,
	 * and only once the handle is actually registered.
	 *
	 * The registration guard is load-bearing, not defensive.
	 * WP_Dependencies::add_data() returns false for an unregistered handle and
	 * discards the inline payload with no warning, while enqueue() stashes
	 * unregistered handles in queued_before_register and resolves them at
	 * registration. So on a block theme — this plugin's primary environment,
	 * where the template renders before wp_head and therefore before
	 * wp_enqueue_scripts — attaching at render time silently dropped the
	 * config and shipped the script alone, leaving the behavior script on its
	 * hardcoded 480px fallback no matter what breakpoints the site had
	 * configured. Same for any pre-head render on a classic theme (early
	 * do_blocks, cache warmers).
	 *
	 * Called from both ends so either order works: from mark-used when the
	 * handle is already registered (classic themes), and from registration
	 * when the render came first (block themes).
	 */
	function blocklane_pro_advanced_group_attach() {
		static $attached = false;
		if ( $attached || ! wp_script_is( 'blocklane-pro-extensions-advanced-group', 'registered' ) ) {
			return;
		}
		$attached = true;

		// The configured responsive breakpoints, so the behavior script's idea
		// of "mobile" tracks Responsive Controls instead of a hardcoded width.
		wp_add_inline_script(
			'blocklane-pro-extensions-advanced-group',
			'window.blocklaneProExtBreakpoints = ' . wp_json_encode( blocklane_pro_ext_breakpoints() ) . ';',
			'before'
		);
		wp_enqueue_script( 'blocklane-pro-extensions-advanced-group' );
	}

	/**
	 * Enqueue the Advanced Group script because this page renders a block that
	 * needs it. Safe to call repeatedly. The breakpoints config attaches on
	 * use rather than at registration, so pages that never need the script
	 * also never pay the breakpoints option read.
	 */
	function blocklane_pro_advanced_group_mark_used() {
		if ( blocklane_pro_advanced_group_used() || is_admin() || ! blocklane_pro_ext_enabled( 'advanced-group', true ) ) {
			return;
		}
		blocklane_pro_advanced_group_used( true );

		// No-op when registration hasn't run yet; that pass calls back here.
		blocklane_pro_advanced_group_attach();
	}

	/**
	 * Load the behavior scripts even when this page's server render contains
	 * nothing that needs them.
	 *
	 * Enqueue-on-use is keyed off render_block during the initial render, so
	 * a page that only gains qualifying markup LATER — an AJAX filter, infinite
	 * scroll, a quick-view modal — no longer gets the script, and the behavior
	 * silently stops. The scripts use event delegation precisely to support
	 * that content, but delegation only helps if the script is on the page.
	 * The same applies to markup that reaches the page without passing through
	 * render_block at all (shortcode output, another plugin's PHP fragment).
	 *
	 * There is no reliable way to detect those cases from the initial render,
	 * so this is the opt-in for sites that know they do it.
	 *
	 * WHY THE DEFAULT STAYS OFF (weighed 2026-08, deliberately): default-on
	 * would put both behavior scripts back on every page of every site —
	 * reversing the 0.3.2 per-use loading that is the plugin's shipped perf
	 * posture — to serve the minority of sites that inject qualifying markup
	 * after the initial render. The harms are not symmetric though: without
	 * the animation script, injected animate-on-scroll content is pinned at
	 * opacity:0 by the stylesheet (which loads whenever any extension is
	 * enabled) and INVISIBLE — content loss, not degradation. That worst tier
	 * is removed unconditionally by the reveal failsafe below, at ~100 inline
	 * bytes and zero extra requests, so the residual default-off cost is only
	 * cosmetic: injected content appears without its animation, and injected
	 * advanced-group markup loses its behaviors, until the site opts in here.
	 */
	function blocklane_pro_ext_maybe_force_enqueue() {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Force the extensions' behavior scripts onto every front-end page.
		 *
		 * @param bool $force Default false — enqueue only when a rendered
		 *                    block needs it.
		 */
		if ( ! apply_filters( 'blocklane_pro_ext_force_enqueue', false ) ) {
			return;
		}

		if ( function_exists( 'blocklane_pro_advanced_group_mark_used' ) ) {
			blocklane_pro_advanced_group_mark_used();
		}
		if ( function_exists( 'blocklane_pro_animation_mark_used' ) ) {
			blocklane_pro_animation_mark_used();
		}
	}

	/**
	 * Reveal failsafe for animate-on-scroll content on pages where the reveal
	 * script is NOT loaded.
	 *
	 * The shared stylesheet (and the generated preset CSS) hides
	 * .blocklane-pro-animate-on-scroll elements at opacity:0 behind
	 * `@media (scripting: enabled)` — a check that the BROWSER runs scripts,
	 * not that OUR reveal script is on the page. With per-use loading, a page
	 * whose initial render has no animated block never enqueues the script, so
	 * markup injected later (AJAX filters, infinite scroll, quick-view modals)
	 * gains the class server-side but nothing ever reveals it: invisible
	 * content. This prints one tiny rule, decided in the footer when the
	 * enqueue state is final, that converts that failure into "visible without
	 * animation". Pages that DO load the script (initial blocks present, or
	 * the blocklane_pro_ext_force_enqueue opt-in) are untouched — the script's
	 * own reveal keeps full behavior, including for injected content.
	 */
	function blocklane_pro_ext_animation_reveal_failsafe() {
		if ( is_admin()
			|| wp_script_is( 'blocklane-pro-extensions-animation', 'enqueued' )
			|| ! wp_style_is( 'blocklane-pro-extensions-frontend', 'enqueued' ) ) {
			return;
		}
		// !important so it also beats the higher-specificity per-preset hiding
		// rules the Animation Designer generates (none are !important). Without
		// the script nothing can ever add .blocklane-pro-animated, so forcing
		// these elements visible is strictly correct here.
		echo '<style id="blocklane-pro-animate-reveal-failsafe">.blocklane-pro-animate-on-scroll:not(.blocklane-pro-animated){opacity:1 !important}</style>' . "\n";
	}

	add_action( 'enqueue_block_assets', 'blocklane_pro_ext_enqueue_frontend_style' );
	add_action( 'wp_enqueue_scripts', 'blocklane_pro_ext_enqueue_frontend_scripts', 99 );
	// After registration, so the force path finds the handles ready.
	add_action( 'wp_enqueue_scripts', 'blocklane_pro_ext_maybe_force_enqueue', 100 );
	// Late in the footer: by then the animation script's enqueue state is
	// final for the request, whichever theme/render order got us here.
	add_action( 'wp_footer', 'blocklane_pro_ext_animation_reveal_failsafe', PHP_INT_MAX );
}
