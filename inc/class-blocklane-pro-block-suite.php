<?php
/**
 * Block_Suite — one owner for manifest-driven block registration.
 *
 * Every block feature used to carry its own registration loop, its own
 * "missing manifest" bookkeeping (a $GLOBALS stash per feature) and its own
 * admin notice — or, in the menu designer's case, a silent skip. build/ is
 * gitignored, so every checkout starts stale, and a block that never
 * registers fails invisibly: WordPress prints whatever the block's save()
 * left behind — nothing for these dynamic suites, the plain fallback link
 * for the mega menu — and no one is told. This file is the one place that
 * alarm lives, keyed per feature (never one shared slot — a shared slot lets
 * feature A mark feature B done).
 *
 * Deliberately NOT owned here: safe-mode and Advanced-toggle gating. Those
 * stay as top-of-file early returns in each runtime, because the gate must
 * run before any hook is registered (defect class: an emitter hooked earlier
 * than the gate that suppresses it). Each runtime calls register() from its
 * OWN init callback, so per-site hook timing is unchanged by construction.
 *
 * Loading: the classmap autoloader, registered at plugin-file scope before
 * any runtime loads, resolves this class on first reference — a runtime
 * cannot be tripped by load order. That is the live half of the old "core
 * WordPress only" rule; the other half ("no plugin classes, so the file can
 * be baked into an mu-plugin") died with the bake.
 *
 * Institutional memory carried from inc/forms/runtime.php (v3): until v3 a
 * stale build fataled on a missing helper, which at least announced itself;
 * moving the helpers into the tracked runtime removed that alarm without
 * replacing it. For forms specifically a missing block also means the server
 * schema no longer matches the form — submissions lose the fields that block
 * carried. One template can only be right for the adopter it was written
 * from (#507, #515), so each adopter supplies its feature's name and the
 * sentence that says what a stale build costs it; the template frames them.
 *
 * @package blocklane_pro
 */

namespace blocklane_pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Block_Suite {

	/**
	 * Missing block names per feature, plus the command that rebuilds them,
	 * the feature's human name and its consequence sentence for the notice.
	 * Keyed per feature — never one shared slot.
	 *
	 * @var array<string, array{blocks: string[], rebuild: string, label: string, consequence: string}>
	 */
	private static array $missing = array();

	/** Whether the admin_notices callback is hooked. */
	private static bool $hooked = false;

	/**
	 * Register every block of a suite from its built block.json under
	 * $build_dir/<slug>/block.json. Registry-idempotent: an already-registered
	 * block is skipped, so init re-runs and a second registration call are both no-ops.
	 *
	 * A missing manifest is NEVER a silent skip: it is collected under
	 * $feature, returned, and announced once by skew_notice() to
	 * administrators. Call from the feature's own init callback — this method
	 * adds no init hook of its own, so the caller keeps its exact hook timing.
	 *
	 * @param string               $feature   Feature key (e.g. 'carousel'); owns its missing list.
	 * @param string               $block_ns  Block namespace without the slash (e.g. 'blocklane').
	 * @param string               $build_dir Absolute path to the feature's build/ directory.
	 * @param string[]             $blocks    Block slugs in registration order (dir names under build/).
	 * @param string               $rebuild     The command that rebuilds the assets (shown in the notice).
	 * @param string               $label       The feature's human name for the notice (translated by the caller).
	 * @param string               $consequence One translated sentence: what a stale build costs THIS feature.
	 * @param array<string, mixed> $args        Extra register_block_type() args applied to every block.
	 * @return string[] Block slugs whose manifest was missing (empty when all registered).
	 */
	public static function register( string $feature, string $block_ns, string $build_dir, array $blocks, string $rebuild, string $label, string $consequence, array $args = array() ): array {
		$registry = \WP_Block_Type_Registry::get_instance();
		$missing  = array();

		foreach ( $blocks as $slug ) {
			if ( $registry->is_registered( $block_ns . '/' . $slug ) ) {
				continue;
			}
			$manifest = rtrim( $build_dir, '/' ) . '/' . $slug . '/block.json';
			if ( file_exists( $manifest ) ) {
				register_block_type( $manifest, $args );
			} else {
				$missing[] = $slug;
			}
		}

		if ( $missing ) {
			self::$missing[ $feature ] = array(
				'blocks'      => $missing,
				'rebuild'     => $rebuild,
				'label'       => $label,
				'consequence' => $consequence,
			);
			if ( is_admin() && ! self::$hooked ) {
				self::$hooked = true;
				add_action( 'admin_notices', array( __CLASS__, 'skew_notice' ) );
			}
		}

		return $missing;
	}

	/**
	 * The missing list for a feature — the hook for pattern suppression
	 * ("a pattern inserting unregistered blocks is unsupported-block noise").
	 *
	 * @param string $feature Feature key.
	 * @return string[]
	 */
	public static function missing( string $feature ): array {
		return isset( self::$missing[ $feature ] ) ? self::$missing[ $feature ]['blocks'] : array();
	}

	/**
	 * admin_notices callback: one notice-error per stale feature, for
	 * administrators only.
	 *
	 * @return void
	 */
	public static function skew_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::$missing as $feature => $entry ) {
			printf(
				'<div class="notice notice-error"><p>%s</p><p><code>%s</code></p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: feature name, 3: comma-separated list of block names, 4: the feature's consequence sentence. */
						__( '%1$s — %2$s: the built assets are out of date, so these blocks did not register: %3$s. %4$s', 'blocklane' ),
						Branding::plugin_name(),
						$entry['label'],
						implode( ', ', array_map( 'sanitize_text_field', $entry['blocks'] ) ),
						$entry['consequence']
					)
				),
				esc_html( $entry['rebuild'] )
			);
		}
	}
}
