<?php
/**
 * Icon collection — the registrar, carried by BOTH editions.
 *
 * WHAT IT IS. Registers the `blocklane-pro` icon collection into core's Icons
 * API from this directory's 75 bundled SVGs (manifest.php names them) plus the
 * user's saved icons (the `blocklane_pro_custom_icons` option and its
 * uploads/blocklane-icons/ mirror), so a `core/icon` block that already names
 * `blocklane-pro/<icon>` keeps rendering under either edition.
 *
 * WHAT IT IS NOT. A capability. It hands a never-paid user the 75 MIT Phosphor
 * glyphs inside core's own picker — which the companion theme gave every
 * Blocklane site from 0.8.0 on, and which no plugin can withdraw without
 * blanking saved content. That is the fifth entry of the edition doctrine's
 * data-preservation allowlist (Amendment 15 of the free-edition build-target
 * spec), which is why `runtime:icon-collection` is carried by both editions
 * with no edition filter and no toggle. The picker, the cloud browse, the save
 * routes and the mirror writer stay Pro (`extension:icon-library`).
 *
 * It moved here from the theme on 2026-09-21: a theme may register neither a
 * plugin's namespace nor a plugin's option (Theme Review §5, §6). The theme's
 * own pass is gone in 1.0.0; on a site still running theme ≤ 0.11.0 that pass
 * runs at `init` 10 and this one at 20, so this loop is 75 `is_registered()`
 * reads and no writes until the theme updates.
 *
 * THE DOORS, named.
 * 1. Safe mode: the return at the top of this file, BELOW which every
 *    declaration is conditionally bound. Nothing registers and placed icons
 *    render nothing until the constant is removed. It sits below safe mode,
 *    unlike the attribute schema, because no data is at risk — core keeps the
 *    `icon` attribute as the plain string it is; only rendering stops.
 * 2. Both registries' own guards: the collection is registered only when
 *    `WP_Icon_Collections_Registry` does not have it, and each icon only when
 *    `WP_Icons_Registry` does not. Core `_doing_it_wrong()`s on a duplicate,
 *    which the guards make unreachable in either order.
 * 3. A missing manifest: `wp_trigger_error()` at first read and nothing
 *    registers. A shipped zip cannot reach it — bin/dist-check.php lists
 *    manifest.php as a sentinel of both editions' zips and svg/ as a floor.
 * 4. A missing SVG: registration still succeeds (core does not stat
 *    `file_path` when registering), and core logs its own "Icon file is
 *    missing or unreadable" on first render. A shipped zip cannot reach it.
 *
 * ONE PATH: core's Icons API (wp_register_icon_collection(), WP 7.1), which
 * is the floor both editions declare (`Requires at least: 7.1`, #966), so
 * core/icon renders our names from its own registry with no Blocklane code
 * in the render path. WP 7.0's registry was sealed; the render-callback
 * wrapper and the copy of core's renderer that served it left with that
 * floor.
 *
 * Registration is LAZY for anything with a file: core reads and sanitizes an
 * SVG on first render, not on every request at `init`.
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
if ( defined( 'BLOCKLANE_PRO_ICON_COLLECTION_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_ICON_COLLECTION_RUNTIME_LOADED', true );

// Safe mode silences every Blocklane surface, this one included — door 1.
// The declarations below are conditionally (late) bound, so returning here
// cleanly skips them.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// ONE wrapper for the whole unit, keyed on blocklane_pro_icon_library_all():
// either all four declarations and the init hook land, or none do. The Pro
// picker half (inc/extensions/loader/icon-library/icon-library-api.php) calls
// two of them, blocklane_pro_icon_library_all() and
// blocklane_pro_icon_mirror_path(), each behind its own function_exists().
if ( ! function_exists( 'blocklane_pro_icon_library_all' ) ) {

	/**
	 * The bundled set's name => label table (door 3).
	 *
	 * @return array<string, string> Unqualified icon name => label.
	 */
	function blocklane_pro_icon_collection_manifest(): array {
		static $manifest = null;

		if ( null === $manifest ) {
			$path = __DIR__ . '/manifest.php';
			if ( ! is_file( $path ) ) {
				wp_trigger_error(
					__FUNCTION__,
					'The bundled icon manifest is missing: inc/icon-collection/manifest.php. Reinstall the plugin.',
					E_USER_WARNING
				);
				$manifest = array();
			} else {
				$manifest = (array) include $path;
			}
		}

		return $manifest;
	}

	/**
	 * Every icon we serve: the bundled set + the user's saved icons.
	 *
	 * Bundled rows carry `file` (this directory's SVG) and never `content`, so
	 * nothing reads 75 files to answer a question about names.
	 *
	 * @param bool $refresh Rebuild the per-request cache (after saving a custom
	 *                      icon in this same request).
	 * @return array<string, array{label: string, weight: string, file?: string, content?: string}>
	 */
	function blocklane_pro_icon_library_all( bool $refresh = false ): array {
		static $icons = null;

		if ( null === $icons || $refresh ) {
			$icons = array();

			foreach ( blocklane_pro_icon_collection_manifest() as $name => $label ) {
				$name = (string) $name;
				// The picker lists the regular weight only; the JS control's own
				// comment asserts no catalog key ends in a weight word, so the
				// suffix is the weight.
				$weight = 'regular';
				if ( str_ends_with( $name, '-bold' ) ) {
					$weight = 'bold';
				} elseif ( str_ends_with( $name, '-fill' ) ) {
					$weight = 'fill';
				}
				$icons[ 'blocklane-pro/' . $name ] = array(
					'label'  => (string) $label,
					'weight' => $weight,
					'file'   => __DIR__ . '/svg/' . $name . '.svg',
				);
			}

			// Option-first with the baked-snapshot fallback (runtime-helpers.php),
			// like every other snapshot setting: placed custom icons must keep
			// rendering even if the option row is ever lost — uninstall keeps it
			// on purpose, but the snapshot survives a manual deletion too. Guarded
			// for the pathological load order where the helpers are absent.
			$custom_icons = function_exists( 'blocklane_pro_ext_setting' )
				? blocklane_pro_ext_setting( 'blocklane_pro_custom_icons', 'custom_icons' )
				: get_option( 'blocklane_pro_custom_icons', array() );

			foreach ( (array) $custom_icons as $custom ) {
				if ( empty( $custom['name'] ) || empty( $custom['content'] ) ) {
					continue;
				}
				$name  = (string) $custom['name'];
				$entry = array(
					'label'  => isset( $custom['label'] ) ? (string) $custom['label'] : $name,
					// Cloud icons store their weight so the picker collection can
					// list the regular weight only, like the bundled set.
					'weight' => ! empty( $custom['weight'] ) ? (string) $custom['weight'] : 'regular',
				);
				// The mirror the picker half writes, when it is there; the option
				// row's bytes when it is not.
				$mirror = blocklane_pro_icon_mirror_path( $name );
				if ( '' !== $mirror && is_file( $mirror ) ) {
					$entry['file'] = $mirror;
				} else {
					$entry['content'] = (string) $custom['content'];
				}
				$icons[ $name ] = $entry;
			}
		}

		return $icons;
	}

	/**
	 * Filesystem mirror of a custom icon: uploads/blocklane-icons/<name>.svg.
	 * The name regex is core's own icon-name rule, so a malformed option row
	 * can never build a path (and no slash can survive into the filename).
	 *
	 * @param string $name Namespaced icon name (blocklane-pro/<name>).
	 * @return string Absolute path, or '' when the name cannot mirror.
	 */
	function blocklane_pro_icon_mirror_path( string $name ): string {
		if ( ! preg_match( '#^blocklane-pro/([a-z0-9](?:[a-z0-9_-]*[a-z0-9])?)$#', $name, $m ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		return $uploads['basedir'] . '/blocklane-icons/' . $m[1] . '.svg';
	}

	/**
	 * Register the collection and every icon into core's registry, so
	 * core/icon renders them natively (door 2).
	 *
	 * The registry has no weight property, so weights stay a picker-side
	 * concept (icon-library-api.php); rendering never needs them.
	 *
	 * @return void
	 */
	function blocklane_pro_icon_library_register(): void {
		if ( ! WP_Icon_Collections_Registry::get_instance()->is_registered( 'blocklane-pro' ) ) {
			wp_register_icon_collection(
				'blocklane-pro',
				array(
					'label'       => __( 'Blocklane', 'blocklane' ),
					'description' => __( 'Phosphor icons bundled with Blocklane, plus icons saved from its cloud library.', 'blocklane' ),
				)
			);
		}

		$registry = WP_Icons_Registry::get_instance();

		foreach ( blocklane_pro_icon_library_all() as $name => $icon ) {
			if ( $registry->is_registered( $name ) ) {
				continue;
			}
			$args = array( 'label' => (string) $icon['label'] );
			if ( ! empty( $icon['file'] ) ) {
				// Lazy: core reads + sanitizes the file only on first render.
				$args['file_path'] = (string) $icon['file'];
			} else {
				$args['content'] = isset( $icon['content'] ) ? (string) $icon['content'] : '';
			}
			wp_register_icon( $name, $args );
		}
	}
	add_action( 'init', 'blocklane_pro_icon_library_register', 20 );
}
