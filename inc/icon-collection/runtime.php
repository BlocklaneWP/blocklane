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
 * 5. What a saved icon renders: blocklane_pro_icon_source(), the one door
 *    (spec 2026-10-07-free-security-followup §18). The reader owns the
 *    verdict, under the policy in force, from the row: either an uploads
 *    mirror whose NAME proves what it was derived from
 *    (`blocklane-icons/<slug>.<tag>.svg`, tag = the policy token and the
 *    row's bytes, blocklane_pro_icon_mirror_tag()), a regular file that is
 *    not a link in a folder that is not a link; or the row judged now by
 *    the clean-room sanitizer; or, for a row it cannot judge, a
 *    placeholder registered under the icon's name and one failure-level
 *    log line naming it (decision D18-1). No stamp, no migration, no row
 *    rewrite: an older build's mirror, a pre-policy `<slug>.svg`, a file
 *    named under an earlier token, or one inside a linked folder is never
 *    read. The Pro writer (extension:icon-library) only fills the cache;
 *    when it fails, or is absent (free, or the Icon Library off), icons
 *    render from memory, more slowly and never unjudged. A name a bundled
 *    glyph already has is not a saved icon (a row cannot re-skin one).
 *
 *    Trusted by name means a FILESYSTEM writer can choose what an icon
 *    renders (after core's kses), the same reach it has over this plugin's
 *    PHP; so can a media upload whose upload_dir a plugin redirects into
 *    blocklane-icons/ (sanitize_file_name() keeps a 16-hex middle part).
 *    Stated, not defended (§18.7 ND18-7).
 *
 * ONE PATH: core's Icons API (wp_register_icon_collection(), WP 7.1), which
 * is the floor both editions declare (`Requires at least: 7.1`, #966), so
 * core/icon renders our names from its own registry with no Blocklane code
 * in the render path. WP 7.0's registry was sealed; the render-callback
 * wrapper and the copy of core's renderer that served it left with that
 * floor.
 *
 * Registration is LAZY for anything with a file: core reads and sanitizes an
 * SVG on first render, not on every request at `init`. That covers the
 * bundled set and every saved icon with a current mirror; a saved icon
 * with none is registered by content, judged by the clean-room sanitizer
 * and then by core's kses at `init` on every request, until the Pro filler
 * writes its mirror.
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
// either every declaration and the init hook land, or none do. The Pro
// writer (inc/extensions/loader/icon-library/icon-library-api.php) calls the
// reader's name, row, judge and path functions, behind function_exists().
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
	 * nothing reads 75 files to answer a question about names. A saved icon
	 * carries what blocklane_pro_icon_source() decided (door 5): its current
	 * mirror as `file`, or its judged bytes as `content`, or the placeholder
	 * as `content` with one failure line logged.
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

			foreach ( blocklane_pro_icon_custom_rows() as $name => $row ) {
				$entry  = array(
					'label'  => $row['label'],
					'weight' => $row['weight'],
				);
				$source = blocklane_pro_icon_source( $name, $row['stored'] );
				if ( is_wp_error( $source ) ) {
					// D18-1: a placeholder under the icon's name, never the
					// stored bytes, and the one trace an owner has of it. The
					// name passed the slug grammar in custom_rows(), so the
					// line names an icon, never a path.
					$entry['content'] = blocklane_pro_icon_placeholder();
					if ( 'blocklane_pro_icon_unjudged' === $source->get_error_code() ) {
						blocklane_pro_log_failure( 'Blocklane: saved icons show a placeholder because this host cannot run the SVG sanitizer.' );
					} else {
						blocklane_pro_log_failure( 'Blocklane: the saved icon ' . $name . ' cannot be read by the SVG sanitizer, so a placeholder shows in its place.' );
					}
				} else {
					$entry += $source;
				}
				$icons[ $name ] = $entry;
			}
		}

		return $icons;
	}

	/**
	 * A saved icon's slug: the part after `blocklane-pro/`, or '' when the
	 * name is not one. The ONE name grammar of saved icons (core's own icon
	 * name rule, anchored at the very end so no trailing newline passes):
	 * no slash or dot survives into a filename built from it.
	 *
	 * @param string $name Namespaced icon name.
	 */
	function blocklane_pro_icon_slug( string $name ): string {
		return 1 === preg_match( '#^blocklane-pro/([a-z0-9](?:[a-z0-9_-]*[a-z0-9])?)$#D', $name, $m ) ? $m[1] : '';
	}

	/**
	 * The user's saved icons, the ONE read of the `blocklane_pro_custom_icons`
	 * option: keyed by name, rows that are not icons dropped (not a list, no
	 * string name, a name outside the slug grammar or one a bundled glyph
	 * has). A later row of the same name replaces an earlier one, as the
	 * registry's one entry per name always did. `stored` is the row's content
	 * as stored, unjudged and of any type: blocklane_pro_icon_source() judges.
	 *
	 * @return array<string, array{label: string, weight: string, stored: mixed}>
	 */
	function blocklane_pro_icon_custom_rows(): array {
		$rows = get_option( 'blocklane_pro_custom_icons', array() );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$bundled = blocklane_pro_icon_collection_manifest();
		$custom  = array();
		foreach ( $rows as $row ) {
			$name = is_array( $row ) && isset( $row['name'] ) && is_string( $row['name'] ) ? $row['name'] : '';
			$slug = blocklane_pro_icon_slug( $name );
			if ( '' === $slug || isset( $bundled[ $slug ] ) ) {
				continue;
			}
			$custom[ $name ] = array(
				'label'  => isset( $row['label'] ) && is_scalar( $row['label'] ) && '' !== (string) $row['label'] ? (string) $row['label'] : $name,
				// Cloud icons store their weight so the picker collection can
				// list the regular weight only, like the bundled set.
				'weight' => isset( $row['weight'] ) && is_string( $row['weight'] ) && '' !== $row['weight'] ? $row['weight'] : 'regular',
				'stored' => $row['content'] ?? null,
			);
		}
		return $custom;
	}

	/**
	 * A saved icon's bytes judged now by the clean-room sanitizer
	 * (blocklane_pro_ext_sanitize_svg(), which reads the one CSS verdict and
	 * the one markup bound).
	 *
	 * @param mixed $stored The row's content as stored.
	 * @return string|\WP_Error The sanitizer's output; blocklane_pro_icon_unreadable
	 *                          when the row is not a non-empty string or the
	 *                          sanitizer refuses it; blocklane_pro_icon_unjudged
	 *                          when the sanitizer cannot run here (not declared:
	 *                          a torn deploy; or no ext-dom).
	 */
	function blocklane_pro_icon_judged( mixed $stored ): string|\WP_Error {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return new \WP_Error( 'blocklane_pro_icon_unreadable', 'The saved icon holds no SVG markup.' );
		}
		if ( ! function_exists( 'blocklane_pro_ext_sanitize_svg' ) || ! class_exists( 'DOMDocument' ) ) {
			return new \WP_Error( 'blocklane_pro_icon_unjudged', 'The SVG sanitizer cannot run on this host.' );
		}
		$clean = blocklane_pro_ext_sanitize_svg( $stored );
		return '' === $clean ? new \WP_Error( 'blocklane_pro_icon_unreadable', 'The SVG sanitizer cannot read the saved icon.' ) : $clean;
	}

	/**
	 * The tag in a saved icon's mirror name: 16 hex characters derived from
	 * the policy token and the row's bytes, so a name says what its bytes
	 * were judged from and under which policy, and nothing in the row or
	 * the file can say otherwise.
	 *
	 * @param string $token  The SVG policy token (blocklane_pro_ext_svg_policy_token()).
	 * @param string $stored The row's content as stored.
	 */
	function blocklane_pro_icon_mirror_tag( string $token, string $stored ): string {
		return substr( hash( 'sha256', $token . "\n" . $stored ), 0, 16 );
	}

	/**
	 * A saved icon's mirror: uploads/blocklane-icons/<slug>.<tag>.svg (see
	 * blocklane_pro_icon_mirror_tag()).
	 *
	 * @param string $name   Namespaced icon name (blocklane-pro/<slug>).
	 * @param string $stored The row's content as stored.
	 * @return string Absolute path, or '' when the name cannot mirror, the
	 *                uploads folder is unavailable, or there is no token.
	 */
	function blocklane_pro_icon_mirror_path( string $name, string $stored ): string {
		$slug = blocklane_pro_icon_slug( $name );
		if ( '' === $slug ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$token = function_exists( 'blocklane_pro_ext_svg_policy_token' ) ? blocklane_pro_ext_svg_policy_token() : '';
		if ( '' === $token ) {
			return '';
		}
		return $uploads['basedir'] . '/blocklane-icons/' . $slug . '.' . blocklane_pro_icon_mirror_tag( $token, $stored ) . '.svg';
	}

	/**
	 * Whether a mirror path is trusted: a regular file that is not a link,
	 * in a folder that is not a link (#1860, P6). A linked uploads basedir
	 * stays acceptable, as core's own media is. The reader's test, and the
	 * Pro filler's "already there", so the two cannot disagree.
	 *
	 * @param string $path A blocklane_pro_icon_mirror_path() answer.
	 */
	function blocklane_pro_icon_mirror_trusted( string $path ): bool {
		return '' !== $path && is_file( $path ) && ! is_link( $path ) && ! is_link( dirname( $path ) );
	}

	/**
	 * THE DOOR (door 5): what a saved icon renders.
	 *
	 * @param string $name   Namespaced icon name (custom_rows() checked it).
	 * @param mixed  $stored The row's content as stored.
	 * @return array{file: string}|array{content: string}|\WP_Error Its current
	 *         mirror, else its bytes judged now, else why it cannot render.
	 */
	function blocklane_pro_icon_source( string $name, mixed $stored ): array|\WP_Error {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return new \WP_Error( 'blocklane_pro_icon_unreadable', 'The saved icon holds no SVG markup.' );
		}
		$mirror = blocklane_pro_icon_mirror_path( $name, $stored );
		if ( blocklane_pro_icon_mirror_trusted( $mirror ) ) {
			return array( 'file' => $mirror );
		}
		$judged = blocklane_pro_icon_judged( $stored );
		return is_wp_error( $judged ) ? $judged : array( 'content' => $judged );
	}

	/**
	 * What renders in place of a saved icon that cannot be judged: Phosphor's
	 * regular `image-broken` glyph (MIT, Phosphor Icons, already credited
	 * for the bundled set), carrying only attributes core's icon kses keeps,
	 * and a class that names the state.
	 */
	function blocklane_pro_icon_placeholder(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" class="blocklane-pro-icon-unreadable"><path fill="currentColor" d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16h64a8,8,0,0,0,7.59-5.47l14.83-44.48L163,151.43a8.07,8.07,0,0,0,4.46-4.46l14.62-36.55,44.48-14.83A8,8,0,0,0,232,88V56A16,16,0,0,0,216,40ZM112.41,157.47,98.23,200H40V172l52-52,30.42,30.42L117,152.57A8,8,0,0,0,112.41,157.47ZM216,82.23,173.47,96.41a8,8,0,0,0-4.9,4.62l-14.72,36.82L138.58,144l-35.27-35.27a16,16,0,0,0-22.62,0L40,149.37V56H216Zm12.68,33a8,8,0,0,0-7.21-1.1l-23.8,7.94a8,8,0,0,0-4.9,4.61l-14.31,35.77-35.77,14.31a8,8,0,0,0-4.61,4.9l-7.94,23.8A8,8,0,0,0,137.73,216H216a16,16,0,0,0,16-16V121.73A8,8,0,0,0,228.68,115.24ZM216,200H148.83l3.25-9.75,35.51-14.2a8.07,8.07,0,0,0,4.46-4.46l14.2-35.51,9.75-3.25Z"/></svg>';
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
