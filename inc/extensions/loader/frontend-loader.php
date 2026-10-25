<?php
/**
 * Extensions front-end loader — the canonical entry point for everything the
 * extensions do on the FRONT of the site: per-extension render filters, block
 * attribute registration, and the shared front-end asset enqueues.
 *
 * One home: this file lives in the plugin (inc/extensions/loader/) and is
 * required in-process, so the extensions render only while Blocklane Pro is
 * active. Until 2026-08 a generated must-use copy of it ran too; the
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
// core blocks. A group this edition gives meaning to is registered in full on
// every site; any other group only where the Pro build has run, as type, enum
// and default, so a save there writes back what Pro wrote (#858; spec
// 2026-10-17-pro-downgrade-inert-attributes). ABOVE the safe-mode return on
// purpose: it is a schema with no behavior to silence, and a safe-mode save
// must not delete attributes either (Spec C D3).
require_once __DIR__ . '/attribute-schema.php';

// Safe mode silences every Blocklane Pro surface, the generated runtimes included —
// define BLOCKLANE_PRO_SAFE_MODE in wp-config to troubleshoot with all of it off
// (see the plugin's Modules class). Helpers above stay loaded (no side effects);
// so does the attribute registrar, which registers a schema and renders nothing.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// Term-image covers are content (a published archive's images), so they
// load unconditionally, before the per-extension toggle map below.
if ( blocklane_pro_edition_has( 'extension:cover-term-image' ) ) {
	require_once __DIR__ . '/cover-term-image/cover-term-image.php';
}

// Tab descriptions are authored, visible copy — content, not tooling — so the
// projection (spans + aria-describedby) renders regardless of the Advanced
// Tabs toggle, by the same principle as icons above (ruled 2026-08-28, issue
// #379). The toggle still gates the editor UI; layout classes ride saved
// markup and degrade with the shared stylesheet as documented in the spec.
if ( blocklane_pro_edition_has( 'extension:advanced-tabs' ) ) {
	require_once __DIR__ . '/advanced-tabs/advanced-tabs.php';
}

// The canvas grid tools switch is a setting with its own route, read by the
// Settings row the moment that row turns on, so it loads whenever the
// edition carries the unit, whatever the extension's toggle says
// (advanced-grid/canvas-tools.php; C-settings Amendment 1 F9).
if ( blocklane_pro_edition_has( 'extension:advanced-grid' ) ) {
	require_once __DIR__ . '/advanced-grid/canvas-tools.php';
}

// The privacy-enhanced video embeds setting loads by the same rule: its
// route answers the Settings row whatever the video lightbox's toggle says,
// and its reader is declared before the lightbox's render half asks it
// (video-modal/video-modal-privacy.php).
if ( blocklane_pro_edition_has( 'extension:video-modal' ) ) {
	require_once __DIR__ . '/video-modal/video-modal-privacy.php';
}

/*
 * Per-extension front-end PHP, gated by the toggles. Slugs are NOT 1:1 with
 * dir names (hover-colors -> hover-color, animation-designer -> animation).
 * JS-only extensions (smart-sync, advanced-paragraph, background-url,
 * pattern-editing) have no PHP loader — their saved markup is static and
 * their CSS rides the shared bundle below.
 *
 * A unit with behavior has a door (`<dir>/door.php`, listed first): the one
 * file that registers and requests its scripts and store modules, hooks it
 * to the force filter, and names its reveal doors for the failsafe. Doors
 * sit in the unit's own path, which the free stage removes, so this
 * free-shipped file registers no unit's assets (security read of the
 * clean-room exemplar, F1).
 */
$blocklane_pro_ext_frontend_map = array(
	'hover-colors'        => array( 'hover-color/hover-color.php', true, 'hover-colors' ),
	'advanced-group'      => array( array( 'advanced-group/door.php', 'advanced-group/advanced-group.php' ), true, 'advanced-group' ),
	'button-icons'        => array( 'button-icons/button-icons.php', true, 'button-icons' ),
	'animation-designer'  => array( array( 'animation/door.php', 'animation/animation.php' ), true, 'animation-designer' ),
	'scroll-motion'       => array( 'scroll-motion/scroll-motion.php', true, 'scroll-motion' ),
	'transforms'          => array( 'transforms/transforms.php', true, 'transforms' ),
	'advanced-grid'       => array( 'advanced-grid/advanced-grid.php', true, 'advanced-grid' ),
	'class-manager'       => array( 'class-manager/class-manager-frontend.php', false, 'class-manager' ),
	'video-modal'         => array( 'video-modal/video-modal.php', true, 'video-modal' ),
	'transparent-header'  => array( 'transparent-header/transparent-header.php', true, 'transparent-header' ),
	'text-wrap'           => array( 'text-wrap/text-wrap.php', true, 'text-wrap' ),
	'responsive-controls' => array( 'responsive-controls/responsive-controls.php', true, 'responsive-controls' ),
	'navigation-toggle'   => array( 'navigation-toggle/navigation-toggle.php', true, 'navigation-toggle' ),
);

foreach ( $blocklane_pro_ext_frontend_map as $blocklane_pro_ext_slug => $blocklane_pro_ext_def ) {
	if ( ! blocklane_pro_edition_has( 'extension:' . $blocklane_pro_ext_def[2] ) ) {
		continue; // Not in this artifact at all.
	}
	if ( ! blocklane_pro_ext_enabled( $blocklane_pro_ext_slug, $blocklane_pro_ext_def[1] ) ) {
		continue; // Present, switched off.
	}
	// All of a unit's files or none (blocklane_pro_ext_require_unit()).
	blocklane_pro_ext_require_unit( __DIR__, (array) $blocklane_pro_ext_def[0] );
}
unset( $blocklane_pro_ext_frontend_map, $blocklane_pro_ext_slug, $blocklane_pro_ext_def );

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
	 * The shared front-end CSS bundle (style-index.css). It paints the CSS of
	 * every unit this stage carries — the loaders above emit classes/vars and
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
	function blocklane_pro_ext_maybe_force_enqueue(): void {
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

		/**
		 * Fires when a site forces the extensions' behavior onto every page:
		 * each unit with behavior hooks its own mark-used door here, from its
		 * own file, so this loader names no unit.
		 */
		do_action( 'blocklane_pro_ext_force_enqueue_units' );
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
	 * own reveal keeps full behavior, including for injected content. Printed
	 * in place through the inline-asset door, so the position (after the
	 * footer scripts) is exactly the old one.
	 */
	function blocklane_pro_ext_animation_reveal_failsafe(): void {
		if ( is_admin()
			|| blocklane_pro_ext_animation_reveal_loaded()
			|| ! wp_style_is( 'blocklane-pro-extensions-frontend', 'enqueued' ) ) {
			return;
		}
		// !important so it also beats the higher-specificity per-preset hiding
		// rules the Animation Designer generates (none are !important). Without
		// the script nothing can ever add .blocklane-pro-animated, nor the
		// animate class a sequential parent hands its children, so forcing
		// both visible is strictly correct here.
		\blocklane_pro\Inline_Asset::print_style( 'blocklane-pro-animate-reveal-failsafe', '.blocklane-pro-animate-on-scroll:not(.blocklane-pro-animated),.blocklane-pro-animate-sequentially>:not(.blocklane-pro-animate){opacity:1 !important}' );
	}

	/**
	 * Is a reveal door open on this page? The one predicate the failsafe
	 * reads (clean-room exemplar D2 (b)). The doors are the units': each unit
	 * that reveals content names its classic handles and its module ids on
	 * `blocklane_pro_ext_reveal_doors`, from its own door file, so this
	 * loader names none. A classic handle counts when it is enqueued; a
	 * module only when blocklane_pro_ext_module_loaded() says the browser
	 * will run it: core printed its tag and every module it imports was in
	 * the import map core printed (J-C4). A queued module that cannot load
	 * (queued after core's printer ran, printed past a map that lacks its
	 * imports, never registered) leaves the failsafe on.
	 */
	function blocklane_pro_ext_animation_reveal_loaded(): bool {
		/**
		 * The reveal doors the units register.
		 *
		 * @param array<string, list<string>> $doors Classic handles under `scripts`, module ids under `modules`, and under `printed` the module ids a page prints its own way.
		 */
		$doors = apply_filters( 'blocklane_pro_ext_reveal_doors', array( 'scripts' => array(), 'modules' => array() ) );
		$doors = is_array( $doors ) ? $doors : array();
		foreach ( (array) ( $doors['scripts'] ?? array() ) as $handle ) {
			if ( is_string( $handle ) && wp_script_is( $handle, 'enqueued' ) ) {
				return true;
			}
		}
		foreach ( (array) ( $doors['modules'] ?? array() ) as $id ) {
			if ( is_string( $id ) && blocklane_pro_ext_module_loaded( $id ) ) {
				return true;
			}
		}
		// A page that prints its modules its own way (core's printers
		// unhooked) names what it prints under `printed`: such a module counts
		// when it is queued and registered. Only reveal modules count.
		$printed = array_intersect( (array) ( $doors['printed'] ?? array() ), (array) ( $doors['modules'] ?? array() ) );
		foreach ( $printed as $id ) {
			if ( is_string( $id ) && in_array( $id, wp_script_modules()->get_queue(), true ) && null !== wp_script_modules()->get_registered( $id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the browser will run this module: core printed its
	 * `<script type="module">` tag on this page, and every module it imports
	 * statically, recursively through core's registry, was a key of the
	 * import map core printed. A bare import the map does not name fails the
	 * module in the browser, whatever the queue says: core maps only the
	 * dependencies of what was queued when the map printed (J-C4, spec
	 * 2026-10-25-popup-script-module-preload C2). What printed is what core's
	 * own tag filters saw (blocklane_pro_ext_module_tags()).
	 */
	function blocklane_pro_ext_module_loaded( string $id ): bool {
		$tags = blocklane_pro_ext_module_tags();
		if ( ! isset( $tags['printed'][ $id ] ) ) {
			return false;
		}
		$modules = wp_script_modules();
		$pending = array( $id );
		$visited = array( $id => true );
		while ( array() !== $pending ) {
			$module = $modules->get_registered( (string) array_shift( $pending ) );
			if ( null === $module ) {
				return false;
			}
			foreach ( $module['dependencies'] as $dependency ) {
				if ( 'static' !== $dependency['import'] ) {
					continue;
				}
				if ( ! isset( $tags['mapped'][ $dependency['id'] ] ) ) {
					return false;
				}
				if ( ! isset( $visited[ $dependency['id'] ] ) ) {
					$visited[ $dependency['id'] ] = true;
					$pending[]                    = $dependency['id'];
				}
			}
		}
		return true;
	}

	/**
	 * What core printed on this page, as the browser receives it: the module
	 * ids printed as `<script type="module">` tags (`printed`) and the keys
	 * of the import map (`mapped`). Recorded per script-modules instance, the
	 * page's one printer, by the two observers below; a call with ids adds
	 * them under $kind, and every call returns the record.
	 *
	 * @param string       $kind 'printed' or 'mapped'.
	 * @param list<string> $ids  The ids to record.
	 * @return array{printed: array<string, true>, mapped: array<string, true>}
	 */
	function blocklane_pro_ext_module_tags( string $kind = '', array $ids = array() ): array {
		/** @var WeakMap<WP_Script_Modules, array{printed: array<string, true>, mapped: array<string, true>}>|null $seen */
		static $seen = null;
		$seen  ??= new WeakMap();
		$modules = wp_script_modules();
		$record  = $seen[ $modules ] ?? array(
			'printed' => array(),
			'mapped'  => array(),
		);
		if ( 'printed' === $kind || 'mapped' === $kind ) {
			foreach ( $ids as $id ) {
				$record[ $kind ][ $id ] = true;
			}
			$seen[ $modules ] = $record;
		}
		return $record;
	}

	/**
	 * Observe core's module tags (wp_script_attributes): core prints a
	 * module as type="module" with the id `<module id>-js-module`. The
	 * attributes come back unchanged.
	 *
	 * @param mixed $attributes The script tag's attributes.
	 * @return mixed
	 */
	function blocklane_pro_ext_observe_module_tag( mixed $attributes ): mixed {
		if ( is_array( $attributes ) && 'module' === ( $attributes['type'] ?? null ) && is_string( $attributes['id'] ?? null ) && str_ends_with( $attributes['id'], '-js-module' ) ) {
			blocklane_pro_ext_module_tags( 'printed', array( substr( $attributes['id'], 0, -strlen( '-js-module' ) ) ) );
		}
		return $attributes;
	}

	/**
	 * Observe core's import map (wp_inline_script_attributes): an inline
	 * type="importmap" script whose JSON `imports` keys are the mapped ids.
	 * The attributes come back unchanged.
	 *
	 * @param mixed $attributes The inline script tag's attributes.
	 * @param mixed $data       Its inline data.
	 * @return mixed
	 */
	function blocklane_pro_ext_observe_import_map( mixed $attributes, mixed $data = '' ): mixed {
		if ( is_array( $attributes ) && 'importmap' === ( $attributes['type'] ?? null ) && is_string( $data ) ) {
			$map = json_decode( $data, true );
			if ( is_array( $map ) && is_array( $map['imports'] ?? null ) ) {
				blocklane_pro_ext_module_tags( 'mapped', array_map( 'strval', array_keys( $map['imports'] ) ) );
			}
		}
		return $attributes;
	}

	add_action( 'enqueue_block_assets', 'blocklane_pro_ext_enqueue_frontend_style' );
	// After the unit doors register (priority 99), so the force path finds
	// the handles ready.
	add_action( 'wp_enqueue_scripts', 'blocklane_pro_ext_maybe_force_enqueue', 100 );
	// Late in the footer: by then the animation script's enqueue state is
	// final for the request, whichever theme/render order got us here.
	add_action( 'wp_footer', 'blocklane_pro_ext_animation_reveal_failsafe', PHP_INT_MAX );
	// Last, so they see the attributes core prints with (J-C4).
	add_filter( 'wp_script_attributes', 'blocklane_pro_ext_observe_module_tag', PHP_INT_MAX );
	add_filter( 'wp_inline_script_attributes', 'blocklane_pro_ext_observe_import_map', PHP_INT_MAX, 2 );
}
